<?php
/**
 * Plugin Name: CTA LMS
 * Plugin URI: https://clinicaltrainingacademy.com
 * Description: Complete LMS platform for Clinical Training and Supervision Academy
 * Version: 1.0.25
 * Author: David James
 * Author URI: https://clinicaltrainingacademy.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cta-lms
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'CTA LMS requires PHP 7.4 or higher.', 'cta-lms' );
			echo '</p></div>';
		}
	);
	return;
}

if ( defined( 'CTA_LMS_LOADED' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'CTA LMS appears to be loaded twice. Deactivate or remove the duplicate plugin folder (for example an old cta-lms folder).', 'cta-lms' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'CTA_LMS_LOADED', true );
define( 'CTA_PLUGIN_FILE', __FILE__ );

$cta_bootstrap = __DIR__ . '/cta-lms.php';

if ( ! file_exists( $cta_bootstrap ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'CTA LMS bootstrap file (cta-lms.php) is missing. Reinstall the plugin from GitHub/WP Pusher.', 'cta-lms' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $cta_bootstrap;

register_activation_hook( __FILE__, array( 'CTA_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CTA_Deactivator', 'deactivate' ) );
