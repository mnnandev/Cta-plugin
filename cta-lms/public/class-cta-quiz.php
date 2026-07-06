<?php
/**
 * Course quiz shortcode and AJAX handlers.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Quiz
 */
if ( ! class_exists( 'CTA_Quiz' ) ) {

class CTA_Quiz {

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_quiz', array( $this, 'render_quiz' ) );

		add_action( 'wp_ajax_cta_start_quiz', array( $this, 'ajax_start_quiz' ) );
		add_action( 'wp_ajax_cta_submit_quiz', array( $this, 'ajax_submit_quiz' ) );
		add_action( 'wp_ajax_cta_submit_evaluation', array( $this, 'ajax_submit_evaluation' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Add quiz page body class.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'cta_quiz' ) ) {
			$classes[] = 'dashboard-page';
		}

		return $classes;
	}

	/**
	 * Render quiz shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_quiz( $atts ) {
		if ( ! is_user_logged_in() ) {
			return $this->redirect_markup( $this->get_login_url() );
		}

		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;

		if ( ! $course_id && isset( $_GET['course'] ) ) {
			$course_id = absint( wp_unslash( $_GET['course'] ) );
		}

		if ( ! $course_id ) {
			$dashboard_url = get_permalink( get_option( 'cta_student_dashboard_page_id' ) );
			ob_start();
			?>
			<div class="cta-plugin-wrapper">
				<div class="cta-empty-state" style="text-align:center; padding:60px 20px;">
					<h2><?php esc_html_e( 'No Course Selected', 'cta-lms' ); ?></h2>
					<p><?php esc_html_e( 'Please access the quiz from your course page.', 'cta-lms' ); ?></p>
					<?php if ( $dashboard_url ) : ?>
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-primary"><?php esc_html_e( 'Go to Dashboard', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$user_id    = get_current_user_id();
		$course     = CTA_Database::get_course( $course_id );
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		$quiz       = CTA_Database::get_quiz_by_course( $course_id );

		if ( ! $course ) {
			return '<div class="cta-plugin-wrapper"><div class="cta-empty-state"><p>' . esc_html__( 'Course not found.', 'cta-lms' ) . '</p></div></div>';
		}

		if ( ! $enrollment ) {
			return $this->render_message_state(
				__( 'Enrollment Required', 'cta-lms' ),
				__( 'You must be enrolled in this course to take the quiz.', 'cta-lms' ),
				$this->get_course_page_url( $course_id ),
				__( 'View Course', 'cta-lms' )
			);
		}

		if ( (int) $enrollment->progress < 100 ) {
			return $this->render_message_state(
				__( 'Complete All Modules First', 'cta-lms' ),
				__( 'Finish every module before starting the course quiz.', 'cta-lms' ),
				$this->get_player_url( $course_id ),
				__( 'Back to Course', 'cta-lms' )
			);
		}

		if ( ! $quiz ) {
			return '<div class="cta-plugin-wrapper"><div class="cta-empty-state"><p>' . esc_html__( 'No quiz is available for this course yet.', 'cta-lms' ) . '</p></div></div>';
		}

		$questions       = CTA_Database::get_quiz_questions( (int) $quiz->id );
		$attempts        = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );
		$active_attempt  = CTA_Database::get_active_quiz_attempt( $user_id, (int) $quiz->id );
		$evaluation      = CTA_Database::get_course_evaluation( $user_id, $course_id );
		$certificate     = CTA_Certificates::get_certificate( $user_id, $course_id );
		$passed_attempt  = $this->get_passed_attempt( $attempts );
		$attempt_count   = count( $attempts );
		$last_attempt    = ! empty( $attempts ) ? $attempts[0] : null;
		$max_reached     = $attempt_count >= (int) $quiz->max_attempts && ! $passed_attempt;
		$view_state      = 'start';

		if ( $certificate && $evaluation && $passed_attempt ) {
			$view_state = 'certificate_ready';
		} elseif ( $passed_attempt && ! $evaluation ) {
			$view_state = 'evaluation';
		} elseif ( $max_reached ) {
			$view_state = 'max_attempts';
		} elseif ( $active_attempt ) {
			$view_state = 'in_progress';
		} elseif ( $passed_attempt && $evaluation ) {
			$view_state = 'certificate_ready';
		}

		$dashboard_url = $this->get_dashboard_url();
		$player_url    = $this->get_player_url( $course_id );
		$quiz_handler  = $this;
		$question_count = count( $questions );
		$time_limit_label = (int) $quiz->time_limit_mins > 0
			? sprintf( __( '%d minutes', 'cta-lms' ), (int) $quiz->time_limit_mins )
			: __( 'No limit', 'cta-lms' );

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/quiz.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: start a new quiz attempt.
	 */
	public function ajax_start_quiz() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$user_id   = get_current_user_id();
		$check     = $this->validate_quiz_access( $user_id, $course_id );

		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		/** @var object $quiz */
		$quiz       = $check['quiz'];
		$attempts   = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );
		$active     = CTA_Database::get_active_quiz_attempt( $user_id, (int) $quiz->id );

		if ( $active ) {
			wp_send_json_success( $this->build_attempt_payload( $quiz, $active ) );
		}

		if ( $this->get_passed_attempt( $attempts ) ) {
			wp_send_json_error( array( 'message' => __( 'You have already passed this quiz.', 'cta-lms' ) ) );
		}

		if ( count( $attempts ) >= (int) $quiz->max_attempts ) {
			wp_send_json_error( array( 'message' => __( 'Maximum attempts reached.', 'cta-lms' ) ) );
		}

		global $wpdb;

		$attempt_number = count( $attempts ) + 1;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_quiz_attempts',
			array(
				'user_id'        => $user_id,
				'quiz_id'        => (int) $quiz->id,
				'course_id'      => $course_id,
				'attempt_number' => $attempt_number,
				'started_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to start quiz.', 'cta-lms' ) ) );
		}

		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quiz_attempts WHERE id = %d",
				(int) $wpdb->insert_id
			)
		);

		wp_send_json_success( $this->build_attempt_payload( $quiz, $attempt ) );
	}

	/**
	 * AJAX: submit quiz answers.
	 */
	public function ajax_submit_quiz() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$attempt_id = absint( wp_unslash( $_POST['attempt_id'] ?? 0 ) );
		$user_id    = get_current_user_id();
		$answers_in = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();

		if ( ! is_array( $answers_in ) ) {
			$answers_in = array();
		}

		global $wpdb;

		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quiz_attempts WHERE id = %d AND user_id = %d",
				$attempt_id,
				$user_id
			)
		);

		if ( ! $attempt || ! empty( $attempt->completed_at ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid quiz attempt.', 'cta-lms' ) ) );
		}

		$quiz      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quizzes WHERE id = %d",
				(int) $attempt->quiz_id
			)
		);
		$questions = CTA_Database::get_quiz_questions( (int) $attempt->quiz_id );

		if ( ! $quiz || empty( $questions ) ) {
			wp_send_json_error( array( 'message' => __( 'Quiz not found.', 'cta-lms' ) ) );
		}

		$sanitized = array();
		$correct   = 0;
		$total     = count( $questions );
		$revealed  = array();

		foreach ( $questions as $question ) {
			$qid    = (int) $question->id;
			$answer = isset( $answers_in[ $qid ] ) ? sanitize_text_field( $answers_in[ $qid ] ) : '';
			$answer = in_array( $answer, array( 'a', 'b', 'c', 'd' ), true ) ? $answer : '';

			$sanitized[ $qid ] = $answer;

			if ( $answer && $answer === $question->correct_option ) {
				++$correct;
			}

			$revealed[] = array(
				'question_id'    => $qid,
				'user_answer'    => $answer,
				'correct_option' => $question->correct_option,
				'explanation'    => $question->explanation,
				'is_correct'     => ( $answer === $question->correct_option ),
			);
		}

		$score  = $total > 0 ? (int) round( ( $correct / $total ) * 100 ) : 0;
		$passed = $score >= (int) $quiz->passing_score ? 1 : 0;

		$wpdb->update(
			$wpdb->prefix . 'cta_quiz_attempts',
			array(
				'answers'      => wp_json_encode( $sanitized ),
				'score'        => $score,
				'passed'       => $passed,
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $attempt_id ),
			array( '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( $passed ) {
			wp_send_json_success(
				array(
					'passed'     => true,
					'score'      => $score,
					'message'    => sprintf(
						/* translators: %d: score percentage */
						__( 'Congratulations! You passed with %d%%', 'cta-lms' ),
						$score
					),
					'next_step'  => 'evaluation',
					'passing_score' => (int) $quiz->passing_score,
					'results'    => $revealed,
				)
			);
		}

		$attempts_remaining = max( 0, (int) $quiz->max_attempts - (int) $attempt->attempt_number );

		wp_send_json_success(
			array(
				'passed'             => false,
				'score'              => $score,
				'message'            => sprintf(
					/* translators: 1: score, 2: passing score */
					__( 'Score: %1$d%%. Passing score is %2$d%%.', 'cta-lms' ),
					$score,
					(int) $quiz->passing_score
				),
				'attempts_remaining' => $attempts_remaining,
				'can_retry'          => $attempts_remaining > 0,
				'passing_score'      => (int) $quiz->passing_score,
				'results'            => $revealed,
			)
		);
	}

	/**
	 * AJAX: submit course evaluation and issue certificate.
	 */
	public function ajax_submit_evaluation() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$user_id   = get_current_user_id();
		$check     = $this->validate_quiz_access( $user_id, $course_id, false );

		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		/** @var object $quiz */
		$quiz     = $check['quiz'];
		$attempts = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );

		if ( ! $this->get_passed_attempt( $attempts ) ) {
			wp_send_json_error( array( 'message' => __( 'You must pass the quiz before submitting an evaluation.', 'cta-lms' ) ) );
		}

		if ( CTA_Database::get_course_evaluation( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Evaluation already submitted.', 'cta-lms' ) ) );
		}

		$rating             = $this->sanitize_rating( wp_unslash( $_POST['rating'] ?? 0 ) );
		$content_quality    = $this->sanitize_rating( wp_unslash( $_POST['content_quality'] ?? 0 ) );
		$instructor_rating  = $this->sanitize_rating( wp_unslash( $_POST['instructor_rating'] ?? 0 ) );
		$would_recommend    = ! empty( $_POST['would_recommend'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['would_recommend'] ) ) ? 1 : 0;
		$comments           = sanitize_textarea_field( wp_unslash( $_POST['comments'] ?? '' ) );

		if ( ! $rating || ! $content_quality || ! $instructor_rating ) {
			wp_send_json_error( array( 'message' => __( 'Please complete all required rating fields.', 'cta-lms' ) ) );
		}

		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_evaluations',
			array(
				'user_id'           => $user_id,
				'course_id'         => $course_id,
				'rating'            => $rating,
				'content_quality'   => $content_quality,
				'instructor_rating' => $instructor_rating,
				'would_recommend'   => $would_recommend,
				'comments'          => $comments,
				'submitted_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save evaluation.', 'cta-lms' ) ) );
		}

		$certificate = CTA_Certificates::generate( $user_id, $course_id );

		if ( ! $certificate ) {
			wp_send_json_error( array( 'message' => __( 'Evaluation saved but certificate could not be generated.', 'cta-lms' ) ) );
		}

		wp_send_json_success(
			array(
				'message'              => __( 'Thank you! Your certificate is ready.', 'cta-lms' ),
				'certificate_number' => $certificate->certificate_number,
				'download_url'         => CTA_Database::get_certificate_url( $certificate ),
				'dashboard_url'        => $this->get_dashboard_url(),
			)
		);
	}

	/**
	 * Render quiz questions for template or AJAX.
	 *
	 * @param object $quiz     Quiz row.
	 * @param object $attempt  Attempt row.
	 * @param array  $questions Question rows.
	 * @param bool   $review   Whether to show review state.
	 * @return string
	 */
	public function render_quiz_questions( $quiz, $attempt, $questions, $review = false ) {
		$quiz_obj = $this;
		$answers  = array();

		if ( ! empty( $attempt->answers ) ) {
			$decoded = json_decode( (string) $attempt->answers, true );
			if ( is_array( $decoded ) ) {
				$answers = $decoded;
			}
		}

		ob_start();

		foreach ( $questions as $index => $question ) {
			$question_number = $index + 1;
			$user_answer     = isset( $answers[ $question->id ] ) ? $answers[ $question->id ] : '';
			include CTA_PLUGIN_DIR . 'templates/partials/quiz-question.php';
		}

		return ob_get_clean();
	}

	/**
	 * Validate quiz access for a user and course.
	 *
	 * @param int  $user_id           User ID.
	 * @param int  $course_id         Course ID.
	 * @param bool $require_complete  Require 100% module progress.
	 * @return array|WP_Error
	 */
	private function validate_quiz_access( $user_id, $course_id, $require_complete = true ) {
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		$quiz       = CTA_Database::get_quiz_by_course( $course_id );

		if ( ! $enrollment ) {
			return new WP_Error( 'not_enrolled', __( 'You are not enrolled in this course.', 'cta-lms' ) );
		}

		if ( $require_complete && (int) $enrollment->progress < 100 ) {
			return new WP_Error( 'incomplete', __( 'Complete all modules first.', 'cta-lms' ) );
		}

		if ( ! $quiz ) {
			return new WP_Error( 'no_quiz', __( 'Quiz not available.', 'cta-lms' ) );
		}

		return array(
			'enrollment' => $enrollment,
			'quiz'       => $quiz,
		);
	}

	/**
	 * Build AJAX payload for quiz attempt start.
	 *
	 * @param object $quiz    Quiz row.
	 * @param object $attempt Attempt row.
	 * @return array
	 */
	private function build_attempt_payload( $quiz, $attempt ) {
		$questions = CTA_Database::get_quiz_questions( (int) $quiz->id );
		$safe      = array();

		foreach ( $questions as $question ) {
			$safe[] = array(
				'id'            => (int) $question->id,
				'question_text' => $question->question_text,
				'option_a'      => $question->option_a,
				'option_b'      => $question->option_b,
				'option_c'      => $question->option_c,
				'option_d'      => $question->option_d,
				'order_index'   => (int) $question->order_index,
			);
		}

		return array(
			'quiz_id'         => (int) $quiz->id,
			'attempt_id'      => (int) $attempt->id,
			'course_id'       => (int) $attempt->course_id,
			'time_limit_mins' => (int) $quiz->time_limit_mins,
			'passing_score'   => (int) $quiz->passing_score,
			'question_count'  => count( $safe ),
			'questions'       => $safe,
			'html'            => $this->render_quiz_questions( $quiz, $attempt, $questions ),
		);
	}

	/**
	 * Get first passing attempt from list.
	 *
	 * @param array $attempts Attempt rows.
	 * @return object|null
	 */
	private function get_passed_attempt( $attempts ) {
		foreach ( $attempts as $attempt ) {
			if ( (int) $attempt->passed ) {
				return $attempt;
			}
		}

		return null;
	}

	/**
	 * Sanitize star rating 1-5.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function sanitize_rating( $value ) {
		$rating = absint( $value );

		if ( $rating < 1 || $rating > 5 ) {
			return 0;
		}

		return $rating;
	}

	/**
	 * Render simple message state block.
	 *
	 * @param string $title   Title.
	 * @param string $message Message.
	 * @param string $url     Button URL.
	 * @param string $label   Button label.
	 * @return string
	 */
	private function render_message_state( $title, $message, $url, $label ) {
		ob_start();
		?>
		<div class="cta-plugin-wrapper">
		<div class="cta-quiz-page">
			<div class="cta-empty-state">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $message ); ?></p>
				<?php if ( $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="btn btn-primary"><?php echo esc_html( $label ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Redirect markup.
	 *
	 * @param string $url Target URL.
	 * @return string
	 */
	private function redirect_markup( $url ) {
		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		return '<script>window.location.replace(' . wp_json_encode( esc_url_raw( $url ) ) . ');</script>';
	}

	/**
	 * Get login URL.
	 *
	 * @return string
	 */
	private function get_login_url() {
		$page_id = absint( get_option( 'cta_login_page_id', 0 ) );

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return wp_login_url( get_permalink() );
	}

	/**
	 * Get student dashboard URL.
	 *
	 * @return string
	 */
	private function get_dashboard_url() {
		$page_id = absint( get_option( 'cta_student_dashboard_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get course player URL.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function get_player_url( $course_id ) {
		$page_id = absint( get_option( 'cta_course_player_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		return add_query_arg( 'course_id', $course_id, get_permalink( $page_id ) );
	}

	/**
	 * Get single course page URL.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function get_course_page_url( $course_id ) {
		$page_id = absint( get_option( 'cta_single_course_page_id', 0 ) );

		if ( ! $page_id ) {
			$courses_page = absint( get_option( 'cta_courses_page_id', 0 ) );
			return $courses_page ? get_permalink( $courses_page ) : '';
		}

		return add_query_arg( 'course_id', $course_id, get_permalink( $page_id ) );
	}
}
}