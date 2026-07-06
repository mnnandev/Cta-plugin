<?php
/**
 * Fired during plugin activation.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Activator
 */
if ( ! class_exists( 'CTA_Activator' ) ) {

class CTA_Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		if ( ! class_exists( 'CTA_Roles' ) || ! class_exists( 'CTA_Database' ) ) {
			wp_die(
				esc_html__( 'CTA LMS could not load required files. Delete the plugin folder and reinstall from WP Pusher.', 'cta-lms' ),
				esc_html__( 'Plugin Activation Error', 'cta-lms' ),
				array( 'back_link' => true )
			);
		}

		CTA_Roles::create_roles();
		CTA_Database::create_tables();
		self::maybe_seed_bundles();
		CTA_Emails::register_cron();

		add_option( 'cta_lms_version', CTA_VERSION );
		add_option( 'cta_login_page_id', 0 );
		add_option( 'cta_courses_page_id', 0 );
		add_option( 'cta_supervision_page_id', 0 );
		add_option( 'cta_memberships_page_id', 0 );
		add_option( 'cta_faq_page_id', 0 );
		add_option( 'cta_policies_page_id', 0 );
		add_option( 'cta_student_dashboard_page_id', 0 );
		add_option( 'cta_course_player_page_id', 0 );
		add_option( 'cta_supervision_dashboard_page_id', 0 );
		add_option( 'cta_single_course_page_id', 0 );
		add_option( 'cta_quiz_page_id', 0 );
		add_option( 'cta_camft_provider_number', '' );
		add_option( 'cta_certificate_upload_dir', 'cta-certificates' );
		add_option( 'cta_stripe_secret_key', '' );
		add_option( 'cta_stripe_publishable_key', '' );
		add_option( 'cta_stripe_webhook_secret', '' );
		add_option( 'cta_stripe_mode', 'test' );
		add_option( 'cta_payments_bypass', 'yes' );
		add_option( 'cta_supervision_monthly_price', 260.0 );
		add_option( 'cta_cepa_provider_number', 'CAMFT CEPA #003369' );
		add_option( 'cta_admin_name', 'Candice Fuimaono, MS, LMFT' );
		add_option( 'cta_support_email', 'support@clinicaltrainingacademy.com' );
		add_option( 'cta_certificate_header_text', 'Certificate of Completion' );
		add_option( 'cta_certificate_footer_text', 'clinicaltrainingacademy.com' );
		add_option( 'cta_certificate_signature_name', 'Candice Fuimaono, MS, LMFT' );

		update_option( 'cta_lms_version', CTA_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Seed default bundles only after the bundles table exists.
	 */
	private static function maybe_seed_bundles() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bundles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		CTA_Database::seed_bundles();
	}
}
}
