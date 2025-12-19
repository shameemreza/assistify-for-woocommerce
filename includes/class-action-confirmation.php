<?php
/**
 * Action Confirmation System.
 *
 * Handles confirmation for destructive actions triggered by AI.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.1.0
 */

namespace Assistify_For_WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action Confirmation Class.
 *
 * @since 1.1.0
 */
class Action_Confirmation {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var Action_Confirmation|null
	 */
	private static $instance = null;

	/**
	 * Actions requiring single confirmation.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $single_confirmation_actions = array(
		'afw/coupons/create',
		'afw/coupons/update',
		'afw/products/update',
		'afw/products/create',
		'afw/orders/update-status',
		'afw/orders/add-note',
		'afw/subscriptions/pause',
		'afw/subscriptions/skip',
		'afw/subscriptions/update-status',
		'afw/bookings/update',
		'afw/bookings/update-status',
		'afw/memberships/update-status',
	);

	/**
	 * Actions requiring double confirmation (destructive).
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $double_confirmation_actions = array(
		'afw/orders/refund',
		'afw/orders/cancel',
		'afw/coupons/delete',
		'afw/products/delete',
		'afw/subscriptions/cancel',
		'afw/subscriptions/terminate',
		'afw/bookings/cancel',
		'afw/memberships/cancel',
		'afw/customer/cancel-subscription',
		'afw/customer/cancel-membership',
	);

	/**
	 * Pending confirmations.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $pending_confirmations = array();

	/**
	 * Get singleton instance.
	 *
	 * @since 1.1.0
	 * @return Action_Confirmation Instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Register REST endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add filters for action confirmation.
		add_filter( 'assistify_ability_response', array( $this, 'maybe_require_confirmation' ), 10, 4 );
	}

	/**
	 * Check if an action requires confirmation.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @return string|false Confirmation level (single, double) or false.
	 */
	public function requires_confirmation( $ability_id ) {
		// Allow filtering of confirmation actions.
		$single_actions = apply_filters( 'assistify_single_confirmation_actions', $this->single_confirmation_actions );
		$double_actions = apply_filters( 'assistify_double_confirmation_actions', $this->double_confirmation_actions );

		if ( in_array( $ability_id, $double_actions, true ) ) {
			return 'double';
		}

		if ( in_array( $ability_id, $single_actions, true ) ) {
			return 'single';
		}

		return false;
	}

	/**
	 * Create a pending confirmation request.
	 *
	 * @since 1.1.0
	 * @param string $ability_id  Ability ID.
	 * @param array  $params      Parameters for the action.
	 * @param string $session_id  Session ID.
	 * @return array Confirmation data.
	 */
	public function create_confirmation( $ability_id, $params, $session_id ) {
		$confirmation_level = $this->requires_confirmation( $ability_id );

		if ( ! $confirmation_level ) {
			return array(
				'requires_confirmation' => false,
			);
		}

		// Generate unique confirmation token.
		$token = wp_generate_password( 32, false );

		// Store pending confirmation in transient.
		$confirmation_data = array(
			'ability_id' => $ability_id,
			'params'     => $params,
			'session_id' => $session_id,
			'user_id'    => get_current_user_id(),
			'level'      => $confirmation_level,
			'created_at' => time(),
			'expires_at' => time() + 300, // 5 minutes expiry.
		);

		set_transient( 'assistify_confirm_' . $token, $confirmation_data, 300 );

		return array(
			'requires_confirmation' => true,
			'confirmation_token'    => $token,
			'level'                 => $confirmation_level,
			'action_summary'        => $this->get_action_summary( $ability_id, $params ),
			'warning_message'       => $this->get_warning_message( $ability_id, $confirmation_level ),
			'expires_in'            => 300,
		);
	}

	/**
	 * Validate and execute a confirmed action.
	 *
	 * @since 1.1.0
	 * @param string $token             Confirmation token.
	 * @param string $confirmation_code Optional code for double confirmation.
	 * @return array|\WP_Error Result of the action or error.
	 */
	public function execute_confirmed( $token, $confirmation_code = '' ) {
		// Get pending confirmation.
		$confirmation = get_transient( 'assistify_confirm_' . $token );

		if ( ! $confirmation ) {
			return new \WP_Error(
				'confirmation_expired',
				__( 'This confirmation has expired. Please try again.', 'assistify-for-woocommerce' )
			);
		}

		// Verify user.
		if ( get_current_user_id() !== $confirmation['user_id'] ) {
			return new \WP_Error(
				'confirmation_invalid_user',
				__( 'This confirmation belongs to a different user.', 'assistify-for-woocommerce' )
			);
		}

		// Check double confirmation code.
		if ( 'double' === $confirmation['level'] ) {
			$expected_code = $this->get_confirmation_code( $confirmation['ability_id'] );
			if ( strtoupper( $confirmation_code ) !== $expected_code ) {
				return new \WP_Error(
					'confirmation_code_invalid',
					sprintf(
						/* translators: %s: expected confirmation code */
						__( 'Invalid confirmation code. Please type "%s" to confirm.', 'assistify-for-woocommerce' ),
						$expected_code
					)
				);
			}
		}

		// Delete the confirmation (one-time use).
		delete_transient( 'assistify_confirm_' . $token );

		// Execute the ability.
		$registry = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();
		$result   = $registry->execute( $confirmation['ability_id'], $confirmation['params'] );

		// Log the confirmed action.
		if ( class_exists( '\Assistify_For_WooCommerce\Audit_Logger' ) ) {
			$logger = Audit_Logger::instance();
			$status = is_wp_error( $result ) ? 'failed' : 'success';
			$logger->log(
				array(
					'action_type'     => 'confirmed_action',
					'action_category' => $this->get_category_from_ability( $confirmation['ability_id'] ),
					'description'     => sprintf(
						/* translators: %s: ability ID */
						__( 'Confirmed and executed: %s', 'assistify-for-woocommerce' ),
						$confirmation['ability_id']
					),
					'ability_id'      => $confirmation['ability_id'],
					'parameters'      => $confirmation['params'],
					'result'          => $result,
					'status'          => $status,
				)
			);
		}

		return $result;
	}

