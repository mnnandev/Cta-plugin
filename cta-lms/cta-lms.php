<?php
/**
 * CTA LMS bootstrap (loaded by Cta-plugin.php).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CTA_PLUGIN_FILE' ) ) {
	define( 'CTA_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'CTA_VERSION' ) ) {
	define( 'CTA_VERSION', '1.0.20' );
}

if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', plugin_dir_path( CTA_PLUGIN_FILE ) );
}

if ( ! defined( 'CTA_PLUGIN_URL' ) ) {
	define( 'CTA_PLUGIN_URL', plugin_dir_url( CTA_PLUGIN_FILE ) );
}

if ( ! defined( 'CTA_PLUGIN_BASENAME' ) ) {
	define( 'CTA_PLUGIN_BASENAME', plugin_basename( CTA_PLUGIN_FILE ) );
}

/**
 * Load a plugin file if it exists.
 *
 * @param string $relative_path Path relative to plugin root.
 */
function cta_lms_require( $relative_path ) {
	$path = CTA_PLUGIN_DIR . ltrim( $relative_path, '/' );

	if ( ! file_exists( $path ) ) {
		if ( is_admin() ) {
			add_action(
				'admin_notices',
				static function () use ( $relative_path ) {
					echo '<div class="notice notice-error"><p>';
					printf(
						/* translators: %s: missing file path */
						esc_html__( 'CTA LMS is missing a required file: %s', 'cta-lms' ),
						esc_html( $relative_path )
					);
					echo '</p></div>';
				}
			);
		}
		return false;
	}

	require_once $path;
	return true;
}

// Load Composer autoloader (Stripe SDK) when present.
if ( file_exists( CTA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once CTA_PLUGIN_DIR . 'vendor/autoload.php';
}

$cta_required_files = array(
	'includes/class-cta-activator.php',
	'includes/class-cta-deactivator.php',
	'includes/class-cta-roles.php',
	'includes/class-cta-database.php',
	'includes/class-cta-emails.php',
	'includes/class-cta-loader.php',
	'includes/class-cta-stripe.php',
	'public/class-cta-shortcodes.php',
	'public/class-cta-auth.php',
	'public/class-cta-courses.php',
	'public/class-cta-memberships.php',
	'public/class-cta-supervision.php',
	'public/class-cta-student-dashboard.php',
	'public/class-cta-supervision-dashboard.php',
	'public/class-cta-certificates.php',
	'public/class-cta-quiz.php',
	'admin/class-cta-admin.php',
);

foreach ( $cta_required_files as $cta_file ) {
	if ( ! cta_lms_require( $cta_file ) ) {
		return;
	}
}

if ( ! function_exists( 'cta_get_stripe' ) ) {
	/**
	 * Get shared Stripe handler instance.
	 *
	 * @return CTA_Stripe
	 */
	function cta_get_stripe() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new CTA_Stripe();
		}

		return $instance;
	}
}

if ( ! function_exists( 'cta_lms_init' ) ) {
	/**
	 * Initialize plugin components.
	 */
	function cta_lms_init() {
		if ( ! class_exists( 'CTA_Loader' ) ) {
			return;
		}

		$loader = new CTA_Loader();
		$loader->run();

		new CTA_Shortcodes();
		new CTA_Auth();
		new CTA_Courses();
		cta_get_stripe();
		new CTA_Memberships();
		new CTA_Supervision();
		new CTA_Student_Dashboard();
		new CTA_Supervision_Dashboard();
		new CTA_Quiz();

		if ( is_admin() ) {
			new CTA_Admin();
		}
	}
}
add_action( 'plugins_loaded', 'cta_lms_init' );

if ( ! function_exists( 'cta_maybe_upgrade_db' ) ) {
	/**
	 * Run database upgrades when plugin version changes.
	 */
	function cta_maybe_upgrade_db() {
		$installed = get_option( 'cta_lms_version', '0' );

		if ( version_compare( $installed, CTA_VERSION, '>=' ) ) {
			return;
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::create_tables();
		}

		update_option( 'cta_lms_version', CTA_VERSION );
	}
}
add_action( 'plugins_loaded', 'cta_maybe_upgrade_db', 5 );

add_action( 'init', array( 'CTA_Emails', 'register_cron' ) );
add_action( 'cta_send_session_reminders', array( 'CTA_Emails', 'send_daily_reminders' ) );

if ( ! function_exists( 'cta_lms_get_icon' ) ) {
	/**
	 * Return an inline SVG icon for CTA templates.
	 *
	 * @param string $name Icon name: check, check-circle, lock, circle, eye, eye-off.
	 * @param int    $size Icon size in pixels.
	 * @param string $class Optional CSS class.
	 * @return string
	 */
	function cta_lms_get_icon( $name, $size = 16, $class = 'cta-icon' ) {
		$size       = max( 12, absint( $size ) );
		$class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

		switch ( $name ) {
			case 'check':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';
			case 'check-circle':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="8 12 11 15 16 9"></polyline></svg>';
			case 'lock':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
			case 'circle':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"' . $class_attr . ' aria-hidden="true"><circle cx="12" cy="12" r="9"></circle></svg>';
			case 'arrow-right':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>';
			case 'eye':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
			case 'eye-off':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
			default:
				return '';
		}
	}
}

