<?php
/**
 * Membership pricing and bundle checkout.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Memberships
 */
if ( ! class_exists( 'CTA_Memberships' ) ) {

class CTA_Memberships {

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_membership_pricing', array( $this, 'render_pricing' ) );

		add_action( 'wp_ajax_cta_purchase_bundle', array( $this, 'handle_bundle_purchase' ) );
		add_action( 'wp_ajax_nopriv_cta_purchase_bundle', array( $this, 'handle_bundle_purchase' ) );
	}

	/**
	 * Render membership pricing shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_pricing( $atts ) {
		global $wpdb;

		$bundles = CTA_Database::get_all_bundles();

		$user_bundles = array();

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$owned   = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT product_id
					FROM {$wpdb->prefix}cta_payments
					WHERE user_id = %d
					AND product_type = 'bundle'
					AND status = 'completed'",
					$user_id
				)
			);
			$user_bundles = array_map( 'intval', $owned );
		}

		$all_courses             = CTA_Database::get_all_courses( 'published' );
		$courses_map             = array();
		$published_course_count  = count( $all_courses );

		foreach ( $all_courses as $course ) {
			$courses_map[ (int) $course->id ] = $course;
		}

		$login_url = $this->get_login_url();

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/memberships.php';
		return ob_get_clean();
	}

	/**
	 * Handle bundle purchase AJAX request.
	 */
	public function handle_bundle_purchase() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to purchase a plan.', 'cta-lms' ),
				)
			);
		}

		$bundle_id = absint( wp_unslash( $_POST['bundle_id'] ?? 0 ) );
		$bundle    = CTA_Database::get_bundle( $bundle_id );

		if ( ! $bundle ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid plan selected.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$user_id = get_current_user_id();

		$already_owned = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'bundle'
				AND product_id = %d
				AND status = 'completed'",
				$user_id,
				$bundle_id
			)
		);

		if ( $already_owned ) {
			wp_send_json_error(
				array(
					'message' => __( 'You have already purchased this plan.', 'cta-lms' ),
				)
			);
		}

		$secret_key = (string) get_option( 'cta_stripe_secret_key', '' );

		if ( '' === $secret_key ) {
			if ( ! empty( $_POST['demo_confirm'] ) ) {
				$stripe = cta_get_stripe();
				if ( $stripe ) {
					$stripe->bypass_bundle_purchase( $bundle );
				}
				return;
			}

			wp_send_json_success(
				array(
					'demo_mode'    => true,
					'checkout_url' => '',
				)
			);
		}

		$stripe = cta_get_stripe();

		if ( $stripe && CTA_Stripe::is_payments_bypass_enabled() ) {
			$stripe->bypass_bundle_purchase( $bundle );
			return;
		}

		if ( ! $stripe || ! $stripe->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Payments are not configured yet. Please contact support.', 'cta-lms' ),
				)
			);
		}

		$stripe->create_bundle_checkout_session( $bundle );
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