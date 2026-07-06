<?php
/**
 * Supervision associate dashboard.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Supervision_Dashboard
 */
if ( ! class_exists( 'CTA_Supervision_Dashboard' ) ) {

class CTA_Supervision_Dashboard {

	/** @var int Max upload size in bytes (10MB). */
	const MAX_UPLOAD_BYTES = 10485760;

	/** @var array Allowed document MIME types. */
	const ALLOWED_MIMES = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);

	/** @var array Allowed document categories. */
	const DOC_CATEGORIES = array(
		'bbs_agreement'   => 'BBS Supervision Agreement',
		'weekly_log'      => 'Weekly Hours Log',
		'experience_form' => 'Experience Verification Form',
		'other'           => 'Other',
	);

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_supervision_dashboard', array( $this, 'render_dashboard' ) );

		add_action( 'wp_ajax_cta_upload_document', array( $this, 'ajax_upload_document' ) );
		add_action( 'wp_ajax_cta_delete_document', array( $this, 'ajax_delete_document' ) );
		add_action( 'wp_ajax_cta_get_portal_url', array( $this, 'ajax_get_portal_url' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Add dashboard body class.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'cta_supervision_dashboard' ) ) {
			$classes[] = 'dashboard-page';
		}

		return $classes;
	}

	/**
	 * Render supervision dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_dashboard( $atts ) {
		$redirect = $this->check_associate_access();

		if ( is_string( $redirect ) ) {
			return $redirect;
		}

		global $wpdb;

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		$supervision_status = (string) get_user_meta( $user_id, 'cta_supervision_status', true );
		$subscription_id    = (string) get_user_meta( $user_id, 'cta_supervision_subscription_id', true );
		$supervision_plan     = (string) get_user_meta( $user_id, 'cta_supervision_plan', true );

		if ( empty( $supervision_plan ) ) {
			$supervision_plan = get_user_meta( $user_id, 'cta_hybrid_plan_active', true ) ? 'hybrid' : 'group';
		}

		$is_active = ( 'active' === $supervision_status );
		$is_locked = in_array( $supervision_status, array( 'locked', 'past_due' ), true );
		$no_plan   = ! $is_active && ! $is_locked;

		$upcoming_sessions = array();
		$session_history   = array();
		$documents         = array();

		if ( $is_active || $is_locked ) {
			$upcoming_sessions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_bookings
					WHERE user_id = %d
					AND status = 'confirmed'
					AND session_date >= CURDATE()
					ORDER BY session_date ASC, session_time ASC",
					$user_id
				)
			);

			foreach ( $upcoming_sessions as $index => $booking ) {
				$upcoming_sessions[ $index ] = $this->enrich_booking( $booking );
			}

			$session_history = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_bookings
					WHERE user_id = %d
					AND (
						session_date < CURDATE()
						OR status IN ('cancelled', 'completed')
					)
					ORDER BY session_date DESC, session_time DESC
					LIMIT 10",
					$user_id
				)
			);

			$documents = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_documents
					WHERE user_id = %d
					ORDER BY uploaded_at DESC",
					$user_id
				)
			);
		}

		$stripe              = cta_get_stripe();
		$monthly_price       = $stripe ? $stripe->get_supervision_monthly_price() : (float) get_option( 'cta_supervision_monthly_price', 260.0 );
		$individual_price    = (float) get_option( 'cta_individual_session_price', 120.0 );
		$next_billing_date   = $this->get_next_billing_date( $subscription_id );
		$next_session_label  = $this->get_next_session_label( $upcoming_sessions );
		$plan_label          = $this->get_plan_label( $supervision_plan );
		$associate_number    = (string) get_user_meta( $user_id, 'cta_associate_number', true );
		$dashboard_url       = $this->get_dashboard_url();
		$supervision_url     = $this->get_supervision_page_url();
		$logout_url          = wp_logout_url( $dashboard_url ? $dashboard_url : home_url( '/' ) );
		$home_url            = home_url( '/' );
		$dashboard_user      = $this->get_dashboard_user_data( $user, $associate_number );
		$document_categories = self::DOC_CATEGORIES;
		$dashboard           = $this;
		$show_renew          = empty( $supervision_status ) || 'active' !== $supervision_status;
		$support_email       = (string) get_option( 'cta_support_email', '' );

		if ( '' === $support_email ) {
			$support_email = (string) get_option( 'admin_email', 'support@clinicaltrainingacademy.com' );
		}

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/dashboard-supervision.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: upload a supervision document.
	 */
	public function ajax_upload_document() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to upload documents.', 'cta-lms' ) ) );
		}

		if ( ! $this->user_can_upload_documents() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to upload documents.', 'cta-lms' ) ) );
		}

		if ( empty( $_FILES['document_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'cta-lms' ) ) );
		}

		$category_key = sanitize_text_field( wp_unslash( $_POST['doc_category'] ?? 'other' ) );

		if ( ! isset( self::DOC_CATEGORIES[ $category_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid document category.', 'cta-lms' ) ) );
		}

		$file = $_FILES['document_file'];

		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload failed. Please try again.', 'cta-lms' ) ) );
		}

		if ( (int) $file['size'] > self::MAX_UPLOAD_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'File exceeds the 10MB limit.', 'cta-lms' ) ) );
		}

		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::ALLOWED_MIMES );

		if ( empty( $checked['ext'] ) || ! isset( self::ALLOWED_MIMES[ $checked['ext'] ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Only PDF, DOC, and DOCX files are allowed.', 'cta-lms' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::ALLOWED_MIMES,
			)
		);

		if ( ! empty( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => esc_html( $upload['error'] ) ) );
		}

		global $wpdb;

		$user_id   = get_current_user_id();
		$file_name = sanitize_file_name( wp_basename( $file['name'] ) );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_documents',
			array(
				'user_id'       => $user_id,
				'file_name'     => $file_name,
				'file_url'      => esc_url_raw( $upload['url'] ),
				'file_type'     => $checked['ext'],
				'file_size'     => (int) $file['size'],
				'doc_category'  => $category_key,
				'review_status' => 'pending',
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save document record.', 'cta-lms' ) ) );
		}

		$document = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_documents WHERE id = %d",
				(int) $wpdb->insert_id
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Document uploaded successfully.', 'cta-lms' ),
				'html'    => $this->render_document_row_html( $document ),
			)
		);
	}

	/**
	 * AJAX: delete a pending document.
	 */
	public function ajax_delete_document() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
		$user_id     = get_current_user_id();

		global $wpdb;

		$document = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_documents
				WHERE id = %d AND user_id = %d",
				$document_id,
				$user_id
			)
		);

		if ( ! $document ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'cta-lms' ) ) );
		}

		if ( 'pending' !== $document->review_status ) {
			wp_send_json_error( array( 'message' => __( 'Reviewed documents cannot be deleted.', 'cta-lms' ) ) );
		}

		$this->delete_document_file( $document->file_url );

		$wpdb->delete(
			$wpdb->prefix . 'cta_documents',
			array( 'id' => $document_id ),
			array( '%d' )
		);

		wp_send_json_success(
			array(
				'message'     => __( 'Document deleted.', 'cta-lms' ),
				'document_id' => $document_id,
			)
		);
	}

	/**
	 * AJAX: create Stripe Customer Portal session.
	 */
	public function ajax_get_portal_url() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$user_id = get_current_user_id();
		$stripe  = cta_get_stripe();
		$status  = (string) get_user_meta( $user_id, 'cta_supervision_status', true );
		$show_renew = empty( $status ) || 'active' !== $status;

		$stripe_ready = $stripe
			&& $stripe->is_configured()
			&& class_exists( '\Stripe\BillingPortal\Session' )
			&& ! CTA_Stripe::is_payments_bypass_enabled();

		if ( $stripe_ready && ! $show_renew ) {
			$customer_id = $this->get_stripe_customer_id( $user_id );

			if ( $customer_id ) {
				try {
					$session = \Stripe\BillingPortal\Session::create(
						array(
							'customer'   => $customer_id,
							'return_url' => $this->get_dashboard_url() ? $this->get_dashboard_url() : home_url( '/' ),
						)
					);

					wp_send_json_success(
						array(
							'portal_url' => esc_url_raw( $session->url ),
						)
					);
				} catch ( Exception $e ) {
					// Fall through to demo portal when Stripe portal cannot be opened.
				}
			}
		}

		$supervision_plan = (string) get_user_meta( $user_id, 'cta_supervision_plan', true );

		if ( empty( $supervision_plan ) ) {
			$supervision_plan = get_user_meta( $user_id, 'cta_hybrid_plan_active', true ) ? 'hybrid' : 'group';
		}

		$monthly_price = $stripe ? $stripe->get_supervision_monthly_price() : (float) get_option( 'cta_supervision_monthly_price', 260.0 );
		$renew_url     = $this->get_supervision_page_url();
		$support_email = (string) get_option( 'cta_support_email', '' );

		if ( '' === $support_email ) {
			$support_email = (string) get_option( 'admin_email', 'support@clinicaltrainingacademy.com' );
		}

		wp_send_json_success(
			array(
				'demo_mode'         => true,
				'stripe_configured' => (bool) $stripe_ready,
				'plan_name'     => $this->get_plan_label( $supervision_plan ),
				'status'        => $status ? $status : 'none',
				'show_renew'    => $show_renew,
				'renew_url'     => $renew_url ? esc_url_raw( $renew_url ) : '',
				'price'         => '$' . number_format( $monthly_price, 0 ) . __( '/month', 'cta-lms' ),
				'next_billing'  => $this->get_demo_next_billing_date( $user_id ),
				'support_email' => sanitize_email( $support_email ),
			)
		);
	}

	/**
	 * Render document row partial HTML.
	 *
	 * @param object $document Document row.
	 * @return string
	 */
	public function render_document_row_html( $document ) {
		$dashboard = $this;

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/partials/document-row.php';
		return ob_get_clean();
	}

	/**
	 * Enrich booking with slot seat data.
	 *
	 * @param object $booking Booking row.
	 * @return object
	 */
	public function enrich_booking( $booking ) {
		global $wpdb;

		$slot_id = 0;
		$notes   = json_decode( (string) $booking->notes, true );

		if ( is_array( $notes ) && ! empty( $notes['slot_id'] ) ) {
			$slot_id = (int) $notes['slot_id'];
		}

		$seats_booked = 0;
		$seats_total  = 'group' === $booking->session_type ? CTA_Supervision::GROUP_SEATS_MAX : 1;

		if ( $slot_id ) {
			$slot = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT seats_booked, seats_total FROM {$wpdb->prefix}cta_bookings WHERE id = %d AND user_id = 0",
					$slot_id
				)
			);

			if ( $slot ) {
				$seats_booked = (int) $slot->seats_booked;
				$seats_total  = (int) $slot->seats_total;
			}
		}

		$booking->seats_booked = $seats_booked;
		$booking->seats_total  = max( 1, $seats_total );
		$booking->can_cancel   = $this->booking_can_cancel( $booking );

		return $booking;
	}

	/**
	 * Whether a booking can be cancelled (24hr policy).
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public function booking_can_cancel( $booking ) {
		$session_start = strtotime( $booking->session_date . ' ' . $booking->session_time );

		return false !== $session_start && $session_start > ( time() + DAY_IN_SECONDS );
	}

	/**
	 * Format session date for display.
	 *
	 * @param string $date Session date.
	 * @return string
	 */
	public function format_session_date( $date ) {
		$timestamp = strtotime( $date );

		return $timestamp ? wp_date( 'l, F j, Y', $timestamp ) : $date;
	}

	/**
	 * Format session time for display.
	 *
	 * @param string $date Session date.
	 * @param string $time Session time.
	 * @return string
	 */
	public function format_session_time( $date, $time ) {
		$timestamp = strtotime( $date . ' ' . $time );

		return $timestamp ? wp_date( 'g:i A T', $timestamp ) : $time;
	}

	/**
	 * Format duration label.
	 *
	 * @param object $booking Booking row.
	 * @return string
	 */
	public function format_duration_label( $booking ) {
		$mins = (int) $booking->duration_mins;

		if ( 'group' === $booking->session_type || $mins >= 120 ) {
			return __( '2 hours', 'cta-lms' );
		}

		return __( '1 hour', 'cta-lms' );
	}

	/**
	 * Get history status label and badge class.
	 *
	 * @param object $booking Booking row.
	 * @return array
	 */
	public function get_history_status( $booking ) {
		if ( 'cancelled' === $booking->status ) {
			return array(
				'label' => __( 'Cancelled', 'cta-lms' ),
				'class' => 'badge--outline',
			);
		}

		return array(
			'label' => __( 'Completed', 'cta-lms' ),
			'class' => 'badge--success',
		);
	}

	/**
	 * Format file size for display.
	 *
	 * @param int $bytes File size in bytes.
	 * @return string
	 */
	public function format_file_size( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 1 ) . ' MB';
		}

		if ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}

		return $bytes . ' B';
	}

	/**
	 * Get review status badge data.
	 *
	 * @param object $document Document row.
	 * @return array
	 */
	public function get_review_badge( $document ) {
		switch ( $document->review_status ) {
			case 'approved':
			case 'reviewed':
				return array(
					'label' => __( 'Reviewed', 'cta-lms' ),
					'class' => 'badge--success',
				);
			case 'rejected':
				return array(
					'label' => __( 'Rejected', 'cta-lms' ),
					'class' => 'badge--danger',
				);
			default:
				return array(
					'label' => __( 'Pending Review', 'cta-lms' ),
					'class' => 'badge--warning',
				);
		}
	}

	/**
	 * Get document category label.
	 *
	 * @param string $key Category key.
	 * @return string
	 */
	public function get_category_label( $key ) {
		return isset( self::DOC_CATEGORIES[ $key ] ) ? self::DOC_CATEGORIES[ $key ] : $key;
	}

	/**
	 * Truncate file name for display.
	 *
	 * @param string $name File name.
	 * @param int    $max  Max length.
	 * @return string
	 */
	public function truncate_filename( $name, $max = 42 ) {
		if ( strlen( $name ) <= $max ) {
			return $name;
		}

		return substr( $name, 0, $max - 3 ) . '...';
	}

	/**
	 * Check associate dashboard access.
	 *
	 * @return string|null
	 */
	private function check_associate_access() {
		if ( ! is_user_logged_in() ) {
			return $this->redirect_markup( $this->get_login_url() );
		}

		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		if ( in_array( 'cta_licensed_professional', $roles, true ) ) {
			$url = $this->get_student_dashboard_url();
			return $this->redirect_markup( $url ? $url : home_url( '/' ) );
		}

		if ( in_array( 'cta_associate', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
			return null;
		}

		return $this->redirect_markup( home_url( '/' ) );
	}

	/**
	 * Whether current user can upload documents.
	 *
	 * @return bool
	 */
	private function user_can_upload_documents() {
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		return in_array( 'cta_associate', $roles, true ) || in_array( 'administrator', $roles, true );
	}

	/**
	 * Get Stripe customer ID for user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_stripe_customer_id( $user_id ) {
		$customer_id = (string) get_user_meta( $user_id, 'cta_stripe_customer_id', true );

		if ( $customer_id ) {
			return $customer_id;
		}

		global $wpdb;

		$customer_id = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT stripe_customer_id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND stripe_customer_id IS NOT NULL
				AND stripe_customer_id != ''
				ORDER BY created_at DESC
				LIMIT 1",
				$user_id
			)
		);

		if ( $customer_id ) {
			update_user_meta( $user_id, 'cta_stripe_customer_id', $customer_id );
		}

		return $customer_id;
	}

	/**
	 * Fetch next billing date from Stripe subscription.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return string
	 */
	private function get_next_billing_date( $subscription_id ) {
		if ( empty( $subscription_id ) || ! class_exists( '\Stripe\Subscription' ) ) {
			return '';
		}

		$stripe = cta_get_stripe();

		if ( ! $stripe || ! $stripe->is_configured() ) {
			return '';
		}

		if ( 0 === strpos( $subscription_id, 'bypass-' ) ) {
			return '';
		}

		try {
			$subscription = \Stripe\Subscription::retrieve( $subscription_id );

			if ( ! empty( $subscription->current_period_end ) ) {
				return wp_date( 'F j, Y', (int) $subscription->current_period_end );
			}
		} catch ( Exception $e ) {
			return '';
		}

		return '';
	}

	/**
	 * Estimate next billing date for demo / bypass subscriptions.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_demo_next_billing_date( $user_id ) {
		global $wpdb;

		$last_payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT created_at FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'supervision'
				AND status = 'completed'
				ORDER BY created_at DESC
				LIMIT 1",
				$user_id
			)
		);

		if ( $last_payment && ! empty( $last_payment->created_at ) ) {
			$timestamp = strtotime( $last_payment->created_at . ' +1 month' );

			if ( $timestamp ) {
				return wp_date( 'F j, Y', $timestamp );
			}
		}

		return wp_date( 'F j, Y', strtotime( 'first day of next month' ) );
	}

	/**
	 * Build next session stat label.
	 *
	 * @param array $sessions Upcoming sessions.
	 * @return string
	 */
	private function get_next_session_label( $sessions ) {
		if ( empty( $sessions ) ) {
			return __( 'No upcoming sessions', 'cta-lms' );
		}

		$next = $sessions[0];

		return sprintf(
			/* translators: %s: session date/time */
			__( 'Next Session: %s', 'cta-lms' ),
			$this->format_session_date( $next->session_date ) . ' · ' . wp_date( 'g:i A', strtotime( $next->session_date . ' ' . $next->session_time ) )
		);
	}

	/**
	 * Get plan display label.
	 *
	 * @param string $plan Plan slug.
	 * @return string
	 */
	private function get_plan_label( $plan ) {
		if ( 'hybrid' === $plan ) {
			return __( 'Supervision + CE Hybrid', 'cta-lms' );
		}

		return __( 'Group Supervision', 'cta-lms' );
	}

	/**
	 * Delete uploaded file from disk.
	 *
	 * @param string $file_url File URL.
	 */
	private function delete_document_file( $file_url ) {
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_dir   = $upload_dir['basedir'];

		if ( 0 === strpos( $file_url, $base_url ) ) {
			$file_path = $base_dir . str_replace( $base_url, '', $file_url );

			if ( file_exists( $file_path ) ) {
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $file_path );
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $file_path );
				}
			}
		}
	}

	/**
	 * Dashboard user display data.
	 *
	 * @param WP_User $user              WordPress user.
	 * @param string  $associate_number  Associate number meta.
	 * @return array
	 */
	private function get_dashboard_user_data( $user, $associate_number ) {
		$name   = $user->display_name ? $user->display_name : $user->user_login;
		$parts  = preg_split( '/\s+/', trim( $name ) );
		$initials = '';

		if ( ! empty( $parts[0] ) ) {
			$initials .= strtoupper( substr( $parts[0], 0, 1 ) );
		}
		if ( count( $parts ) > 1 && ! empty( $parts[ count( $parts ) - 1 ] ) ) {
			$initials .= strtoupper( substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
		}

		return array(
			'displayName'      => $name,
			'email'            => $user->user_email,
			'associateNumber'  => $associate_number ? $associate_number : __( 'Associate', 'cta-lms' ),
			'initials'         => $initials ? $initials : '--',
		);
	}

	/**
	 * Redirect markup fallback.
	 *
	 * @param string $url Target URL.
	 * @return string
	 */
	private function redirect_markup( $url ) {
		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		return '<script>window.location.replace(' . wp_json_encode( esc_url_raw( $url ) ) . ');</script>';
	}

	/**
	 * Get login page URL.
	 *
	 * @return string
	 */
	private function get_login_url() {
		$page_id = absint( get_option( 'cta_login_page_id', 0 ) );

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return wp_login_url( get_permalink() );
	}

	/**
	 * Get supervision dashboard URL.
	 *
	 * @return string
	 */
	private function get_dashboard_url() {
		$page_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get CE student dashboard URL.
	 *
	 * @return string
	 */
	private function get_student_dashboard_url() {
		$page_id = absint( get_option( 'cta_student_dashboard_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get supervision booking page URL.
	 *
	 * @return string
	 */
	private function get_supervision_page_url() {
		$page_id = absint( get_option( 'cta_supervision_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}
}
}