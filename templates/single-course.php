<?php
/**
 * Single course detail template.
 *
 * @package CTA_LMS
 *
 * @var object $course
 * @var array  $modules
 * @var array  $objectives
 * @var bool   $is_enrolled
 * @var string $player_url
 * @var string $courses_url
 * @var int    $total_mins
 * @var bool   $payment_success
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ce_hours = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
$duration_hours = $total_mins > 0 ? round( $total_mins / 60, 1 ) : $course->ce_hours;
$admin_name = get_option( 'cta_admin_name', 'Candice Fuimaono, MS, LMFT' );
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-single-course">
	<?php if ( ! empty( $payment_success ) ) : ?>
		<div class="cta-notice cta-notice--success" role="status">
			<p><?php esc_html_e( 'Payment successful! You are now enrolled. Start learning below.', 'cta-lms' ); ?></p>
		</div>
	<?php endif; ?>

	<section class="course-hero" aria-labelledby="course-hero-title">
		<div class="course-hero__bg" aria-hidden="true"></div>
		<div class="cta-container course-hero__layout">
			<div class="course-hero__content">
				<nav class="course-hero__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'cta-lms' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'cta-lms' ); ?></a>
					<span class="course-hero__breadcrumb-separator" aria-hidden="true">/</span>
					<a href="<?php echo esc_url( $courses_url ); ?>"><?php esc_html_e( 'CE Courses', 'cta-lms' ); ?></a>
					<span class="course-hero__breadcrumb-separator" aria-hidden="true">/</span>
					<span class="course-hero__breadcrumb-current"><?php echo esc_html( $course->title ); ?></span>
				</nav>
				<div class="course-hero__badges">
					<span class="badge badge--success"><?php echo esc_html( $ce_hours ); ?> <?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></span>
					<?php if ( ! empty( $course->category ) ) : ?>
						<span class="badge badge--primary"><?php echo esc_html( $course->category ); ?></span>
					<?php endif; ?>
				</div>
				<h1 class="course-hero__title" id="course-hero-title"><?php echo esc_html( $course->title ); ?></h1>
				<?php if ( ! empty( $course->description ) ) : ?>
					<p class="course-hero__summary"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $course->description ), 40 ) ); ?></p>
				<?php endif; ?>
				<div class="course-hero__meta">
					<div class="course-hero__instructor">
						<div class="course-hero__instructor-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $admin_name, 0, 1 ) ) ); ?></div>
						<span class="course-hero__instructor-name"><?php echo esc_html( $admin_name ); ?></span>
					</div>
				</div>
			</div>
			<div class="course-hero__media">
				<?php if ( ! empty( $course->thumbnail_url ) ) : ?>
					<img src="<?php echo esc_url( $course->thumbnail_url ); ?>" alt="<?php echo esc_attr( $course->title ); ?>" class="course-hero__video-thumb">
				<?php else : ?>
					<div class="course-hero__video course-hero__video--placeholder" aria-hidden="true"></div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="course-detail">
		<div class="cta-container course-detail__layout">
			<div class="course-detail__main">
				<?php if ( ! empty( $course->description ) ) : ?>
					<section class="course-section" aria-labelledby="course-overview-title">
						<h2 class="course-section__title" id="course-overview-title"><?php esc_html_e( 'Course Overview', 'cta-lms' ); ?></h2>
						<div class="course-content-block__text"><?php echo wp_kses_post( $course->description ); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $objectives ) ) : ?>
					<section class="course-section" aria-labelledby="course-learn-title">
						<h2 class="course-section__title" id="course-learn-title"><?php esc_html_e( 'What You\'ll Learn In This Course:', 'cta-lms' ); ?></h2>
						<ul class="checklist">
							<?php foreach ( $objectives as $objective ) : ?>
								<li class="checklist__item">
									<span class="checklist__icon" aria-hidden="true">✓</span>
									<?php echo esc_html( $objective ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<section class="course-section" aria-labelledby="course-content-title">
					<h2 class="course-section__title" id="course-content-title"><?php esc_html_e( 'Course Content', 'cta-lms' ); ?></h2>
					<?php if ( empty( $modules ) ) : ?>
						<p><?php esc_html_e( 'Course modules coming soon.', 'cta-lms' ); ?></p>
					<?php else : ?>
						<div class="accordion accordion--course-curriculum" data-accordion="single">
							<?php foreach ( $modules as $index => $module ) : ?>
								<div class="accordion-item <?php echo 0 === $index ? 'accordion-item--active' : ''; ?>">
									<button type="button" class="accordion-item__header accordion-item__header--module" aria-expanded="<?php echo 0 === $index ? 'true' : 'false'; ?>">
										<span class="accordion-item__header-inner">
											<span class="accordion-item__module-title"><?php echo esc_html( ( $index + 1 ) . '. ' . $module->title ); ?></span>
											<span class="accordion-item__meta">
												<span><?php echo esc_html( (int) $module->duration_mins . ' min' ); ?></span>
											</span>
										</span>
										<span class="accordion-item__icon" aria-hidden="true"></span>
									</button>
									<div class="accordion-item__body">
										<div class="accordion-item__content">
											<?php if ( ! empty( $module->description ) ) : ?>
												<p><?php echo esc_html( $module->description ); ?></p>
											<?php endif; ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<aside class="course-detail__sidebar course-sidebar" aria-label="<?php esc_attr_e( 'Course enrollment', 'cta-lms' ); ?>">
				<div class="course-sidebar__card">
					<p class="course-sidebar__price">$<?php echo esc_html( number_format( (float) $course->price, 2 ) ); ?></p>
					<ul class="course-sidebar__meta">
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Format:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Self-paced, online', 'cta-lms' ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Modules:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) count( $modules ) ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'CE Hours:', 'cta-lms' ); ?></strong> <?php echo esc_html( $ce_hours ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Duration:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) $duration_hours ); ?> <?php esc_html_e( 'hours', 'cta-lms' ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Certificate:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Provided on completion', 'cta-lms' ); ?></span></li>
					</ul>

					<?php if ( $is_enrolled && $player_url ) : ?>
						<a href="<?php echo esc_url( $player_url ); ?>" class="btn btn-primary btn--lg course-sidebar__enroll"><?php esc_html_e( 'Continue Learning', 'cta-lms' ); ?></a>
					<?php else : ?>
						<button type="button" id="enroll-btn" class="btn btn-primary btn--lg course-sidebar__enroll" data-cta-course-checkout data-course-id="<?php echo esc_attr( $course->id ); ?>" data-course-title="<?php echo esc_attr( $course->title ); ?>" data-price="$<?php echo esc_attr( number_format( (float) $course->price, 2 ) ); ?>">
							<?php esc_html_e( 'Enroll Now', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</section>
</div>
</div>
