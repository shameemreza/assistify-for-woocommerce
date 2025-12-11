<?php
/**
 * REST Chat Controller Class.
 *
 * Handles REST API endpoints for chat functionality.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\REST_API;

use Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST Chat Controller Class.
 *
 * @since 1.0.0
 */
class REST_Chat_Controller extends REST_API_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'chat';

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		// Admin chat endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/admin',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_admin_message' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_chat_args(),
				),
			)
		);

		// Customer chat endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/customer',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_customer_message' ),
					'permission_callback' => array( $this, 'customer_permissions_check' ),
					'args'                => $this->get_chat_args(),
				),
			)
		);

		// Get chat history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/history/(?P<session_id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_chat_history' ),
					'permission_callback' => array( $this, 'customer_permissions_check' ),
					'args'                => array(
						'session_id' => array(
							'description'       => __( 'Chat session ID.', 'assistify-for-woocommerce' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Create new session.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/session',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_session' ),
					'permission_callback' => array( $this, 'customer_permissions_check' ),
					'args'                => array(
						'context' => array(
							'description'       => __( 'Chat context (admin or customer).', 'assistify-for-woocommerce' ),
							'type'              => 'string',
							'default'           => 'customer',
							'enum'              => array( 'admin', 'customer' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Get chat endpoint arguments.
	 *
	 * @since 1.0.0
	 * @return array Endpoint arguments.
	 */
	private function get_chat_args() {
		return array(
			'message'    => array(
				'description'       => __( 'The message to send.', 'assistify-for-woocommerce' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'session_id' => array(
				'description'       => __( 'Chat session ID.', 'assistify-for-woocommerce' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'history'    => array(
				'description' => __( 'Previous message history.', 'assistify-for-woocommerce' ),
				'type'        => 'array',
				'required'    => false,
				'default'     => array(),
			),
		);
	}

	/**
	 * Send a message in admin chat.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function send_admin_message( $request ) {
		// Check rate limit.
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' );
		$history    = $this->sanitize_messages( $request->get_param( 'history' ) );

		// Get the AI provider.
		$provider = AI_Provider_Factory::get_configured_provider();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Build messages array.
		$messages   = $history;
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Get system prompt for admin context.
		$system_prompt = $this->get_admin_system_prompt();

		// Send to AI.
		$response = $provider->chat(
			$messages,
			array(
				'system_prompt' => $system_prompt,
				'max_tokens'    => 2048,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Store message in database.
		$this->store_message( $session_id, 'user', $message, 'admin' );
		$this->store_message( $session_id, 'assistant', $response['content'], 'admin' );

		return rest_ensure_response(
			array(
				'success'    => true,
				'response'   => $response['content'],
				'session_id' => $session_id,
				'usage'      => $response['usage'],
			)
		);
	}

	/**
	 * Send a message in customer chat.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function send_customer_message( $request ) {
		// Check rate limit.
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Check if customer chat is enabled.
		$enabled = get_option( 'assistify_customer_chat_enabled', 'yes' );
		if ( 'yes' !== $enabled ) {
			return new \WP_Error(
				'assistify_chat_disabled',
				__( 'Customer chat is currently disabled.', 'assistify-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' );
		$history    = $this->sanitize_messages( $request->get_param( 'history' ) );

		// Get the AI provider.
		$provider = AI_Provider_Factory::get_configured_provider();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Build messages array.
		$messages   = $history;
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Get system prompt for customer context.
		$system_prompt = $this->get_customer_system_prompt();

		// Send to AI.
		$response = $provider->chat(
			$messages,
			array(
				'system_prompt' => $system_prompt,
				'max_tokens'    => 1024, // Shorter responses for customers.
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Store message in database.
		$this->store_message( $session_id, 'user', $message, 'customer' );
		$this->store_message( $session_id, 'assistant', $response['content'], 'customer' );

		return rest_ensure_response(
			array(
				'success'    => true,
				'response'   => $response['content'],
				'session_id' => $session_id,
			)
		);
	}

	/**
	 * Get chat history for a session.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_chat_history( $request ) {
		global $wpdb;

		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
		$table_name = $wpdb->prefix . 'afw_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetching fresh chat history.
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT role, content, created_at FROM %i WHERE session_id = %s ORDER BY created_at ASC',
				$table_name,
				$session_id
			),
			ARRAY_A
		);

		if ( empty( $messages ) ) {
			$messages = array();
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'session_id' => $session_id,
				'messages'   => $messages,
			)
		);
	}

	/**
	 * Create a new chat session.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function create_session( $request ) {
		global $wpdb;

		$context = $request->get_param( 'context' );
		$user_id = get_current_user_id();

		// Generate session ID.
		$session_id = wp_generate_uuid4();

		// Insert session into database.
		$table_name = $wpdb->prefix . 'afw_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Creating new session record.
		$result = $wpdb->insert(
			$table_name,
			array(
				'session_id' => $session_id,
				'user_id'    => $user_id,
				'context'    => $context,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'assistify_session_failed',
				__( 'Failed to create chat session.', 'assistify-for-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'session_id' => $session_id,
				'context'    => $context,
			)
		);
	}

	/**
	 * Store a message in the database.
	 *
	 * @since 1.0.0
	 * @param string $session_id Session ID.
	 * @param string $role       Message role (user/assistant).
	 * @param string $content    Message content.
	 * @param string $context    Chat context (admin/customer).
	 * @return bool True on success, false on failure.
	 */
	private function store_message( $session_id, $role, $content, $context ) {
		global $wpdb;

		if ( empty( $session_id ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'afw_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Storing chat message.
		return false !== $wpdb->insert(
			$table_name,
			array(
				'session_id' => $session_id,
				'role'       => $role,
				'content'    => $content,
				'context'    => $context,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get system prompt for admin context.
	 *
	 * @since 1.0.0
	 * @return string System prompt.
	 */
	private function get_admin_system_prompt() {
		$store_name = get_bloginfo( 'name' );
		$currency   = get_woocommerce_currency();

		// Get real store context using abilities.
		$store_context = $this->get_admin_store_context();

		$prompt = sprintf(
			/* translators: %1$s: Store name, %2$s: Currency code, %3$s: Store context data. */
			__(
				'You are an AI assistant for %1$s, a WooCommerce store. You help store administrators by providing ACCURATE information based ONLY on the actual store data provided below.

Store currency: %2$s

CRITICAL RULES:
- ONLY use the data provided below. DO NOT make up or hallucinate any information.
- If asked about something not in the provided data, say "I don\'t have that information in my current context. Please check the WooCommerce dashboard directly."
- NEVER invent order numbers, coupon codes, customer names, or any other data.
- If the data section is empty, tell the user the data could not be retrieved.
- Be concise and professional.
- Format responses clearly with line breaks for readability.

=== CURRENT STORE DATA ===
%3$s
=== END OF STORE DATA ===

When answering questions:
1. First check if the relevant data is available above
2. If yes, provide the accurate information from the data
3. If no, clearly state you don\'t have that specific information',
				'assistify-for-woocommerce'
			),
			$store_name,
			$currency,
			$store_context
		);

		/**
		 * Filter the admin system prompt.
		 *
		 * @since 1.0.0
		 * @param string $prompt The system prompt.
		 */
		return apply_filters( 'assistify_admin_system_prompt', $prompt );
	}

	/**
	 * Get real store context for admin chat.
	 *
	 * Fetches actual store data using the abilities registry.
	 *
	 * @since 1.0.0
	 * @return string Formatted store context.
	 */
	private function get_admin_store_context() {
		$context_parts = array();

		// Get abilities registry.
		$abilities = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();

		// Recent orders summary.
		$recent_orders = $abilities->execute( 'afw/orders/list', array( 'limit' => 10 ) );
		if ( ! is_wp_error( $recent_orders ) && ! empty( $recent_orders ) ) {
			$context_parts[] = 'RECENT ORDERS (Last 10):';
			foreach ( $recent_orders as $order ) {
				$context_parts[] = sprintf(
					'- Order #%s: %s - %s (%s) - Customer: %s',
					$order['number'] ?? $order['id'],
					$order['status_label'] ?? $order['status'],
					$order['total'] ?? 'N/A',
					$order['date_created'] ?? 'N/A',
					$order['customer']['name'] ?? 'Guest'
				);
			}
			$context_parts[] = '';
		} else {
			$context_parts[] = "RECENT ORDERS: No orders found or unable to retrieve.\n";
		}

		// Low stock products.
		$low_stock = $abilities->execute(
			'afw/products/low-stock',
			array(
				'threshold' => 5,
				'limit'     => 10,
			)
		);
		if ( ! is_wp_error( $low_stock ) && ! empty( $low_stock ) ) {
			$context_parts[] = 'LOW STOCK PRODUCTS (threshold: 5):';
			foreach ( $low_stock as $product ) {
				$context_parts[] = sprintf(
					'- %s (SKU: %s): %d in stock',
					$product['name'] ?? 'Unknown',
					$product['sku'] ?? 'N/A',
					$product['stock_quantity'] ?? 0
				);
			}
			$context_parts[] = '';
		}

		// Active coupons.
		$coupons = $abilities->execute(
			'afw/coupons/list',
			array(
				'status' => 'publish',
				'limit'  => 10,
			)
		);
		if ( ! is_wp_error( $coupons ) && ! empty( $coupons ) ) {
			$context_parts[] = 'ACTIVE COUPONS:';
			foreach ( $coupons as $coupon ) {
				$expiry          = ! empty( $coupon['expiry_date'] ) ? $coupon['expiry_date'] : 'No expiry';
				$context_parts[] = sprintf(
					'- Code: %s - %s %s (Used: %d times, Expiry: %s)',
					$coupon['code'] ?? 'N/A',
					$coupon['amount'] ?? 'N/A',
					$coupon['discount_type_label'] ?? '',
					$coupon['usage_count'] ?? 0,
					$expiry
				);
			}
			$context_parts[] = '';
		} else {
			$context_parts[] = "ACTIVE COUPONS: No coupons found or unable to retrieve.\n";
		}

		// Recent products.
		$recent_products = $abilities->execute( 'afw/products/list', array( 'limit' => 5 ) );
		if ( ! is_wp_error( $recent_products ) && ! empty( $recent_products ) ) {
			$context_parts[] = 'RECENT PRODUCTS (Last 5):';
			foreach ( $recent_products as $product ) {
				$context_parts[] = sprintf(
					'- %s: %s (%s)',
					$product['name'] ?? 'Unknown',
					$product['price_html'] ?? $product['price'] ?? 'N/A',
					$product['stock_status_label'] ?? $product['stock_status'] ?? 'N/A'
				);
			}
			$context_parts[] = '';
		}

		// Store analytics summary.
		$analytics = $abilities->execute( 'afw/analytics/daily-summary', array( 'days' => 7 ) );
		if ( ! is_wp_error( $analytics ) && ! empty( $analytics ) ) {
			$context_parts[] = 'STORE ANALYTICS (Last 7 days):';
			$context_parts[] = sprintf( '- Total Revenue: %s', $analytics['total_sales_formatted'] ?? $analytics['total_sales'] ?? 'N/A' );
			$context_parts[] = sprintf( '- Orders: %d', $analytics['total_orders'] ?? 0 );
			$context_parts[] = sprintf( '- Average Order Value: %s', $analytics['average_order_value_formatted'] ?? $analytics['average_order_value'] ?? 'N/A' );
			$context_parts[] = sprintf( '- New Customers: %d', $analytics['new_customers'] ?? 0 );
			$context_parts[] = '';
		}

		if ( empty( $context_parts ) ) {
			return 'Unable to retrieve store data. The user should check the WooCommerce dashboard directly.';
		}

		return implode( "\n", $context_parts );
	}

	/**
	 * Get system prompt for customer context.
	 *
	 * @since 1.0.0
	 * @return string System prompt.
	 */
	private function get_customer_system_prompt() {
		$store_name = get_bloginfo( 'name' );

		// Get real customer context.
		$customer_context = $this->get_customer_store_context();

		$prompt = sprintf(
			/* translators: %1$s: Store name, %2$s: Customer context data. */
			__(
				'You are a friendly customer support assistant for %1$s.

CRITICAL RULES:
- ONLY use the data provided below. DO NOT make up or hallucinate any information.
- If asked about something not in the provided data, say "I don\'t have that specific information. Please contact our support team or check your account."
- NEVER invent order numbers, tracking numbers, product details, or coupon codes.
- Be friendly, helpful, and concise.
- If you can\'t help with something, suggest contacting human support.
- Never share sensitive customer information.
- Don\'t process payments or make account changes.

=== STORE INFORMATION ===
%2$s
=== END OF STORE INFORMATION ===

When answering questions:
1. First check if the relevant data is available above
2. If yes, provide the accurate information
3. If no, politely explain you don\'t have that information and suggest alternatives',
				'assistify-for-woocommerce'
			),
			$store_name,
			$customer_context
		);

		/**
		 * Filter the customer system prompt.
		 *
		 * @since 1.0.0
		 * @param string $prompt The system prompt.
		 */
		return apply_filters( 'assistify_customer_system_prompt', $prompt );
	}

	/**
	 * Get real store context for customer chat.
	 *
	 * Fetches customer-specific data and public store information.
	 *
	 * @since 1.0.0
	 * @return string Formatted customer context.
	 */
	private function get_customer_store_context() {
		$context_parts = array();
		$user_id       = get_current_user_id();

		// Get abilities registry.
		$abilities = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();

		// Store information.
		$store_settings = $abilities->execute( 'afw/store/settings', array() );
		if ( ! is_wp_error( $store_settings ) && ! empty( $store_settings ) ) {
			$context_parts[] = 'STORE INFORMATION:';
			if ( ! empty( $store_settings['store_address'] ) ) {
				$context_parts[] = '- Location: ' . wp_strip_all_tags( $store_settings['store_address'] );
			}
			$context_parts[] = '- Currency: ' . ( $store_settings['currency'] ?? get_woocommerce_currency() );
			$context_parts[] = '';
		}

		// Available public coupons.
		$public_coupons = $abilities->execute( 'afw/coupons/available', array( 'limit' => 5 ) );
		if ( ! is_wp_error( $public_coupons ) && ! empty( $public_coupons ) ) {
			$context_parts[] = 'AVAILABLE PROMOTIONS:';
			foreach ( $public_coupons as $coupon ) {
				$expiry          = ! empty( $coupon['expiry_date'] ) ? " (expires: {$coupon['expiry_date']})" : '';
				$context_parts[] = sprintf(
					'- Use code %s for %s%s',
					$coupon['code'] ?? 'N/A',
					$coupon['description'] ?? $coupon['discount_description'] ?? 'discount',
					$expiry
				);
			}
			$context_parts[] = '';
		}

		// If customer is logged in, show their orders.
		if ( $user_id > 0 ) {
			$my_orders = $abilities->execute(
				'afw/customers/orders',
				array(
					'customer_id' => $user_id,
					'limit'       => 5,
				)
			);
			if ( ! is_wp_error( $my_orders ) && ! empty( $my_orders ) ) {
				$context_parts[] = 'YOUR RECENT ORDERS:';
				foreach ( $my_orders as $order ) {
					$tracking_info = '';
					if ( ! empty( $order['tracking'] ) ) {
						$tracking_info = ' - Tracking: ' . ( $order['tracking']['tracking_number'] ?? 'Available' );
					}
					$context_parts[] = sprintf(
						'- Order #%s: %s - %s (%s)%s',
						$order['number'] ?? $order['id'],
						$order['status_label'] ?? $order['status'],
						$order['total'] ?? 'N/A',
						$order['date_created'] ?? 'N/A',
						$tracking_info
					);
				}
				$context_parts[] = '';
			} else {
				$context_parts[] = "YOUR ORDERS: No orders found in your account.\n";
			}
		} else {
			$context_parts[] = "CUSTOMER STATUS: Guest (not logged in). To view order history, please log in.\n";
		}

		// Featured/popular products.
		$featured = $abilities->execute( 'afw/products/featured', array( 'limit' => 5 ) );
		if ( ! is_wp_error( $featured ) && ! empty( $featured ) ) {
			$context_parts[] = 'FEATURED PRODUCTS:';
			foreach ( $featured as $product ) {
				$context_parts[] = sprintf(
					'- %s: %s',
					$product['name'] ?? 'Unknown',
					$product['price_html'] ?? $product['price'] ?? 'N/A'
				);
			}
			$context_parts[] = '';
		}

		// Contact information.
		$store_email     = get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );
		$context_parts[] = 'CONTACT SUPPORT:';
		$context_parts[] = '- Email: ' . $store_email;
		$context_parts[] = '- For urgent issues, contact human support directly.';

		return implode( "\n", $context_parts );
	}
}
