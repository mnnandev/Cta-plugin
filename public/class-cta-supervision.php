<?php
/**
 * Supervision booking shortcode and AJAX handlers.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Supervision
 */
if ( ! class_exists( 'CTA_Supervision' ) ) {

class CTA_Supervision {

	/** @var int BBS max group size — hardcoded, not user-editable. */
	const GROUP_SEATS_MAX = 8;

	/** @var int Group session duration in minutes. */
	const GROUP_DURATION_MINS = 120;

	/** @var int Individual session duration in minutes. */
	const INDIVIDUAL_DURATION_MINS = 60;

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_supervision_booking', array( $this, 'render_supervision' ) );

		add_action( 'wp_ajax_cta_book_session', array( $this, 'ajax_book_session' ) );
		add_action( 'wp_ajax_cta_cancel_booking', array( $this, 'ajax_cancel_booking' ) );
	}

	/**
	 * Render supervision booking shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_supervision( $atts ) {
		global $wpdb;

		$is_logged_in = is_user_logged_in();
		$user_status  = 'guest';
		$user_bookings = array();

		if ( $is_logged_in ) {
			$user_id     = get_current_user_id();
			$meta_status = (string) get_user_meta( $user_id, 'cta_supervision_status', true );

			if ( 'active' === $meta_status ) {
				$user_status = 'active';
			} elseif ( 'locked' === $meta_status ) {
				$user_status = 'locked';
			} else {
				$user_status = 'inactive';
			}

			$bookings = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT session_date, session_time, session_type, id
					FROM {$wpdb->prefix}cta_bookings
					WHERE user_id = %d
					AND status = 'confirmed'
					AND session_date >= CURDATE()",
					$user_id
				)
			);

			foreach ( $bookings as $booking ) {
				$key = $this->get_session_key( $booking->session_date, $booking->session_time, $booking->session_type );
				$user_bookings[ $key ] = (int) $booking->id;
			}
		}

		$sessions = $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->prefix}cta_bookings
			WHERE user_id = 0
			AND status = 'open'
			AND seats_booked < seats_total
			AND session_date >= CURDATE()
			ORDER BY session_date ASC, session_time ASC"
		);

		foreach ( $sessions as $session ) {
			if ( 'group' === $session->session_type ) {
				$session->seats_total   = self::GROUP_SEATS_MAX;
				$session->duration_mins = self::GROUP_DURATION_MINS;
			} else {
				$session->duration_mins = self::INDIVIDUAL_DURATION_MINS;
			}
		}

		$stripe              = cta_get_stripe();
		$monthly_price       = $stripe ? $stripe->get_supervision_monthly_price() : (float) get_option( 'cta_supervision_monthly_price', 260.0 );
		$individual_price    = (float) get_option( 'cta_individual_session_price', 120.0 );
		$login_url           = $this->get_login_url();
		$calendar_month      = gmdate( 'Y-m-01' );
		$session_dates       = array();

		foreach ( $sessions as $session ) {
			$session_dates[] = $session->session_date;
		}

		$session_dates = array_unique( $session_dates );
		$supervision   = $this;

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/supervision.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: book a supervision session.
	 */
	public function ajax_book_session() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to book a session.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();
		$status  = (string) get_user_meta( $user_id, 'cta_supervision_status', true );

		if ( 'active' !== $status ) {
			wp_send_json_error(
				array(
					'message' => __( 'An active supervision subscription is required to book sessions.', 'cta-lms' ),
				)
			);
		}

		$session_id = absint( wp_unslash( $_POST['session_id'] ?? 0 ) );

		if ( ! $session_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid session selected.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'cta_bookings';
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE id = %d
				AND user_id = 0
				AND status = 'open'
				AND session_date >= CURDATE()",
				$session_id
			)
		);

		if ( ! $session ) {
			wp_send_json_error(
				array(
					'message' => __( 'This session is no longer available.', 'cta-lms' ),
				)
			);
		}

		$session_type  = sanitize_text_field( $session->session_type );
		$duration_mins = 'group' === $session_type ? self::GROUP_DURATION_MINS : self::INDIVIDUAL_DURATION_MINS;
		$seats_total   = 'group' === $session_type ? self::GROUP_SEATS_MAX : 1;

		if ( (int) $session->seats_booked >= $seats_total ) {
			wp_send_json_error(
				array(
					'message' => __( 'This session is full.', 'cta-lms' ),
				)
			);
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE user_id = %d
				AND session_date = %s
				AND session_time = %s
				AND session_type = %s
				AND status = 'confirmed'",
				$user_id,
				$session->session_date,
				$session->session_time,
				$session_type
			)
		);

		if ( $existing ) {
			wp_send_json_error(
				array(
					'message' => __( 'You have already booked this session.', 'cta-lms' ),
				)
			);
		}

		$sub_id = (string) get_user_meta( $user_id, 'cta_supervision_subscription_id', true );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'       => $user_id,
				'session_type'  => $session_type,
				'session_date'  => $session->session_date,
				'session_time'  => $session->session_time,
				'duration_mins' => $duration_mins,
				'seats_total'   => 0,
				'seats_booked'  => 0,
				'status'        => 'confirmed',
				'stripe_sub_id' => $sub_id ? $sub_id : null,
				'notes'         => wp_json_encode(
					array(
						'slot_id' => (int) $session->id,
					)
				),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( ! $inserted ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to complete booking. Please try again.', 'cta-lms' ),
				)
			);
		}

		$booking_id = (int) $wpdb->insert_id;

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET seats_booked = seats_booked + 1,
				seats_total = %d,
				duration_mins = %d
				WHERE id = %d
				AND user_id = 0
				AND status = 'open'
				AND seats_booked < %d",
				$seats_total,
				$duration_mins,
				$session_id,
				$seats_total
			)
		);

		if ( ! $updated ) {
			$wpdb->delete( $table, array( 'id' => $booking_id ), array( '%d' ) );

			wp_send_json_error(
				array(
					'message' => __( 'This session just filled up. Please choose another time.', 'cta-lms' ),
				)
			);
		}

		$seats_remaining = max( 0, $seats_total - ( (int) $session->seats_booked + 1 ) );

		CTA_Emails::send(
			'booking_confirmation',
			$user_id,
			array(
				'session'      => $session,
				'session_type' => $session_type,
			)
		);

		wp_send_json_success(
			array(
				'message'         => __( 'Session booked successfully!', 'cta-lms' ),
				'booking_id'      => $booking_id,
				'session_id'      => $session_id,
				'seats_remaining' => $seats_remaining,
				'datetime_label'  => $this->format_session_datetime( $session->session_date, $session->session_time ),
				'session_type'    => $session_type,
				'dashboard_url'   => $this->get_supervision_dashboard_url(),
			)
		);
	}

	/**
	 * AJAX: cancel a supervision booking.
	 */
	public function ajax_cancel_booking() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to cancel a booking.', 'cta-lms' ),
				)
			);
		}

		$booking_id = absint( wp_unslash( $_POST['booking_id'] ?? 0 ) );
		$user_id    = get_current_user_id();

		if ( ! $booking_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid booking.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'cta_bookings';
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE id = %d
				AND user_id = %d
				AND status = 'confirmed'",
				$booking_id,
				$user_id
			)
		);

		if ( ! $booking ) {
			wp_send_json_error(
				array(
					'message' => __( 'Booking not found.', 'cta-lms' ),
				)
			);
		}

		$session_start = strtotime( $booking->session_date . ' ' . $booking->session_time );

		if ( false === $session_start || $session_start <= ( time() + DAY_IN_SECONDS ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Bookings must be cancelled at least 24 hours before the session.', 'cta-lms' ),
				)
			);
		}

		$wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		$slot_id = 0;
		$notes   = json_decode( (string) $booking->notes, true );

		if ( is_array( $notes ) && ! empty( $notes['slot_id'] ) ) {
			$slot_id = (int) $notes['slot_id'];
		}

		if ( $slot_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET seats_booked = GREATEST(0, seats_booked - 1)
					WHERE id = %d
					AND user_id = 0
					AND status = 'open'",
					$slot_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET seats_booked = GREATEST(0, seats_booked - 1)
					WHERE user_id = 0
					AND status = 'open'
					AND session_date = %s
					AND session_time = %s
					AND session_type = %s
					AND seats_booked > 0",
					$booking->session_date,
					$booking->session_time,
					$booking->session_type
				)
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Your booking has been cancelled.', 'cta-lms' ),
				'booking_id' => $booking_id,
			)
		);
	}

	/**
	 * Build a unique key for a session slot.
	 *
	 * @param string $date Session date.
	 * @param string $time Session time.
	 * @param string $type Session type.
	 * @return string
	 */
	private function get_session_key( $date, $time, $type ) {
		return $date . '|' . $time . '|' . $type;
	}

	/**
	 * Format session date and time for display.
	 *
	 * @param string $date Session date (Y-m-d).
	 * @param string $time Session time (H:i:s).
	 * @return string
	 */
	public function format_session_datetime( $date, $time ) {
		$timestamp = strtotime( $date . ' ' . $time );

		if ( ! $timestamp ) {
			return $date . ' ' . $time;
		}

		return wp_date( 'l, F j, Y · g:i A', $timestamp );
	}

	/**
	 * Get supervision associate dashboard URL.
	 *
	 * @return string
	 */
	private function get_supervision_dashboard_url() {
		$page_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );

		if ( ! $page_id && function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$page_id = cta_lms_find_page_id_by_shortcode( 'cta_supervision_dashboard' );
		}

		if ( $page_id ) {
			$url = get_permalink( $page_id );

			if ( $url ) {
				return $url;
			}
		}

		return '';
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
}
}