	/**
	 * Cancel a pending confirmation.
	 *
	 * @since 1.1.0
	 * @param string $token Confirmation token.
	 * @return bool True if cancelled, false if not found.
	 */
	public function cancel_confirmation( $token ) {
		$confirmation = get_transient( 'assistify_confirm_' . $token );

		if ( $confirmation ) {
			delete_transient( 'assistify_confirm_' . $token );

			// Log cancellation.
			if ( class_exists( '\Assistify_For_WooCommerce\Audit_Logger' ) ) {
				$logger = Audit_Logger::instance();
				$logger->log(
					array(
						'action_type'     => 'cancelled_action',
						'action_category' => $this->get_category_from_ability( $confirmation['ability_id'] ),
						'description'     => sprintf(
							/* translators: %s: ability ID */
							__( 'Cancelled action: %s', 'assistify-for-woocommerce' ),
							$confirmation['ability_id']
						),
						'ability_id'      => $confirmation['ability_id'],
						'parameters'      => $confirmation['params'],
						'status'          => 'cancelled',
					)
				);
			}

			return true;
		}

		return false;
	}

	/**
	 * Get action summary for confirmation display.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @param array  $params     Parameters.
	 * @return array Summary data.
	 */
	public function get_action_summary( $ability_id, $params ) {
		$summaries = array(
			'afw/orders/refund'                => array(
				'title' => __( 'Process Refund', 'assistify-for-woocommerce' ),
				'icon'  => '💰',
			),
			'afw/orders/cancel'                => array(
				'title' => __( 'Cancel Order', 'assistify-for-woocommerce' ),
				'icon'  => '❌',
			),
			'afw/coupons/create'               => array(
				'title' => __( 'Create Coupon', 'assistify-for-woocommerce' ),
				'icon'  => '🎫',
			),
			'afw/coupons/delete'               => array(
				'title' => __( 'Delete Coupon', 'assistify-for-woocommerce' ),
				'icon'  => '🗑️',
			),
			'afw/products/update'              => array(
				'title' => __( 'Update Product', 'assistify-for-woocommerce' ),
				'icon'  => '📦',
			),
			'afw/products/create'              => array(
				'title' => __( 'Create Product', 'assistify-for-woocommerce' ),
				'icon'  => '➕',
			),
			'afw/products/delete'              => array(
				'title' => __( 'Delete Product', 'assistify-for-woocommerce' ),
				'icon'  => '🗑️',
			),
			'afw/subscriptions/pause'          => array(
				'title' => __( 'Pause Subscription', 'assistify-for-woocommerce' ),
				'icon'  => '⏸️',
			),
			'afw/subscriptions/cancel'         => array(
				'title' => __( 'Cancel Subscription', 'assistify-for-woocommerce' ),
				'icon'  => '❌',
			),
			'afw/bookings/cancel'              => array(
				'title' => __( 'Cancel Booking', 'assistify-for-woocommerce' ),
				'icon'  => '📅',
			),
			'afw/memberships/cancel'           => array(
				'title' => __( 'Cancel Membership', 'assistify-for-woocommerce' ),
				'icon'  => '👤',
			),
			'afw/customer/cancel-subscription' => array(
				'title' => __( 'Cancel Your Subscription', 'assistify-for-woocommerce' ),
				'icon'  => '❌',
			),
		);

		$summary = $summaries[ $ability_id ] ?? array(
			'title' => __( 'Confirm Action', 'assistify-for-woocommerce' ),
			'icon'  => '⚠️',
		);

		// Add details from params.
		$details = array();

		if ( ! empty( $params['order_id'] ) ) {
			$details[] = sprintf(
				/* translators: %d: order ID */
				__( 'Order #%d', 'assistify-for-woocommerce' ),
				$params['order_id']
			);
		}
		if ( ! empty( $params['product_id'] ) ) {
			$product = wc_get_product( $params['product_id'] );
			if ( $product ) {
				$details[] = $product->get_name();
			}
		}
		if ( ! empty( $params['subscription_id'] ) ) {
			$details[] = sprintf(
				/* translators: %d: subscription ID */
				__( 'Subscription #%d', 'assistify-for-woocommerce' ),
				$params['subscription_id']
			);
		}
		if ( ! empty( $params['booking_id'] ) ) {
			$details[] = sprintf(
				/* translators: %d: booking ID */
				__( 'Booking #%d', 'assistify-for-woocommerce' ),
				$params['booking_id']
			);
		}
		if ( ! empty( $params['amount'] ) ) {
			$details[] = wc_price( $params['amount'] );
		}
		if ( ! empty( $params['code'] ) ) {
			$details[] = sprintf(
				/* translators: %s: coupon code */
				__( 'Code: %s', 'assistify-for-woocommerce' ),
				$params['code']
			);
		}

		$summary['details'] = $details;
		$summary['params']  = $params;

		return $summary;
	}

