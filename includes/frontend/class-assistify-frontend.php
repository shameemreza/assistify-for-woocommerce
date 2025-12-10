<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Frontend;

use Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class.
 *
 * @since 1.0.0
 */
class Assistify_Frontend {

	/**
	 * Session ID for the current chat.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $session_id = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor.
	}

	/**
	 * Handle customer chat AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_customer_chat() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page.', 'assistify-for-woocommerce' ) )
			);
		}

		// Check if customer chat is enabled.
		$enabled = get_option( 'assistify_customer_chat_enabled', 'no' );
		if ( 'yes' !== $enabled ) {
			wp_send_json_error(
				array( 'message' => __( 'Chat is currently unavailable.', 'assistify-for-woocommerce' ) )
			);
		}

		// Check guest access.
		if ( ! is_user_logged_in() ) {
			$guest_enabled = get_option( 'assistify_guest_chat_enabled', 'no' );
			if ( 'yes' !== $guest_enabled ) {
				wp_send_json_error(
					array( 'message' => __( 'Please log in to use the chat feature.', 'assistify-for-woocommerce' ) )
				);
			}
		}

		// Rate limiting.
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error(
				array( 'message' => $rate_check->get_error_message() )
			);
		}

		// Get message.
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( empty( $message ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter a message.', 'assistify-for-woocommerce' ) )
			);
		}

		// Get or create session.
		$this->session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		if ( empty( $this->session_id ) ) {
			$this->session_id = wp_generate_uuid4();
		}

		// Get chat history.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_history method.
		$history = isset( $_POST['history'] ) ? $this->sanitize_history( wp_unslash( $_POST['history'] ) ) : array();

		// Get AI provider.
		$provider = AI_Provider_Factory::get_configured_provider();
		if ( is_wp_error( $provider ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Chat service is currently unavailable. Please try again later.', 'assistify-for-woocommerce' ) )
			);
		}

		// Build messages array.
		$messages   = $history;
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Get system prompt.
		$system_prompt = $this->get_customer_system_prompt();

		// Get customer context if logged in.
		$context = $this->get_customer_context();
		if ( ! empty( $context ) ) {
			$system_prompt .= "\n\n" . $context;
		}

		// Send to AI.
		$response = $provider->chat(
			$messages,
			array(
				'system_prompt' => $system_prompt,
				'max_tokens'    => 1024,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Sorry, I couldn\'t process your request. Please try again.', 'assistify-for-woocommerce' ) )
			);
		}

		// Save messages to database.
		$this->save_message( $this->session_id, 'user', $message );
		$this->save_message( $this->session_id, 'assistant', $response['content'] );

		wp_send_json_success(
			array(
				'message'    => $response['content'],
				'session_id' => $this->session_id,
			)
		);
	}

	/**
	 * Check rate limit for customer chat.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error True if within limit, WP_Error otherwise.
	 */
	private function check_rate_limit() {
		$user_id = get_current_user_id();

		// Define limits.
		if ( $user_id > 0 ) {
			$limit    = 20;
			$cooldown = 60;
			$key      = 'user_' . $user_id;
		} else {
			$limit    = 5;
			$cooldown = 120;
			$ip       = $this->get_client_ip();
			$key      = 'guest_' . md5( $ip );
		}

		$transient_key = 'assistify_rate_' . md5( $key );
		$current       = get_transient( $transient_key );

		if ( false === $current ) {
			$current = 0;
		}

		if ( $current >= $limit ) {
			return new \WP_Error(
				'rate_limit',
				sprintf(
					/* translators: %d: seconds until rate limit resets. */
					__( 'You\'ve sent too many messages. Please wait %d seconds.', 'assistify-for-woocommerce' ),
					$cooldown
				)
			);
		}

		set_transient( $transient_key, $current + 1, $cooldown );

		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 * @return string Client IP.
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ip = explode( ',', $ip )[0];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return trim( $ip );
	}

