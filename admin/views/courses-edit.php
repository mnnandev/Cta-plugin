<?php
/**
 * Admin course edit view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = (bool) $course;
$notice  = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );

$course_video_type  = 'vimeo';
$course_video_value = '';
$course_video_url   = '';

if ( $course ) {
	$course_video_url = (string) ( $course->video_url ?? '' );

	if ( '' !== $course_video_url ) {
		if ( false !== strpos( $course_video_url, 'youtube.com' ) || false !== strpos( $course_video_url, 'youtu.be' ) ) {
			$course_video_type  = 'youtube';
			$course_video_value = $course_video_url;
		} elseif ( false !== strpos( $course_video_url, 'vimeo.com' ) ) {
			$course_video_type = 'vimeo';
			if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $course_video_url, $matches ) ) {
				$course_video_value = $matches[1];
			}
		} elseif ( false !== strpos( $course_video_url, '/wp-content/' ) ) {
			$course_video_type  = 'wordpress';
			$course_video_value = $course_video_url;
		} else {
			$course_video_type  = 'url';
			$course_video_value = $course_video_url;
		}
	} elseif ( ! empty( $course->vimeo_id ) ) {
		$course_video_type  = 'vimeo';
		$course_video_value = preg_replace( '/\D/', '', (string) $course->vimeo_id );
	}
}
?>
<div class="wrap cta-admin-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Course', 'cta-lms' ) : esc_html__( 'Add New Course', 'cta-lms' ); ?></h1>

	<?php if ( 'course_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Course saved successfully.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'course_save_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Course could not be saved. Check that only one CTA LMS plugin is installed, then deactivate and reactivate the plugin.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-admin-form">
		<?php wp_nonce_field( 'cta_save_course' ); ?>
		<input type="hidden" name="action" value="cta_save_course">
		<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>">

		<div class="cta-admin-panel">
			<table class="form-table">
				<tr>
					<th><label for="cta-course-title"><?php esc_html_e( 'Course Title', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta-course-title" name="title" value="<?php echo esc_attr( $course->title ?? '' ); ?>" required></td>
				</tr>
				<tr>
					<th><label for="cta-course-slug"><?php esc_html_e( 'Slug', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta-course-slug" name="slug" value="<?php echo esc_attr( $course->slug ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta-course-category"><?php esc_html_e( 'Category', 'cta-lms' ); ?></label></th>
					<td>
						<select id="cta-course-category" name="category">
							<option value=""><?php esc_html_e( 'Select category', 'cta-lms' ); ?></option>
							<?php foreach ( $categories as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $course->category ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-ce-hours"><?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></label></th>
					<td><input type="number" step="0.5" min="0" id="cta-course-ce-hours" name="ce_hours" value="<?php echo esc_attr( $course->ce_hours ?? '0' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta-course-price"><?php esc_html_e( 'Price', 'cta-lms' ); ?></label></th>
					<td><input type="number" step="0.01" min="0" id="cta-course-price" name="price" value="<?php echo esc_attr( $course->price ?? '0' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta-course-description"><?php esc_html_e( 'Description', 'cta-lms' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$course->description ?? '',
							'cta-course-description',
							array(
								'textarea_name' => 'description',
								'textarea_rows' => 10,
								'media_buttons' => false,
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Learning Objectives', 'cta-lms' ); ?></th>
					<td>
						<div id="cta-objectives-repeater" class="cta-objectives-repeater">
							<?php foreach ( $objectives as $objective ) : ?>
								<div class="cta-objective-row">
									<input type="text" class="regular-text" name="learning_objectives[]" value="<?php echo esc_attr( $objective ); ?>">
									<button type="button" class="button cta-remove-objective"><?php esc_html_e( 'Remove', 'cta-lms' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="cta-add-objective"><?php esc_html_e( 'Add Objective', 'cta-lms' ); ?></button>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-thumbnail"><?php esc_html_e( 'Thumbnail URL', 'cta-lms' ); ?></label></th>
					<td>
						<input type="url" class="regular-text" id="cta-course-thumbnail" name="thumbnail_url" value="<?php echo esc_url( $course->thumbnail_url ?? '' ); ?>">
						<?php if ( ! empty( $course->thumbnail_url ) ) : ?>
							<p><img src="<?php echo esc_url( $course->thumbnail_url ); ?>" alt="" class="cta-thumb-preview"></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-video-type"><?php esc_html_e( 'Preview Video', 'cta-lms' ); ?></label></th>
					<td>
						<p>
							<label for="cta-course-video-type"><strong><?php esc_html_e( 'Video Source', 'cta-lms' ); ?></strong></label><br>
							<select id="cta-course-video-type" name="course_video_type">
								<option value="vimeo" <?php selected( $course_video_type, 'vimeo' ); ?>><?php esc_html_e( 'Vimeo', 'cta-lms' ); ?></option>
								<option value="youtube" <?php selected( $course_video_type, 'youtube' ); ?>><?php esc_html_e( 'YouTube URL', 'cta-lms' ); ?></option>
								<option value="wordpress" <?php selected( $course_video_type, 'wordpress' ); ?>><?php esc_html_e( 'WordPress Media Library', 'cta-lms' ); ?></option>
								<option value="url" <?php selected( $course_video_type, 'url' ); ?>><?php esc_html_e( 'Direct Video URL (MP4)', 'cta-lms' ); ?></option>
							</select>
						</p>
						<p class="cta-course-video-row">
							<input type="text" class="regular-text" id="cta-course-video-value" name="course_video_value" value="<?php echo esc_attr( $course_video_value ); ?>" placeholder="<?php esc_attr_e( 'Vimeo ID or video URL', 'cta-lms' ); ?>">
							<input type="hidden" id="cta-course-video-url" name="course_video_url" value="<?php echo esc_url( $course_video_url ); ?>">
							<button type="button" class="button" id="cta-course-video-select" style="display:none;"><?php esc_html_e( 'Select Video', 'cta-lms' ); ?></button>
						</p>
						<p class="description cta-course-video-help" data-help="vimeo"><?php esc_html_e( 'Enter the Vimeo video ID (numbers only). Used as fallback when a module has no video.', 'cta-lms' ); ?></p>
						<p class="description cta-course-video-help" data-help="youtube" style="display:none;"><?php esc_html_e( 'Example: https://www.youtube.com/watch?v=VIDEO_ID', 'cta-lms' ); ?></p>
						<p class="description cta-course-video-help" data-help="wordpress" style="display:none;"><?php esc_html_e( 'Click Select Video to choose an uploaded MP4 from your Media Library.', 'cta-lms' ); ?></p>
						<p class="description cta-course-video-help" data-help="url" style="display:none;"><?php esc_html_e( 'Paste a direct link to an MP4 or other supported video file.', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
					<td>
						<label><input type="radio" name="status" value="published" <?php checked( $course->status ?? 'draft', 'published' ); ?>> <?php esc_html_e( 'Published', 'cta-lms' ); ?></label>
						<label><input type="radio" name="status" value="draft" <?php checked( $course->status ?? 'draft', 'draft' ); ?>> <?php esc_html_e( 'Draft', 'cta-lms' ); ?></label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Course', 'cta-lms' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-courses' ) ); ?>"><?php esc_html_e( 'Back to Courses', 'cta-lms' ); ?></a>
			</p>
		</div>
	</form>

	<?php if ( $course_id ) : ?>
		<div class="cta-admin-panel" id="cta-modules-panel" data-course-id="<?php echo esc_attr( (string) $course_id ); ?>">
			<h2><?php esc_html_e( 'Course Modules', 'cta-lms' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th></th>
						<th><?php esc_html_e( 'Order', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Title', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
					</tr>
				</thead>
				<tbody id="cta-modules-list">
					<?php foreach ( $modules as $module ) : ?>
						<?php echo $admin->render_module_row_html( $module ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Add Module', 'cta-lms' ); ?></h3>
			<div class="cta-module-form">
				<input type="hidden" id="cta-module-id" value="">
				<p><input type="text" id="cta-module-title" class="regular-text" placeholder="<?php esc_attr_e( 'Module title', 'cta-lms' ); ?>"></p>
				<p><textarea id="cta-module-description" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'Description', 'cta-lms' ); ?>"></textarea></p>
				<p>
					<label for="cta-module-video-type"><strong><?php esc_html_e( 'Video Source', 'cta-lms' ); ?></strong></label><br>
					<select id="cta-module-video-type">
						<option value="vimeo"><?php esc_html_e( 'Vimeo', 'cta-lms' ); ?></option>
						<option value="youtube"><?php esc_html_e( 'YouTube URL', 'cta-lms' ); ?></option>
						<option value="wordpress"><?php esc_html_e( 'WordPress Media Library', 'cta-lms' ); ?></option>
						<option value="url"><?php esc_html_e( 'Direct Video URL (MP4)', 'cta-lms' ); ?></option>
					</select>
				</p>
				<p class="cta-module-video-row">
					<input type="text" id="cta-module-video" class="regular-text" placeholder="<?php esc_attr_e( 'Vimeo ID or video URL', 'cta-lms' ); ?>">
					<button type="button" class="button" id="cta-module-video-select" style="display:none;"><?php esc_html_e( 'Select Video', 'cta-lms' ); ?></button>
				</p>
				<p class="description cta-module-video-help" data-help="vimeo"><?php esc_html_e( 'Enter the Vimeo video ID (numbers only) or full Vimeo URL.', 'cta-lms' ); ?></p>
				<p class="description cta-module-video-help" data-help="youtube"><?php esc_html_e( 'Example: https://www.youtube.com/watch?v=VIDEO_ID', 'cta-lms' ); ?></p>
				<p class="description cta-module-video-help" data-help="wordpress" style="display:none;"><?php esc_html_e( 'Click Select Video to choose an uploaded MP4 from your Media Library.', 'cta-lms' ); ?></p>
				<p class="description cta-module-video-help" data-help="url" style="display:none;"><?php esc_html_e( 'Paste a direct link to an MP4 or other supported video file.', 'cta-lms' ); ?></p>
				<p>
					<input type="number" id="cta-module-duration" min="0" placeholder="<?php esc_attr_e( 'Duration (mins)', 'cta-lms' ); ?>">
					<label><input type="checkbox" id="cta-module-locked" checked> <?php esc_html_e( 'Locked until previous complete', 'cta-lms' ); ?></label>
				</p>
				<button type="button" class="button button-primary" id="cta-save-module"><?php esc_html_e( 'Add Module', 'cta-lms' ); ?></button>
			</div>
		</div>

		<div class="cta-admin-panel" id="cta-quiz-panel" data-course-id="<?php echo esc_attr( (string) $course_id ); ?>">
			<h2><?php esc_html_e( 'Course Quiz', 'cta-lms' ); ?></h2>
			<div id="cta-quiz-status-line" class="cta-quiz-status-line">
				<?php if ( $quiz ) : ?>
					<p><?php esc_html_e( 'Quiz exists for this course.', 'cta-lms' ); ?> <strong><?php echo esc_html( $quiz->title ); ?></strong></p>
				<?php else : ?>
					<p><?php esc_html_e( 'No quiz created yet.', 'cta-lms' ); ?></p>
				<?php endif; ?>
			</div>

			<div id="cta-quiz-saved-list" class="cta-quiz-saved-list">
				<?php if ( ! empty( $quiz_questions ) ) : ?>
					<h3><?php esc_html_e( 'Saved Questions', 'cta-lms' ); ?> (<?php echo esc_html( (string) count( $quiz_questions ) ); ?>)</h3>
					<ol class="cta-quiz-saved-list__items">
						<?php foreach ( $quiz_questions as $index => $question ) : ?>
							<li>
								<strong><?php echo esc_html( sprintf( __( 'Q%d', 'cta-lms' ), $index + 1 ) ); ?>:</strong>
								<?php echo esc_html( wp_trim_words( $question->question_text, 12 ) ); ?>
								<span class="cta-quiz-saved-list__answer">(<?php echo esc_html( strtoupper( $question->correct_option ) ); ?>)</span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>

			<p>
				<label for="cta-quiz-title"><strong><?php esc_html_e( 'Quiz Title', 'cta-lms' ); ?></strong></label><br>
				<input type="text" id="cta-quiz-title" class="regular-text" placeholder="<?php esc_attr_e( 'Quiz title', 'cta-lms' ); ?>" value="<?php echo esc_attr( $quiz->title ?? '' ); ?>">
			</p>

			<div id="cta-quiz-builder" class="cta-quiz-builder">
				<div id="cta-quiz-questions" class="cta-quiz-questions"></div>
				<p>
					<button type="button" class="button" id="cta-add-quiz-question"><?php esc_html_e( '+ Add Question', 'cta-lms' ); ?></button>
				</p>
			</div>

			<p>
				<button type="button" class="button button-primary" id="cta-save-quiz"><?php echo $quiz ? esc_html__( 'Save Quiz', 'cta-lms' ) : esc_html__( 'Create Quiz', 'cta-lms' ); ?></button>
				<span id="cta-quiz-save-status" class="cta-inline-result"></span>
			</p>
			<p class="description"><?php esc_html_e( 'Add multiple-choice questions below. Students must pass the quiz to earn their certificate.', 'cta-lms' ); ?></p>
		</div>
	<?php else : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Save the course first to add modules and a quiz.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>
</div>
