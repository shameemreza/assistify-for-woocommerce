<?php
/**
 * Intent Classifier Class.
 *
 * Classifies user messages and routes them to appropriate abilities.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intent Classifier Class.
 *
 * @since 1.0.0
 */
class Intent_Classifier {

	/**
	 * Intent patterns with their keywords and entity extractors.
	 *
	 * @var array
	 */
	private $intent_patterns = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_default_patterns();
	}

	/**
	 * Register default intent patterns.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_default_patterns() {
		// Order intents.
		$this->intent_patterns['order_get'] = array(
			'keywords'  => array( 'order #', 'order number', 'order id', 'show order', 'get order', 'find order', 'lookup order', 'check order' ),
			'patterns'  => array( '/order\s*#?\s*(\d+)/i', '/\border\b.*?(\d{3,})/i' ),
			'ability'   => 'afw/orders/get',
			'extractor' => 'extract_order_id',
			'priority'  => 10,
		);

		$this->intent_patterns['order_list'] = array(
			'keywords'  => array( 'orders', 'recent order', 'latest order', 'list order', 'show order', 'all order' ),
			'patterns'  => array( '/(?:recent|latest|last|new|show|list|all|pending|processing|completed|cancelled|on-hold)\s*orders?/i' ),
			'ability'   => 'afw/orders/list',
			'extractor' => 'extract_order_list_params',
			'priority'  => 5,
		);

		$this->intent_patterns['order_search'] = array(
			'keywords'  => array( 'search order', 'find order', 'orders from', 'orders by' ),
			'patterns'  => array( '/search\s+orders?\s+(?:for\s+)?(.+)/i', '/find\s+orders?\s+(.+)/i' ),
			'ability'   => 'afw/orders/search',
			'extractor' => 'extract_order_search_params',
			'priority'  => 8,
		);

		// Product intents.
		$this->intent_patterns['product_get'] = array(
			'keywords'  => array( 'product #', 'product id', 'show product', 'get product', 'find product' ),
			'patterns'  => array( '/product\s*#?\s*(\d+)/i', '/\bproduct\b.*?(\d{2,})/i', '/sku\s*[:\s]*([a-zA-Z0-9_-]+)/i' ),
			'ability'   => 'afw/products/get',
			'extractor' => 'extract_product_id',
			'priority'  => 10,
		);

		$this->intent_patterns['product_list'] = array(
			'keywords'  => array( 'products', 'list product', 'show product', 'all product' ),
			'patterns'  => array( '/(?:list|show|all|recent)\s*products?/i' ),
			'ability'   => 'afw/products/list',
			'extractor' => 'extract_product_list_params',
			'priority'  => 5,
		);

		$this->intent_patterns['product_count'] = array(
			'keywords'  => array( 'how many product', 'count product', 'number of product', 'total product' ),
			'patterns'  => array(
				'/how\s+many\s+(?:(\w+)\s+)?products?/i',
				'/count\s+(?:(\w+)\s+)?products?/i',
				'/number\s+of\s+(?:(\w+)\s+)?products?/i',
				'/total\s+(?:(\w+)\s+)?products?/i',
			),
			'ability'   => 'afw/products/count',
			'extractor' => 'extract_product_count_params',
			'priority'  => 9,
		);

		$this->intent_patterns['product_search'] = array(
			'keywords'  => array( 'search product', 'find product', 'products named', 'products called' ),
			'patterns'  => array( '/search\s+products?\s+(?:for\s+)?(.+)/i', '/find\s+products?\s+(.+)/i' ),
			'ability'   => 'afw/products/search',
			'extractor' => 'extract_product_search_params',
			'priority'  => 8,
		);

		$this->intent_patterns['product_low_stock'] = array(
			'keywords'  => array( 'low stock', 'low inventory', 'running low', 'out of stock', 'stock level', 'inventory low' ),
			'patterns'  => array( '/low\s*(?:on\s*)?stock/i', '/out\s*of\s*stock/i', '/inventory\s+(?:is\s+)?low/i', '/running\s+low/i' ),
			'ability'   => 'afw/products/low-stock',
			'extractor' => 'extract_low_stock_params',
			'priority'  => 9,
		);

		$this->intent_patterns['product_virtual'] = array(
			'keywords'  => array( 'virtual product', 'digital product', 'downloadable', 'non-physical' ),
			'patterns'  => array( '/virtual\s+products?/i', '/digital\s+products?/i', '/downloadable\s+products?/i' ),
			'ability'   => 'afw/products/count',
			'extractor' => 'extract_virtual_product_params',
			'priority'  => 9,
		);

		// Customer intents.
		$this->intent_patterns['customer_get'] = array(
			'keywords'  => array( 'customer #', 'customer id', 'show customer', 'get customer', 'find customer' ),
			'patterns'  => array( '/customer\s*#?\s*(\d+)/i', '/customer\s+(.+@.+\..+)/i' ),
			'ability'   => 'afw/customers/get',
			'extractor' => 'extract_customer_id',
			'priority'  => 10,
		);

		$this->intent_patterns['customer_list'] = array(
			'keywords'  => array( 'customers', 'list customer', 'show customer', 'all customer', 'buyer', 'client' ),
			'patterns'  => array( '/(?:list|show|all|recent)\s*customers?/i', '/\bcustomers?\b/i', '/\bbuyers?\b/i' ),
			'ability'   => 'afw/customers/list',
			'extractor' => 'extract_customer_list_params',
			'priority'  => 5,
		);

		$this->intent_patterns['top_customers'] = array(
			'keywords'  => array( 'top customer', 'best customer', 'vip customer', 'high value', 'loyal customer' ),
			'patterns'  => array( '/top\s+customers?/i', '/best\s+customers?/i', '/vip\s+customers?/i', '/high\s*value\s+customers?/i' ),
			'ability'   => 'afw/analytics/top-customers',
			'extractor' => 'extract_top_customers_params',
			'priority'  => 9,
		);

		// Analytics intents.
		$this->intent_patterns['sales_analytics'] = array(
			'keywords'  => array( 'sales', 'revenue', 'how much', 'total', 'earning', 'income', 'made today', 'made this' ),
			'patterns'  => array(
				'/(?:sales|revenue|earning|income)\s*(?:today|this\s+week|this\s+month|this\s+year)?/i',
				'/how\s+much\s+(?:did\s+(?:we|i)\s+)?(?:make|earn|sell)/i',
				'/total\s+(?:sales|revenue)/i',
			),
			'ability'   => 'afw/analytics/sales',
			'extractor' => 'extract_sales_params',
			'priority'  => 8,
		);

		$this->intent_patterns['revenue_analytics'] = array(
			'keywords'  => array( 'revenue breakdown', 'revenue detail', 'net revenue', 'gross revenue', 'tax collected', 'shipping revenue' ),
			'patterns'  => array( '/revenue\s+(?:breakdown|detail|report)/i', '/(?:net|gross)\s+revenue/i' ),
			'ability'   => 'afw/analytics/revenue',
			'extractor' => 'extract_revenue_params',
			'priority'  => 9,
		);

		$this->intent_patterns['top_products'] = array(
			'keywords'  => array( 'top product', 'best selling', 'bestseller', 'popular product', 'top seller', 'most sold' ),
			'patterns'  => array( '/top\s+(?:selling\s+)?products?/i', '/best\s*sell(?:ing|er)/i', '/popular\s+products?/i', '/most\s+sold/i' ),
			'ability'   => 'afw/analytics/top-products',
			'extractor' => 'extract_top_products_params',
			'priority'  => 9,
		);

		// Store settings intents.
		$this->intent_patterns['store_settings'] = array(
			'keywords'  => array( 'store setting', 'shop setting', 'woocommerce setting', 'configuration' ),
			'patterns'  => array( '/(?:store|shop|woocommerce)\s+settings?/i', '/store\s+config/i' ),
			'ability'   => 'afw/store/settings',
			'extractor' => 'extract_settings_params',
			'priority'  => 7,
		);

		$this->intent_patterns['store_status'] = array(
			'keywords'  => array( 'store status', 'system status', 'woocommerce status', 'health check', 'store health' ),
			'patterns'  => array( '/(?:store|system|woocommerce)\s+status/i', '/health\s+check/i' ),
			'ability'   => 'afw/store/status',
			'extractor' => null,
			'priority'  => 7,
		);

		$this->intent_patterns['payment_gateways'] = array(
			'keywords'  => array( 'payment gateway', 'payment method', 'payment option', 'how can customer pay' ),
			'patterns'  => array( '/payment\s+(?:gateway|method|option)s?/i', '/how\s+(?:can|do)\s+customers?\s+pay/i' ),
			'ability'   => 'afw/store/payment-gateways',
			'extractor' => null,
			'priority'  => 7,
		);

		$this->intent_patterns['shipping_zones'] = array(
			'keywords'  => array( 'shipping zone', 'shipping method', 'shipping option', 'delivery zone' ),
			'patterns'  => array( '/shipping\s+(?:zone|method|option)s?/i', '/delivery\s+zones?/i' ),
			'ability'   => 'afw/store/shipping-zones',
			'extractor' => null,
			'priority'  => 7,
		);

		$this->intent_patterns['tax_rates'] = array(
			'keywords'  => array( 'tax rate', 'tax class', 'vat rate', 'sales tax' ),
			'patterns'  => array( '/tax\s+(?:rate|class|setting)s?/i', '/(?:vat|sales)\s+tax/i' ),
			'ability'   => 'afw/store/tax-rates',
			'extractor' => 'extract_tax_params',
			'priority'  => 7,
		);

		// Coupon intents.
		$this->intent_patterns['coupon_list'] = array(
			'keywords'  => array( 'coupons', 'discount code', 'promo code', 'list coupon', 'show coupon' ),
			'patterns'  => array( '/(?:list|show|all|active)\s*coupons?/i', '/discount\s*codes?/i', '/promo\s*codes?/i' ),
			'ability'   => 'afw/coupons/list',
			'extractor' => 'extract_coupon_list_params',
			'priority'  => 6,
		);

		$this->intent_patterns['coupon_get'] = array(
			'keywords'  => array( 'coupon code', 'get coupon', 'show coupon', 'coupon detail' ),
			'patterns'  => array( '/coupon\s+(?:code\s+)?["\']?([A-Z0-9_-]+)["\']?/i', '/show\s+coupon\s+(.+)/i' ),
			'ability'   => 'afw/coupons/get',
			'extractor' => 'extract_coupon_code',
			'priority'  => 8,
		);

		// URL intents.
		$this->intent_patterns['url_product'] = array(
			'keywords'  => array( 'product url', 'product link', 'link to product', 'product page' ),
			'patterns'  => array(
				'/(?:url|link)\s+(?:to|for|of)\s+(?:the\s+)?product/i',
				'/product\s+(?:url|link|page)/i',
				'/(?:get|give|show|share)\s+(?:me\s+)?(?:the\s+)?(?:url|link)\s+(?:to|for|of)?\s*(?:the\s+)?product/i',
			),
			'ability'   => 'afw/urls/product',
			'extractor' => 'extract_product_url_params',
			'priority'  => 9,
		);

		$this->intent_patterns['url_product_edit'] = array(
			'keywords'  => array( 'edit product url', 'edit link', 'admin link', 'edit product' ),
			'patterns'  => array(
				'/edit\s+(?:url|link)\s+(?:for|of)\s+product/i',
				'/product\s+edit\s+(?:url|link)/i',
				'/admin\s+(?:url|link)\s+(?:for|to)\s+product/i',
			),
			'ability'   => 'afw/urls/product-edit',
			'extractor' => 'extract_product_url_params',
			'priority'  => 9,
		);

		$this->intent_patterns['url_order'] = array(
			'keywords'  => array( 'order url', 'order link', 'link to order', 'view order' ),
			'patterns'  => array(
				'/(?:url|link)\s+(?:to|for|of)\s+(?:the\s+)?order/i',
				'/order\s+(?:url|link)/i',
				'/(?:get|show)\s+(?:the\s+)?order\s+(?:url|link)/i',
			),
			'ability'   => 'afw/urls/order',
			'extractor' => 'extract_order_id',
			'priority'  => 9,
		);

		$this->intent_patterns['url_settings'] = array(
			'keywords'  => array( 'settings url', 'settings link', 'shipping settings', 'tax settings', 'payment settings' ),
			'patterns'  => array(
				'/(?:url|link)\s+(?:to|for)\s+(?:woocommerce\s+)?settings?/i',
				'/(?:shipping|tax|payment|email|account)\s+settings?\s+(?:url|link|page)/i',
				'/where\s+(?:can\s+)?i\s+(?:find|change|configure)\s+(?:shipping|tax|payment)/i',
			),
			'ability'   => 'afw/urls/settings',
			'extractor' => 'extract_settings_section',
			'priority'  => 8,
		);

		$this->intent_patterns['url_category'] = array(
			'keywords'  => array( 'category url', 'category link', 'link to category' ),
			'patterns'  => array(
				'/(?:url|link)\s+(?:to|for|of)\s+(?:the\s+)?category/i',
				'/category\s+(?:url|link|page)/i',
			),
			'ability'   => 'afw/urls/category',
			'extractor' => 'extract_category_params',
			'priority'  => 8,
		);

		// WordPress Content intents.
		$this->intent_patterns['content_pages'] = array(
			'keywords'  => array( 'pages', 'list pages', 'wordpress pages', 'site pages', 'show pages' ),
			'patterns'  => array(
				'/(?:list|show|all|site)\s+pages?/i',
				'/wordpress\s+pages?/i',
				'/what\s+pages?\s+(?:do\s+)?(?:we|i)\s+have/i',
			),
			'ability'   => 'afw/content/pages',
			'extractor' => 'extract_content_params',
			'priority'  => 6,
		);

		$this->intent_patterns['content_posts'] = array(
			'keywords'  => array( 'posts', 'blog posts', 'list posts', 'articles', 'show posts' ),
			'patterns'  => array(
				'/(?:list|show|all|recent)\s+(?:blog\s+)?posts?/i',
				'/blog\s+(?:posts?|articles?)/i',
				'/what\s+(?:blog\s+)?posts?\s+(?:do\s+)?(?:we|i)\s+have/i',
			),
			'ability'   => 'afw/content/posts',
			'extractor' => 'extract_content_params',
			'priority'  => 6,
		);

		$this->intent_patterns['content_menus'] = array(
			'keywords'  => array( 'menus', 'navigation', 'nav menu', 'site menu' ),
			'patterns'  => array(
				'/(?:navigation|nav)\s+menus?/i',
				'/(?:site|website)\s+menus?/i',
				'/what\s+menus?\s+(?:do\s+)?(?:we|i)\s+have/i',
			),
			'ability'   => 'afw/content/menus',
			'extractor' => null,
			'priority'  => 5,
		);

		/**
		 * Filter intent patterns to allow customization.
		 *
		 * @since 1.0.0
		 * @param array $intent_patterns The intent patterns array.
		 */
		$this->intent_patterns = apply_filters( 'assistify_intent_patterns', $this->intent_patterns );
	}

	/**
	 * Classify a message and return matched intents with parameters.
	 *
	 * @since 1.0.0
	 * @param string $message The user message.
	 * @return array Array of matched intents sorted by priority.
	 */
	public function classify( $message ) {
		$message_lower = strtolower( $message );
		$matches       = array();

		foreach ( $this->intent_patterns as $intent_name => $pattern ) {
			$score = $this->calculate_match_score( $message_lower, $pattern );

			if ( $score > 0 ) {
				$params = array();

				// Extract parameters if extractor exists.
				if ( ! empty( $pattern['extractor'] ) && method_exists( $this, $pattern['extractor'] ) ) {
					$params = call_user_func( array( $this, $pattern['extractor'] ), $message );
				}

				$matches[] = array(
					'intent'   => $intent_name,
					'ability'  => $pattern['ability'],
					'score'    => $score,
					'priority' => $pattern['priority'],
					'params'   => $params,
				);
			}
		}

		// Sort by score (descending), then by priority (descending).
		usort(
			$matches,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return $b['priority'] - $a['priority'];
				}
				return $b['score'] - $a['score'];
			}
		);

		return $matches;
	}

	/**
	 * Get the best matching intent.
	 *
	 * @since 1.0.0
	 * @param string $message The user message.
	 * @return array|null Best matching intent or null.
	 */
	public function get_best_match( $message ) {
		$matches = $this->classify( $message );
		return ! empty( $matches ) ? $matches[0] : null;
	}

	/**
	 * Execute abilities based on classified intents.
	 *
	 * @since 1.0.0
	 * @param string $message The user message.
	 * @return array Array of ability results.
	 */
	public function execute_matching_abilities( $message ) {
		$matches  = $this->classify( $message );
		$results  = array();
		$executed = array();
		$registry = Abilities\Abilities_Registry::instance();

		// Execute up to 3 matching abilities (avoid duplicates).
		foreach ( $matches as $match ) {
			if ( count( $results ) >= 3 ) {
				break;
			}

			// Skip if ability already executed.
			if ( in_array( $match['ability'], $executed, true ) ) {
				continue;
			}

			$result = $registry->execute( $match['ability'], $match['params'] );

			if ( ! is_wp_error( $result ) ) {
				$results[ $match['intent'] ] = $result;
				$executed[]                  = $match['ability'];
			}
		}

		return $results;
	}

	/**
	 * Calculate match score for a message against a pattern.
	 *
	 * @since 1.0.0
	 * @param string $message The message (lowercase).
	 * @param array  $pattern The intent pattern.
	 * @return int Match score (0 = no match).
	 */
	private function calculate_match_score( $message, $pattern ) {
		$score = 0;

		// Check keywords.
		foreach ( $pattern['keywords'] as $keyword ) {
			if ( strpos( $message, $keyword ) !== false ) {
				++$score;
			}
		}

		// Check regex patterns (higher weight).
		if ( ! empty( $pattern['patterns'] ) ) {
			foreach ( $pattern['patterns'] as $regex ) {
				if ( preg_match( $regex, $message ) ) {
					$score += 3;
				}
			}
		}

		return $score;
	}

	// ==========================================================================
	// Parameter Extractors
	// ==========================================================================

	/**
	 * Extract order ID from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_order_id( $message ) {
		if ( preg_match( '/order\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'order_id' => (int) $matches[1] );
		}
		if ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			return array( 'order_id' => (int) $matches[1] );
		}
		return array();
	}

	/**
	 * Extract order list parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_order_list_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'limit' => 10 );

		// Extract status.
		$statuses = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
		foreach ( $statuses as $status ) {
			if ( strpos( $message_lower, $status ) !== false ) {
				$params['status'] = $status;
				break;
			}
		}

		// Extract limit.
		if ( preg_match( '/(?:last|recent|top)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract order search parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_order_search_params( $message ) {
		$params = array( 'limit' => 10 );

		// Extract email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $message, $matches ) ) {
			$params['email'] = $matches[1];
		}

		return $params;
	}

	/**
	 * Extract product ID from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_product_id( $message ) {
		// Check for SKU.
		if ( preg_match( '/sku\s*[:\s]*([a-zA-Z0-9_-]+)/i', $message, $matches ) ) {
			return array( 'sku' => $matches[1] );
		}

		// Check for product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'product_id' => (int) $matches[1] );
		}

		return array();
	}

	/**
	 * Extract product list parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_product_list_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'limit' => 10 );

		// Extract type.
		$types = array( 'simple', 'variable', 'grouped', 'external' );
		foreach ( $types as $type ) {
			if ( strpos( $message_lower, $type ) !== false ) {
				$params['type'] = $type;
				break;
			}
		}

		// Check for virtual/downloadable.
		if ( strpos( $message_lower, 'virtual' ) !== false ) {
			$params['virtual'] = true;
		}
		if ( strpos( $message_lower, 'downloadable' ) !== false ) {
			$params['downloadable'] = true;
		}

		// Extract stock status.
		if ( strpos( $message_lower, 'out of stock' ) !== false ) {
			$params['stock_status'] = 'outofstock';
		} elseif ( strpos( $message_lower, 'in stock' ) !== false ) {
			$params['stock_status'] = 'instock';
		}

		return $params;
	}

	/**
	 * Extract product count parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_product_count_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array();

		// Extract type filter.
		$types = array( 'simple', 'variable', 'grouped', 'external' );
		foreach ( $types as $type ) {
			if ( strpos( $message_lower, $type ) !== false ) {
				$params['type'] = $type;
				break;
			}
		}

		// Check for virtual.
		if ( strpos( $message_lower, 'virtual' ) !== false ) {
			$params['virtual'] = true;
		}

		// Check for downloadable.
		if ( strpos( $message_lower, 'downloadable' ) !== false || strpos( $message_lower, 'digital' ) !== false ) {
			$params['downloadable'] = true;
		}

		// Check for featured.
		if ( strpos( $message_lower, 'featured' ) !== false ) {
			$params['featured'] = true;
		}

		// Check for on sale.
		if ( strpos( $message_lower, 'on sale' ) !== false || strpos( $message_lower, 'discounted' ) !== false ) {
			$params['on_sale'] = true;
		}

		// Check for stock status.
		if ( strpos( $message_lower, 'out of stock' ) !== false ) {
			$params['stock_status'] = 'outofstock';
		} elseif ( strpos( $message_lower, 'in stock' ) !== false ) {
			$params['stock_status'] = 'instock';
		}

		return $params;
	}

	/**
	 * Extract virtual product parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_virtual_product_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array();

		if ( strpos( $message_lower, 'downloadable' ) !== false ) {
			$params['downloadable'] = true;
		} else {
			$params['virtual'] = true;
		}

		return $params;
	}

	/**
	 * Extract product search parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_product_search_params( $message ) {
		$params = array( 'limit' => 10 );

		// Extract search term.
		if ( preg_match( '/(?:search|find)\s+products?\s+(?:for\s+)?["\']?(.+?)["\']?$/i', $message, $matches ) ) {
			$params['search'] = trim( $matches[1] );
		}

		return $params;
	}

	/**
	 * Extract low stock parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_low_stock_params( $message ) {
		$params = array(
			'threshold' => 10,
			'limit'     => 20,
		);

		// Extract threshold.
		if ( preg_match( '/(?:below|under|less\s+than)\s+(\d+)/i', $message, $matches ) ) {
			$params['threshold'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract customer ID from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_id( $message ) {
		// Check for email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $message, $matches ) ) {
			return array( 'email' => $matches[1] );
		}

		// Check for customer ID.
		if ( preg_match( '/customer\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'customer_id' => (int) $matches[1] );
		}

		return array();
	}

	/**
	 * Extract customer list parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_list_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'limit' => 10 );

		// Check for sorting.
		if ( strpos( $message_lower, 'top' ) !== false || strpos( $message_lower, 'best' ) !== false ) {
			$params['orderby'] = 'total_spent';
			$params['order']   = 'DESC';
		} elseif ( strpos( $message_lower, 'recent' ) !== false || strpos( $message_lower, 'new' ) !== false ) {
			$params['orderby'] = 'registered';
			$params['order']   = 'DESC';
		}

		return $params;
	}

	/**
	 * Extract top customers parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_top_customers_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array(
			'limit'   => 10,
			'orderby' => 'total_spent',
		);

		// Extract period.
		if ( strpos( $message_lower, 'this week' ) !== false || strpos( $message_lower, 'week' ) !== false ) {
			$params['period'] = 'week';
		} elseif ( strpos( $message_lower, 'this month' ) !== false || strpos( $message_lower, 'month' ) !== false ) {
			$params['period'] = 'month';
		} elseif ( strpos( $message_lower, 'this year' ) !== false || strpos( $message_lower, 'year' ) !== false ) {
			$params['period'] = 'year';
		} else {
			$params['period'] = 'all';
		}

		// Check if by order count.
		if ( strpos( $message_lower, 'order count' ) !== false || strpos( $message_lower, 'most orders' ) !== false ) {
			$params['orderby'] = 'order_count';
		}

		return $params;
	}

	/**
	 * Extract sales analytics parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_sales_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'period' => 'month' );

		// Extract period.
		if ( strpos( $message_lower, 'today' ) !== false ) {
			$params['period'] = 'today';
		} elseif ( strpos( $message_lower, 'yesterday' ) !== false ) {
			$params['period'] = 'yesterday';
		} elseif ( strpos( $message_lower, 'this week' ) !== false || strpos( $message_lower, 'week' ) !== false ) {
			$params['period'] = 'week';
		} elseif ( strpos( $message_lower, 'this year' ) !== false || strpos( $message_lower, 'year' ) !== false ) {
			$params['period'] = 'year';
		} elseif ( strpos( $message_lower, 'quarter' ) !== false ) {
			$params['period'] = 'quarter';
		}

		return $params;
	}

	/**
	 * Extract revenue analytics parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_revenue_params( $message ) {
		$params            = $this->extract_sales_params( $message );
		$params['compare'] = strpos( strtolower( $message ), 'compare' ) !== false;
		return $params;
	}

	/**
	 * Extract top products parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_top_products_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'limit' => 10 );

		// Extract period.
		if ( strpos( $message_lower, 'this week' ) !== false ) {
			$params['period'] = 'week';
		} elseif ( strpos( $message_lower, 'this month' ) !== false ) {
			$params['period'] = 'month';
		} elseif ( strpos( $message_lower, 'this year' ) !== false ) {
			$params['period'] = 'year';
		}

		// Extract limit.
		if ( preg_match( '/top\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract settings parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_settings_params( $message ) {
		$message_lower = strtolower( $message );
		$groups        = array( 'general', 'products', 'tax', 'shipping', 'checkout', 'accounts', 'emails', 'advanced' );

		foreach ( $groups as $group ) {
			if ( strpos( $message_lower, $group ) !== false ) {
				return array( 'group' => $group );
			}
		}

		return array( 'group' => 'all' );
	}

	/**
	 * Extract tax parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_tax_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array();

		// Check for specific tax class.
		if ( strpos( $message_lower, 'reduced' ) !== false ) {
			$params['tax_class'] = 'reduced-rate';
		} elseif ( strpos( $message_lower, 'zero' ) !== false ) {
			$params['tax_class'] = 'zero-rate';
		}

		return $params;
	}

	/**
	 * Extract coupon list parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_coupon_list_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'limit' => 10 );

		if ( strpos( $message_lower, 'active' ) !== false ) {
			$params['status'] = 'publish';
		} elseif ( strpos( $message_lower, 'expired' ) !== false ) {
			$params['status'] = 'expired';
		}

		return $params;
	}

	/**
	 * Extract coupon code from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_coupon_code( $message ) {
		// Look for quoted coupon code.
		if ( preg_match( '/["\']([A-Z0-9_-]+)["\']/i', $message, $matches ) ) {
			return array( 'code' => strtoupper( $matches[1] ) );
		}

		// Look for coupon code pattern.
		if ( preg_match( '/coupon\s+(?:code\s+)?([A-Z0-9_-]{3,})/i', $message, $matches ) ) {
			return array( 'code' => strtoupper( $matches[1] ) );
		}

		return array();
	}

	/**
	 * Extract product URL parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_product_url_params( $message ) {
		// Check for product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'product_id' => (int) $matches[1] );
		}

		// Check for quoted product name.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			return array( 'product_name' => $matches[1] );
		}

		// Check for "for <product name>".
		if ( preg_match( '/(?:for|of|to)\s+(?:the\s+)?(?:product\s+)?(.+?)(?:\s+product)?$/i', $message, $matches ) ) {
			$name = trim( $matches[1] );
			if ( strlen( $name ) > 2 && ! preg_match( '/^(url|link|page)$/i', $name ) ) {
				return array( 'product_name' => $name );
			}
		}

		return array();
	}

	/**
	 * Extract settings section from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_settings_section( $message ) {
		$message_lower = strtolower( $message );

		$section_map = array(
			'shipping'    => 'shipping',
			'tax'         => 'tax',
			'payment'     => 'payments',
			'checkout'    => 'payments',
			'email'       => 'emails',
			'notification' => 'emails',
			'account'     => 'accounts',
			'product'     => 'products',
			'inventory'   => 'products',
			'advanced'    => 'advanced',
			'integration' => 'integration',
			'api'         => 'advanced',
			'webhook'     => 'advanced',
			'assistify'   => 'assistify',
		);

		foreach ( $section_map as $keyword => $section ) {
			if ( strpos( $message_lower, $keyword ) !== false ) {
				return array( 'section' => $section );
			}
		}

		return array( 'section' => 'general' );
	}

	/**
	 * Extract category parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_category_params( $message ) {
		// Check for category ID.
		if ( preg_match( '/category\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'category_id' => (int) $matches[1] );
		}

		// Check for quoted category name.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			return array( 'category_name' => $matches[1] );
		}

		// Check for "for <category name>".
		if ( preg_match( '/(?:for|of|to)\s+(?:the\s+)?(?:category\s+)?(.+?)(?:\s+category)?$/i', $message, $matches ) ) {
			$name = trim( $matches[1] );
			if ( strlen( $name ) > 2 && ! preg_match( '/^(url|link|page)$/i', $name ) ) {
				return array( 'category_name' => $name );
			}
		}

		return array();
	}

	/**
	 * Extract content parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_content_params( $message ) {
		$params = array( 'limit' => 20 );

		// Check for search query.
		if ( preg_match( '/(?:about|containing|with)\s+["\']?([^"\']+)["\']?/i', $message, $matches ) ) {
			$params['search'] = trim( $matches[1] );
		}

		// Check for limit.
		if ( preg_match( '/(?:top|first|last)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = (int) $matches[1];
		}

		return $params;
	}
}
