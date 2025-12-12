<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Frontend;

use Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory;
use Assistify_For_WooCommerce\Assistify_Privacy;

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

		// Get store context (coupons, sales, featured - available to all).
		$store_context = $this->get_store_context();
		if ( ! empty( $store_context ) ) {
			$system_prompt .= "\n\n" . $store_context;
		}

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
- Help with order tracking for logged-in customers
- Explain shipping options and delivery times
- Clarify return and refund policies
- Provide store information and contact details
- Share actual page/product links when customers ask
- Share available coupon codes and current sales
- Recommend featured products

CRITICAL RULES - YOU MUST FOLLOW THESE:
1. NEVER make up or invent order data, tracking numbers, or any information
2. ONLY use information explicitly provided in the "Customer Information" section below
3. If no order data is provided, tell the customer they have no orders
4. If a customer asks about orders but none are listed, say: "I don\'t see any orders in your account."
5. Always introduce yourself as Ayana in your first response

ORDER TRACKING (for logged-in customers):
- ONLY mention orders that are explicitly listed in the Customer Information below
- If no orders are listed, clearly state: "You don\'t have any orders yet."
- Share order status, items, and tracking ONLY if that data is provided
- Use the exact "View details" URL provided for each order
- DO NOT invent order numbers, tracking numbers, or product names

PRODUCT RECOMMENDATIONS:
- When customer asks for products, recommend specific products WITH links
- ONLY recommend products from the Store Information provided below
- Be enthusiastic about products - highlight features, prices, availability
- If a product is out of stock, suggest similar alternatives from the list

COUPON CODES (IMPORTANT):
- When customer asks about discounts, coupons, promo codes, or deals:
- Share the EXACT coupon codes from "AVAILABLE COUPON CODES" section below
- Display codes prominently like: **Use code `COUPONCODE`** (in backticks for easy copying)
- Include the discount amount and any minimum spend requirements
- If no coupons are available, say "We don\'t have any coupon codes available right now, but check back soon!"

SALE ITEMS:
- When customer asks what\'s on sale, share products from "PRODUCTS ON SALE" section
- Include the original price, sale price, and discount percentage
- Always include product links so they can buy directly

