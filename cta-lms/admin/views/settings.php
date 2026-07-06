<?php
/**
 * Admin settings view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );
?>
<div class="wrap cta-admin-wrap">
	<h1><?php esc_html_e( 'CTA LMS Settings', 'cta-lms' ); ?></h1>

	<?php if ( 'settings_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-admin-form">
		<?php wp_nonce_field( 'cta_save_settings' ); ?>
		<input type="hidden" name="action" value="cta_save_settings">

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Stripe Configuration', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Mode', 'cta-lms' ); ?></th>
					<td>
						<label><input type="radio" name="cta_stripe_mode" value="test" <?php checked( get_option( 'cta_stripe_mode', 'test' ), 'test' ); ?>> <?php esc_html_e( 'Test', 'cta-lms' ); ?></label>
						<label><input type="radio" name="cta_stripe_mode" value="live" <?php checked( get_option( 'cta_stripe_mode', 'test' ), 'live' ); ?>> <?php esc_html_e( 'Live', 'cta-lms' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="cta_stripe_secret_key"><?php esc_html_e( 'Secret Key', 'cta-lms' ); ?></label></th>
					<td><input type="password" class="regular-text" id="cta_stripe_secret_key" name="cta_stripe_secret_key" value="<?php echo esc_attr( get_option( 'cta_stripe_secret_key', '' ) ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th><label for="cta_stripe_publishable_key"><?php esc_html_e( 'Publishable Key', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_stripe_publishable_key" name="cta_stripe_publishable_key" value="<?php echo esc_attr( get_option( 'cta_stripe_publishable_key', '' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_stripe_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'cta-lms' ); ?></label></th>
					<td><input type="password" class="regular-text" id="cta_stripe_webhook_secret" name="cta_stripe_webhook_secret" value="<?php echo esc_attr( get_option( 'cta_stripe_webhook_secret', '' ) ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Webhook URL', 'cta-lms' ); ?></th>
					<td><input type="text" class="large-text" readonly value="<?php echo esc_attr( $webhook_url ); ?>"></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button" id="cta-test-stripe"><?php esc_html_e( 'Test Connection', 'cta-lms' ); ?></button>
				<span id="cta-stripe-test-result" class="cta-inline-result"></span>
			</p>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Testing Mode', 'cta-lms' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cta_payments_bypass" value="yes" <?php checked( get_option( 'cta_payments_bypass', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Skip payments (instant enroll / subscribe without Stripe)', 'cta-lms' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Enable while testing. Turn off before going live and configure Stripe keys above.', 'cta-lms' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Page Assignments', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<?php foreach ( $page_options as $option_key => $label ) : ?>
					<tr>
						<th><label for="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<select id="<?php echo esc_attr( $option_key ); ?>" name="<?php echo esc_attr( $option_key ); ?>">
								<option value="0"><?php esc_html_e( '— Select Page —', 'cta-lms' ); ?></option>
								<?php foreach ( $pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( get_option( $option_key, 0 ), $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'CTA Configuration', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="cta_camft_provider_number"><?php esc_html_e( 'CAMFT CEPA Provider Number', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_camft_provider_number" name="cta_camft_provider_number" value="<?php echo esc_attr( get_option( 'cta_camft_provider_number', '' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_admin_name"><?php esc_html_e( 'Program Administrator Name', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_admin_name" name="cta_admin_name" value="<?php echo esc_attr( get_option( 'cta_admin_name', 'Candice Fuimaono, MS, LMFT' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_support_email"><?php esc_html_e( 'Support Email', 'cta-lms' ); ?></label></th>
					<td><input type="email" class="regular-text" id="cta_support_email" name="cta_support_email" value="<?php echo esc_attr( get_option( 'cta_support_email', 'support@clinicaltrainingacademy.com' ) ); ?>"></td>
				</tr>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Certificate Settings', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="cta_certificate_header_text"><?php esc_html_e( 'Certificate Header Text', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_certificate_header_text" name="cta_certificate_header_text" value="<?php echo esc_attr( get_option( 'cta_certificate_header_text', 'Certificate of Completion' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_certificate_footer_text"><?php esc_html_e( 'Certificate Footer Text', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_certificate_footer_text" name="cta_certificate_footer_text" value="<?php echo esc_attr( get_option( 'cta_certificate_footer_text', 'clinicaltrainingacademy.com' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_certificate_signature_name"><?php esc_html_e( 'Administrator Signature Name', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_certificate_signature_name" name="cta_certificate_signature_name" value="<?php echo esc_attr( get_option( 'cta_certificate_signature_name', 'Candice Fuimaono, MS, LMFT' ) ); ?>"></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button" id="cta-preview-certificate"><?php esc_html_e( 'Preview Certificate', 'cta-lms' ); ?></button>
			</p>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'cta-lms' ); ?></button>
		</p>
	</form>
</div>