	/**
	 * Sanitize chat history array.
	 *
	 * @since 1.0.0
	 * @param mixed $history Raw history data.
	 * @return array Sanitized history.
	 */
	private function sanitize_history( $history ) {
		if ( ! is_array( $history ) ) {
			return array();
		}

		$sanitized     = array();
		$allowed_roles = array( 'user', 'assistant' );

		foreach ( $history as $msg ) {
			if ( ! isset( $msg['role'] ) || ! isset( $msg['content'] ) ) {
				continue;
			}

			$role = sanitize_text_field( $msg['role'] );
			if ( ! in_array( $role, $allowed_roles, true ) ) {
				continue;
			}

			$sanitized[] = array(
				'role'    => $role,
				'content' => sanitize_textarea_field( $msg['content'] ),
			);
		}

		// Limit history to last 10 messages for customers.
		return array_slice( $sanitized, -10 );
	}

	/**
	 * Get system prompt for customer chat.
	 *
	 * @since 1.0.0
	 * @return string System prompt.
	 */
	private function get_customer_system_prompt() {
		$store_name = get_bloginfo( 'name' );
		$currency   = html_entity_decode( get_woocommerce_currency_symbol() );

		$prompt = sprintf(
			/* translators: %1$s: Store name, %2$s: Currency symbol. */
			__(
				'You are Ayana, a friendly AI customer support assistant for %1$s.

Your personality:
- Warm, helpful, and conversational
- Professional yet approachable
- Patient and understanding

Your capabilities:
- Answer product questions and give recommendations
- Help with order tracking (ask for order number if needed)
- Explain shipping options and delivery times
- Clarify return and refund policies
- Provide store information and contact details
- Share actual page/product links when customers ask

Important guidelines:
- Always introduce yourself as Ayana in your first response
- Be helpful, friendly, and SALES-FOCUSED - help customers find and buy products
- ALWAYS share direct product links when recommending products
- Format links as markdown: [Product Name](URL)
- When customer asks for products, recommend specific products WITH links
- If customer asks "show me hats", list the actual hat products with buy links
- Be enthusiastic about products - highlight features, prices, availability
- If a product is out of stock, suggest similar alternatives
- Use category links only when customer wants to browse entire category
- Never ask for sensitive info (passwords, payment details)
- For complex issues, suggest contacting human support
- Currency: %2$s

Use a friendly, conversational tone. Format with line breaks for readability.',
				'assistify-for-woocommerce'
			),
			$store_name,
			$currency
		);

		// Add store context.
		$store_context = $this->get_store_context();
		if ( ! empty( $store_context ) ) {
			$prompt .= "\n\n" . $store_context;
		}

		/**
		 * Filter the customer system prompt.
		 *
		 * @since 1.0.0
		 * @param string $prompt The system prompt.
		 */
		return apply_filters( 'assistify_customer_system_prompt', $prompt );
	}

	/**
	 * Get store context for customer AI.
	 *
	 * @since 1.0.0
	 * @return string Store context.
	 */
	private function get_store_context() {
		$context_parts = array();

		// Store info.
		$store_address = WC()->countries->get_base_address();
		$store_city    = WC()->countries->get_base_city();
		$store_country = WC()->countries->get_base_country();

		if ( $store_city || $store_country ) {
			$location = array_filter( array( $store_city, $store_country ) );
			/* translators: %s: Store location. */
			$context_parts[] = sprintf( __( 'Store location: %s', 'assistify-for-woocommerce' ), implode( ', ', $location ) );
		}

		// Shipping info.
		$shipping_zones = \WC_Shipping_Zones::get_zones();
		if ( ! empty( $shipping_zones ) ) {
			$zone_names = array();
			foreach ( array_slice( $shipping_zones, 0, 5 ) as $zone ) {
				$zone_names[] = $zone['zone_name'];
			}
			/* translators: %s: Shipping zones. */
			$context_parts[] = sprintf( __( 'Ships to: %s', 'assistify-for-woocommerce' ), implode( ', ', $zone_names ) );
		}

		// Payment methods.
		$gateways         = WC()->payment_gateways->get_available_payment_gateways();
		$gateway_names    = array();
		foreach ( $gateways as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				$gateway_names[] = $gateway->get_title();
			}
		}
		if ( ! empty( $gateway_names ) ) {
			/* translators: %s: Payment methods. */
			$context_parts[] = sprintf( __( 'Payment methods: %s', 'assistify-for-woocommerce' ), implode( ', ', $gateway_names ) );
		}

		// Get product limit from settings (default 100).
		$product_limit = absint( get_option( 'assistify_product_context_limit', 100 ) );
		if ( $product_limit < 10 ) {
			$product_limit = 100;
		}