if ( ! function_exists( 'cta_lms_render_password_field' ) ) {
	/**
	 * Render a password input with show/hide toggle.
	 *
	 * @param array $args Field arguments: id, name, label, autocomplete, required.
	 * @return string
	 */
	function cta_lms_render_password_field( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'           => '',
				'name'         => '',
				'label'        => '',
				'autocomplete' => 'current-password',
				'required'     => true,
			)
		);

		if ( empty( $args['id'] ) || empty( $args['name'] ) || empty( $args['label'] ) ) {
			return '';
		}

		$required_attr = ! empty( $args['required'] ) ? ' required' : '';

		ob_start();
		?>
		<div class="form-group">
			<label class="form-label" for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
			<div class="form-password" data-password-field>
				<input
					type="password"
					id="<?php echo esc_attr( $args['id'] ); ?>"
					name="<?php echo esc_attr( $args['name'] ); ?>"
					class="form-input form-password__input"
					autocomplete="<?php echo esc_attr( $args['autocomplete'] ); ?>"
					<?php echo $required_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				>
				<button
					type="button"
					class="form-password__toggle"
					aria-label="<?php echo esc_attr__( 'Show password', 'cta-lms' ); ?>"
					aria-pressed="false"
				>
					<span class="form-password__icon--show"><?php echo cta_lms_get_icon( 'eye', 20, 'form-password__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="form-password__icon--hide"><?php echo cta_lms_get_icon( 'eye-off', 20, 'form-password__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
	/**
	 * Find a published page that contains a CTA shortcode.
	 *
	 * @param string $shortcode Shortcode tag without brackets.
	 * @return int Page ID or 0.
	 */
	function cta_lms_find_page_id_by_shortcode( $shortcode ) {
		static $cache = array();

		if ( isset( $cache[ $shortcode ] ) ) {
			return $cache[ $shortcode ];
		}

		$page_ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $page_ids as $page_id ) {
			$post = get_post( $page_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				$cache[ $shortcode ] = (int) $page_id;
				return $cache[ $shortcode ];
			}

			if ( metadata_exists( 'post', $page_id, '_elementor_data' ) ) {
				$elementor_data = get_post_meta( $page_id, '_elementor_data', true );

				if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, '[' . $shortcode ) ) {
					$cache[ $shortcode ] = (int) $page_id;
					return $cache[ $shortcode ];
				}
			}
		}

		$cache[ $shortcode ] = 0;
		return 0;
	}
}

if ( ! function_exists( 'cta_lms_get_single_course_url' ) ) {
	/**
	 * Build the single course detail URL for a course ID.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	function cta_lms_get_single_course_url( $course_id ) {
		$course_id = absint( $course_id );

		if ( ! $course_id ) {
			return '';
		}

		$page_id = absint( get_option( 'cta_single_course_page_id', 0 ) );

		if ( ! $page_id ) {
			$page_id = cta_lms_find_page_id_by_shortcode( 'cta_single_course' );
		}

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		if ( ! $permalink ) {
			return '';
		}

		return add_query_arg( 'course_id', $course_id, $permalink );
	}
}

if ( ! function_exists( 'cta_lms_admin_notices' ) ) {
	/**
	 * Warn when duplicate CTA LMS plugin folders are installed.
	 */
	function cta_lms_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$matches      = array();
		$active_file  = defined( 'CTA_PLUGIN_BASENAME' ) ? CTA_PLUGIN_BASENAME : '';
		$cta_plugins  = get_plugins();

		foreach ( $cta_plugins as $plugin_file => $plugin_data ) {
			$text_domain = $plugin_data['TextDomain'] ?? '';
			$name        = $plugin_data['Name'] ?? '';

			if ( 'cta-lms' === $text_domain || false !== stripos( $name, 'CTA LMS' ) ) {
				$matches[ $plugin_file ] = $plugin_data;
			}
		}

		if ( count( $matches ) <= 1 ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'CTA LMS: Multiple plugin copies detected.', 'cta-lms' );
		echo '</strong></p><p>';
		esc_html_e( 'This breaks course saving, modules, and quizzes. Keep only the Cta-plugin folder and delete the old copy.', 'cta-lms' );
		echo '</p><ul style="list-style:disc;padding-left:20px;">';

		foreach ( $matches as $plugin_file => $plugin_data ) {
			$is_active = ( $plugin_file === $active_file );
			printf(
				'<li><code>%1$s</code> — %2$s %3$s</li>',
				esc_html( $plugin_file ),
				esc_html( $plugin_data['Version'] ?? '?' ),
				$is_active ? esc_html__( '(active — keep this one)', 'cta-lms' ) : esc_html__( '(delete this copy)', 'cta-lms' )
			);
		}

		echo '</ul><p>';
		esc_html_e( 'Steps: Deactivate all CTA LMS plugins → delete the old folder via FTP/File Manager → WP Pusher pull → activate once.', 'cta-lms' );
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'cta_lms_admin_notices' );
