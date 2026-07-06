<?php
/**
 * Centralized HTML email notifications for CTA LMS.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Emails
 */
if ( ! class_exists( 'CTA_Emails' ) ) {

class CTA_Emails {

	/**
	 * Register daily session reminder cron.
	 */
	public static function register_cron() {
		if ( ! wp_next_scheduled( 'cta_send_session_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'cta_send_session_reminders' );
		}
	}

	/**
	 * Send a typed email to a user.
	 *
	 * @param string $type    Email type slug.
	 * @param int    $user_id WordPress user ID.
	 * @param array  $data    Additional template data.
	 * @return bool
	 */
	public static function send( $type, $user_id, $data = array() ) {
		$user = get_userdata( $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'CTA_Emails: user %d not found or invalid email for type %s', $user_id, $type ) );
			}
			return false;
		}

		switch ( $type ) {
			case 'welcome':
				return self::send_welcome( $user, $data );
			case 'enrollment_confirmation':
				return self::send_enrollment_confirmation( $user, $data );
			case 'booking_confirmation':
				return self::send_booking_confirmation( $user, $data );
			case 'session_reminder':
				return self::send_session_reminder( $user, $data );
			case 'certificate_ready':
				return self::send_certificate_ready( $user, $data );
			case 'payment_receipt':
				return self::send_payment_receipt( $user, $data );
			case 'payment_failed':
				return self::send_payment_failed( $user, $data );
			case 'supervision_locked':
				return self::send_supervision_locked( $user, $data );
			default:
				return false;
		}
	}

	/**
	 * Send daily session reminder emails.
	 */
	public static function send_daily_reminders() {
		global $wpdb;

		$tomorrow = wp_date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_bookings
				WHERE user_id > 0
				AND status = 'confirmed'
				AND session_date = %s",
				$tomorrow
			)
		);

		$sent = 0;

		foreach ( $bookings as $booking ) {
			if ( self::send( 'session_reminder', (int) $booking->user_id, array( 'session' => $booking ) ) ) {
				++$sent;
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'CTA_Emails: sent %d session reminder(s) for %s', $sent, $tomorrow ) );
		}
	}

	/**
	 * Welcome email for new registrations.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Extra data.
	 * @return bool
	 */
	private static function send_welcome( $user, $data ) {
		$subject = __( 'Welcome to Clinical Training and Supervision Academy', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'welcome',
			array(
				'role_label'     => self::get_role_label( $user ),
				'dashboard_url'  => self::get_dashboard_url( $user ),
				'faq_url'        => self::get_page_url( 'cta_faq_page_id' ),
				'is_associate'   => in_array( 'cta_associate', (array) $user->roles, true ),
			)
		);
	}

	/**
	 * Enrollment confirmation email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include course_id; optional payment_id.
	 * @return bool
	 */
	private static function send_enrollment_confirmation( $user, $data ) {
		$course_id = absint( $data['course_id'] ?? 0 );
		$course    = CTA_Database::get_course( $course_id );

		if ( ! $course ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: course title */
			__( 'You\'re Enrolled — %s', 'cta-lms' ),
			$course->title
		);

		return self::deliver(
			$user,
			$subject,
			'enrollment-confirmation',
			array(
				'course'            => $course,
				'payment_id'        => sanitize_text_field( $data['payment_id'] ?? '' ),
				'payment_reference' => self::format_payment_reference( $data['payment_id'] ?? '' ),
				'ce_hours'          => self::format_ce_hours( $course ),
				'enrolled_date'     => wp_date( 'F j, Y' ),
				'player_url'        => self::get_course_player_url( $course_id ),
			)
		);
	}

	/**
	 * Booking confirmation email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include session; optional session_type.
	 * @return bool
	 */
	private static function send_booking_confirmation( $user, $data ) {
		$session = $data['session'] ?? null;

		if ( ! $session ) {
			return false;
		}

		$session_type = sanitize_text_field( $data['session_type'] ?? $session->session_type ?? 'group' );
		$subject      = sprintf(
			/* translators: %s: session date */
			__( 'Supervision Session Confirmed — %s', 'cta-lms' ),
			self::format_session_date( $session->session_date )
		);

		return self::deliver(
			$user,
			$subject,
			'booking-confirmation',
			array(
				'session'           => $session,
				'session_type'      => $session_type,
				'session_type_label'=> 'group' === $session_type ? __( 'Group Supervision', 'cta-lms' ) : __( 'Individual Supervision', 'cta-lms' ),
				'session_date'      => self::format_session_date( $session->session_date ),
				'session_time'      => self::format_session_time( $session->session_time ),
				'duration_label'    => 'group' === $session_type ? __( '2 hours', 'cta-lms' ) : __( '1 hour', 'cta-lms' ),
				'dashboard_url'     => self::get_page_url( 'cta_supervision_dashboard_page_id' ),
			)
		);
	}

	/**
	 * Session reminder email (24 hours before).
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include session booking object.
	 * @return bool
	 */
	private static function send_session_reminder( $user, $data ) {
		$session = $data['session'] ?? null;

		if ( ! $session ) {
			return false;
		}

		$session_type = sanitize_text_field( $session->session_type ?? 'group' );
		$subject      = __( 'Reminder: Your Supervision Session is Tomorrow', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'session-reminder',
			array(
				'session'                => $session,
				'session_type_label'     => 'group' === $session_type ? __( 'Group Supervision', 'cta-lms' ) : __( 'Individual Supervision', 'cta-lms' ),
				'session_date'           => self::format_session_date( $session->session_date ),
				'session_time'           => self::format_session_time( $session->session_time ),
				'duration_label'         => (int) $session->duration_mins . ' ' . __( 'minutes', 'cta-lms' ),
				'cancellation_deadline'  => self::get_cancellation_deadline( $session ),
				'dashboard_url'          => self::get_page_url( 'cta_supervision_dashboard_page_id' ),
			)
		);
	}

	/**
	 * Certificate ready email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include course and certificate objects.
	 * @return bool
	 */
	private static function send_certificate_ready( $user, $data ) {
		$course      = $data['course'] ?? null;
		$certificate = $data['certificate'] ?? null;

		if ( ! $course || ! $certificate ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: course title */
			__( 'Your CE Certificate is Ready — %s', 'cta-lms' ),
			$course->title
		);

		return self::deliver(
			$user,
			$subject,
			'certificate-ready',
			array(
				'course'             => $course,
				'certificate'        => $certificate,
				'ce_hours'           => self::format_ce_hours( $course ),
				'certificate_url'    => CTA_Database::get_certificate_url( $certificate ),
				'completion_date'    => ! empty( $certificate->issued_at ) ? wp_date( 'F j, Y', strtotime( $certificate->issued_at ) ) : wp_date( 'F j, Y' ),
				'dashboard_url'      => self::get_page_url( 'cta_student_dashboard_page_id' ),
			)
		);
	}

	/**
	 * Payment receipt email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include payment object or payment_id; optional product_name.
	 * @return bool
	 */
	private static function send_payment_receipt( $user, $data ) {
		$payment = $data['payment'] ?? null;

		if ( ! $payment && ! empty( $data['payment_id'] ) ) {
			global $wpdb;
			$payment = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_payments WHERE stripe_payment_id = %s OR id = %d LIMIT 1",
					sanitize_text_field( $data['payment_id'] ),
					absint( $data['payment_id'] )
				)
			);
		}

		if ( ! $payment ) {
			return false;
		}

		$product_name = sanitize_text_field( $data['product_name'] ?? __( 'CTA Purchase', 'cta-lms' ) );
		$subject      = __( 'Payment Received — Thank You', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'payment-receipt',
			array(
				'payment'            => $payment,
				'product_name'       => $product_name,
				'amount'             => number_format( (float) $payment->amount, 2 ),
				'payment_date'       => ! empty( $payment->created_at ) ? wp_date( 'F j, Y', strtotime( $payment->created_at ) ) : wp_date( 'F j, Y' ),
				'transaction_ref'    => self::format_transaction_reference( $payment->stripe_payment_id ?? '' ),
				'dashboard_url'      => self::get_dashboard_url( $user ),
				'support_email'      => self::get_support_email(),
			)
		);
	}

	/**
	 * Payment failed email for supervision subscriptions.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Optional subscription_plan label.
	 * @return bool
	 */
	private static function send_payment_failed( $user, $data ) {
		$plan = sanitize_text_field( $data['subscription_plan'] ?? __( 'Supervision Subscription', 'cta-lms' ) );
		$subject = __( 'Action Required: Payment Failed', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'payment-failed',
			array(
				'subscription_plan' => $plan,
				'portal_url'        => self::get_billing_portal_url( $user->ID ),
				'support_email'     => self::get_support_email(),
			)
		);
	}

	/**
	 * Supervision access locked email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Extra data.
	 * @return bool
	 */
	private static function send_supervision_locked( $user, $data ) {
		unset( $data );
		$subject = __( 'Your Supervision Access Has Been Paused', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'supervision-access-locked',
			array(
				'supervision_url' => self::get_page_url( 'cta_supervision_page_id' ),
				'support_email'   => self::get_support_email(),
			)
		);
	}

	/**
	 * Render email HTML using base wrapper.
	 *
	 * @param string $template Template slug without .php.
	 * @param array  $vars     Template variables.
	 * @return string
	 */
	private static function render( $template, $vars = array() ) {
		$vars['template']      = $template;
		$vars['email_subject'] = $vars['email_subject'] ?? 'CTA';
		$vars['logo_url']      = self::get_logo_url();

		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include CTA_PLUGIN_DIR . 'templates/emails/base.php';
		return ob_get_clean();
	}

	/**
	 * Send rendered HTML email.
	 *
	 * @param WP_User $user     Recipient.
	 * @param string  $subject  Email subject.
	 * @param string  $template Template slug.
	 * @param array   $vars     Template variables.
	 * @return bool
	 */
	private static function deliver( $user, $subject, $template, $vars ) {
		$vars['email_subject'] = $subject;
		$html                  = self::render( $template, $vars );

		return wp_mail( $user->user_email, $subject, $html, self::get_headers() );
	}

	/**
	 * Email headers with branded From address.
	 *
	 * @return array
	 */
	public static function get_headers() {
		$from_name  = get_option( 'cta_admin_name', 'Clinical Training and Supervision Academy' );
		$from_email = self::get_support_email();

		return array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $from_email,
		);
	}

	/**
	 * Support email address.
	 *
	 * @return string
	 */
	public static function get_support_email() {
		$email = get_option( 'cta_support_email', 'support@clinicaltrainingacademy.com' );
		return is_email( $email ) ? $email : 'support@clinicaltrainingacademy.com';
	}

	/**
	 * Logo URL for email header.
	 *
	 * @return string
	 */
	private static function get_logo_url() {
		$white = CTA_PLUGIN_DIR . 'assets/img/logo-white.png';
		if ( file_exists( $white ) ) {
			return CTA_PLUGIN_URL . 'assets/img/logo-white.png';
		}

		$placeholder = CTA_PLUGIN_DIR . 'assets/img/placeholder/logo.png';
		if ( file_exists( $placeholder ) ) {
			return CTA_PLUGIN_URL . 'assets/img/placeholder/logo.png';
		}

		return CTA_PLUGIN_URL . 'assets/img/logo-white.png';
	}

	/**
	 * Dashboard URL based on user role.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	public static function get_dashboard_url( $user ) {
		$roles = (array) $user->roles;

		if ( in_array( 'cta_associate', $roles, true ) ) {
			return self::get_page_url( 'cta_supervision_dashboard_page_id' );
		}

		if ( in_array( 'cta_licensed_professional', $roles, true ) ) {
			return self::get_page_url( 'cta_student_dashboard_page_id' );
		}

		if ( in_array( 'administrator', $roles, true ) ) {
			return admin_url();
		}

		return home_url( '/' );
	}

	/**
	 * Get permalink from plugin page option.
	 *
	 * @param string $option_name Option key.
	 * @return string
	 */
	public static function get_page_url( $option_name ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return home_url( '/' );
		}

		$url = get_permalink( $page_id );

		return $url ? $url : home_url( '/' );
	}

	/**
	 * Course player URL for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function get_course_player_url( $course_id ) {
		$base = self::get_page_url( 'cta_course_player_page_id' );
		return add_query_arg( 'course_id', absint( $course_id ), $base );
	}

	/**
	 * Human-readable role label.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	private static function get_role_label( $user ) {
		$roles = (array) $user->roles;

		if ( in_array( 'cta_associate', $roles, true ) ) {
			return __( 'Associate (Supervision)', 'cta-lms' );
		}

		if ( in_array( 'cta_licensed_professional', $roles, true ) ) {
			return __( 'Licensed Professional (CE)', 'cta-lms' );
		}

		if ( in_array( 'administrator', $roles, true ) ) {
			return __( 'Administrator', 'cta-lms' );
		}

		return __( 'Student', 'cta-lms' );
	}

	/**
	 * Format CE hours for display.
	 *
	 * @param object $course Course row.
	 * @return string
	 */
	private static function format_ce_hours( $course ) {
		return rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * Format payment ID reference (last 8 chars).
	 *
	 * @param string $payment_id Payment reference.
	 * @return string
	 */
	private static function format_payment_reference( $payment_id ) {
		$payment_id = sanitize_text_field( $payment_id );
		if ( strlen( $payment_id ) <= 8 ) {
			return $payment_id ? '#' . $payment_id : '—';
		}
		return '#' . substr( $payment_id, -8 );
	}

	/**
	 * Format Stripe transaction reference (last 12 chars).
	 *
	 * @param string $transaction_id Stripe ID.
	 * @return string
	 */
	private static function format_transaction_reference( $transaction_id ) {
		$transaction_id = sanitize_text_field( $transaction_id );
		if ( strlen( $transaction_id ) <= 12 ) {
			return $transaction_id ? $transaction_id : '—';
		}
		return substr( $transaction_id, -12 );
	}

	/**
	 * Format session date.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	private static function format_session_date( $date ) {
		return wp_date( 'l, F j, Y', strtotime( $date ) );
	}

	/**
	 * Format session time with PST label.
	 *
	 * @param string $time Time string.
	 * @return string
	 */
	private static function format_session_time( $time ) {
		return substr( (string) $time, 0, 5 ) . ' PST';
	}

	/**
	 * Cancellation deadline (24 hours before session).
	 *
	 * @param object $session Booking row.
	 * @return string
	 */
	private static function get_cancellation_deadline( $session ) {
		$timestamp = strtotime( $session->session_date . ' ' . $session->session_time ) - DAY_IN_SECONDS;
		return wp_date( 'F j, Y g:i A', $timestamp ) . ' PST';
	}

	/**
	 * Create Stripe billing portal URL for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function get_billing_portal_url( $user_id ) {
		$fallback = self::get_page_url( 'cta_supervision_dashboard_page_id' );
		$stripe   = function_exists( 'cta_get_stripe' ) ? cta_get_stripe() : null;

		if ( ! $stripe || ! $stripe->is_configured() || ! class_exists( '\Stripe\BillingPortal\Session' ) ) {
			return $fallback;
		}

		$customer_id = (string) get_user_meta( $user_id, 'cta_stripe_customer_id', true );

		if ( ! $customer_id ) {
			global $wpdb;
			$customer_id = (string) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT stripe_customer_id FROM {$wpdb->prefix}cta_payments WHERE user_id = %d AND stripe_customer_id != '' ORDER BY id DESC LIMIT 1",
					$user_id
				)
			);
		}

		if ( ! $customer_id ) {
			return $fallback;
		}

		try {
			$session = \Stripe\BillingPortal\Session::create(
				array(
					'customer'   => $customer_id,
					'return_url' => $fallback,
				)
			);

			return ! empty( $session->url ) ? $session->url : $fallback;
		} catch ( Exception $e ) {
			return $fallback;
		}
	}
}
}