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
 * @var string $preview_video
 * @var object|null $quiz
 * @var array  $quiz_questions
 * @var string $login_url
 * @var bool   $is_free_course
 * @var CTA_Student_Dashboard $video_helper
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
				<?php if ( ! empty( $preview_video ) ) : ?>
					<?php echo $preview_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php elseif ( ! empty( $course->thumbnail_url ) ) : ?>
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
					<?php if ( empty( $modules ) && empty( $quiz ) ) : ?>
						<p><?php esc_html_e( 'Course modules coming soon.', 'cta-lms' ); ?></p>
					<?php else : ?>
						<?php if ( ! empty( $modules ) ) : ?>
							<ul class="course-module-list">
								<?php foreach ( $modules as $index => $module ) : ?>
									<?php
									$module_video_markup = $video_helper->get_module_video_markup( $module, $course );
									$has_module_video    = ! empty( trim( (string) $module->video_url ) );
									$module_video_id     = 'cta-module-video-' . (int) $module->id;
									?>
									<li class="course-module-list__item<?php echo $has_module_video ? ' course-module-list__item--has-video' : ''; ?>">
										<div class="course-module-list__header">
											<span class="course-module-list__number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
											<div class="course-module-list__info">
												<strong class="course-module-list__title"><?php echo esc_html( $module->title ); ?></strong>
												<?php if ( ! empty( $module->description ) ) : ?>
													<p class="course-module-list__desc"><?php echo esc_html( $module->description ); ?></p>
												<?php endif; ?>
												<?php if ( $has_module_video ) : ?>
													<span class="course-module-list__video-tag">
														<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
														<?php esc_html_e( 'Video lesson', 'cta-lms' ); ?>
													</span>
												<?php endif; ?>
											</div>
											<span class="course-module-list__duration"><?php echo esc_html( (int) $module->duration_mins . ' min' ); ?></span>
											<?php if ( $has_module_video ) : ?>
												<button
													type="button"
													class="course-module-list__play"
													data-cta-module-preview
													data-module-title="<?php echo esc_attr( $module->title ); ?>"
													data-target="<?php echo esc_attr( $module_video_id ); ?>"
													aria-label="<?php echo esc_attr( sprintf( __( 'Preview video: %s', 'cta-lms' ), $module->title ) ); ?>"
												>
													<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
												</button>
												<div id="<?php echo esc_attr( $module_video_id ); ?>" class="cta-module-preview-source" hidden>
													<?php echo $module_video_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</div>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $quiz ) : ?>
							<div class="course-module-list__quiz">
								<div class="course-module-list__header course-module-list__header--quiz">
									<span class="course-module-list__number" aria-hidden="true">✓</span>
									<div class="course-module-list__info">
										<strong class="course-module-list__title"><?php echo esc_html( $quiz->title ); ?></strong>
										<p class="course-module-list__desc">
											<?php
											printf(
												/* translators: 1: question count, 2: passing score */
												esc_html__( 'Final quiz — %1$d questions, %2$d%% required to pass.', 'cta-lms' ),
												count( $quiz_questions ),
												(int) $quiz->passing_score
											);
											?>
										</p>
									</div>
									<span class="course-module-list__badge"><?php esc_html_e( 'Quiz', 'cta-lms' ); ?></span>
								</div>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</section>
			</div>

			<aside class="course-detail__sidebar course-sidebar" aria-label="<?php esc_attr_e( 'Course enrollment', 'cta-lms' ); ?>">
				<div class="course-sidebar__card">
					<p class="course-sidebar__price">
						<?php if ( $is_free_course ) : ?>
							<?php esc_html_e( 'Free', 'cta-lms' ); ?>
						<?php else : ?>
							$<?php echo esc_html( number_format( (float) $course->price, 2 ) ); ?>
						<?php endif; ?>
					</p>
					<ul class="course-sidebar__meta">
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Format:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Self-paced, online', 'cta-lms' ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Modules:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) count( $modules ) ); ?></span></li>
						<?php if ( $quiz ) : ?>
							<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Quiz:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) count( $quiz_questions ) ); ?> <?php esc_html_e( 'questions', 'cta-lms' ); ?></span></li>
						<?php endif; ?>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'CE Hours:', 'cta-lms' ); ?></strong> <?php echo esc_html( $ce_hours ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Duration:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) $duration_hours ); ?> <?php esc_html_e( 'hours', 'cta-lms' ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Certificate:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Provided on completion', 'cta-lms' ); ?></span></li>
					</ul>

					<?php if ( ! $is_free_course ) : ?>
						<p class="course-sidebar__label"><?php esc_html_e( 'Secure Payment:', 'cta-lms' ); ?></p>
						<div class="course-sidebar__payments" aria-label="<?php esc_attr_e( 'Accepted payment methods', 'cta-lms' ); ?>">
							<span class="course-sidebar__payment-icon">Visa</span>
							<span class="course-sidebar__payment-icon">MC</span>
							<span class="course-sidebar__payment-icon">Amex</span>
							<span class="course-sidebar__payment-icon">Stripe</span>
						</div>
					<?php endif; ?>

					<?php if ( $is_enrolled && $player_url ) : ?>
						<a href="<?php echo esc_url( $player_url ); ?>" class="btn btn-primary btn--lg course-sidebar__enroll"><?php esc_html_e( 'Continue Learning', 'cta-lms' ); ?></a>
					<?php elseif ( $is_enrolled ) : ?>
						<p class="course-sidebar__notice"><?php esc_html_e( 'You are enrolled. Configure the Course Player page in CTA LMS Settings to start learning.', 'cta-lms' ); ?></p>
					<?php elseif ( ! is_user_logged_in() && $login_url ) : ?>
						<a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-primary btn--lg course-sidebar__enroll">
							<?php esc_html_e( 'Login to Enroll', 'cta-lms' ); ?>
						</a>
					<?php else : ?>
						<button type="button" id="enroll-btn" class="btn btn-primary btn--lg course-sidebar__enroll" data-cta-course-checkout data-course-id="<?php echo esc_attr( $course->id ); ?>" data-course-title="<?php echo esc_attr( $course->title ); ?>" data-price="<?php echo esc_attr( $is_free_course ? __( 'Free', 'cta-lms' ) : '$' . number_format( (float) $course->price, 2 ) ); ?>">
							<?php echo $is_free_course ? esc_html__( 'Enroll Free', 'cta-lms' ) : esc_html__( 'Enroll Now', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</section>

	<div class="cta-video-modal" id="cta-course-video-modal" hidden>
		<div class="cta-video-modal__backdrop" data-cta-close-video-modal></div>
		<div class="cta-video-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cta-course-video-modal-title">
			<button type="button" class="cta-video-modal__close" data-cta-close-video-modal aria-label="<?php esc_attr_e( 'Close video', 'cta-lms' ); ?>">&times;</button>
			<h3 class="cta-video-modal__title" id="cta-course-video-modal-title"></h3>
			<div class="cta-video-modal__player"></div>
		</div>
	</div>
</div>
</div>
