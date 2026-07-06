<?php
/**
 * WordPress admin panel for CTA LMS.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Admin
 */
if ( ! class_exists( 'CTA_Admin' ) ) {

class CTA_Admin {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_head', array( $this, 'print_admin_menu_icon_styles' ) );

		add_action( 'admin_post_cta_save_course', array( $this, 'save_course' ) );
		add_action( 'admin_post_cta_delete_course', array( $this, 'delete_course' ) );
		add_action( 'admin_post_cta_toggle_course', array( $this, 'toggle_course_status' ) );
		add_action( 'admin_post_cta_save_settings', array( $this, 'save_settings' ) );

		add_action( 'wp_ajax_cta_admin_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_cta_save_module', array( $this, 'ajax_save_module' ) );
		add_action( 'wp_ajax_cta_delete_module', array( $this, 'ajax_delete_module' ) );
		add_action( 'wp_ajax_cta_reorder_modules', array( $this, 'ajax_reorder_modules' ) );
		add_action( 'wp_ajax_cta_review_document', array( $this, 'ajax_review_document' ) );
		add_action( 'wp_ajax_cta_admin_add_session', array( $this, 'ajax_add_session' ) );
		add_action( 'wp_ajax_cta_admin_cancel_session', array( $this, 'ajax_cancel_session' ) );
		add_action( 'wp_ajax_cta_test_stripe_connection', array( $this, 'ajax_test_stripe_connection' ) );
		add_action( 'wp_ajax_cta_preview_certificate', array( $this, 'ajax_preview_certificate' ) );
		add_action( 'wp_ajax_cta_save_quiz', array( $this, 'ajax_save_quiz' ) );
		add_action( 'wp_ajax_cta_load_quiz', array( $this, 'ajax_load_quiz' ) );
	}

	/**
	 * Register admin menus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'CTA LMS', 'cta-lms' ),
			__( 'CTA LMS', 'cta-lms' ),
			'manage_options',
			'cta-lms',
			array( $this, 'render_dashboard' ),
			CTA_PLUGIN_URL . 'assets/img/admin-icon.svg',
			30
		);

		add_submenu_page(
			'cta-lms',
			__( 'Dashboard', 'cta-lms' ),
			__( 'Dashboard', 'cta-lms' ),
			'manage_options',
			'cta-lms',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'cta-lms',
			__( 'Courses', 'cta-lms' ),
			__( 'Courses', 'cta-lms' ),
			'manage_options',
			'cta-lms-courses',
			array( $this, 'render_courses' )
		);

		add_submenu_page(
			null,
			__( 'Edit Course', 'cta-lms' ),
			__( 'Edit Course', 'cta-lms' ),
			'manage_options',
			'cta-lms-course-edit',
			array( $this, 'render_course_edit' )
		);

		add_submenu_page(
			'cta-lms',
			__( 'Users', 'cta-lms' ),
			__( 'Users', 'cta-lms' ),
			'manage_options',
			'cta-lms-users',
			array( $this, 'render_users' )
		);

		add_submenu_page(
			'cta-lms',
			__( 'Bookings', 'cta-lms' ),
			__( 'Bookings', 'cta-lms' ),
			'manage_options',
			'cta-lms-bookings',
			array( $this, 'render_bookings' )
		);

		add_submenu_page(
			'cta-lms',
			__( 'Settings', 'cta-lms' ),
			__( 'Settings', 'cta-lms' ),
			'manage_options',
			'cta-lms-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'cta-lms',
			__( 'Shortcodes', 'cta-lms' ),
			__( 'Shortcodes', 'cta-lms' ),
			'manage_options',
			'cta-lms-shortcodes',
			array( $this, 'render_shortcodes' )
		);
	}

	/**
	 * Ensure the CTA LMS admin menu icon renders at the correct size.
	 */
	public function print_admin_menu_icon_styles() {
		echo '<style>#adminmenu .toplevel_page_cta-lms .wp-menu-image img{width:20px;height:20px;padding:6px 0 0;opacity:.6}#adminmenu .toplevel_page_cta-lms.wp-has-current-submenu .wp-menu-image img,#adminmenu .toplevel_page_cta-lms.current .wp-menu-image img{opacity:1}</style>';
	}

	/**
	 * Enqueue admin assets on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'cta-lms' ) ) {
			return;
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		wp_enqueue_style(
			'cta-admin',
			CTA_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			CTA_VERSION
		);

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'cta-admin',
			CTA_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			CTA_VERSION,
			true
		);

		wp_localize_script(
			'cta-admin',
			'ctaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cta_admin_nonce' ),
				'i18n'    => array(
					'confirmDelete'  => __( 'Are you sure you want to delete this item?', 'cta-lms' ),
					'confirmCancel'  => __( 'Cancel this session and notify booked users?', 'cta-lms' ),
					'copied'         => __( 'Copied!', 'cta-lms' ),
					'stripeTesting'  => __( 'Testing connection...', 'cta-lms' ),
					'stripeSuccess'  => __( 'Stripe connection successful.', 'cta-lms' ),
					'stripeFailed'   => __( 'Stripe connection failed.', 'cta-lms' ),
				),
			)
		);

		if ( 'cta-lms_page_cta-lms-course-edit' === $hook ) {
			wp_enqueue_editor();
			wp_enqueue_media();
		}
	}

	/**
	 * Render dashboard view.
	 */
	public function render_dashboard() {
		$this->load_view(
			'dashboard.php',
			array(
				'stats'               => self::get_dashboard_stats(),
				'recent_enrollments'  => self::get_recent_enrollments( 10 ),
				'recent_bookings'     => self::get_recent_bookings( 5 ),
			)
		);
	}

	/**
	 * Render courses list.
	 */
	public function render_courses() {
		global $wpdb;

		$status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'all' ) );
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$table  = $wpdb->prefix . 'cta_courses';
		$where  = array( '1=1' );
		$params = array();

		if ( in_array( $status, array( 'published', 'draft' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		if ( $search ) {
			$where[]  = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC';

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$courses = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$courses = $wpdb->get_results( $sql );
		}

		$enrollment_counts = array();
		$count_rows        = $wpdb->get_results(
			"SELECT course_id, COUNT(*) AS total FROM {$wpdb->prefix}cta_enrollments GROUP BY course_id"
		);

		foreach ( $count_rows as $row ) {
			$enrollment_counts[ (int) $row->course_id ] = (int) $row->total;
		}

		$this->load_view(
			'courses.php',
			array(
				'courses'           => $courses ? $courses : array(),
				'enrollment_counts' => $enrollment_counts,
				'status_filter'     => $status,
				'search'            => $search,
			)
		);
	}

	/**
	 * Render course add/edit form.
	 */
	public function render_course_edit() {
		$course_id = absint( wp_unslash( $_GET['course_id'] ?? 0 ) );
		$course    = $course_id ? CTA_Database::get_course( $course_id ) : null;
		$modules   = $course_id ? CTA_Database::get_course_modules( $course_id ) : array();
		$quiz      = $course_id ? $this->get_course_quiz( $course_id ) : null;
		$quiz_questions = ( $quiz ) ? CTA_Database::get_quiz_questions( (int) $quiz->id ) : array();
		$objectives = array();

		if ( $course && ! empty( $course->learning_objectives ) ) {
			$decoded = json_decode( (string) $course->learning_objectives, true );
			if ( is_array( $decoded ) ) {
				$objectives = $decoded;
			}
		}

		if ( empty( $objectives ) ) {
			$objectives = array( '' );
		}

		$this->load_view(
			'courses-edit.php',
			array(
				'course'     => $course,
				'course_id'  => $course_id,
				'modules'    => $modules,
				'quiz'       => $quiz,
				'quiz_questions' => $quiz_questions,
				'objectives' => $objectives,
				'categories' => self::get_course_categories(),
			)
		);
	}

	/**
	 * Render users list.
	 */
	public function render_users() {
		$role_filter = sanitize_text_field( wp_unslash( $_GET['role'] ?? 'all' ) );
		$search      = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

		$args = array(
			'number'  => 100,
			'orderby' => 'registered',
			'order'   => 'DESC',
		);

		if ( 'licensed' === $role_filter ) {
			$args['role'] = 'cta_licensed_professional';
		} elseif ( 'associate' === $role_filter ) {
			$args['role'] = 'cta_associate';
		} elseif ( 'administrator' === $role_filter ) {
			$args['role'] = 'administrator';
		} else {
			$args['role__in'] = array( 'cta_licensed_professional', 'cta_associate', 'administrator' );
		}

		if ( $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$user_query = new WP_User_Query( $args );
		$users      = $user_query->get_results();

		$this->load_view(
			'users.php',
			array(
				'users'       => $users ? $users : array(),
				'role_filter' => $role_filter,
				'search'      => $search,
			)
		);
	}

	/**
	 * Render bookings management.
	 */
	public function render_bookings() {
		global $wpdb;

		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'upcoming' ) );

		if ( 'history' === $tab ) {
			$sessions = $wpdb->get_results(
				"SELECT b.*, u.display_name
				FROM {$wpdb->prefix}cta_bookings b
				LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id
				WHERE b.user_id > 0
				AND (b.session_date < CURDATE() OR b.status IN ('cancelled','completed'))
				ORDER BY b.session_date DESC, b.session_time DESC
				LIMIT 100"
			);
		} else {
			$sessions = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}cta_bookings
				WHERE user_id = 0
				AND status = 'open'
				AND session_date >= CURDATE()
				ORDER BY session_date ASC, session_time ASC"
			);
		}

		$this->load_view(
			'bookings.php',
			array(
				'sessions' => $sessions ? $sessions : array(),
				'tab'      => $tab,
			)
		);
	}

	/**
	 * Render settings form.
	 */
	public function render_settings() {
		$this->load_view(
			'settings.php',
			array(
				'pages'        => get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) ),
				'webhook_url'  => rest_url( 'cta-lms/v1/stripe-webhook' ),
				'page_options' => self::get_page_option_map(),
			)
		);
	}

	/**
	 * Render shortcodes reference.
	 */
	public function render_shortcodes() {
		$this->load_view( 'shortcodes.php', array( 'shortcodes' => self::get_shortcode_reference() ) );
	}

	/**
	 * Save course from admin form.
	 */
	public function save_course() {
		$this->verify_admin_request( 'cta_save_course' );

		global $wpdb;

		if ( ! CTA_Database::ensure_tables() ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'cta-lms-course-edit',
						'course_id'  => absint( wp_unslash( $_POST['course_id'] ?? 0 ) ),
						'cta_notice' => 'course_save_failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$course_id  = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$slug       = sanitize_title( wp_unslash( $_POST['slug'] ?? $title ) );
		$category   = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$ce_hours   = (float) wp_unslash( $_POST['ce_hours'] ?? 0 );
		$price      = (float) wp_unslash( $_POST['price'] ?? 0 );
		$description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
		$thumbnail  = esc_url_raw( wp_unslash( $_POST['thumbnail_url'] ?? '' ) );
		$video_type = sanitize_text_field( wp_unslash( $_POST['course_video_type'] ?? 'vimeo' ) );
		$video_raw  = sanitize_text_field( wp_unslash( $_POST['course_video_value'] ?? '' ) );
		$video_url  = esc_url_raw( wp_unslash( $_POST['course_video_url'] ?? '' ) );
		$vimeo_id   = '';
		$allowed_video_types = array( 'vimeo', 'youtube', 'wordpress', 'url' );

		if ( ! in_array( $video_type, $allowed_video_types, true ) ) {
			$video_type = 'vimeo';
		}

		if ( 'vimeo' === $video_type ) {
			$vimeo_id = preg_replace( '/\D/', '', $video_raw );
			$video_url = $vimeo_id ? 'https://vimeo.com/' . $vimeo_id : '';
		} elseif ( 'youtube' === $video_type ) {
			$video_url = esc_url_raw( $video_raw );
			$vimeo_id  = '';
		} elseif ( 'wordpress' === $video_type || 'url' === $video_type ) {
			$video_url = $video_url ? $video_url : esc_url_raw( $video_raw );
			$vimeo_id  = '';
		}
		$status     = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) );
		$status     = in_array( $status, array( 'published', 'draft' ), true ) ? $status : 'draft';

		$objectives_in = isset( $_POST['learning_objectives'] ) ? wp_unslash( $_POST['learning_objectives'] ) : array();
		$objectives    = array();

		if ( is_array( $objectives_in ) ) {
			foreach ( $objectives_in as $objective ) {
				$objective = sanitize_text_field( $objective );
				if ( '' !== $objective ) {
					$objectives[] = $objective;
				}
			}
		}

		if ( '' === $title ) {
			wp_die( esc_html__( 'Course title is required.', 'cta-lms' ) );
		}

		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}

		$data = array(
			'title'               => $title,
			'slug'                => $slug,
			'category'            => $category,
			'ce_hours'            => $ce_hours,
			'price'               => $price,
			'description'         => $description,
			'learning_objectives' => wp_json_encode( $objectives ),
			'thumbnail_url'       => $thumbnail,
			'vimeo_id'            => $vimeo_id,
			'video_url'           => $video_url,
			'status'              => $status,
		);

		$table = $wpdb->prefix . 'cta_courses';
		$saved = false;

		if ( $course_id ) {
			$saved = false !== $wpdb->update(
				$table,
				$data,
				array( 'id' => $course_id ),
				array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$saved = false !== $wpdb->insert(
				$table,
				$data,
				array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
			$course_id = (int) $wpdb->insert_id;
		}

		if ( ! $saved || ! $course_id ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'cta-lms-course-edit',
						'course_id'  => absint( wp_unslash( $_POST['course_id'] ?? 0 ) ),
						'cta_notice' => 'course_save_failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$module_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}cta_course_modules WHERE course_id = %d",
				$course_id
			)
		);

		$wpdb->update(
			$table,
			array( 'modules_count' => $module_count ),
			array( 'id' => $course_id ),
			array( '%d' ),
			array( '%d' )
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'cta-lms-course-edit',
					'course_id'  => $course_id,
					'cta_notice' => 'course_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete a course.
	 */
	public function delete_course() {
		$this->verify_admin_request( 'cta_delete_course' );

		$course_id = absint( wp_unslash( $_GET['course_id'] ?? 0 ) );

		if ( ! $course_id ) {
			wp_die( esc_html__( 'Invalid course.', 'cta-lms' ) );
		}

		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'cta_course_modules', array( 'course_id' => $course_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'cta_courses', array( 'id' => $course_id ), array( '%d' ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'cta-lms-courses',
					'cta_notice' => 'course_deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Toggle course published/draft status.
	 */
	public function toggle_course_status() {
		$this->verify_admin_request( 'cta_toggle_course' );

		$course_id = absint( wp_unslash( $_GET['course_id'] ?? 0 ) );
		$course    = CTA_Database::get_course( $course_id );

		if ( ! $course ) {
			wp_die( esc_html__( 'Course not found.', 'cta-lms' ) );
		}

		global $wpdb;

		$new_status = 'published' === $course->status ? 'draft' : 'published';

		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'status' => $new_status ),
			array( 'id' => $course_id ),
			array( '%s' ),
			array( '%d' )
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'cta-lms-courses',
					'cta_notice' => 'status_updated',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save plugin settings.
	 */
	public function save_settings() {
		$this->verify_admin_request( 'cta_save_settings' );

		update_option( 'cta_stripe_mode', sanitize_text_field( wp_unslash( $_POST['cta_stripe_mode'] ?? 'test' ) ) );
		update_option( 'cta_stripe_secret_key', sanitize_text_field( wp_unslash( $_POST['cta_stripe_secret_key'] ?? '' ) ) );
		update_option( 'cta_stripe_publishable_key', sanitize_text_field( wp_unslash( $_POST['cta_stripe_publishable_key'] ?? '' ) ) );
		update_option( 'cta_stripe_webhook_secret', sanitize_text_field( wp_unslash( $_POST['cta_stripe_webhook_secret'] ?? '' ) ) );
		update_option( 'cta_payments_bypass', isset( $_POST['cta_payments_bypass'] ) ? 'yes' : 'no' );

		foreach ( self::get_page_option_map() as $option_key => $label ) {
			update_option( $option_key, absint( wp_unslash( $_POST[ $option_key ] ?? 0 ) ) );
		}

		update_option( 'cta_camft_provider_number', sanitize_text_field( wp_unslash( $_POST['cta_camft_provider_number'] ?? '' ) ) );
		update_option( 'cta_admin_name', sanitize_text_field( wp_unslash( $_POST['cta_admin_name'] ?? '' ) ) );
		update_option( 'cta_support_email', sanitize_email( wp_unslash( $_POST['cta_support_email'] ?? '' ) ) );
		update_option( 'cta_certificate_header_text', sanitize_text_field( wp_unslash( $_POST['cta_certificate_header_text'] ?? '' ) ) );
		update_option( 'cta_certificate_footer_text', sanitize_text_field( wp_unslash( $_POST['cta_certificate_footer_text'] ?? '' ) ) );
		update_option( 'cta_certificate_signature_name', sanitize_text_field( wp_unslash( $_POST['cta_certificate_signature_name'] ?? '' ) ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'cta-lms-settings',
					'cta_notice' => 'settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX: user stats for admin users table.
	 */
	public function ajax_get_stats() {
		$this->verify_admin_ajax();

		$user_id = absint( wp_unslash( $_POST['user_id'] ?? 0 ) );

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'cta-lms' ) ) );
		}

		global $wpdb;

		$courses_enrolled = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}cta_enrollments WHERE user_id = %d",
				$user_id
			)
		);

		$courses_completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}cta_enrollments WHERE user_id = %d AND status = 'completed'",
				$user_id
			)
		);

		$certificates_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}cta_certificates WHERE user_id = %d",
				$user_id
			)
		);

		$total_paid = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}cta_payments WHERE user_id = %d AND status = 'completed'",
				$user_id
			)
		);

		wp_send_json_success(
			array(
				'courses_enrolled'   => $courses_enrolled,
				'courses_completed'  => $courses_completed,
				'certificates_count' => $certificates_count,
				'supervision_status' => (string) get_user_meta( $user_id, 'cta_supervision_status', true ),
				'total_paid'         => number_format( $total_paid, 2 ),
			)
		);
	}

	/**
	 * AJAX: save course module.
	 */
	public function ajax_save_module() {
		$this->verify_admin_ajax();

		global $wpdb;

		$module_id   = absint( wp_unslash( $_POST['module_id'] ?? 0 ) );
		$course_id   = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$video_url   = esc_url_raw( wp_unslash( $_POST['video_url'] ?? '' ) );
		$duration    = absint( wp_unslash( $_POST['duration_mins'] ?? 0 ) );
		$is_locked   = ! empty( $_POST['is_locked'] ) ? 1 : 0;

		if ( ! $course_id || '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Course and module title are required.', 'cta-lms' ) ) );
		}

		$table = $wpdb->prefix . 'cta_course_modules';
		$data  = array(
			'course_id'     => $course_id,
			'title'         => $title,
			'description'   => $description,
			'video_url'     => $video_url,
			'duration_mins' => $duration,
			'is_locked'     => $is_locked,
		);

		if ( $module_id ) {
			$wpdb->update(
				$table,
				$data,
				array( 'id' => $module_id ),
				array( '%d', '%s', '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);
			$module = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $module_id ) );
		} else {
			$max_order = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(order_index) FROM {$table} WHERE course_id = %d",
					$course_id
				)
			);
			$data['order_index'] = $max_order + 1;

			$wpdb->insert(
				$table,
				$data,
				array( '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
			);
			$module = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $wpdb->insert_id ) );
		}

		$module_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE course_id = %d",
				$course_id
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'modules_count' => $module_count ),
			array( 'id' => $course_id ),
			array( '%d' ),
			array( '%d' )
		);

		wp_send_json_success(
			array(
				'module_id' => (int) $module->id,
				'html'      => $this->render_module_row_html( $module ),
			)
		);
	}

	/**
	 * AJAX: delete course module.
	 */
	public function ajax_delete_module() {
		$this->verify_admin_ajax();

		$module_id = absint( wp_unslash( $_POST['module_id'] ?? 0 ) );
		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );

		if ( ! $module_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid module.', 'cta-lms' ) ) );
		}

		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'cta_course_modules', array( 'id' => $module_id ), array( '%d' ) );

		if ( $course_id ) {
			$module_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}cta_course_modules WHERE course_id = %d",
					$course_id
				)
			);

			$wpdb->update(
				$wpdb->prefix . 'cta_courses',
				array( 'modules_count' => $module_count ),
				array( 'id' => $course_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: reorder modules.
	 */
	public function ajax_reorder_modules() {
		$this->verify_admin_ajax();

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$order     = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array();

		if ( ! $course_id || ! is_array( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'cta-lms' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cta_course_modules';

		foreach ( $order as $index => $module_id ) {
			$wpdb->update(
				$table,
				array( 'order_index' => (int) $index ),
				array(
					'id'        => absint( $module_id ),
					'course_id' => $course_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: review uploaded document.
	 */
	public function ajax_review_document() {
		$this->verify_admin_ajax();

		$document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
		$status      = sanitize_text_field( wp_unslash( $_POST['review_status'] ?? '' ) );

		if ( ! in_array( $status, array( 'approved', 'rejected', 'pending' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid review status.', 'cta-lms' ) ) );
		}

		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->prefix . 'cta_documents',
			array(
				'review_status' => $status,
				'reviewed_at'   => current_time( 'mysql' ),
				'reviewed_by'   => get_current_user_id(),
			),
			array( 'id' => $document_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Unable to update document.', 'cta-lms' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: add supervision session slot.
	 */
	public function ajax_add_session() {
		$this->verify_admin_ajax();

		$session_date = sanitize_text_field( wp_unslash( $_POST['session_date'] ?? '' ) );
		$session_time = sanitize_text_field( wp_unslash( $_POST['session_time'] ?? '' ) );
		$session_type = sanitize_text_field( wp_unslash( $_POST['session_type'] ?? 'group' ) );
		$seats_total  = absint( wp_unslash( $_POST['seats_total'] ?? 8 ) );
		$duration     = absint( wp_unslash( $_POST['duration_mins'] ?? 120 ) );

		if ( ! $session_date || ! $session_time ) {
			wp_send_json_error( array( 'message' => __( 'Date and time are required.', 'cta-lms' ) ) );
		}

		if ( strtotime( $session_date . ' ' . $session_time ) <= time() ) {
			wp_send_json_error( array( 'message' => __( 'Session must be in the future.', 'cta-lms' ) ) );
		}

		if ( 'group' === $session_type ) {
			$seats_total = min( 8, max( 1, $seats_total ) );
			$duration    = 120;
		} else {
			$session_type = 'individual';
			$seats_total  = 1;
			$duration     = 60;
		}

		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_bookings',
			array(
				'user_id'       => 0,
				'session_type'  => $session_type,
				'session_date'  => $session_date,
				'session_time'  => $session_time,
				'duration_mins' => $duration,
				'seats_total'   => $seats_total,
				'seats_booked'  => 0,
				'status'        => 'open',
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to create session.', 'cta-lms' ) ) );
		}

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_bookings WHERE id = %d",
				(int) $wpdb->insert_id
			)
		);

		wp_send_json_success(
			array(
				'html' => $this->render_session_row_html( $session ),
			)
		);
	}

	/**
	 * AJAX: cancel open session and notify booked users.
	 */
	public function ajax_cancel_session() {
		$this->verify_admin_ajax();

		$session_id = absint( wp_unslash( $_POST['session_id'] ?? 0 ) );

		global $wpdb;
		$table = $wpdb->prefix . 'cta_bookings';

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND user_id = 0",
				$session_id
			)
		);

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'cta-lms' ) ) );
		}

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE session_date = %s
				AND session_time = %s
				AND session_type = %s
				AND user_id > 0
				AND status = 'confirmed'",
				$session->session_date,
				$session->session_time,
				$session->session_type
			)
		);

		foreach ( $bookings as $booking ) {
			$user = get_userdata( (int) $booking->user_id );
			if ( $user && is_email( $user->user_email ) ) {
				wp_mail(
					$user->user_email,
					__( 'Supervision Session Cancelled', 'cta-lms' ),
					sprintf(
						/* translators: 1: date, 2: time */
						__( "Hi %1\$s,\n\nYour supervision session on %2\$s at %3\$s has been cancelled.\n\nPlease book another session from your dashboard.\n\nCTA Team", 'cta-lms' ),
						$user->display_name,
						$session->session_date,
						$session->session_time
					)
				);
			}

			$wpdb->update(
				$table,
				array( 'status' => 'cancelled' ),
				array( 'id' => (int) $booking->id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		$wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $session_id ),
			array( '%s' ),
			array( '%d' )
		);

		wp_send_json_success();
	}

	/**
	 * AJAX: test Stripe API connection.
	 */
	public function ajax_test_stripe_connection() {
		$this->verify_admin_ajax();

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			wp_send_json_error( array( 'message' => __( 'Stripe SDK not installed. Run composer install.', 'cta-lms' ) ) );
		}

		$secret = sanitize_text_field( wp_unslash( $_POST['secret_key'] ?? get_option( 'cta_stripe_secret_key', '' ) ) );

		if ( '' === $secret ) {
			wp_send_json_error( array( 'message' => __( 'Secret key is required.', 'cta-lms' ) ) );
		}

		try {
			\Stripe\Stripe::setApiKey( $secret );
			$account = \Stripe\Account::retrieve();

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: Stripe account ID */
						__( 'Connected to Stripe account %s', 'cta-lms' ),
						isset( $account->id ) ? $account->id : ''
					),
					'account' => array(
						'id'      => $account->id ?? '',
						'country' => $account->country ?? '',
						'email'   => $account->email ?? '',
					),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: preview certificate with sample data.
	 */
	public function ajax_preview_certificate() {
		$this->verify_admin_ajax();

		$student_name       = 'Sample Student, LMFT';
		$course_title       = 'Sample CE Course';
		$ce_hours           = '2.0';
		$completion_date    = wp_date( 'F j, Y' );
		$license_number     = 'LMFT12345';
		$provider_number    = (string) get_option( 'cta_camft_provider_number', get_option( 'cta_cepa_provider_number', '' ) );
		$certificate_number = 'CTA-' . gmdate( 'Y' ) . '-000000';
		$logo_url           = CTA_PLUGIN_URL . 'assets/img/placeholder/logo.png';

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/certificate.php';
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX: load quiz questions for the visual builder.
	 */
	public function ajax_load_quiz() {
		$this->verify_admin_ajax();

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );

		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'cta-lms' ) ) );
		}

		$quiz = $this->get_course_quiz( $course_id );

		if ( ! $quiz ) {
			wp_send_json_success(
				array(
					'quiz'      => null,
					'questions' => array(),
				)
			);
		}

		$questions = CTA_Database::get_quiz_questions( (int) $quiz->id );
		$payload   = array();

		foreach ( $questions as $question ) {
			$payload[] = array(
				'question_text'  => $question->question_text,
				'option_a'       => $question->option_a,
				'option_b'       => $question->option_b,
				'option_c'       => $question->option_c,
				'option_d'       => $question->option_d,
				'correct_option' => $question->correct_option,
				'explanation'    => $question->explanation,
				'order_index'    => (int) $question->order_index,
			);
		}

		wp_send_json_success(
			array(
				'quiz'      => array(
					'id'    => (int) $quiz->id,
					'title' => $quiz->title,
				),
				'questions' => $payload,
			)
		);
	}

	/**
	 * AJAX: create/update quiz and import questions JSON.
	 */
	public function ajax_save_quiz() {
		$this->verify_admin_ajax();

		$course_id   = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$quiz_title  = sanitize_text_field( wp_unslash( $_POST['quiz_title'] ?? '' ) );
		$questions_json = wp_unslash( $_POST['questions_json'] ?? '' );

		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Course is required.', 'cta-lms' ) ) );
		}

		global $wpdb;

		$quiz = $this->get_course_quiz( $course_id );
		$course = CTA_Database::get_course( $course_id );
		$title = $quiz_title ? $quiz_title : ( $course ? $course->title . ' Quiz' : 'Course Quiz' );

		if ( $quiz ) {
			$quiz_id = (int) $quiz->id;
			$wpdb->update(
				$wpdb->prefix . 'cta_quizzes',
				array(
					'title'  => $title,
					'status' => 'active',
				),
				array( 'id' => $quiz_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'cta_quizzes',
				array(
					'course_id' => $course_id,
					'title'       => $title,
					'status'      => 'active',
				),
				array( '%d', '%s', '%s' )
			);
			$quiz_id = (int) $wpdb->insert_id;
		}

		if ( $questions_json ) {
			$questions = json_decode( $questions_json, true );

			if ( ! is_array( $questions ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid quiz questions format.', 'cta-lms' ),
					)
				);
			}

			if ( is_array( $questions ) ) {
				$wpdb->delete( $wpdb->prefix . 'cta_quiz_questions', array( 'quiz_id' => $quiz_id ), array( '%d' ) );

				foreach ( $questions as $index => $question ) {
					if ( empty( $question['question_text'] ) ) {
						continue;
					}

					$correct = sanitize_text_field( $question['correct_option'] ?? 'a' );
					$correct = in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ? $correct : 'a';

					$wpdb->insert(
						$wpdb->prefix . 'cta_quiz_questions',
						array(
							'quiz_id'        => $quiz_id,
							'question_text'  => sanitize_textarea_field( $question['question_text'] ),
							'option_a'       => sanitize_text_field( $question['option_a'] ?? '' ),
							'option_b'       => sanitize_text_field( $question['option_b'] ?? '' ),
							'option_c'       => sanitize_text_field( $question['option_c'] ?? '' ),
							'option_d'       => sanitize_text_field( $question['option_d'] ?? '' ),
							'correct_option' => $correct,
							'explanation'    => sanitize_textarea_field( $question['explanation'] ?? '' ),
							'order_index'    => isset( $question['order_index'] ) ? absint( $question['order_index'] ) : $index,
						),
						array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
					);
				}
			}
		}

		wp_send_json_success(
			array(
				'quiz_id'   => $quiz_id,
				'message'   => __( 'Quiz saved successfully.', 'cta-lms' ),
				'quiz'      => array(
					'id'    => $quiz_id,
					'title' => $title,
				),
				'questions' => CTA_Database::get_quiz_questions( $quiz_id ),
			)
		);
	}

	/**
	 * Render module row HTML for AJAX responses.
	 *
	 * @param object $module Module row.
	 * @return string
	 */
	public function render_module_row_html( $module ) {
		ob_start();
		?>
		<tr
			class="cta-module-row"
			data-module-id="<?php echo esc_attr( $module->id ); ?>"
			data-title="<?php echo esc_attr( $module->title ); ?>"
			data-description="<?php echo esc_attr( wp_strip_all_tags( (string) $module->description ) ); ?>"
			data-video-url="<?php echo esc_url( (string) $module->video_url ); ?>"
			data-duration="<?php echo esc_attr( (string) $module->duration_mins ); ?>"
			data-locked="<?php echo esc_attr( (string) $module->is_locked ); ?>"
		>
			<td class="cta-module-row__handle" aria-label="<?php esc_attr_e( 'Drag to reorder', 'cta-lms' ); ?>">⋮⋮</td>
			<td><?php echo esc_html( (string) $module->order_index ); ?></td>
			<td><?php echo esc_html( $module->title ); ?></td>
			<td><?php echo esc_html( (string) $module->duration_mins ); ?> <?php esc_html_e( 'mins', 'cta-lms' ); ?></td>
			<td class="cta-table-actions">
				<button type="button" class="button button-small cta-edit-module" data-module-id="<?php echo esc_attr( $module->id ); ?>"><?php esc_html_e( 'Edit', 'cta-lms' ); ?></button>
				<button type="button" class="button button-small button-link-delete cta-delete-module" data-module-id="<?php echo esc_attr( $module->id ); ?>"><?php esc_html_e( 'Delete', 'cta-lms' ); ?></button>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render upcoming session row HTML.
	 *
	 * @param object $session Session row.
	 * @return string
	 */
	public function render_session_row_html( $session ) {
		ob_start();
		?>
		<tr data-session-id="<?php echo esc_attr( $session->id ); ?>">
			<td><?php echo esc_html( $session->session_date ); ?></td>
			<td><?php echo esc_html( substr( (string) $session->session_time, 0, 5 ) ); ?></td>
			<td><?php echo esc_html( ucfirst( $session->session_type ) ); ?></td>
			<td><?php echo esc_html( (int) $session->seats_booked . ' / ' . (int) $session->seats_total ); ?></td>
			<td><span class="cta-status-badge cta-status-badge--open"><?php echo esc_html( ucfirst( $session->status ) ); ?></span></td>
			<td class="cta-table-actions">
				<button type="button" class="button button-small button-link-delete cta-cancel-session" data-session-id="<?php echo esc_attr( $session->id ); ?>"><?php esc_html_e( 'Cancel', 'cta-lms' ); ?></button>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Dashboard stats from database.
	 *
	 * @return array
	 */
	public static function get_dashboard_stats() {
		global $wpdb;

		return array(
			'total_courses'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cta_courses WHERE status = 'published'" ),
			'total_enrolled'      => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}cta_enrollments" ),
			'total_completions'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cta_enrollments WHERE status = 'completed'" ),
			'total_revenue'       => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}cta_payments WHERE status = 'completed'" ),
			'active_subscribers'  => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
					'cta_supervision_status',
					'active'
				)
			),
			'certificates_issued' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cta_certificates" ),
		);
	}

	/**
	 * Recent enrollments for dashboard.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_recent_enrollments( $limit = 10 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, u.display_name, c.title AS course_title, p.status AS payment_status
				FROM {$wpdb->prefix}cta_enrollments e
				LEFT JOIN {$wpdb->users} u ON u.ID = e.user_id
				LEFT JOIN {$wpdb->prefix}cta_courses c ON c.id = e.course_id
				LEFT JOIN {$wpdb->prefix}cta_payments p ON p.stripe_payment_id = e.payment_id
				ORDER BY e.enrolled_at DESC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Recent user bookings for dashboard.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_recent_bookings( $limit = 5 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, u.display_name
				FROM {$wpdb->prefix}cta_bookings b
				LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id
				WHERE b.user_id > 0
				ORDER BY b.created_at DESC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Course category options.
	 *
	 * @return array
	 */
	public static function get_course_categories() {
		return array(
			'Law & Ethics'        => __( 'Law & Ethics', 'cta-lms' ),
			'Clinical Skills'     => __( 'Clinical Skills', 'cta-lms' ),
			'Specialized Topics'  => __( 'Specialized Topics', 'cta-lms' ),
			'Supervision'         => __( 'Supervision', 'cta-lms' ),
		);
	}

	/**
	 * Page assignment option map.
	 *
	 * @return array
	 */
	public static function get_page_option_map() {
		return array(
			'cta_login_page_id'                => __( 'Login Page', 'cta-lms' ),
			'cta_courses_page_id'              => __( 'Courses Page', 'cta-lms' ),
			'cta_single_course_page_id'        => __( 'Single Course Page', 'cta-lms' ),
			'cta_supervision_page_id'          => __( 'Supervision Page', 'cta-lms' ),
			'cta_memberships_page_id'          => __( 'Memberships Page', 'cta-lms' ),
			'cta_student_dashboard_page_id'    => __( 'CE Dashboard', 'cta-lms' ),
			'cta_supervision_dashboard_page_id'=> __( 'Supervision Dashboard', 'cta-lms' ),
			'cta_course_player_page_id'        => __( 'Course Player Page', 'cta-lms' ),
			'cta_quiz_page_id'                 => __( 'Quiz Page', 'cta-lms' ),
		);
	}

	/**
	 * Shortcode reference data.
	 *
	 * @return array
	 */
	public static function get_shortcode_reference() {
		return array(
			array(
				'code'        => '[cta_header]',
				'description' => __( 'Site header with navigation', 'cta-lms' ),
				'usage'       => __( 'Add to any page top', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_footer]',
				'description' => __( 'Site footer', 'cta-lms' ),
				'usage'       => __( 'Add to any page bottom', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_auth_button]',
				'description' => __( 'Login / Dashboard button (changes when user is logged in)', 'cta-lms' ),
				'usage'       => __( 'Any page or Elementor. Optional: login_url, dashboard_url, login_text, dashboard_text, style="outline|primary", size="sm".', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_login_form]',
				'description' => __( 'Login and register forms', 'cta-lms' ),
				'usage'       => __( 'Login page', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_course_catalog]',
				'description' => __( 'Full CE courses grid', 'cta-lms' ),
				'usage'       => __( 'Courses page. Use limit="3" for featured only.', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_single_course]',
				'description' => __( 'Individual course detail page', 'cta-lms' ),
				'usage'       => __( 'Single course page. Requires ?course_id=X in URL.', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_supervision_booking]',
				'description' => __( 'Supervision services + booking', 'cta-lms' ),
				'usage'       => __( 'Supervision page', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_membership_pricing]',
				'description' => __( 'Bundles and pricing cards', 'cta-lms' ),
				'usage'       => __( 'Memberships page', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_student_dashboard]',
				'description' => __( 'CE student portal', 'cta-lms' ),
				'usage'       => __( 'CE Dashboard page', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_supervision_dashboard]',
				'description' => __( 'Supervision associate portal', 'cta-lms' ),
				'usage'       => __( 'Supervision Dashboard page', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_course_player]',
				'description' => __( 'CE course module player', 'cta-lms' ),
				'usage'       => __( 'Course Player page. Requires ?course_id=X in URL.', 'cta-lms' ),
			),
			array(
				'code'        => '[cta_quiz]',
				'description' => __( 'Course quiz + evaluation', 'cta-lms' ),
				'usage'       => __( 'Quiz page. Requires ?course_id=X. Linked from course player.', 'cta-lms' ),
			),
		);
	}

	/**
	 * Fetch quiz row for a course (admin — any status).
	 *
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	private function get_course_quiz( $course_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quizzes WHERE course_id = %d LIMIT 1",
				$course_id
			)
		);
	}

	/**
	 * Load an admin view template.
	 *
	 * @param string $file View filename.
	 * @param array  $vars Variables for template.
	 */
	private function load_view( $file, $vars = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cta-lms' ) );
		}

		$path = CTA_PLUGIN_DIR . 'admin/views/' . $file;

		if ( ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Admin view not found.', 'cta-lms' ) );
		}

		$admin = $this;
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		include $path;
	}

	/**
	 * Verify admin POST request.
	 *
	 * @param string $action Nonce action.
	 */
	private function verify_admin_request( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cta-lms' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Verify admin AJAX request.
	 */
	private function verify_admin_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cta-lms' ) ) );
		}

		check_ajax_referer( 'cta_admin_nonce', 'nonce' );
	}
}
}