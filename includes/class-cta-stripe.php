<?php
/**
 * Stripe payment integration for courses and supervision subscriptions.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Stripe
 */
if ( ! class_exists( 'CTA_Stripe' ) ) {

class CTA_Stripe {

	/**
	 * Stripe secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Stripe publishable key.
	 *
	 * @var string
	 */
	private $publishable_key;

	/**
	 * Stripe webhook signing secret.
	 *
	 * @var string
	 */
	private $webhook_secret;

	/**
	 * Stripe mode (test|live).
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->secret_key      = (string) get_option( 'cta_stripe_secret_key', '' );
		$this->publishable_key = (string) get_option( 'cta_stripe_publishable_key', '' );
		$this->webhook_secret  = (string) get_option( 'cta_stripe_webhook_secret', '' );
		$this->mode            = (string) get_option( 'cta_stripe_mode', 'test' );

		if ( ! empty( $this->secret_key ) && class_exists( '\Stripe\Stripe' ) ) {
			\Stripe\Stripe::setApiKey( $this->secret_key );
		}

		add_action( 'wp_ajax_cta_create_checkout', array( $this, 'create_checkout_session' ) );
		add_action( 'wp_ajax_nopriv_cta_create_checkout', array( $this, 'create_checkout_session' ) );

		add_action( 'wp_ajax_cta_create_subscription', array( $this, 'create_subscription_session' ) );
		add_action( 'wp_ajax_nopriv_cta_create_subscription', array( $this, 'create_subscription_session' ) );

		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
	}

	/**
	 * Whether Stripe secret key is configured.
	 *
	 * @return bool
	 */
	private function is_stripe_configured() {
		$key = get_option( 'cta_stripe_secret_key', '' );
		return ! empty( $key );
	}

	/**
	 * Whether Stripe keys are configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->secret_key ) && ! empty( $this->publishable_key ) && class_exists( '\Stripe\Stripe' );
	}

	/**
	 * Whether test/demo mode skips Stripe and enrolls users instantly.
	 *
	 * @return bool
	 */
	public static function is_payments_bypass_enabled() {
		return 'yes' === get_option( 'cta_payments_bypass', 'yes' );
	}

	/**
	 * Get publishable key for frontend.
	 *
	 * @return string
	 */
	public function get_publishable_key() {
		return $this->publishable_key;
	}

	/**
	 * Get supervision monthly price from options (DB).
	 *
	 * @return float
	 */
	public function get_supervision_monthly_price() {
		return (float) get_option( 'cta_supervision_monthly_price', 260.0 );
	}

	/**
	 * Create one-time Stripe Checkout session for a course.
	 */
	public function create_checkout_session() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to enroll in this course.', 'cta-lms' ),
				)
			);
		}

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );

		if ( ! $course_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid course selected.', 'cta-lms' ),
				)
			);
		}

		if ( ! $this->is_stripe_configured() ) {
			if ( ! empty( $_POST['demo_confirm'] ) ) {
				$this->bypass_course_enrollment( $course_id );
				return;
			}

			wp_send_json_success(
				array(
					'demo_mode'    => true,
					'checkout_url' => '',
				)
			);
		}

		if ( self::is_payments_bypass_enabled() ) {
			$this->bypass_course_enrollment( $course_id );
			return;
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Payments are not configured yet. Please contact support.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$course = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_courses
				WHERE id = %d AND status = 'published'",
				$course_id
			)
		);

		if ( ! $course ) {
			wp_send_json_error(
				array(
					'message' => __( 'Course not found.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();

		$enrolled = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_enrollments
				WHERE user_id = %d AND course_id = %d AND status = 'active'",
				$user_id,
				$course_id
			)
		);

		if ( $enrolled ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are already enrolled in this course.', 'cta-lms' ),
				)
			);
		}

		if ( (float) $course->price <= 0 ) {
			$this->bypass_course_enrollment( $course_id );
			return;
		}

		$course_page = $this->get_page_url( 'cta_single_course_page_id' );
		if ( ! $course_page ) {
			$course_page = home_url( '/' );
		}

		$success_url = add_query_arg(
			array(
				'course_id'  => $course_id,
				'payment'    => 'success',
				'session_id' => '{CHECKOUT_SESSION_ID}',
			),
			$course_page
		);

		$cancel_url = add_query_arg( 'course_id', $course_id, $course_page );

		$ce_hours = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );

		try {
			$session = \Stripe\Checkout\Session::create(
				array(
					'payment_method_types' => array( 'card' ),
					'mode'                 => 'payment',
					'customer_email'       => wp_get_current_user()->user_email,
					'line_items'           => array(
						array(
							'price_data' => array(
								'currency'     => 'usd',
								'unit_amount'  => (int) round( (float) $course->price * 100 ),
								'product_data' => array(
									'name'        => $course->title,
									'description' => sprintf(
										/* translators: %s: CE hours */
										__( '%s CE Hours — BBS Approved', 'cta-lms' ),
										$ce_hours
									),
								),
							),
							'quantity' => 1,
						),
					),
					'metadata'    => array(
						'user_id'      => (string) $user_id,
						'course_id'    => (string) $course_id,
						'product_type' => 'course',
					),
					'success_url' => $success_url,
					'cancel_url'  => $cancel_url,
				)
			);

			$wpdb->insert(
				$wpdb->prefix . 'cta_payments',
				array(
					'user_id'           => $user_id,
					'stripe_payment_id' => $session->id,
					'amount'            => $course->price,
					'currency'          => 'usd',
					'payment_type'      => 'one_time',
					'product_type'      => 'course',
					'product_id'        => $course_id,
					'status'            => 'pending',
				),
				array( '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
			);

			wp_send_json_success(
				array(
					'checkout_url' => $session->url,
				)
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Stripe error message */
						__( 'Payment error: %s', 'cta-lms' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Create Stripe Checkout session for supervision subscription.
	 */
	public function create_subscription_session() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to subscribe to supervision.', 'cta-lms' ),
				)
			);
		}

		if ( ! $this->is_stripe_configured() ) {
			if ( ! empty( $_POST['demo_confirm'] ) ) {
				$this->bypass_supervision_subscription();
				return;
			}

			wp_send_json_success(
				array(
					'demo_mode'    => true,
					'checkout_url' => '',
				)
			);
		}

		if ( self::is_payments_bypass_enabled() ) {
			$this->bypass_supervision_subscription();
			return;
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Payments are not configured yet. Please contact support.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$user_id = get_current_user_id();
		$price   = $this->get_supervision_monthly_price();

		if ( $price <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Supervision pricing is not configured.', 'cta-lms' ),
				)
			);
		}

		$active_sub = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'supervision'
				AND payment_type = 'subscription'
				AND status = 'completed'",
				$user_id
			)
		);

		if ( $active_sub ) {
			wp_send_json_error(
				array(
					'message' => __( 'You already have an active supervision subscription.', 'cta-lms' ),
				)
			);
		}

		$supervision_page = $this->get_page_url( 'cta_supervision_dashboard_page_id' );
		if ( ! $supervision_page ) {
			$supervision_page = $this->get_page_url( 'cta_supervision_page_id' );
		}
		if ( ! $supervision_page ) {
			$supervision_page = home_url( '/' );
		}

		$product_name = (string) get_option( 'cta_supervision_product_name', '' );
		if ( '' === $product_name ) {
			$product_name = __( 'Clinical Supervision — Monthly', 'cta-lms' );
		}

		$product_desc = (string) get_option( 'cta_supervision_product_description', '' );
		if ( '' === $product_desc ) {
			$product_desc = __( 'Monthly group supervision subscription', 'cta-lms' );
		}

		$success_url = add_query_arg(
			array(
				'subscription' => 'success',
				'session_id'   => '{CHECKOUT_SESSION_ID}',
			),
			$supervision_page
		);

		$cancel_url = add_query_arg( 'subscription', 'cancelled', $supervision_page );

		try {
			$session = \Stripe\Checkout\Session::create(
				array(
					'payment_method_types' => array( 'card' ),
					'mode'                 => 'subscription',
					'customer_email'       => wp_get_current_user()->user_email,
					'line_items'           => array(
						array(
							'price_data' => array(
								'currency'     => 'usd',
								'unit_amount'  => (int) round( $price * 100 ),
								'recurring'    => array(
									'interval' => 'month',
								),
								'product_data' => array(
									'name'        => $product_name,
									'description' => $product_desc,
								),
							),
							'quantity' => 1,
						),
					),
					'metadata'    => array(
						'user_id'      => (string) $user_id,
						'product_type' => 'supervision',
					),
					'success_url' => $success_url,
					'cancel_url'  => $cancel_url,
				)
			);

			$wpdb->insert(
				$wpdb->prefix . 'cta_payments',
				array(
					'user_id'           => $user_id,
					'stripe_payment_id' => $session->id,
					'amount'            => $price,
					'currency'          => 'usd',
					'payment_type'      => 'subscription',
					'product_type'      => 'supervision',
					'product_id'        => 0,
					'status'            => 'pending',
				),
				array( '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
			);

			wp_send_json_success(
				array(
					'checkout_url' => $session->url,
				)
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Stripe error message */
						__( 'Payment error: %s', 'cta-lms' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Register Stripe webhook REST route.
	 */
	public function register_webhook_route() {
		register_rest_route(
			'cta-lms/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming Stripe webhook events.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		if ( ! class_exists( '\Stripe\Webhook' ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Stripe SDK not loaded.' ),
				500
			);
		}

		$payload    = $request->get_body();
		$sig_header = $request->get_header( 'stripe-signature' );

		try {
			if ( ! empty( $this->webhook_secret ) ) {
				$event = \Stripe\Webhook::constructEvent(
					$payload,
					$sig_header,
					$this->webhook_secret
				);
			} else {
				$event = json_decode( $payload );
			}
		} catch ( \UnexpectedValueException $e ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload.' ), 400 );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			return new WP_REST_Response( array( 'error' => 'Invalid signature.' ), 400 );
		}

		if ( empty( $event->type ) ) {
			return new WP_REST_Response( array( 'error' => 'Missing event type.' ), 400 );
		}

		switch ( $event->type ) {
			case 'checkout.session.completed':
				$this->handle_checkout_completed( $event->data->object );
				break;

			case 'customer.subscription.deleted':
				$this->handle_subscription_cancelled( $event->data->object );
				break;

			case 'invoice.payment_failed':
				$this->handle_subscription_payment_failed( $event->data->object );
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Process completed checkout session.
	 *
	 * @param object $session Stripe checkout session.
	 */
	private function handle_checkout_completed( $session ) {
		global $wpdb;

		$metadata = isset( $session->metadata ) ? (array) $session->metadata : array();
		$user_id  = absint( $metadata['user_id'] ?? 0 );
		$type     = sanitize_text_field( $metadata['product_type'] ?? '' );

		$wpdb->update(
			$wpdb->prefix . 'cta_payments',
			array(
				'status'             => 'completed',
				'stripe_customer_id' => sanitize_text_field( $session->customer ?? '' ),
			),
			array( 'stripe_payment_id' => sanitize_text_field( $session->id ) ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		if ( 'course' === $type ) {
			$course_id = absint( $metadata['course_id'] ?? 0 );

			if ( $user_id && $course_id ) {
				$this->create_enrollment( $user_id, $course_id, sanitize_text_field( $session->id ) );

				$course = CTA_Database::get_course( $course_id );
				if ( $course ) {
					CTA_Emails::send(
						'payment_receipt',
						$user_id,
						array(
							'payment_id'   => sanitize_text_field( $session->id ),
							'product_name' => $course->title,
						)
					);
				}
			}
		}

		if ( 'supervision' === $type && $user_id ) {
			$subscription_id = sanitize_text_field( $session->subscription ?? '' );
			$customer_id       = sanitize_text_field( $session->customer ?? '' );

			if ( $customer_id ) {
				update_user_meta( $user_id, 'cta_stripe_customer_id', $customer_id );
			}

			if ( $subscription_id ) {
				$wpdb->update(
					$wpdb->prefix . 'cta_payments',
					array(
						'stripe_payment_id' => $subscription_id,
						'status'            => 'completed',
					),
					array( 'stripe_payment_id' => sanitize_text_field( $session->id ) ),
					array( '%s', '%s' ),
					array( '%s' )
				);

				update_user_meta( $user_id, 'cta_supervision_subscription_id', $subscription_id );
				update_user_meta( $user_id, 'cta_supervision_status', 'active' );
				update_user_meta( $user_id, 'cta_supervision_plan', 'group' );

				CTA_Emails::send(
					'payment_receipt',
					$user_id,
					array(
						'payment_id'   => sanitize_text_field( $session->id ),
						'product_name' => __( 'Group Supervision', 'cta-lms' ),
					)
				);
			}
		}

		if ( 'bundle' === $type && $user_id ) {
			$bundle_id = absint( $metadata['bundle_id'] ?? 0 );
			$billing   = sanitize_text_field( $metadata['billing'] ?? '' );

			if ( $bundle_id ) {
				$this->activate_bundle_access(
					$user_id,
					$bundle_id,
					$billing,
					sanitize_text_field( $session->id )
				);

				$subscription_id = sanitize_text_field( $session->subscription ?? '' );

				if ( $subscription_id ) {
					$wpdb->update(
						$wpdb->prefix . 'cta_payments',
						array(
							'stripe_payment_id' => $subscription_id,
							'status'            => 'completed',
						),
						array(
							'user_id'      => $user_id,
							'product_id'   => $bundle_id,
							'product_type' => 'bundle',
						),
						array( '%s', '%s' ),
						array( '%d', '%d', '%s' )
					);

					update_user_meta( $user_id, 'cta_bundle_subscription_id', $subscription_id );
				}
			}
		}
	}

	/**
	 * Create Stripe Checkout session for a membership bundle.
	 *
	 * @param object $bundle Bundle row from database.
	 */
	public function create_bundle_checkout_session( $bundle ) {
		if ( ! $this->is_stripe_configured() ) {
			if ( ! empty( $_POST['demo_confirm'] ) ) {
				$this->bypass_bundle_purchase( $bundle );
				return;
			}

			wp_send_json_success(
				array(
					'demo_mode'    => true,
					'checkout_url' => '',
				)
			);
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Payments are not configured yet. Please contact support.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$billing = sanitize_text_field( $bundle->billing_cycle );

		$memberships_page = $this->get_page_url( 'cta_memberships_page_id' );
		if ( ! $memberships_page ) {
			$memberships_page = home_url( '/' );
		}

		$success_url = add_query_arg(
			array(
				'bundle_purchase' => 'success',
				'bundle_id'       => (int) $bundle->id,
				'session_id'      => '{CHECKOUT_SESSION_ID}',
			),
			$memberships_page
		);

		$cancel_url = $memberships_page;

		$mode         = ( 'monthly' === $billing ) ? 'subscription' : 'payment';
		$payment_type = ( 'monthly' === $billing ) ? 'subscription' : 'one_time';

		try {
			$session_args = array(
				'payment_method_types' => array( 'card' ),
				'mode'                 => $mode,
				'customer_email'       => $user->user_email,
				'metadata'             => array(
					'user_id'      => (string) $user_id,
					'bundle_id'    => (string) $bundle->id,
					'product_type' => 'bundle',
					'billing'      => $billing,
				),
				'success_url'          => $success_url,
				'cancel_url'           => $cancel_url,
			);

			if ( 'monthly' === $billing && ! empty( $bundle->stripe_price_id ) ) {
				$session_args['line_items'] = array(
					array(
						'price'    => $bundle->stripe_price_id,
						'quantity' => 1,
					),
				);
			} elseif ( 'monthly' === $billing ) {
				$session_args['line_items'] = array(
					array(
						'price_data' => array(
							'currency'     => 'usd',
							'unit_amount'  => (int) round( (float) $bundle->price * 100 ),
							'recurring'    => array(
								'interval' => 'month',
							),
							'product_data' => array(
								'name'        => $bundle->name,
								'description' => wp_strip_all_tags( (string) $bundle->description ),
							),
						),
						'quantity'   => 1,
					),
				);
			} else {
				$session_args['line_items'] = array(
					array(
						'price_data' => array(
							'currency'     => 'usd',
							'unit_amount'  => (int) round( (float) $bundle->price * 100 ),
							'product_data' => array(
								'name'        => $bundle->name,
								'description' => wp_strip_all_tags( (string) $bundle->description ),
							),
						),
						'quantity'   => 1,
					),
				);
			}

			$session = \Stripe\Checkout\Session::create( $session_args );

			$wpdb->insert(
				$wpdb->prefix . 'cta_payments',
				array(
					'user_id'           => $user_id,
					'stripe_payment_id' => $session->id,
					'amount'            => $bundle->price,
					'currency'          => 'usd',
					'payment_type'      => $payment_type,
					'product_type'      => 'bundle',
					'product_id'        => (int) $bundle->id,
					'status'            => 'pending',
				),
				array( '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
			);

			wp_send_json_success(
				array(
					'checkout_url' => $session->url,
				)
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Stripe error message */
						__( 'Payment error: %s', 'cta-lms' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Activate bundle access after successful payment.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $bundle_id  Bundle ID.
	 * @param string $billing    Billing cycle.
	 * @param string $payment_id Stripe session or payment ID.
	 */
	private function activate_bundle_access( $user_id, $bundle_id, $billing, $payment_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'cta_payments',
			array( 'status' => 'completed' ),
			array(
				'user_id'      => $user_id,
				'product_id'   => $bundle_id,
				'product_type' => 'bundle',
			),
			array( '%s' ),
			array( '%d', '%d', '%s' )
		);

		$bundle = CTA_Database::get_bundle( $bundle_id );
		if ( ! $bundle ) {
			return;
		}

		$included_ids = json_decode( (string) $bundle->included_courses, true );
		if ( ! is_array( $included_ids ) ) {
			$included_ids = array();
		}

		if ( 'annual' === $bundle->plan_type || 'yearly' === $billing || 'subscription' === $bundle->plan_type ) {
			$all_courses = CTA_Database::get_all_courses( 'published' );
			foreach ( $all_courses as $course ) {
				$this->enroll_user_in_course( $user_id, (int) $course->id, $payment_id );
			}
		} else {
			foreach ( $included_ids as $course_id ) {
				$this->enroll_user_in_course( $user_id, (int) $course_id, $payment_id );
			}
		}

		if ( 'subscription' === $bundle->plan_type ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'active' );
			update_user_meta( $user_id, 'cta_hybrid_plan_active', (int) $bundle->id );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		CTA_Emails::send(
			'payment_receipt',
			$user_id,
			array(
				'payment_id'   => $payment_id,
				'product_name' => $bundle->name,
			)
		);
	}

	/**
	 * Skip Stripe and enroll the current user in a course (testing mode).
	 *
	 * @param int $course_id Course ID.
	 */
	private function bypass_course_enrollment( $course_id ) {
		global $wpdb;

		$course = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_courses
				WHERE id = %d AND status = 'published'",
				$course_id
			)
		);

		if ( ! $course ) {
			wp_send_json_error(
				array(
					'message' => __( 'Course not found.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();
		$payment_id = 'bypass-' . time();

		$this->create_enrollment( $user_id, $course_id, $payment_id );

		wp_send_json_success(
			array(
				'enrolled'     => true,
				'redirect_url' => $this->get_course_player_url( $course_id ),
				'message'      => __( 'Enrolled successfully (payment bypass mode).', 'cta-lms' ),
			)
		);
	}

	/**
	 * Skip Stripe and activate supervision subscription (testing mode).
	 */
	private function bypass_supervision_subscription() {
		global $wpdb;

		$user_id = get_current_user_id();

		$active_sub = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'supervision'
				AND payment_type = 'subscription'
				AND status = 'completed'",
				$user_id
			)
		);

		if ( $active_sub ) {
			wp_send_json_error(
				array(
					'message' => __( 'You already have an active supervision subscription.', 'cta-lms' ),
				)
			);
		}

		$payment_id = 'bypass-sub-' . time();

		$wpdb->insert(
			$wpdb->prefix . 'cta_payments',
			array(
				'user_id'             => $user_id,
				'amount'              => 0,
				'status'              => 'completed',
				'payment_type'        => 'subscription',
				'product_type'        => 'supervision',
				'stripe_payment_id'   => $payment_id,
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);

		update_user_meta( $user_id, 'cta_supervision_subscription_id', $payment_id );
		update_user_meta( $user_id, 'cta_supervision_status', 'active' );

		$redirect = $this->get_page_url( 'cta_supervision_dashboard_page_id' );
		if ( ! $redirect ) {
			$redirect = $this->get_page_url( 'cta_supervision_page_id' );
		}
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		wp_send_json_success(
			array(
				'enrolled'     => true,
				'redirect_url' => $redirect,
				'message'      => __( 'Subscription activated (payment bypass mode).', 'cta-lms' ),
			)
		);
	}

	/**
	 * Skip Stripe and activate a membership bundle (testing mode).
	 *
	 * @param object $bundle Bundle row.
	 */
	public function bypass_bundle_purchase( $bundle ) {
		$user_id    = get_current_user_id();
		$payment_id = 'bypass-bundle-' . time();
		$billing    = sanitize_text_field( $bundle->billing_cycle );

		$this->activate_bundle_access( $user_id, (int) $bundle->id, $billing, $payment_id );

		$redirect = CTA_Emails::get_page_url( 'cta_student_dashboard_page_id' );
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		wp_send_json_success(
			array(
				'enrolled'     => true,
				'redirect_url' => $redirect,
				'message'      => __( 'Plan activated (payment bypass mode).', 'cta-lms' ),
			)
		);
	}

	/**
	 * Get course player URL for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function get_course_player_url( $course_id ) {
		$page = $this->get_page_url( 'cta_course_player_page_id' );
		if ( ! $page ) {
			$page = $this->get_page_url( 'cta_single_course_page_id' );
		}
		if ( ! $page ) {
			$page = home_url( '/' );
		}

		return add_query_arg( 'course_id', $course_id, $page );
	}

	/**
	 * Enroll a user in a course if not already enrolled.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $course_id  Course ID.
	 * @param string $payment_id Payment reference ID.
	 */
	private function enroll_user_in_course( $user_id, $course_id, $payment_id ) {
		if ( ! $course_id ) {
			return;
		}

		$this->create_enrollment( $user_id, $course_id, $payment_id );
	}

	/**
	 * Create course enrollment after successful payment.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $course_id  Course ID.
	 * @param string $payment_id Stripe session/payment ID.
	 */
	private function create_enrollment( $user_id, $course_id, $payment_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_enrollments';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE user_id = %d AND course_id = %d AND status = 'active'",
				$user_id,
				$course_id
			)
		);

		if ( $exists ) {
			return;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'status'     => 'active',
				'progress'   => 0,
				'payment_id' => $payment_id,
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return;
		}

		CTA_Emails::send(
			'enrollment_confirmation',
			$user_id,
			array(
				'course_id'  => $course_id,
				'payment_id' => $payment_id,
			)
		);
	}

	/**
	 * Handle cancelled supervision subscription.
	 *
	 * @param object $subscription Stripe subscription object.
	 */
	private function handle_subscription_cancelled( $subscription ) {
		global $wpdb;

		$subscription_id = sanitize_text_field( $subscription->id ?? '' );

		if ( ! $subscription_id ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'cta_payments',
			array( 'status' => 'refunded' ),
			array( 'stripe_payment_id' => $subscription_id ),
			array( '%s' ),
			array( '%s' )
		);

		$user_id = $this->get_user_id_by_subscription( $subscription_id );

		if ( $user_id ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'cancelled' );
			CTA_Emails::send( 'supervision_locked', $user_id );
		}
	}

	/**
	 * Handle failed subscription invoice payment.
	 *
	 * @param object $invoice Stripe invoice object.
	 */
	private function handle_subscription_payment_failed( $invoice ) {
		$subscription_id = sanitize_text_field( $invoice->subscription ?? '' );

		if ( ! $subscription_id ) {
			return;
		}

		$user_id = $this->get_user_id_by_subscription( $subscription_id );

		if ( $user_id ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'locked' );
			CTA_Emails::send(
				'payment_failed',
				$user_id,
				array(
					'subscription_plan' => __( 'Group Supervision', 'cta-lms' ),
				)
			);
		}
	}

	/**
	 * Find WordPress user ID by Stripe subscription ID.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return int
	 */
	private function get_user_id_by_subscription( $subscription_id ) {
		global $wpdb;

		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}cta_payments
				WHERE stripe_payment_id = %s
				AND product_type = 'supervision'
				LIMIT 1",
				$subscription_id
			)
		);

		if ( $user_id ) {
			return (int) $user_id;
		}

		$users = get_users(
			array(
				'meta_key'   => 'cta_supervision_subscription_id',
				'meta_value' => $subscription_id,
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		return ! empty( $users ) ? (int) $users[0] : 0;
	}

	/**
	 * Get permalink from plugin page option.
	 *
	 * @param string $option_name Option key.
	 * @return string
	 */
	private function get_page_url( $option_name ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}
}
}