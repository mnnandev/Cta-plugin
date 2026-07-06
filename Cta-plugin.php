<?php
/**
 * Plugin Name: CTA LMS
 * Plugin URI: https://clinicaltrainingacademy.com
 * Description: Complete LMS platform for Clinical Training and Supervision Academy
 * Version: 1.0.20
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

$cta_plugin_bootstrap = __DIR__ . '/cta-lms/Cta-plugin.php';

if ( ! file_exists( $cta_plugin_bootstrap ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'CTA LMS plugin files are missing. Reinstall from GitHub or WP Pusher.', 'cta-lms' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $cta_plugin_bootstrap;