	/**
	 * Get warning message for confirmation.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @param string $level      Confirmation level.
	 * @return string Warning message.
	 */
	public function get_warning_message( $ability_id, $level ) {
		if ( 'double' === $level ) {
			$messages = array(
				'afw/orders/refund'                => __( 'This will process a refund and cannot be easily undone. The customer will be refunded.', 'assistify-for-woocommerce' ),
				'afw/orders/cancel'                => __( 'This will cancel the order. Inventory may be restocked.', 'assistify-for-woocommerce' ),
				'afw/coupons/delete'               => __( 'This will permanently delete the coupon.', 'assistify-for-woocommerce' ),
				'afw/products/delete'              => __( 'This will move the product to trash.', 'assistify-for-woocommerce' ),
				'afw/subscriptions/cancel'         => __( 'This will cancel the subscription. The customer will lose access.', 'assistify-for-woocommerce' ),
				'afw/bookings/cancel'              => __( 'This will cancel the booking. The customer will be notified.', 'assistify-for-woocommerce' ),
				'afw/memberships/cancel'           => __( 'This will cancel the membership. The customer will lose access.', 'assistify-for-woocommerce' ),
				'afw/customer/cancel-subscription' => __( 'This will cancel your subscription. You will lose access to benefits.', 'assistify-for-woocommerce' ),
			);

			return $messages[ $ability_id ] ?? __( 'This action cannot be easily undone. Please confirm.', 'assistify-for-woocommerce' );
		}

		return __( 'Please confirm this action.', 'assistify-for-woocommerce' );
	}

	/**
	 * Get confirmation code for double confirmation.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @return string Confirmation code.
	 */
	public function get_confirmation_code( $ability_id ) {
		$codes = array(
			'afw/orders/refund'                => 'REFUND',
			'afw/orders/cancel'                => 'CANCEL',
			'afw/coupons/delete'               => 'DELETE',
			'afw/products/delete'              => 'DELETE',
			'afw/subscriptions/cancel'         => 'CANCEL',
			'afw/subscriptions/terminate'      => 'TERMINATE',
			'afw/bookings/cancel'              => 'CANCEL',
			'afw/memberships/cancel'           => 'CANCEL',
			'afw/customer/cancel-subscription' => 'CANCEL',
			'afw/customer/cancel-membership'   => 'CANCEL',
		);

		return $codes[ $ability_id ] ?? 'CONFIRM';
	}

	/**
	 * Get category from ability ID.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @return string Category.
	 */
	private function get_category_from_ability( $ability_id ) {
		$parts = explode( '/', $ability_id );
		return $parts[1] ?? 'general';
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'assistify/v1',
			'/confirm/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_execute_confirmed' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'code'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'assistify/v1',
			'/confirm/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_cancel_confirmation' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permission for REST endpoints.
	 *
	 * @since 1.1.0
	 * @return bool Whether user is logged in.
	 */
	public function check_permission() {
		return is_user_logged_in();
	}

	/**
	 * REST endpoint: Execute confirmed action.
	 *
	 * @since 1.1.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_execute_confirmed( $request ) {
		$token = $request->get_param( 'token' );
		$code  = $request->get_param( 'code' );

		$result = $this->execute_confirmed( $token, $code );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * REST endpoint: Cancel confirmation.
	 *
	 * @since 1.1.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_cancel_confirmation( $request ) {
		$token     = $request->get_param( 'token' );
		$cancelled = $this->cancel_confirmation( $token );

		return rest_ensure_response(
			array(
				'success' => $cancelled,
			)
		);
	}

	/**
	 * Filter for ability response - may inject confirmation requirement.
	 *
	 * @since 1.1.0
	 * @param mixed  $response   The response.
	 * @param string $ability_id Ability ID.
	 * @param array  $params     Parameters.
	 * @param string $session_id Session ID.
	 * @return mixed Modified response.
	 */
	public function maybe_require_confirmation( $response, $ability_id, $params, $session_id ) {
		// Skip if confirmation was already handled.
		if ( ! empty( $params['_confirmed'] ) ) {
			return $response;
		}

		$confirmation_level = $this->requires_confirmation( $ability_id );

		if ( $confirmation_level ) {
			return $this->create_confirmation( $ability_id, $params, $session_id );
		}

		return $response;
	}
}
