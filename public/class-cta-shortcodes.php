<?php
/**
 * Register and render plugin shortcodes.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Shortcodes
 */
if ( ! class_exists( 'CTA_Shortcodes' ) ) {

class CTA_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public function __construct() {
		add_shortcode( 'cta_header', array( $this, 'render_header' ) );
		add_shortcode( 'cta_footer', array( $this, 'render_footer' ) );
		add_shortcode( 'cta_auth_button', array( $this, 'render_auth_button' ) );
	}

	/**
	 * Get a page permalink from a plugin option.
	 *
	 * @param string $option_name Option key storing the page ID.
	 * @return string
	 */
	private function get_page_url( $option_name ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return $permalink ? $permalink : '';
	}

	/**
	 * Check whether a page ID matches the current page.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private function is_current_page( $page_id ) {
		$page_id = absint( $page_id );

		if ( ! $page_id ) {
			return false;
		}

		return is_page( $page_id );
	}

	/**
	 * Render the site header shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_header( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_nav' => 'yes',
			),
			$atts,
			'cta_header'
		);

		$current_user = wp_get_current_user();
		$is_logged_in = is_user_logged_in();
		$show_nav     = 'yes' === $atts['show_nav'];

		$login_url     = $this->get_page_url( 'cta_login_page_id' );
		$dashboard_url = '';

		if ( $is_logged_in ) {
			$user_roles = (array) $current_user->roles;

			if ( in_array( 'cta_associate', $user_roles, true ) ) {
				$dashboard_url = $this->get_page_url( 'cta_supervision_dashboard_page_id' );
			} else {
				$dashboard_url = $this->get_page_url( 'cta_student_dashboard_page_id' );
			}
		}

		$nav_items = array(
			array(
				'label'     => 'CE Courses',
				'url'       => $this->get_page_url( 'cta_courses_page_id' ),
				'page_id'   => absint( get_option( 'cta_courses_page_id', 0 ) ),
				'is_active' => $this->is_current_page( get_option( 'cta_courses_page_id', 0 ) ),
			),
			array(
				'label'     => 'Supervision',
				'url'       => $this->get_page_url( 'cta_supervision_page_id' ),
				'page_id'   => absint( get_option( 'cta_supervision_page_id', 0 ) ),
				'is_active' => $this->is_current_page( get_option( 'cta_supervision_page_id', 0 ) ),
			),
			array(
				'label'     => 'Memberships',
				'url'       => $this->get_page_url( 'cta_memberships_page_id' ),
				'page_id'   => absint( get_option( 'cta_memberships_page_id', 0 ) ),
				'is_active' => $this->is_current_page( get_option( 'cta_memberships_page_id', 0 ) ),
			),
			array(
				'label'     => 'FAQ',
				'url'       => $this->get_page_url( 'cta_faq_page_id' ),
				'page_id'   => absint( get_option( 'cta_faq_page_id', 0 ) ),
				'is_active' => $this->is_current_page( get_option( 'cta_faq_page_id', 0 ) ),
			),
			array(
				'label'     => 'Policies',
				'url'       => $this->get_page_url( 'cta_policies_page_id' ),
				'page_id'   => absint( get_option( 'cta_policies_page_id', 0 ) ),
				'is_active' => $this->is_current_page( get_option( 'cta_policies_page_id', 0 ) ),
			),
		);

		$nav_links = array(
			'CE Courses'  => $nav_items[0]['url'],
			'Supervision' => $nav_items[1]['url'],
			'Memberships' => $nav_items[2]['url'],
			'FAQ'         => $nav_items[3]['url'],
			'Policies'    => $nav_items[4]['url'],
		);

		$home_url    = home_url( '/' );
		$enroll_url  = $nav_links['CE Courses'] ? $nav_links['CE Courses'] : $home_url;
		$logout_url  = wp_logout_url( home_url() );
		$logo_url    = CTA_PLUGIN_URL . 'assets/img/logo.png';
		$site_name   = get_bloginfo( 'name' );

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/header.php';
		return ob_get_clean();
	}

	/**
	 * Get dashboard URL for the current user based on role.
	 *
	 * @return string
	 */
	private function get_dashboard_url_for_user() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user = wp_get_current_user();

		if ( in_array( 'cta_associate', (array) $user->roles, true ) ) {
			return $this->get_page_url( 'cta_supervision_dashboard_page_id' );
		}

		return $this->get_page_url( 'cta_student_dashboard_page_id' );
	}

	/**
	 * Render login/dashboard toggle button shortcode.
	 *
	 * Usage:
	 * [cta_auth_button]
	 * [cta_auth_button login_url="https://yoursite.com/login/" login_text="Log In" dashboard_text="My Dashboard"]
	 * [cta_auth_button style="primary" size="sm"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_auth_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'login_url'      => '',
				'dashboard_url'  => '',
				'login_text'     => __( 'Login', 'cta-lms' ),
				'dashboard_text' => __( 'Dashboard', 'cta-lms' ),
				'style'          => 'outline',
				'size'           => '',
				'class'          => '',
			),
			$atts,
			'cta_auth_button'
		);

		$is_logged_in = is_user_logged_in();
		$button_url   = '';
		$button_text  = $atts['login_text'];

		if ( $is_logged_in ) {
			$button_text = $atts['dashboard_text'];
			$button_url  = ! empty( $atts['dashboard_url'] )
				? esc_url_raw( $atts['dashboard_url'] )
				: $this->get_dashboard_url_for_user();

			if ( ! $button_url ) {
				$button_url = home_url( '/' );
			}
		} else {
			if ( ! empty( $atts['login_url'] ) ) {
				$button_url = esc_url_raw( $atts['login_url'] );
			} else {
				$button_url = $this->get_page_url( 'cta_login_page_id' );
			}

			if ( ! $button_url ) {
				$button_url = wp_login_url( get_permalink() );
			}
		}

		$button_class = 'btn cta-auth-button';

		if ( 'primary' === $atts['style'] ) {
			$button_class .= ' btn-primary';
		} else {
			$button_class .= ' btn-outline';
		}

		if ( 'sm' === $atts['size'] ) {
			$button_class .= ' btn--sm';
		}

		if ( ! empty( $atts['class'] ) ) {
			$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $atts['class'] ) ) );
			if ( $extra_classes ) {
				$button_class .= ' ' . implode( ' ', $extra_classes );
			}
		}

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/partials/auth-button.php';
		return ob_get_clean();
	}

	/**
	 * Render the site footer shortcode.
	 *
	 * @return string
	 */
	public function render_footer( $atts = array() ) {
		ob_start();
		include CTA_PLUGIN_DIR . 'templates/footer.php';
		return ob_get_clean();
	}
}
}