GENERAL:
- Be helpful, friendly, and SALES-FOCUSED
- Format links as markdown: [Product Name](URL)
- Never ask for sensitive info (passwords, payment details)
- For complex issues (refunds, payment problems), suggest contacting human support
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
		$gateways      = WC()->payment_gateways->get_available_payment_gateways();
		$gateway_names = array();
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

		// Available coupons (public only).
		$coupons_info = $this->get_available_coupons_context();
		if ( $coupons_info ) {
			$context_parts[] = $coupons_info;
		}

		// Products on sale.
		$sale_info = $this->get_products_on_sale_context();
		if ( $sale_info ) {
			$context_parts[] = $sale_info;
		}

		// Featured products.
		$featured_info = $this->get_featured_products_context();
		if ( $featured_info ) {
			$context_parts[] = $featured_info;
		}

		if ( empty( $context_parts ) ) {
			return '';
		}

		return __( 'Store Information (use to answer customer questions):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $context_parts );
	}

	/**
	 * Get customer context for AI.
	 *
	 * Provides detailed customer data including order history for personalized assistance.
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

		// Total orders and total spent.
		$order_count = $customer->get_order_count();
		$total_spent = $customer->get_total_spent();

		// Customer's detailed order history (last 10 orders for tracking).
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		// IMPORTANT: Explicitly state order status to prevent AI hallucination.
		if ( $order_count > 0 && ! empty( $orders ) ) {
			$context_parts[] = sprintf(
				/* translators: %1$d: Order count, %2$s: Total spent. */
				__( 'CUSTOMER ORDER HISTORY: Customer has placed %1$d orders (total spent: %2$s)', 'assistify-for-woocommerce' ),
				$order_count,
				html_entity_decode( wp_strip_all_tags( wc_price( $total_spent ) ) )
			);

			$order_details = array();
			foreach ( $orders as $order ) {
				$order_data      = $this->format_order_for_customer( $order );
				$order_details[] = $order_data;
			}
			$context_parts[] = __( 'ORDERS LIST (ONLY mention these orders, do NOT invent any):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $order_details );
		} else {
			// CRITICAL: Explicitly state NO orders to prevent AI from making up data.
			$context_parts[] = __( 'CUSTOMER ORDER HISTORY: This customer has NO orders. They have not placed any orders yet. Do NOT mention any orders or tracking information.', 'assistify-for-woocommerce' );
		}

		// Customer's downloadable products.
		$downloads = $this->get_customer_downloads( $customer );
		if ( $downloads ) {
			$context_parts[] = $downloads;
		}

		// Customer's saved addresses.
		$addresses = $this->get_customer_addresses( $customer );
		if ( $addresses ) {
			$context_parts[] = $addresses;
		}

		// Current cart contents (if any).
		$cart_info = $this->get_cart_context();
		if ( $cart_info ) {
			$context_parts[] = $cart_info;
		}

		// Shipping information for customer.
		$shipping_info = $this->get_shipping_info_for_customer( $customer );
		if ( $shipping_info ) {
			$context_parts[] = $shipping_info;
		}

		// Store contact information.
		$contact_info = $this->get_store_contact_info();
		if ( $contact_info ) {
			$context_parts[] = $contact_info;
		}

		// My Account URLs for self-service actions.
		$account_url = wc_get_page_permalink( 'myaccount' );
		if ( $account_url ) {
			$account_pages = array();
			/* translators: %s: Dashboard URL. */
			$account_pages[] = sprintf( __( 'Dashboard: %s', 'assistify-for-woocommerce' ), $account_url );
			/* translators: %s: Orders URL. */
			$account_pages[] = sprintf( __( 'Orders: %s', 'assistify-for-woocommerce' ), wc_get_account_endpoint_url( 'orders' ) );
			/* translators: %s: Downloads URL. */
			$account_pages[] = sprintf( __( 'Downloads: %s', 'assistify-for-woocommerce' ), wc_get_account_endpoint_url( 'downloads' ) );
			/* translators: %s: Addresses URL. */
			$account_pages[] = sprintf( __( 'Addresses: %s', 'assistify-for-woocommerce' ), wc_get_account_endpoint_url( 'edit-address' ) );
			/* translators: %s: Payment methods URL. */
			$account_pages[] = sprintf( __( 'Payment Methods: %s', 'assistify-for-woocommerce' ), wc_get_account_endpoint_url( 'payment-methods' ) );
			/* translators: %s: Account details URL. */
			$account_pages[] = sprintf( __( 'Account Details: %s', 'assistify-for-woocommerce' ), wc_get_account_endpoint_url( 'edit-account' ) );

			$context_parts[] = __( 'MY ACCOUNT PAGES (share these links when customer asks):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $account_pages );
		}

		if ( empty( $context_parts ) ) {
			return __( 'CUSTOMER DATA: No customer data available. Customer is not logged in or no data found.', 'assistify-for-woocommerce' );
		}

		return __( 'CUSTOMER INFORMATION - USE ONLY THIS DATA (do NOT invent or assume anything not listed here):', 'assistify-for-woocommerce' ) . "\n\n" . implode( "\n\n", $context_parts );
	}

	/**
	 * Format order data for customer context.
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return string Formatted order info.
	 */
	private function format_order_for_customer( $order ) {
		$order_number = $order->get_order_number();
		$status       = wc_get_order_status_name( $order->get_status() );
		$date         = $order->get_date_created()->date_i18n( get_option( 'date_format' ) );
		$total        = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ) );

		// Get items.
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ' x' . $item->get_quantity();
		}
		$items_str = implode( ', ', array_slice( $items, 0, 3 ) );
		if ( count( $items ) > 3 ) {
			$items_str .= sprintf(
				/* translators: %d: Number of additional items. */
				__( ' + %d more items', 'assistify-for-woocommerce' ),
				count( $items ) - 3
			);
		}

		// Shipping info.
		$shipping_info = '';
		if ( $order->has_shipping_address() ) {
			$shipping_method = $order->get_shipping_method();
			$shipping_info   = $shipping_method ? $shipping_method : __( 'Standard shipping', 'assistify-for-woocommerce' );
		}

		// Payment method info.
		$payment_info = $this->get_order_payment_info( $order );

		// Tracking info (if available from popular tracking plugins).
		$tracking_info = $this->get_order_tracking_info( $order );

		// Build order string.
		$order_str = sprintf(
			"- Order #%s | Status: %s | Date: %s | Total: %s\n  Items: %s",
			$order_number,
			$status,
			$date,
			$total,
			$items_str
		);

		if ( $shipping_info ) {
			$order_str .= "\n  Shipping: " . $shipping_info;
		}

		// Add payment info.
		if ( $payment_info ) {
			$order_str .= "\n  " . $payment_info;
		}

		if ( $tracking_info ) {
			$order_str .= "\n  Tracking: " . $tracking_info;
		}

		// Estimated delivery for processing/shipped orders.
		if ( in_array( $order->get_status(), array( 'processing', 'shipped', 'on-hold' ), true ) ) {
			$order_str .= "\n  " . $this->get_estimated_delivery( $order );
		}

		// View order URL.
		$view_url = $order->get_view_order_url();
		if ( $view_url ) {
			$order_str .= sprintf( "\n  View details: %s", $view_url );
		}

		return $order_str;
	}

	/**
	 * Get payment method info for an order.
	 *
	 * Includes payment method title and instructions for pending payments.
	 * For bank transfer (BACS), includes bank account details.
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return string Payment info string.
	 */
	private function get_order_payment_info( $order ) {
		$payment_method = $order->get_payment_method();
		$payment_title  = $order->get_payment_method_title();
		$order_status   = $order->get_status();

		if ( empty( $payment_method ) ) {
			return '';
		}

		$info = sprintf(
			/* translators: %s: Payment method title. */
			__( 'Payment Method: %s', 'assistify-for-woocommerce' ),
			$payment_title
		);

		// For on-hold or pending orders, add payment instructions.
		if ( in_array( $order_status, array( 'on-hold', 'pending' ), true ) ) {
			$info .= $this->get_payment_instructions( $payment_method, $order );
		}

		return $info;
	}

	/**
	 * Get payment instructions based on payment method.
	 *
	 * @since 1.0.0
	 * @param string    $payment_method Payment method ID.
	 * @param \WC_Order $order          Order object.
	 * @return string Payment instructions.
	 */
	private function get_payment_instructions( $payment_method, $order ) {
		$instructions = '';

		switch ( $payment_method ) {
			case 'bacs':
				// Bank transfer - include bank account details.
				$instructions .= "\n  " . __( 'PAYMENT REQUIRED: Please make a bank transfer to complete your order.', 'assistify-for-woocommerce' );
				$bacs_info     = $this->get_bacs_account_details();
				if ( $bacs_info ) {
					$instructions .= "\n  " . $bacs_info;
				}
				/* translators: %s: Order number. */
				$instructions .= "\n  " . sprintf( __( 'Use Order #%s as payment reference.', 'assistify-for-woocommerce' ), $order->get_order_number() );
				break;

			case 'cheque':
				// Check payment.
				$instructions .= "\n  " . __( 'PAYMENT REQUIRED: Please send a check to complete your order.', 'assistify-for-woocommerce' );
				$check_info    = $this->get_check_payment_details();
				if ( $check_info ) {
					$instructions .= "\n  " . $check_info;
				}
				break;

			case 'cod':
				// Cash on delivery.
				$instructions .= "\n  " . __( 'Payment will be collected upon delivery.', 'assistify-for-woocommerce' );
				break;

			case 'paypal':
			case 'ppcp-gateway':
				// PayPal.
				if ( 'pending' === $order->get_status() ) {
					$instructions .= "\n  " . __( 'Your PayPal payment is pending. Please check your PayPal account.', 'assistify-for-woocommerce' );
				}
				break;

			default:
				// Generic message for other payment methods.
				if ( 'on-hold' === $order->get_status() ) {
					$instructions .= "\n  " . __( 'Your order is awaiting payment confirmation.', 'assistify-for-woocommerce' );
				}
				break;
		}

		return $instructions;
	}

	/**
	 * Get BACS (Bank Transfer) account details.
	 *
	 * @since 1.0.0
	 * @return string Bank account details formatted for AI context.
	 */
	private function get_bacs_account_details() {
		$bacs_accounts = get_option( 'woocommerce_bacs_accounts', array() );

		if ( empty( $bacs_accounts ) || ! is_array( $bacs_accounts ) ) {
			return '';
		}

		$details = array();
		foreach ( $bacs_accounts as $account ) {
			$account_info = array();

			if ( ! empty( $account['bank_name'] ) ) {
				/* translators: %s: Bank name. */
				$account_info[] = sprintf( __( 'Bank: %s', 'assistify-for-woocommerce' ), $account['bank_name'] );
			}
			if ( ! empty( $account['account_name'] ) ) {
				/* translators: %s: Account holder name. */
				$account_info[] = sprintf( __( 'Account Name: %s', 'assistify-for-woocommerce' ), $account['account_name'] );
			}
			if ( ! empty( $account['account_number'] ) ) {
				/* translators: %s: Account number. */
				$account_info[] = sprintf( __( 'Account Number: %s', 'assistify-for-woocommerce' ), $account['account_number'] );
			}
			if ( ! empty( $account['sort_code'] ) ) {
				/* translators: %s: Sort code/routing number. */
				$account_info[] = sprintf( __( 'Sort Code: %s', 'assistify-for-woocommerce' ), $account['sort_code'] );
			}
			if ( ! empty( $account['iban'] ) ) {
				/* translators: %s: IBAN. */
				$account_info[] = sprintf( __( 'IBAN: %s', 'assistify-for-woocommerce' ), $account['iban'] );
			}
			if ( ! empty( $account['bic'] ) ) {
				/* translators: %s: BIC/SWIFT. */
				$account_info[] = sprintf( __( 'BIC/SWIFT: %s', 'assistify-for-woocommerce' ), $account['bic'] );
			}

			if ( ! empty( $account_info ) ) {
				$details[] = implode( ' | ', $account_info );
			}
		}

		if ( empty( $details ) ) {
			return '';
		}

		return __( 'Bank Account Details:', 'assistify-for-woocommerce' ) . ' ' . implode( '; ', $details );
	}

	/**
	 * Get check payment details.
	 *
	 * @since 1.0.0
	 * @return string Check payment mailing address.
	 */
	private function get_check_payment_details() {
		$cheque_settings = get_option( 'woocommerce_cheque_settings', array() );

		if ( ! empty( $cheque_settings['instructions'] ) ) {
			// Get the first line or brief version of instructions.
			$instructions = wp_strip_all_tags( $cheque_settings['instructions'] );
			// Limit to reasonable length.
			if ( strlen( $instructions ) > 200 ) {
				$instructions = substr( $instructions, 0, 200 ) . '...';
			}
			return $instructions;
		}

		// Fall back to store address.
		$store_address   = array();
		$store_address[] = get_option( 'woocommerce_store_address', '' );
		$store_address[] = get_option( 'woocommerce_store_address_2', '' );
		$store_address[] = get_option( 'woocommerce_store_city', '' );
		$store_address[] = get_option( 'woocommerce_store_postcode', '' );

		$store_address = array_filter( $store_address );

		if ( ! empty( $store_address ) ) {
			/* translators: %s: Store address. */
			return sprintf( __( 'Mail check to: %s', 'assistify-for-woocommerce' ), implode( ', ', $store_address ) );
		}

		return '';
	}

	/**
	 * Get customer's downloadable products.
	 *
	 * @since 1.0.0
	 * @param \WC_Customer $customer Customer object.
	 * @return string Downloadable products info or empty string.
	 */
	private function get_customer_downloads( $customer ) {
		$downloads = $customer->get_downloadable_products();

		if ( empty( $downloads ) ) {
			return '';
		}

		$download_list = array();
		$count         = 0;

		foreach ( $downloads as $download ) {
			if ( $count >= 10 ) {
				break; // Limit to 10 downloads.
			}

			$download_info = $download['product_name'];

			// Add download link if available.
			if ( ! empty( $download['download_url'] ) ) {
				/* translators: %s: Download URL. */
				$download_info .= sprintf( __( ' - Download: %s', 'assistify-for-woocommerce' ), $download['download_url'] );
			}

			// Add expiry info.
			if ( ! empty( $download['access_expires'] ) && 'never' !== $download['access_expires'] ) {
				/* translators: %s: Expiry date. */
				$download_info .= sprintf( __( ' (Expires: %s)', 'assistify-for-woocommerce' ), $download['access_expires'] );
			}

			// Add remaining downloads.
			if ( ! empty( $download['downloads_remaining'] ) && 'unlimited' !== strtolower( $download['downloads_remaining'] ) ) {
				/* translators: %s: Number of downloads remaining. */
				$download_info .= sprintf( __( ' [%s downloads left]', 'assistify-for-woocommerce' ), $download['downloads_remaining'] );
			}

			$download_list[] = '- ' . $download_info;
			++$count;
		}

		if ( empty( $download_list ) ) {
			return '';
		}

		$header = __( 'CUSTOMER DOWNLOADS (digital products they purchased):', 'assistify-for-woocommerce' );
		return $header . "\n" . implode( "\n", $download_list );
	}

	/**
	 * Get customer's saved addresses.
	 *
	 * @since 1.0.0
	 * @param \WC_Customer $customer Customer object.
	 * @return string Addresses info or empty string.
	 */
	private function get_customer_addresses( $customer ) {
		$addresses = array();

		// Billing address.
		$billing = $customer->get_billing();
		if ( ! empty( $billing['address_1'] ) || ! empty( $billing['city'] ) ) {
			$billing_parts = array_filter(
				array(
					$billing['first_name'] . ' ' . $billing['last_name'],
					$billing['address_1'],
					$billing['address_2'],
					$billing['city'],
					$billing['state'],
					$billing['postcode'],
					$billing['country'],
				)
			);
			if ( ! empty( $billing_parts ) ) {
				$addresses[] = __( 'Billing Address:', 'assistify-for-woocommerce' ) . ' ' . implode( ', ', $billing_parts );
			}
		}

		// Shipping address.
		$shipping = $customer->get_shipping();
		if ( ! empty( $shipping['address_1'] ) || ! empty( $shipping['city'] ) ) {
			$shipping_parts = array_filter(
				array(
					$shipping['first_name'] . ' ' . $shipping['last_name'],
					$shipping['address_1'],
					$shipping['address_2'],
					$shipping['city'],
					$shipping['state'],
					$shipping['postcode'],
					$shipping['country'],
				)
			);
			if ( ! empty( $shipping_parts ) ) {
				$addresses[] = __( 'Shipping Address:', 'assistify-for-woocommerce' ) . ' ' . implode( ', ', $shipping_parts );
			}
		}

		if ( empty( $addresses ) ) {
			return '';
		}

		$header = __( 'SAVED ADDRESSES:', 'assistify-for-woocommerce' );
		return $header . "\n" . implode( "\n", $addresses );
	}

	/**
	 * Get current cart context.
	 *
	 * @since 1.0.0
	 * @return string Cart info or empty string.
	 */
	private function get_cart_context() {
		// Ensure cart is loaded.
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$cart = WC()->cart;

		if ( $cart->is_empty() ) {
			return __( 'CART: Customer\'s cart is currently empty.', 'assistify-for-woocommerce' );
		}

		$cart_items = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product ) {
				$item_info    = sprintf(
					'%s x%d - %s',
					$product->get_name(),
					$cart_item['quantity'],
					html_entity_decode( wp_strip_all_tags( wc_price( $cart_item['line_total'] ) ) )
				);
				$cart_items[] = '- ' . $item_info;
			}
		}

		$cart_info   = array();
		$cart_info[] = __( 'CURRENT CART:', 'assistify-for-woocommerce' );
		$cart_info[] = implode( "\n", $cart_items );

		// Cart totals.
		/* translators: %s: Cart subtotal. */
		$cart_info[] = sprintf( __( 'Subtotal: %s', 'assistify-for-woocommerce' ), html_entity_decode( wp_strip_all_tags( $cart->get_cart_subtotal() ) ) );

		// Applied coupons.
		$coupons = $cart->get_applied_coupons();
		if ( ! empty( $coupons ) ) {
			/* translators: %s: Applied coupon codes. */
			$cart_info[] = sprintf( __( 'Applied Coupons: %s', 'assistify-for-woocommerce' ), implode( ', ', $coupons ) );
		}

		// Total.
		/* translators: %s: Cart total. */
		$cart_info[] = sprintf( __( 'Total: %s', 'assistify-for-woocommerce' ), html_entity_decode( wp_strip_all_tags( wc_price( $cart->get_total( 'edit' ) ) ) ) );

		// Cart and checkout URLs.
		$cart_url     = wc_get_cart_url();
		$checkout_url = wc_get_checkout_url();
		/* translators: %s: Cart URL. */
		$cart_info[] = sprintf( __( 'View Cart: %s', 'assistify-for-woocommerce' ), $cart_url );
		/* translators: %s: Checkout URL. */
		$cart_info[] = sprintf( __( 'Checkout: %s', 'assistify-for-woocommerce' ), $checkout_url );

		return implode( "\n", $cart_info );
	}

	/**
	 * Get store contact information for customer context.
	 *
	 * @since 1.0.0
	 * @return string Store contact info.
	 */
	private function get_store_contact_info() {
		$contact = array();

		// Store name.
		$store_name = get_bloginfo( 'name' );
		if ( $store_name ) {
			/* translators: %s: Store name. */
			$contact[] = sprintf( __( 'Store: %s', 'assistify-for-woocommerce' ), $store_name );
		}

		// Store email.
		$store_email = get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );
		if ( $store_email ) {
			/* translators: %s: Store email. */
			$contact[] = sprintf( __( 'Email: %s', 'assistify-for-woocommerce' ), $store_email );
		}

		// Store address.
		$address_parts = array_filter(
			array(
				get_option( 'woocommerce_store_address', '' ),
				get_option( 'woocommerce_store_city', '' ),
				get_option( 'woocommerce_store_postcode', '' ),
				WC()->countries->get_base_country(),
			)
		);
		if ( ! empty( $address_parts ) ) {
			/* translators: %s: Store address. */
			$contact[] = sprintf( __( 'Address: %s', 'assistify-for-woocommerce' ), implode( ', ', $address_parts ) );
		}

		// Contact page if exists.
		$contact_page = get_page_by_path( 'contact' );
		if ( ! $contact_page ) {
			$contact_page = get_page_by_path( 'contact-us' );
		}
		if ( $contact_page ) {
			/* translators: %s: Contact page URL. */
			$contact[] = sprintf( __( 'Contact Page: %s', 'assistify-for-woocommerce' ), get_permalink( $contact_page ) );
		}

		// Phone if set in customizer or option.
		$phone = get_option( 'woocommerce_store_phone', '' );
		if ( empty( $phone ) ) {
			$phone = get_theme_mod( 'phone_number', '' );
		}
		if ( $phone ) {
			/* translators: %s: Store phone number. */
			$contact[] = sprintf( __( 'Phone: %s', 'assistify-for-woocommerce' ), $phone );
		}

		if ( empty( $contact ) ) {
			return '';
		}

		return __( 'STORE CONTACT INFORMATION:', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $contact );
	}

	/**
	 * Get shipping information for customer context.
	 *
	 * @since 1.0.0
	 * @param \WC_Customer $customer Customer object.
	 * @return string Shipping info.
	 */
	private function get_shipping_info_for_customer( $customer ) {
		$info = array();

		// Get customer's shipping country.
		$country = $customer->get_shipping_country();
		if ( empty( $country ) ) {
			$country = $customer->get_billing_country();
		}
		if ( empty( $country ) ) {
			$country = WC()->countries->get_base_country();
		}

		// Get shipping zones.
		$shipping_zones = \WC_Shipping_Zones::get_zones();
		$customer_zone  = null;

		// Find matching zone for customer.
		foreach ( $shipping_zones as $zone_data ) {
			$zone = new \WC_Shipping_Zone( $zone_data['id'] );
			foreach ( $zone->get_zone_locations() as $location ) {
				if ( 'country' === $location->type && $location->code === $country ) {
					$customer_zone = $zone;
					break 2;
				}
			}
		}

		// Fall back to "Rest of the World" zone.
		if ( ! $customer_zone ) {
			$customer_zone = new \WC_Shipping_Zone( 0 );
		}

		// Get shipping methods for this zone.
		$methods = $customer_zone->get_shipping_methods( true );
		if ( ! empty( $methods ) ) {
			$method_list = array();
			foreach ( $methods as $method ) {
				$method_info = $method->get_title();
				// Try to get cost if it's a flat rate.
				if ( method_exists( $method, 'get_option' ) ) {
					$cost = $method->get_option( 'cost' );
					if ( ! empty( $cost ) && is_numeric( $cost ) ) {
						$method_info .= ' - ' . html_entity_decode( wp_strip_all_tags( wc_price( $cost ) ) );
					}
				}
				$method_list[] = '- ' . $method_info;
			}
			if ( ! empty( $method_list ) ) {
				$info[] = __( 'Available Shipping Methods:', 'assistify-for-woocommerce' );
				$info[] = implode( "\n", $method_list );
			}
		}

		// Free shipping threshold.
		$free_shipping_min = get_option( 'woocommerce_free_shipping_min_amount', '' );
		if ( ! empty( $free_shipping_min ) ) {
			$info[] = sprintf(
				/* translators: %s: Minimum amount for free shipping. */
				__( 'Free shipping available on orders over %s', 'assistify-for-woocommerce' ),
				html_entity_decode( wp_strip_all_tags( wc_price( $free_shipping_min ) ) )
			);
		}

		if ( empty( $info ) ) {
			return '';
		}

		return __( 'SHIPPING INFORMATION:', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $info );
	}

	/**
	 * Get tracking info from popular tracking plugins.
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return string Tracking info or empty string.
	 */
	private function get_order_tracking_info( $order ) {
		$tracking = '';

		// WooCommerce Shipment Tracking.
		$shipment_tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( ! empty( $shipment_tracking ) && is_array( $shipment_tracking ) ) {
			$track = reset( $shipment_tracking );
			if ( isset( $track['tracking_number'] ) ) {
				$tracking = sprintf(
					'%s - %s',
					isset( $track['tracking_provider'] ) ? $track['tracking_provider'] : __( 'Carrier', 'assistify-for-woocommerce' ),
					$track['tracking_number']
				);
				if ( isset( $track['tracking_link'] ) && ! empty( $track['tracking_link'] ) ) {
					$tracking .= sprintf( ' (%s)', $track['tracking_link'] );
				}
			}
		}

		// Advanced Shipment Tracking.
		if ( empty( $tracking ) ) {
			$ast_tracking = $order->get_meta( '_wc_shipment_tracking_items', true );
			if ( ! empty( $ast_tracking ) ) {
				$tracking = is_array( $ast_tracking ) ? wp_json_encode( $ast_tracking ) : $ast_tracking;
			}
		}

		return $tracking;
	}

	/**
	 * Get estimated delivery info.
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return string Estimated delivery info.
	 */
	private function get_estimated_delivery( $order ) {
		$status = $order->get_status();

		// Check for custom delivery date meta.
		$delivery_date = $order->get_meta( '_delivery_date' );
		if ( $delivery_date ) {
			return sprintf(
				/* translators: %s: Delivery date. */
				__( 'Estimated delivery: %s', 'assistify-for-woocommerce' ),
				$delivery_date
			);
		}

		// Generic estimates based on status.
		switch ( $status ) {
			case 'processing':
				return __( 'Order is being prepared for shipment', 'assistify-for-woocommerce' );
			case 'on-hold':
				return __( 'Order is on hold - awaiting payment confirmation', 'assistify-for-woocommerce' );
			case 'shipped':
				return __( 'Order has been shipped - check tracking for delivery estimate', 'assistify-for-woocommerce' );
			default:
				return '';
		}
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

		// Get privacy policy URL.
		$privacy_url       = Assistify_Privacy::get_privacy_policy_url();
		$show_privacy_link = get_option( 'assistify_show_privacy_link', 'yes' ) === 'yes';

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
				'privacyUrl'     => $show_privacy_link && $privacy_url ? esc_url( $privacy_url ) : '',
				'strings'        => array(
					'error'          => esc_html__( 'An error occurred. Please try again.', 'assistify-for-woocommerce' ),
					'loading'        => esc_html__( 'Loading...', 'assistify-for-woocommerce' ),
					'placeholder'    => esc_html__( 'Type your message...', 'assistify-for-woocommerce' ),
					'send'           => esc_html__( 'Send', 'assistify-for-woocommerce' ),
					'chatTitle'      => esc_html__( 'Chat with us', 'assistify-for-woocommerce' ),
					'subtitle'       => esc_html__( 'AI Assistant', 'assistify-for-woocommerce' ),
					'assistantName'  => esc_html( $assistant_name ),
					'consentTitle'   => esc_html__( 'Privacy Notice', 'assistify-for-woocommerce' ),
					'consentText'    => esc_html__( 'This chat is powered by AI to help you find products, answer questions, and assist with your orders. By continuing, you agree that your messages will be processed to provide responses.', 'assistify-for-woocommerce' ),
					'consentAgree'   => esc_html__( 'I Agree', 'assistify-for-woocommerce' ),
					'consentDecline' => esc_html__( 'No Thanks', 'assistify-for-woocommerce' ),
					'privacyLink'    => esc_html__( 'Privacy Policy', 'assistify-for-woocommerce' ),
					'proactiveMsg'   => __( 'Need any help? I\'m here if you have questions!', 'assistify-for-woocommerce' ),
					'offlineMsg'     => esc_html__( 'I\'m currently offline. Please contact us directly for assistance.', 'assistify-for-woocommerce' ),
					'contactLink'    => esc_html__( 'Contact Us', 'assistify-for-woocommerce' ),
				),
				'settings'       => array(
					'position'        => get_option( 'assistify_chat_position', 'bottom-right' ),
					'primaryColor'    => get_option( 'assistify_primary_color', '#6861F2' ),
					'autoOpenDelay'   => absint( get_option( 'assistify_auto_open_delay', 90 ) ), // Seconds.
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
			'type'         => 'other',
			'productName'  => '',
			'productId'    => 0,
			'categoryName' => '',
			'isCart'       => false,
			'isCheckout'   => false,
			'isAccount'    => false,
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
					<div class="assistify-header-content">
						<h3 class="assistify-chat-title">
							<?php esc_html_e( 'Chat with us', 'assistify-for-woocommerce' ); ?>
							<span class="assistify-status-dot is-online" title="<?php esc_attr_e( 'Online', 'assistify-for-woocommerce' ); ?>"></span>
						</h3>
						<span class="assistify-header-subtitle"><?php esc_html_e( 'We\'re here to help', 'assistify-for-woocommerce' ); ?></span>
					</div>
					<button 
						type="button" 
						class="assistify-chat-close" 
						aria-label="<?php esc_attr_e( 'Close chat', 'assistify-for-woocommerce' ); ?>"
					>
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
							<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
						</svg>
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

	/**
	 * Get available public coupons for customers.
	 *
	 * Only returns coupons that are:
	 * - Published and not expired
	 * - Not restricted to specific emails (public)
	 * - Within usage limits
	 *
	 * @since 1.0.0
	 * @return string Formatted coupon info.
	 */
	private function get_available_coupons_context() {
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $coupons ) ) {
			return '';
		}

		$coupon_list = array();
		$now         = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		foreach ( $coupons as $coupon_post ) {
			if ( count( $coupon_list ) >= 5 ) {
				break;
			}

			$coupon = new \WC_Coupon( $coupon_post->ID );

			// Skip expired coupons.
			$expiry = $coupon->get_date_expires();
			if ( $expiry && $expiry->getTimestamp() < $now ) {
				continue;
			}

			// Skip coupons with usage limits reached.
			$usage_limit = $coupon->get_usage_limit();
			$usage_count = $coupon->get_usage_count();
			if ( $usage_limit && $usage_count >= $usage_limit ) {
				continue;
			}

			// Skip email-restricted coupons (private).
			$email_restrictions = $coupon->get_email_restrictions();
			if ( ! empty( $email_restrictions ) ) {
				continue;
			}

			// Format coupon info.
			$discount_type  = $coupon->get_discount_type();
			$amount         = $coupon->get_amount();
			$discount_label = '';

			if ( 'percent' === $discount_type ) {
				/* translators: %s: Discount percentage. */
				$discount_label = sprintf( __( '%s%% off', 'assistify-for-woocommerce' ), $amount );
			} elseif ( 'fixed_cart' === $discount_type ) {
				/* translators: %s: Discount amount. */
				$discount_label = sprintf( __( '%s off your order', 'assistify-for-woocommerce' ), html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ) ) );
			} elseif ( 'fixed_product' === $discount_type ) {
				/* translators: %s: Discount amount. */
				$discount_label = sprintf( __( '%s off per item', 'assistify-for-woocommerce' ), html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ) ) );
			}

			$coupon_info = '- ' . strtoupper( $coupon->get_code() ) . ': ' . $discount_label;

			// Add free shipping if applicable.
			if ( $coupon->get_free_shipping() ) {
				$coupon_info .= ' + ' . __( 'Free Shipping', 'assistify-for-woocommerce' );
			}

			// Add minimum spend if set.
			$min_amount = $coupon->get_minimum_amount();
			if ( $min_amount ) {
				/* translators: %s: Minimum spend amount. */
				$coupon_info .= sprintf( __( ' (min spend %s)', 'assistify-for-woocommerce' ), html_entity_decode( wp_strip_all_tags( wc_price( $min_amount ) ) ) );
			}

			// Add expiry if set.
			if ( $expiry ) {
				/* translators: %s: Expiry date. */
				$coupon_info .= sprintf( __( ' - expires %s', 'assistify-for-woocommerce' ), $expiry->format( 'M j, Y' ) );
			}

			$coupon_list[] = $coupon_info;
		}

		if ( empty( $coupon_list ) ) {
			return __( 'AVAILABLE COUPONS: No coupons are currently available.', 'assistify-for-woocommerce' );
		}

		return __( 'AVAILABLE COUPON CODES (share these with customers when they ask):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $coupon_list );
	}

	/**
	 * Get products currently on sale.
	 *
	 * @since 1.0.0
	 * @return string Formatted sale products info.
	 */
	private function get_products_on_sale_context() {
		$sale_ids = wc_get_product_ids_on_sale();

		if ( empty( $sale_ids ) ) {
			return '';
		}

		$products = wc_get_products(
			array(
				'include' => array_slice( $sale_ids, 0, 5 ),
				'status'  => 'publish',
			)
		);

		if ( empty( $products ) ) {
			return '';
		}

		$product_list = array();

		foreach ( $products as $product ) {
			$regular_price = $product->get_regular_price();
			$sale_price    = $product->get_sale_price();

			// Calculate discount percentage.
			$discount = 0;
			if ( $regular_price && $sale_price ) {
				$discount = round( ( ( floatval( $regular_price ) - floatval( $sale_price ) ) / floatval( $regular_price ) ) * 100 );
			}

			$product_info = sprintf(
				/* translators: 1: Product name, 2: Regular price, 3: Sale price, 4: Discount percentage, 5: Product URL. */
				__( '- %1$s: was %2$s, now %3$s (%4$d%% off) - %5$s', 'assistify-for-woocommerce' ),
				$product->get_name(),
				html_entity_decode( wp_strip_all_tags( wc_price( $regular_price ) ) ),
				html_entity_decode( wp_strip_all_tags( wc_price( $sale_price ) ) ),
				$discount,
				get_permalink( $product->get_id() )
			);

			$product_list[] = $product_info;
		}

		$total_count = count( $sale_ids );
		/* translators: 1: Number of products shown, 2: Total products on sale. */
		$header = sprintf( __( 'PRODUCTS ON SALE (showing %1$d of %2$d):', 'assistify-for-woocommerce' ), count( $product_list ), $total_count );

		return $header . "\n" . implode( "\n", $product_list );
	}

	/**
	 * Get featured products.
	 *
	 * @since 1.0.0
	 * @return string Formatted featured products info.
	 */
	private function get_featured_products_context() {
		$featured_ids = wc_get_featured_product_ids();

		if ( empty( $featured_ids ) ) {
			return '';
		}

		$products = wc_get_products(
			array(
				'include' => array_slice( $featured_ids, 0, 5 ),
				'status'  => 'publish',
			)
		);

		if ( empty( $products ) ) {
			return '';
		}

		$product_list = array();

		foreach ( $products as $product ) {
			$product_info = sprintf(
				/* translators: 1: Product name, 2: Price, 3: Product URL. */
				__( '- %1$s: %2$s - %3$s', 'assistify-for-woocommerce' ),
				$product->get_name(),
				html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ) ),
				get_permalink( $product->get_id() )
			);

			$product_list[] = $product_info;
		}

		return __( 'FEATURED PRODUCTS (recommend these to customers):', 'assistify-for-woocommerce' ) . "\n" . implode( "\n", $product_list );
	}
}

