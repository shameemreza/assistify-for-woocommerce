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
		$messages = $history;
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
		$messages = $history;
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

		$prompt = sprintf(
			/* translators: %1$s: Store name, %2$s: Currency code. */
			__(
				'You are an AI assistant for %1$s, a WooCommerce store. You help store administrators manage their store efficiently.

Your capabilities include:
- Providing information about orders, products, and customers
- Helping with store analytics and insights
- Assisting with content generation for products
- Offering recommendations for store improvements

Store currency: %2$s

Guidelines:
- Be concise and professional
- Always prioritize accuracy over speed
- If you don\'t know something, say so
- Never make up order numbers or customer information
- Format responses clearly with line breaks for readability',
				'assistify-for-woocommerce'
			),
			$store_name,
			$currency
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
	 * Get system prompt for customer context.
	 *
	 * @since 1.0.0
	 * @return string System prompt.
	 */
	private function get_customer_system_prompt() {
		$store_name = get_bloginfo( 'name' );

		$prompt = sprintf(
			/* translators: %s: Store name. */
			__(
				'You are a friendly customer support assistant for %s.

Your capabilities include:
- Answering questions about products
- Helping customers track their orders
- Providing information about shipping and returns
- Assisting with general store inquiries

Guidelines:
- Be friendly, helpful, and concise
- If you can\'t help with something, suggest contacting human support
- Never share sensitive customer information
- Don\'t process payments or make account changes
- Keep responses brief and easy to understand',
				'assistify-for-woocommerce'
			),
			$store_name
		);

		/**
		 * Filter the customer system prompt.
		 *
		 * @since 1.0.0
		 * @param string $prompt The system prompt.
		 */
		return apply_filters( 'assistify_customer_system_prompt', $prompt );
	}
}

