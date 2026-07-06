<?php
/**
 * Completed course certificate card.
 *
 * @package CTA_LMS
 *
 * @var object $item Enrollment bundle with course and certificate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enrollment  = $item->enrollment;
$course      = $item->course;
$certificate = isset( $item->certificate ) ? $item->certificate : null;
$ce_hours    = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
$completed   = $enrollment->completed_at
	? wp_date( 'F j, Y', strtotime( $enrollment->completed_at ) )
	: wp_date( 'F j, Y' );
?>
<article class="card dashboard-course-card cta-certificate-card">
	<span class="badge badge--success cta-certificate-card__badge"><?php echo esc_html__( 'Completed', 'cta-lms' ); ?></span>
	<div class="dashboard-course-card__header">
		<h3 class="dashboard-course-card__title"><?php echo esc_html( $course->title ); ?></h3>
	</div>
	<p class="dashboard-course-card__completed-date">
		<?php
		printf(
			/* translators: %s: completion date */
			esc_html__( 'Completed on %s', 'cta-lms' ),
			esc_html( $completed )
		);
		?>
	</p>
	<div class="dashboard-course-card__actions">
		<span class="badge badge--success"><?php echo esc_html( $ce_hours ); ?> <?php echo esc_html__( 'CE Hours', 'cta-lms' ); ?></span>
		<?php if ( $certificate ) : ?>
			<span class="text-small cta-certificate-number"><?php echo esc_html( $certificate->certificate_number ); ?></span>
			<button
				type="button"
				class="btn btn-outline btn--download btn--sm cta-download-cert-btn"
				data-certificate-id="<?php echo esc_attr( $certificate->id ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
				<?php echo esc_html__( 'Download Certificate', 'cta-lms' ); ?>
			</button>
		<?php endif; ?>
	</div>
</article>
