<?php
/**
 * Printable CE certificate HTML (self-contained inline CSS).
 *
 * @package CTA_LMS
 *
 * @var string $student_name
 * @var string $course_title
 * @var string $ce_hours
 * @var string $completion_date
 * @var string $license_number
 * @var string $provider_number
 * @var string $certificate_number
 * @var string $logo_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_display = $license_number ? esc_html( $license_number ) : esc_html__( 'N/A', 'cta-lms' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( $certificate_number ); ?></title>
	<style>
		@page { size: A4 landscape; margin: 0; }
		* { box-sizing: border-box; }
		body {
			margin: 0;
			padding: 24px;
			font-family: Georgia, "Times New Roman", serif;
			color: #122B51;
			background: #f4f7fb;
		}
		.certificate {
			width: 100%;
			min-height: 520px;
			padding: 48px 64px;
			background: #ffffff;
			border: 8px double #122B51;
			outline: 2px solid #c5a572;
			outline-offset: -16px;
			text-align: center;
			position: relative;
		}
		.logo { max-height: 64px; margin-bottom: 16px; }
		h1 {
			font-size: 34px;
			margin: 0 0 4px;
			letter-spacing: 0.08em;
			text-transform: uppercase;
		}
		.subtitle {
			font-size: 16px;
			margin: 0 0 28px;
			letter-spacing: 0.14em;
			text-transform: uppercase;
			color: #475467;
		}
		.lead { font-size: 18px; margin: 12px 0; }
		.recipient {
			font-size: 36px;
			font-weight: bold;
			margin: 16px 0;
		}
		.course-title {
			font-size: 24px;
			font-weight: bold;
			margin: 12px 0 8px;
		}
		.ce-hours {
			font-size: 20px;
			margin: 8px 0 20px;
		}
		.meta {
			font-size: 16px;
			line-height: 1.8;
			margin: 16px auto;
			max-width: 720px;
		}
		.divider {
			width: 240px;
			height: 2px;
			background: #122B51;
			margin: 28px auto;
		}
		.signature-block {
			margin-top: 24px;
			text-align: center;
		}
		.signature-line {
			width: 280px;
			border-top: 1px solid #122B51;
			margin: 0 auto 8px;
			padding-top: 8px;
			font-size: 14px;
			line-height: 1.5;
		}
		.verify {
			margin-top: 24px;
			font-size: 14px;
			font-weight: bold;
		}
		.footer {
			margin-top: 16px;
			font-size: 12px;
			color: #667085;
			text-transform: lowercase;
		}
	</style>
</head>
<body>
	<div class="certificate">
		<?php if ( ! empty( $logo_url ) ) : ?>
			<img class="logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Clinical Training and Supervision Academy', 'cta-lms' ); ?>">
		<?php endif; ?>

		<h1><?php esc_html_e( 'Certificate of Completion', 'cta-lms' ); ?></h1>
		<p class="subtitle"><?php esc_html_e( 'Continuing Education', 'cta-lms' ); ?></p>

		<p class="lead"><?php esc_html_e( 'This certifies that', 'cta-lms' ); ?></p>
		<p class="recipient"><?php echo esc_html( $student_name ); ?></p>
		<p class="lead"><?php esc_html_e( 'has successfully completed', 'cta-lms' ); ?></p>
		<p class="course-title"><?php echo esc_html( $course_title ); ?></p>
		<p class="ce-hours"><?php echo esc_html( $ce_hours ); ?> <?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></p>

		<div class="meta">
			<p><?php esc_html_e( 'Completion Date:', 'cta-lms' ); ?> <?php echo esc_html( $completion_date ); ?></p>
			<p><?php esc_html_e( 'License/Registration Number:', 'cta-lms' ); ?> <?php echo $license_display; ?></p>
		</div>

		<div class="divider"></div>

		<p class="meta">
			<?php esc_html_e( 'CAMFT CEPA Provider Number:', 'cta-lms' ); ?>
			<?php echo esc_html( $provider_number ? $provider_number : __( 'N/A', 'cta-lms' ) ); ?>
		</p>

		<div class="signature-block">
			<div class="signature-line">
				<?php esc_html_e( 'Candice Fuimaono, MS, LMFT', 'cta-lms' ); ?><br>
				<?php esc_html_e( 'Program Administrator', 'cta-lms' ); ?><br>
				<?php esc_html_e( 'Clinical Training and Supervision Academy', 'cta-lms' ); ?>
			</div>
		</div>

		<p class="verify">
			<?php esc_html_e( 'Certificate Verification Number:', 'cta-lms' ); ?>
			<?php echo esc_html( $certificate_number ); ?>
		</p>
		<p class="footer">clinicaltrainingacademy.com</p>
	</div>
</body>
</html>
