<?php
/**
 * Course catalog and AJAX filtering.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Courses
 */
if ( ! class_exists( 'CTA_Courses' ) ) {

class CTA_Courses {

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_course_catalog', array( $this, 'render_catalog' ) );
		add_shortcode( 'cta_single_course', array( $this, 'render_single_course' ) );

		add_action( 'wp_ajax_cta_filter_courses', array( $this, 'ajax_filter_courses' ) );
		add_action( 'wp_ajax_nopriv_cta_filter_courses', array( $this, 'ajax_filter_courses' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Render the course catalog shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_catalog( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => -1,
				'category' => '',
				'columns'  => 3,
			),
			$atts,
			'cta_course_catalog'
		);

		$limit           = intval( $atts['limit'] );
		$columns         = max( 1, min( 4, absint( $atts['columns'] ) ) );
		$active_category = sanitize_text_field( $atts['category'] );
		$search          = '';

		$courses = $this->get_courses(
			array(
				'limit'    => $limit,
				'category' => $active_category,
				'status'   => 'published',
			)
		);

		$categories = $this->get_categories();

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/courses.php';
		return ob_get_clean();
	}

	/**
	 * Render single course detail shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_single_course( $atts ) {
		$course_id = absint( wp_unslash( $_GET['course_id'] ?? 0 ) );

		if ( ! $course_id ) {
			return '<div class="cta-empty-state"><p>' . esc_html__( 'No course specified.', 'cta-lms' ) . '</p></div>';
		}

		$course = CTA_Database::get_course( $course_id );

		if ( ! $course || 'published' !== $course->status ) {
			return '<div class="cta-empty-state"><p>' . esc_html__( 'Course not found.', 'cta-lms' ) . '</p></div>';
		}

		$modules     = CTA_Database::get_course_modules( $course_id );
		$objectives  = array();
		$is_enrolled = false;
		$player_url  = '';

		if ( ! empty( $course->learning_objectives ) ) {
			$decoded = json_decode( (string) $course->learning_objectives, true );
			if ( is_array( $decoded ) ) {
				$objectives = $decoded;
			}
		}

		if ( is_user_logged_in() ) {
			global $wpdb;
			$user_id     = get_current_user_id();
			$is_enrolled = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}cta_enrollments
					WHERE user_id = %d AND course_id = %d AND status IN ('active','completed')",
					$user_id,
					$course_id
				)
			);
		}

		$player_page_id = absint( get_option( 'cta_course_player_page_id', 0 ) );
		if ( $player_page_id ) {
			$permalink = get_permalink( $player_page_id );
			if ( $permalink ) {
				$player_url = add_query_arg( 'course_id', $course_id, $permalink );
			}
		}

		$courses_url = CTA_Emails::get_page_url( 'cta_courses_page_id' );
		$total_mins  = 0;

		foreach ( $modules as $module ) {
			$total_mins += (int) $module->duration_mins;
		}

		$payment_success = isset( $_GET['payment'] ) && 'success' === sanitize_text_field( wp_unslash( $_GET['payment'] ) );
		$quiz            = CTA_Database::get_quiz_by_course( $course_id );
		$quiz_questions  = $quiz ? CTA_Database::get_quiz_questions( (int) $quiz->id ) : array();
		$preview_video   = $this->get_course_preview_video_markup( $course );
		$login_url       = CTA_Emails::get_page_url( 'cta_login_page_id' );
		$is_free_course  = (float) $course->price <= 0;
		$video_helper    = new CTA_Student_Dashboard();

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/single-course.php';
		return ob_get_clean();
	}

	/**
	 * Add body class on course pages.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'cta_single_course' ) ) {
			$classes[] = 'single-course-page';
		}

		return $classes;
	}

	/**
	 * Build preview video markup for the course hero.
	 *
	 * @param object $course Course row.
	 * @return string
	 */
	private function get_course_preview_video_markup( $course ) {
		$video_url = '';

		if ( ! empty( $course->video_url ) ) {
			$video_url = (string) $course->video_url;
		} elseif ( ! empty( $course->vimeo_id ) ) {
			$video_url = 'https://vimeo.com/' . preg_replace( '/\D/', '', (string) $course->vimeo_id );
		}

		if ( '' === $video_url ) {
			return '';
		}

		if ( preg_match( '/^\d+$/', trim( $video_url ) ) ) {
			$video_url = 'https://vimeo.com/' . trim( $video_url );
		}

		$youtube_id = $this->extract_youtube_id( $video_url );
		if ( $youtube_id ) {
			return sprintf(
				'<div class="course-hero__video-wrap"><iframe class="course-hero__iframe" src="https://www.youtube.com/embed/%1$s" title="%2$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>',
				esc_attr( $youtube_id ),
				esc_attr( $course->title )
			);
		}

		if ( false !== strpos( $video_url, 'vimeo.com' ) ) {
			$vimeo_id = '';
			if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $video_url, $matches ) ) {
				$vimeo_id = $matches[1];
			}

			if ( $vimeo_id ) {
				return sprintf(
					'<div class="course-hero__video-wrap"><iframe class="course-hero__iframe" src="https://player.vimeo.com/video/%1$s" title="%2$s" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>',
					esc_attr( $vimeo_id ),
					esc_attr( $course->title )
				);
			}
		}

		return sprintf(
			'<div class="course-hero__video-wrap"><video class="course-hero__html5-video" controls playsinline src="%1$s"></video></div>',
			esc_url( $video_url )
		);
	}

	/**
	 * Extract a YouTube video ID from a URL.
	 *
	 * @param string $url Video URL.
	 * @return string
	 */
	private function extract_youtube_id( $url ) {
		if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * AJAX handler for filtering and searching courses.
	 */
	public function ajax_filter_courses() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$search   = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$sort     = sanitize_text_field( wp_unslash( $_POST['sort'] ?? 'default' ) );
		$limit    = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : -1;

		$courses = $this->get_courses(
			array(
				'category' => $category,
				'search'   => $search,
				'sort'     => $sort,
				'limit'    => $limit,
				'status'   => 'published',
			)
		);

		ob_start();

		if ( empty( $courses ) ) {
			echo '<div class="cta-empty-state cta-empty-state--inline">';
			echo '<p>' . esc_html__( 'No courses found matching your search.', 'cta-lms' ) . '</p>';
			echo '</div>';
		} else {
			foreach ( $courses as $course ) {
				include CTA_PLUGIN_DIR . 'templates/partials/course-card.php';
			}
		}

		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'  => $html,
				'count' => count( $courses ),
			)
		);
	}

	/**
	 * Fetch courses from the database with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_courses( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		$defaults = array(
			'limit'    => -1,
			'category' => '',
			'search'   => '',
			'sort'     => 'default',
			'status'   => 'published',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'status = %s' );
		$values = array( $args['status'] );

		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'category = %s';
			$values[] = $args['category'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'title LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$order_sql = 'ORDER BY created_at DESC';

		if ( 'price_low' === $args['sort'] ) {
			$order_sql = 'ORDER BY price ASC';
		} elseif ( 'price_high' === $args['sort'] ) {
			$order_sql = 'ORDER BY price DESC';
		} elseif ( 'ce_hours' === $args['sort'] ) {
			$order_sql = 'ORDER BY ce_hours DESC';
		}

		$limit_sql = '';
		if ( $args['limit'] > 0 ) {
			$limit_sql = 'LIMIT ' . absint( $args['limit'] );
		}

		$sql = "SELECT * FROM {$table} {$where_sql} {$order_sql} {$limit_sql}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders filled below.
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );
	}

	/**
	 * Get unique published course categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefixed.
		return $wpdb->get_col(
			"SELECT DISTINCT category FROM {$table}
			WHERE status = 'published'
			AND category != ''
			AND category IS NOT NULL
			ORDER BY category ASC"
		);
	}
}
}