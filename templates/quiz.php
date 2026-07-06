<?php
/**
 * Course quiz page template.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cert_url = $certificate ? CTA_Database::get_certificate_url( $certificate ) : '';
?>
<div class="cta-plugin-wrapper">
<div
	class="cta-lms cta-quiz-page"
	id="cta-quiz-app"
	data-course-id="<?php echo esc_attr( $course->id ); ?>"
	data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>"
	data-attempt-id="<?php echo esc_attr( $active_attempt ? $active_attempt->id : 0 ); ?>"
	data-time-limit="<?php echo esc_attr( (int) $quiz->time_limit_mins ); ?>"
	data-passing-score="<?php echo esc_attr( (int) $quiz->passing_score ); ?>"
	data-question-count="<?php echo esc_attr( $question_count ); ?>"
	data-view-state="<?php echo esc_attr( $view_state ); ?>"
>
	<div class="cta-quiz-header">
		<p class="course-player__back">
			<?php if ( $player_url ) : ?>
				<a href="<?php echo esc_url( $player_url ); ?>">&larr; <?php echo esc_html__( 'Back to Course', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</p>
		<h1 class="cta-quiz-course-title"><?php echo esc_html( $course->title ); ?></h1>
		<div class="cta-quiz-timer" id="cta-quiz-timer" hidden aria-live="polite"></div>
	</div>

	<?php if ( 'max_attempts' === $view_state ) : ?>
		<div class="cta-quiz-panel cta-quiz-panel--active" data-quiz-panel="max_attempts">
			<div class="cta-empty-state">
				<h2><?php echo esc_html__( 'Maximum Attempts Reached', 'cta-lms' ); ?></h2>
				<p><?php echo esc_html__( 'Maximum attempts reached. Contact support for assistance.', 'cta-lms' ); ?></p>
				<a href="mailto:info@ctacademy.org" class="btn btn-outline"><?php echo esc_html__( 'Contact Support', 'cta-lms' ); ?></a>
			</div>
		</div>
	<?php endif; ?>

	<div class="cta-quiz-panel <?php echo 'start' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="start" <?php echo 'start' !== $view_state ? 'hidden' : ''; ?>>
		<div class="card cta-quiz-start-card">
			<h2><?php echo esc_html( $quiz->title ); ?></h2>
			<div class="cta-quiz-info-grid">
				<div><strong><?php echo esc_html__( 'Questions', 'cta-lms' ); ?></strong><span><?php echo esc_html( (string) $question_count ); ?></span></div>
				<div><strong><?php echo esc_html__( 'Passing Score', 'cta-lms' ); ?></strong><span><?php echo esc_html( (int) $quiz->passing_score ); ?>%</span></div>
				<div><strong><?php echo esc_html__( 'Time Limit', 'cta-lms' ); ?></strong><span><?php echo esc_html( $time_limit_label ); ?></span></div>
				<div><strong><?php echo esc_html__( 'Your Attempts', 'cta-lms' ); ?></strong><span><?php echo esc_html( (string) $attempt_count . ' / ' . (int) $quiz->max_attempts ); ?></span></div>
			</div>
			<?php if ( $last_attempt ) : ?>
				<p class="cta-quiz-last-attempt">
					<?php
					$result_label = (int) $last_attempt->passed
						? esc_html__( 'Passed', 'cta-lms' )
						: esc_html__( 'Failed', 'cta-lms' );
					printf(
						/* translators: 1: score, 2: result */
						esc_html__( 'Last attempt: %1$d%% — %2$s', 'cta-lms' ),
						(int) $last_attempt->score,
						$result_label
					);
					?>
				</p>
			<?php endif; ?>
			<button type="button" class="btn btn-primary btn--lg" id="cta-start-quiz"><?php echo esc_html__( 'Start Quiz', 'cta-lms' ); ?></button>
		</div>
	</div>

	<div class="cta-quiz-panel <?php echo 'in_progress' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="questions" <?php echo 'in_progress' !== $view_state ? 'hidden' : ''; ?>>
		<p class="cta-quiz-progress" id="cta-quiz-progress"><?php echo esc_html__( 'Questions answered: 0 of 0', 'cta-lms' ); ?></p>
		<form id="cta-quiz-form" class="cta-quiz-form">
			<div id="cta-quiz-questions">
				<?php
				if ( 'in_progress' === $view_state && $active_attempt ) {
					echo $quiz_handler->render_quiz_questions( $quiz, $active_attempt, $questions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<div class="cta-quiz-submit-section">
				<p class="cta-quiz-submit-warning"><?php echo esc_html__( 'Are you sure? You cannot change answers after submitting.', 'cta-lms' ); ?></p>
				<button type="button" class="btn btn-primary" id="cta-submit-quiz" disabled><?php echo esc_html__( 'Submit Quiz', 'cta-lms' ); ?></button>
			</div>
		</form>
	</div>

	<div class="cta-quiz-panel" data-quiz-panel="result" hidden>
		<div class="cta-quiz-result" id="cta-quiz-result"></div>
	</div>

	<div class="cta-quiz-panel <?php echo 'evaluation' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="evaluation" <?php echo 'evaluation' !== $view_state ? 'hidden' : ''; ?>>
		<div class="card cta-quiz-evaluation">
			<h2><?php echo esc_html__( 'Course Evaluation', 'cta-lms' ); ?></h2>
			<p><?php echo esc_html__( 'Please complete this evaluation to receive your certificate.', 'cta-lms' ); ?></p>
			<form id="cta-evaluation-form">
				<?php
				$rating_fields = array(
					'rating'             => __( 'Overall Course Rating', 'cta-lms' ),
					'content_quality'    => __( 'Content Quality', 'cta-lms' ),
					'instructor_rating'  => __( 'Instructor Rating', 'cta-lms' ),
				);
				foreach ( $rating_fields as $field => $label ) :
					?>
					<div class="form-group">
						<span class="form-label"><?php echo esc_html( $label ); ?></span>
						<div class="cta-star-rating">
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<label><input type="radio" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( (string) $i ); ?>" required> <?php echo esc_html( (string) $i ); ?></label>
							<?php endfor; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="form-group">
					<span class="form-label"><?php echo esc_html__( 'Would you recommend this course?', 'cta-lms' ); ?></span>
					<label><input type="radio" name="would_recommend" value="yes" required> <?php echo esc_html__( 'Yes', 'cta-lms' ); ?></label>
					<label><input type="radio" name="would_recommend" value="no" required> <?php echo esc_html__( 'No', 'cta-lms' ); ?></label>
				</div>
				<div class="form-group">
					<label class="form-label" for="evaluation-comments"><?php echo esc_html__( 'Additional Comments (optional)', 'cta-lms' ); ?></label>
					<textarea id="evaluation-comments" name="comments" class="form-input" rows="4"></textarea>
				</div>
				<button type="button" class="btn btn-primary" id="cta-submit-evaluation"><?php echo esc_html__( 'Submit Evaluation & Get Certificate', 'cta-lms' ); ?></button>
			</form>
		</div>
	</div>

	<div class="cta-quiz-panel <?php echo 'certificate_ready' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="certificate" <?php echo 'certificate_ready' !== $view_state ? 'hidden' : ''; ?>>
		<div class="cta-quiz-certificate-ready card">
			<div class="cta-quiz-certificate-ready__icon" aria-hidden="true">🏆</div>
			<h2><?php echo esc_html__( 'Your certificate is ready!', 'cta-lms' ); ?></h2>
			<?php if ( $certificate ) : ?>
				<p><?php echo esc_html__( 'Certificate number:', 'cta-lms' ); ?> <strong><?php echo esc_html( $certificate->certificate_number ); ?></strong></p>
			<?php endif; ?>
			<?php if ( $cert_url && $certificate ) : ?>
				<a href="<?php echo esc_url( $cert_url ); ?>" class="btn btn-primary cta-download-cert-btn" data-certificate-id="<?php echo esc_attr( $certificate->id ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html__( 'Download Certificate', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $dashboard_url ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-outline"><?php echo esc_html__( 'Return to Dashboard', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
</div>