		// Fetch products with URLs for AI context.
		$all_products = wc_get_products(
			array(
				'limit'   => $product_limit,
				'orderby' => 'title',
				'order'   => 'ASC',
				'status'  => 'publish',
			)
		);

		if ( ! empty( $all_products ) ) {
			$product_list = array();
			foreach ( $all_products as $product ) {
				$price       = html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ) );
				$product_url = $product->get_permalink();
				$stock       = $product->is_in_stock() ? 'In Stock' : 'Out of Stock';
				$categories  = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
				$cat_str     = ! empty( $categories ) ? implode( ', ', $categories ) : '';

				$product_list[] = sprintf(
					'- %s | %s | %s | %s | %s',
					$product->get_name(),
					$price,
					$stock,
					$cat_str,
					$product_url
				);
			}
			/* translators: %d: Number of products. */
			$context_parts[] = sprintf( __( 'Store Products (%d items - share direct links when asked):', 'assistify-for-woocommerce' ), count( $all_products ) ) . "\n" . implode( "\n", $product_list );
		}

		// Product categories with URLs (for browsing suggestions).
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$cat_info = array();
			foreach ( $categories as $cat ) {
				$cat_url    = get_term_link( $cat );
				$cat_info[] = sprintf( '- %s (%d) - %s', $cat->name, $cat->count, $cat_url );
			}
			$context_parts[] = __( 'Product Categories:', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $cat_info );
		}

		// Important page URLs (with actual links for AI to share).
		$important_pages = array();

		// Contact page.
		$contact_url = $this->get_contact_page_url();
		if ( $contact_url && home_url( '/' ) !== $contact_url ) {
			/* translators: %s: Contact page URL. */
			$important_pages[] = sprintf( __( 'Contact page: %s', 'assistify-for-woocommerce' ), $contact_url );
		}

		// Privacy policy.
		$privacy_page_id = get_option( 'wp_page_for_privacy_policy' );
		if ( $privacy_page_id > 0 ) {
			$privacy_url = get_permalink( $privacy_page_id );
			/* translators: %s: Privacy policy URL. */
			$important_pages[] = sprintf( __( 'Privacy policy: %s', 'assistify-for-woocommerce' ), $privacy_url );
		}

		// Terms & Conditions.
		$terms_page_id = wc_get_page_id( 'terms' );
		if ( $terms_page_id > 0 ) {
			$terms_url = get_permalink( $terms_page_id );
			/* translators: %s: Terms URL. */
			$important_pages[] = sprintf( __( 'Terms & Conditions: %s', 'assistify-for-woocommerce' ), $terms_url );
		}

		// Refund/Returns policy.
		$refund_page_id = wc_get_page_id( 'refund_returns' );
		if ( $refund_page_id > 0 ) {
			$refund_url = get_permalink( $refund_page_id );
			/* translators: %s: Refund policy URL. */
			$important_pages[] = sprintf( __( 'Returns & Refunds: %s', 'assistify-for-woocommerce' ), $refund_url );
		}

		// Shop page.
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( $shop_page_id > 0 ) {
			$shop_url = get_permalink( $shop_page_id );
			/* translators: %s: Shop URL. */
			$important_pages[] = sprintf( __( 'Shop/Products: %s', 'assistify-for-woocommerce' ), $shop_url );
		}

		// My Account page.
		$account_page_id = wc_get_page_id( 'myaccount' );
		if ( $account_page_id > 0 ) {
			$account_url = get_permalink( $account_page_id );
			/* translators: %s: Account URL. */
			$important_pages[] = sprintf( __( 'My Account: %s', 'assistify-for-woocommerce' ), $account_url );
		}

		// FAQ page (common slugs).
		$faq_slugs = array( 'faq', 'faqs', 'frequently-asked-questions', 'help' );
		foreach ( $faq_slugs as $slug ) {
			$faq_page = get_page_by_path( $slug );
			if ( $faq_page && 'publish' === $faq_page->post_status ) {
				/* translators: %s: FAQ URL. */
				$important_pages[] = sprintf( __( 'FAQ/Help: %s', 'assistify-for-woocommerce' ), get_permalink( $faq_page->ID ) );
				break;
			}
		}

		if ( ! empty( $important_pages ) ) {
			$context_parts[] = __( 'Important Links (share these when customers ask):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $important_pages );
		}

		if ( empty( $context_parts ) ) {
			return '';
		}

		return __( 'Store Information (use to answer customer questions):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $context_parts );
	}

	/**
	 * Get customer context for AI.
	 *
	 * @since 1.0.0
	 * @return string Customer context string.
	 */
	private function get_customer_context() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id  = get_current_user_id();
		$customer = new \WC_Customer( $user_id );

		$context_parts = array();

		// Basic customer info (non-sensitive).
		$first_name = $customer->get_first_name();
		if ( $first_name ) {
			$context_parts[] = sprintf(
				/* translators: %s: Customer first name. */
				__( 'Customer name: %s', 'assistify-for-woocommerce' ),
				$first_name
			);
		}

		// Order count.
		$order_count = $customer->get_order_count();
		if ( $order_count > 0 ) {
			$context_parts[] = sprintf(
				/* translators: %d: Number of orders. */
				__( 'Total orders: %d', 'assistify-for-woocommerce' ),
				$order_count
			);
		}

		// Recent orders (last 3).
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 3,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		if ( ! empty( $orders ) ) {
			$order_info = array();
			foreach ( $orders as $order ) {
				$order_info[] = sprintf(
					/* translators: %1$s: Order number, %2$s: Order status, %3$s: Order date. */
					__( 'Order #%1$s - %2$s (%3$s)', 'assistify-for-woocommerce' ),
					$order->get_order_number(),
					wc_get_order_status_name( $order->get_status() ),
					$order->get_date_created()->date_i18n( get_option( 'date_format' ) )
				);
			}
			$context_parts[] = __( 'Recent orders:', 'assistify-for-woocommerce' ) . "\n- " . implode( "\n- ", $order_info );
		}

		if ( empty( $context_parts ) ) {
			return '';
		}

		return __( 'Customer Information (use to personalize responses):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $context_parts );
	}

	/**
	 * Save message to database.
	 *
	 * @since 1.0.0
	 * @param string $session_id Session ID.
	 * @param string $role       Message role.
	 * @param string $content    Message content.
	 * @return bool Success status.
	 */
	private function save_message( $session_id, $role, $content ) {
		global $wpdb;

		$user_id = get_current_user_id();

		// Ensure session exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $wpdb->prefix . 'afw_sessions WHERE session_id = %s',
				$session_id
			)
		);

		if ( ! $session_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'afw_sessions',
				array(
					'session_id'    => $session_id,
					'user_id'       => $user_id,
					'context'       => 'customer',
					'started_at'    => current_time( 'mysql' ),
					'last_activity' => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'afw_sessions',
				array( 'last_activity' => current_time( 'mysql' ) ),
				array( 'session_id' => $session_id ),
				array( '%s' ),
				array( '%s' )
			);
		}

		// Insert message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->insert(
			$wpdb->prefix . 'afw_messages',
			array(
				'session_id' => $session_id,
				'role'       => $role,
				'content'    => $content,
				'context'    => 'customer',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Check if the chat widget should be displayed.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function should_display_chat() {
		// Check if customer chat is enabled.
		$customer_chat_enabled = get_option( 'assistify_customer_chat_enabled', 'no' );

		if ( 'yes' !== $customer_chat_enabled ) {
			return false;
		}

		// Check if guest chat is allowed for non-logged-in users.
		if ( ! is_user_logged_in() ) {
			$guest_chat_enabled = get_option( 'assistify_guest_chat_enabled', 'no' );
			if ( 'yes' !== $guest_chat_enabled ) {
				return false;
			}
		}

		// Allow filtering of chat display.
		return apply_filters( 'assistify_display_chat_widget', true );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! $this->should_display_chat() ) {
			return;
		}

		wp_enqueue_style(
			'assistify-frontend',
			ASSISTIFY_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ASSISTIFY_VERSION,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_display_chat() ) {
			return;
		}

		wp_enqueue_script(
			'assistify-frontend',
			ASSISTIFY_PLUGIN_URL . 'assets/js/frontend/frontend.js',
			array( 'jquery' ),
			ASSISTIFY_VERSION,
			true
		);

		// Get user info for personalization.
		$user_first_name = '';
		if ( is_user_logged_in() ) {
			$current_user    = wp_get_current_user();
			$user_first_name = $current_user->first_name;
		}

		// Detect page context for smart FAQs.
		$page_context = $this->get_page_context();

		// Check API health status.
		$api_status = $this->check_api_health();

		// Get assistant name from settings.
		$assistant_name = get_option( 'assistify_assistant_name', 'Ayana' );
		if ( empty( $assistant_name ) ) {
			$assistant_name = 'Ayana';
		}

		// Get contact page URL.
		$contact_page_url = $this->get_contact_page_url();

		wp_localize_script(
			'assistify-frontend',
			'assistifyFrontend',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'assistify_frontend_nonce' ),
				'isLoggedIn'     => is_user_logged_in(),
				'userFirstName'  => esc_html( $user_first_name ),
				'storeName'      => esc_html( get_bloginfo( 'name' ) ),
				'pageContext'    => $page_context,
				'apiStatus'      => $api_status,
				'contactPageUrl' => $contact_page_url,
				'strings'        => array(
					'error'          => esc_html__( 'An error occurred. Please try again.', 'assistify-for-woocommerce' ),
					'loading'        => esc_html__( 'Loading...', 'assistify-for-woocommerce' ),
					'placeholder'    => esc_html__( 'Type your message...', 'assistify-for-woocommerce' ),
					'send'           => esc_html__( 'Send', 'assistify-for-woocommerce' ),
					'chatTitle'      => esc_html__( 'Chat with us', 'assistify-for-woocommerce' ),
					'assistantName'  => esc_html( $assistant_name ),
					'consentTitle'   => esc_html__( 'Privacy Notice', 'assistify-for-woocommerce' ),
					'consentText'    => esc_html__( 'This chat is powered by AI. By continuing, you agree that your messages will be processed to provide responses.', 'assistify-for-woocommerce' ),
					'consentAgree'   => esc_html__( 'I Agree', 'assistify-for-woocommerce' ),
					'consentDecline' => esc_html__( 'No Thanks', 'assistify-for-woocommerce' ),
					'proactiveMsg'   => __( 'Need any help? I\'m here if you have questions!', 'assistify-for-woocommerce' ),
					'offlineMsg'     => esc_html__( 'I\'m currently offline. Please contact us directly for assistance.', 'assistify-for-woocommerce' ),
					'contactLink'    => esc_html__( 'Contact Us', 'assistify-for-woocommerce' ),
				),
				'settings'       => array(
					'position'        => get_option( 'assistify_chat_position', 'bottom-right' ),
					'primaryColor'    => get_option( 'assistify_primary_color', '#7F54B3' ),
					'autoOpenDelay'   => absint( get_option( 'assistify_auto_open_delay', 90 ) ), // seconds
					'autoOpenEnabled' => get_option( 'assistify_auto_open_enabled', 'no' ) === 'yes',
					'soundEnabled'    => get_option( 'assistify_sound_enabled', 'yes' ) === 'yes',
					'customSoundUrl'  => esc_url( get_option( 'assistify_custom_sound_url', '' ) ),
				),
			)
		);
	}

	/**
	 * Check if the API is properly configured and healthy.
	 *
	 * @since 1.0.0
	 * @return array API status.
	 */
	private function check_api_health() {
		$api_key  = get_option( 'assistify_api_key', '' );
		$provider = get_option( 'assistify_ai_provider', 'openai' );

		$status = array(
			'online'   => false,
			'provider' => $provider,
		);

		// Check if API key is configured.
		if ( empty( $api_key ) ) {
			return $status;
		}

		// Check if provider is valid.
		$valid_providers = array( 'openai', 'anthropic', 'google', 'xai', 'deepseek' );
		if ( ! in_array( $provider, $valid_providers, true ) ) {
			return $status;
		}

		// API key exists and provider is valid - consider it online.
		// Skip API verification calls on page load for performance.
		$status['online'] = true;

		return $status;
	}

	/**
	 * Get contact page URL.
	 *
	 * @since 1.0.0
	 * @return string Contact page URL.
	 */
	private function get_contact_page_url() {
		// Try to find contact page by common slugs.
		$contact_slugs = array( 'contact', 'contact-us', 'get-in-touch', 'reach-us' );

		foreach ( $contact_slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				return get_permalink( $page->ID );
			}
		}

		// Try to find by title using WP_Query (get_page_by_title deprecated in WP 6.2).
		$contact_titles = array( 'Contact', 'Contact Us', 'Get in Touch' );

		foreach ( $contact_titles as $title ) {
			$query = new \WP_Query(
				array(
					'post_type'              => 'page',
					'title'                  => $title,
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
				)
			);

			if ( $query->have_posts() ) {
				return get_permalink( $query->posts[0]->ID );
			}
		}

		// Fallback to home page.
		return home_url( '/' );
	}

	/**
	 * Get current page context for smart FAQs.
	 *
	 * @since 1.0.0
	 * @return array Page context data.
	 */
	private function get_page_context() {
		$context = array(
			'type'        => 'other',
			'productName' => '',
			'productId'   => 0,
			'categoryName' => '',
			'isCart'      => false,
			'isCheckout'  => false,
			'isAccount'   => false,
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			$context['type'] = 'product';
			$product         = wc_get_product( get_the_ID() );
			if ( $product ) {
				$context['productName'] = $product->get_name();
				$context['productId']   = $product->get_id();
			}
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$context['type'] = 'category';
			$term            = get_queried_object();
			if ( $term ) {
				$context['categoryName'] = $term->name;
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$context['type'] = 'shop';
		} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
			$context['type']   = 'cart';
			$context['isCart'] = true;
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$context['type']       = 'checkout';
			$context['isCheckout'] = true;
		} elseif ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$context['type']      = 'account';
			$context['isAccount'] = true;
		}

		return $context;
	}

	/**
	 * Render the chat widget in the footer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_chat_widget() {
		if ( ! $this->should_display_chat() ) {
			return;
		}

		$position = get_option( 'assistify_chat_position', 'bottom-right' );
		?>
		<div id="assistify-chat-widget" class="assistify-chat-widget assistify-position-<?php echo esc_attr( $position ); ?>" aria-label="<?php esc_attr_e( 'Chat widget', 'assistify-for-woocommerce' ); ?>">
			<!-- Chat toggle button -->
			<button 
				type="button" 
				id="assistify-chat-toggle" 
				class="assistify-chat-toggle"
				aria-expanded="false"
				aria-controls="assistify-chat-container"
				aria-label="<?php esc_attr_e( 'Open chat', 'assistify-for-woocommerce' ); ?>"
			>
				<span class="assistify-chat-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
						<path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.36 5.07L2 22l4.93-1.36C8.42 21.5 10.15 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.57 0-3.05-.43-4.32-1.18l-.31-.18-3.22.89.89-3.22-.18-.31C4.43 15.05 4 13.57 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8z"/>
					</svg>
				</span>
				<span class="assistify-close-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
						<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
					</svg>
				</span>
			</button>

			<!-- Chat container -->
			<div id="assistify-chat-container" class="assistify-chat-container" hidden>
				<div class="assistify-chat-header">
					<h3 class="assistify-chat-title"><?php esc_html_e( 'Chat with us', 'assistify-for-woocommerce' ); ?></h3>
					<button 
						type="button" 
						class="assistify-chat-minimize" 
						aria-label="<?php esc_attr_e( 'Minimize chat', 'assistify-for-woocommerce' ); ?>"
					>
						<span aria-hidden="true">−</span>
					</button>
				</div>

				<div class="assistify-chat-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Chat messages', 'assistify-for-woocommerce' ); ?>">
					<!-- Messages will be inserted here via JavaScript -->
				</div>

				<form class="assistify-chat-form" id="assistify-chat-form">
					<label for="assistify-chat-input" class="screen-reader-text">
						<?php esc_html_e( 'Type your message', 'assistify-for-woocommerce' ); ?>
					</label>
					<input 
						type="text" 
						id="assistify-chat-input" 
						class="assistify-chat-input" 
						placeholder="<?php esc_attr_e( 'Type your message...', 'assistify-for-woocommerce' ); ?>"
						autocomplete="off"
					/>
					<button 
						type="submit" 
						class="assistify-chat-send"
						aria-label="<?php esc_attr_e( 'Send message', 'assistify-for-woocommerce' ); ?>"
					>
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
							<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
						</svg>
					</button>
				</form>
			</div>
		</div>
		<?php
	}
}

