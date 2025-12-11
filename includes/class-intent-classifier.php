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

		// Get last/recent/latest order (singular - detailed view).
		$this->intent_patterns['order_get_last'] = array(
			'keywords'  => array( 'last order', 'recent order', 'latest order', 'most recent order', 'newest order' ),
			'patterns'  => array(
				'/(?:the\s+)?(?:last|most\s+recent|latest|newest)\s+order(?:\s+details?)?/i',
				'/check\s+(?:the\s+)?(?:last|latest|recent)\s+order/i',
				'/what.*(?:last|latest|recent)\s+order/i',
				'/show\s+(?:me\s+)?(?:the\s+)?(?:last|latest|recent)\s+order/i',
			),
			'ability'   => 'afw/orders/get-last',
			'extractor' => 'extract_last_order_params',
			'priority'  => 12, // Higher than order_list.
		);

		$this->intent_patterns['order_list'] = array(
			'keywords'  => array( 'orders', 'list order', 'show orders', 'all order', 'how many order', 'order count', 'total order' ),
			'patterns'  => array(
				'/(?:show|list|all|pending|processing|completed|cancelled|on-hold)\s*orders/i',
				'/how\s+many\s+(?:total\s+)?orders?/i',
				'/(?:total|count)\s+(?:of\s+)?orders?/i',
				'/orders?\s+(?:do\s+)?(?:i|we)\s+have/i',
			),
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
			'keywords'  => array( 'products', 'list product', 'show product', 'all product', 'my product', 'our product', 'created product', 'new product' ),
			'patterns'  => array(
				'/(?:list|show|all|recent|my|our)\s*products?/i',
				'/products?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/(?:what|which)\s+products?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/(?:created|added|new)\s+products?/i',
				'/how\s+many\s+products?/i',
			),
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
			'keywords'  => array( 'customers', 'list customer', 'show customer', 'all customer', 'buyer', 'client', 'my customer', 'our customer' ),
			'patterns'  => array(
				'/(?:list|show|all|recent|my|our)\s*customers?/i',
				'/customers?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/(?:what|which|how\s+many)\s+customers?/i',
				'/\bbuyers?\b/i',
				'/\bclients?\b/i',
			),
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

		// Daily summary intent.
		$this->intent_patterns['daily_summary'] = array(
			'keywords'  => array( 'summary', 'overview', 'today', 'how are we doing', 'store status', 'daily report', 'daily stats', 'dashboard', 'update', 'business' ),
			'patterns'  => array(
				'/(?:daily|today\'?s?|store|business)\s+(?:summary|overview|report|stats|update)/i',
				'/how\s+(?:are\s+we|is\s+(?:the\s+)?(?:store|business))\s+doing/i',
				'/store\s+(?:status|overview|summary|dashboard)/i',
				'/what\'?s?\s+(?:happening|going\s+on|new)\s+(?:today|in\s+(?:the\s+)?store)/i',
				'/give\s+(?:me\s+)?(?:a\s+)?(?:summary|overview|update)/i',
				'/(?:store|business|sales)\s+(?:performance|update)/i',
				'/how\s+(?:is|was)\s+(?:today|yesterday|this\s+week)/i',
			),
			'ability'   => 'afw/analytics/daily-summary',
			'extractor' => 'extract_daily_summary_params',
			'priority'  => 10,
		);

		// Orders ready to ship.
		$this->intent_patterns['orders_ready_to_ship'] = array(
			'keywords'  => array( 'ready to ship', 'ship today', 'fulfillment', 'needs shipping', 'pending shipment', 'to ship' ),
			'patterns'  => array(
				'/(?:orders?\s+)?ready\s+to\s+ship/i',
				'/(?:orders?\s+)?(?:need|needs|pending)\s+(?:to\s+)?ship/i',
				'/what\s+(?:orders?\s+)?(?:need|needs|should)\s+(?:I|we)\s+ship/i',
				'/fulfillment\s+(?:queue|list)/i',
			),
			'ability'   => 'afw/orders/ready-to-ship',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 10,
		);

		// Pending payment orders.
		$this->intent_patterns['pending_payment'] = array(
			'keywords'  => array( 'awaiting payment', 'pending payment', 'unpaid', 'not paid', 'waiting for payment' ),
			'patterns'  => array(
				'/(?:orders?\s+)?(?:awaiting|pending|waiting\s+for)\s+payment/i',
				'/unpaid\s+orders?/i',
				'/orders?\s+not\s+paid/i',
				'/who\s+(?:hasn\'t|has\s+not)\s+paid/i',
			),
			'ability'   => 'afw/orders/pending-payment',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 10,
		);

		// Out of stock products.
		$this->intent_patterns['out_of_stock'] = array(
			'keywords'  => array( 'out of stock', 'no stock', 'zero stock', 'sold out' ),
			'patterns'  => array(
				'/(?:products?\s+)?out\s+of\s+stock/i',
				'/(?:products?\s+)?(?:with\s+)?(?:no|zero)\s+stock/i',
				'/sold\s+out\s+(?:products?|items?)/i',
				'/what\'?s?\s+out\s+of\s+stock/i',
			),
			'ability'   => 'afw/products/out-of-stock',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 10,
		);

		// Stock value.
		$this->intent_patterns['stock_value'] = array(
			'keywords'  => array( 'stock value', 'inventory value', 'worth of inventory', 'total stock' ),
			'patterns'  => array(
				'/(?:stock|inventory)\s+value/i',
				'/worth\s+of\s+(?:stock|inventory)/i',
				'/(?:total|how\s+much)\s+(?:is\s+)?(?:my|our)\s+(?:stock|inventory)/i',
			),
			'ability'   => 'afw/products/stock-value',
			'extractor' => 'extract_empty_params',
			'priority'  => 10,
		);

		// Refunds summary.
		$this->intent_patterns['refunds'] = array(
			'keywords'  => array( 'refund', 'refunded', 'money back', 'return' ),
			'patterns'  => array(
				'/(?:recent|show|list)?\s*refunds?/i',
				'/how\s+(?:many|much)\s+refund/i',
				'/refund\s+(?:summary|report|stats)/i',
			),
			'ability'   => 'afw/analytics/refunds',
			'extractor' => 'extract_days_params',
			'priority'  => 9,
		);

		// Payment methods breakdown.
		$this->intent_patterns['payment_methods_stats'] = array(
			'keywords'  => array( 'payment method', 'how do customers pay', 'payment breakdown', 'popular payment' ),
			'patterns'  => array(
				'/payment\s+(?:method|breakdown|stats)/i',
				'/how\s+(?:do|are)\s+(?:customers?|people)\s+pay/i',
				'/popular\s+payment/i',
				'/revenue\s+by\s+payment/i',
			),
			'ability'   => 'afw/analytics/payment-methods',
			'extractor' => 'extract_period_params',
			'priority'  => 9,
		);

		// Recent customers.
		$this->intent_patterns['recent_customers'] = array(
			'keywords'  => array( 'new customer', 'recent customer', 'latest customer', 'just signed up' ),
			'patterns'  => array(
				'/(?:new|recent|latest)\s+customers?/i',
				'/customers?\s+(?:who\s+)?(?:just\s+)?signed?\s+up/i',
				'/(?:who|how\s+many)\s+(?:new\s+)?customers?\s+(?:this|today|last)/i',
			),
			'ability'   => 'afw/customers/recent',
			'extractor' => 'extract_days_params',
			'priority'  => 9,
		);

		// Repeat customers.
		$this->intent_patterns['repeat_customers'] = array(
			'keywords'  => array( 'repeat customer', 'loyal customer', 'returning customer', 'multiple order' ),
			'patterns'  => array(
				'/(?:repeat|loyal|returning)\s+customers?/i',
				'/customers?\s+(?:with\s+)?multiple\s+orders?/i',
				'/who\s+(?:bought|ordered)\s+(?:more\s+than\s+once|again)/i',
			),
			'ability'   => 'afw/customers/repeat',
			'extractor' => 'extract_repeat_customers_params',
			'priority'  => 9,
		);

		// Reviews summary.
		$this->intent_patterns['reviews_summary'] = array(
			'keywords'  => array( 'review', 'rating', 'feedback', 'customer review' ),
			'patterns'  => array(
				'/(?:recent|show|list)?\s*reviews?/i',
				'/(?:product|customer)\s+(?:reviews?|ratings?|feedback)/i',
				'/how\s+are\s+(?:our|the)\s+reviews?/i',
				'/(?:average|overall)\s+rating/i',
			),
			'ability'   => 'afw/analytics/reviews',
			'extractor' => 'extract_days_params',
			'priority'  => 9,
		);

		// Order comparison.
		$this->intent_patterns['order_comparison'] = array(
			'keywords'  => array( 'compare', 'vs', 'versus', 'compared to', 'this week vs', 'growth' ),
			'patterns'  => array(
				'/(?:compare|comparison)\s+(?:orders?|sales|revenue)/i',
				'/(?:this|last)\s+(?:week|month|year)\s+(?:vs|versus|compared)/i',
				'/(?:sales|revenue|orders?)\s+(?:vs|versus|compared)/i',
				'/(?:are\s+we|how\s+are\s+we)\s+(?:doing|growing)/i',
			),
			'ability'   => 'afw/analytics/comparison',
			'extractor' => 'extract_comparison_params',
			'priority'  => 10,
		);

		// Category sales.
		$this->intent_patterns['category_sales'] = array(
			'keywords'  => array( 'category sales', 'best category', 'top category', 'sales by category' ),
			'patterns'  => array(
				'/(?:category|categories)\s+(?:sales|performance|revenue)/i',
				'/(?:best|top)\s+(?:selling\s+)?(?:category|categories)/i',
				'/sales\s+by\s+category/i',
				'/which\s+(?:category|categories)\s+(?:sell|perform)/i',
			),
			'ability'   => 'afw/analytics/category-sales',
			'extractor' => 'extract_period_params',
			'priority'  => 9,
		);

		// Average order value.
		$this->intent_patterns['aov'] = array(
			'keywords'  => array( 'average order', 'aov', 'average cart', 'order value' ),
			'patterns'  => array(
				'/(?:average|avg)\s+(?:order|cart)\s+(?:value|size)/i',
				'/\baov\b/i',
				'/(?:what|how\s+much)\s+(?:is|are)\s+(?:customers?|people)\s+spending/i',
			),
			'ability'   => 'afw/analytics/aov',
			'extractor' => 'extract_period_params',
			'priority'  => 9,
		);

		// Failed orders.
		$this->intent_patterns['failed_orders'] = array(
			'keywords'  => array( 'failed order', 'failed payment', 'payment failure', 'declined' ),
			'patterns'  => array(
				'/(?:failed|declined)\s+(?:orders?|payments?)/i',
				'/(?:orders?|payments?)\s+(?:that\s+)?failed/i',
				'/payment\s+(?:failures?|declines?)/i',
			),
			'ability'   => 'afw/orders/failed',
			'extractor' => 'extract_days_params',
			'priority'  => 9,
		);

		// Pending reviews.
		$this->intent_patterns['pending_reviews'] = array(
			'keywords'  => array( 'pending review', 'unapproved review', 'review moderation', 'approve review' ),
			'patterns'  => array(
				'/(?:pending|unapproved|awaiting)\s+reviews?/i',
				'/reviews?\s+(?:to\s+)?(?:approve|moderate)/i',
				'/(?:any|are\s+there)\s+reviews?\s+(?:pending|waiting)/i',
			),
			'ability'   => 'afw/products/pending-reviews',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 9,
		);

		// Product availability (for customers).
		$this->intent_patterns['product_availability'] = array(
			'keywords'  => array( 'in stock', 'available', 'can i buy', 'is there', 'do you have' ),
			'patterns'  => array(
				'/(?:is|are)\s+.+\s+(?:in\s+stock|available)/i',
				'/(?:do\s+you|does\s+the\s+store)\s+have\s+.+/i',
				'/(?:can\s+i|is\s+it\s+possible\s+to)\s+(?:buy|get|order)\s+.+/i',
				'/(?:check|what\'?s?)\s+(?:the\s+)?(?:stock|availability)/i',
			),
			'ability'   => 'afw/products/availability',
			'extractor' => 'extract_product_availability_params',
			'priority'  => 8,
		);

		// Related/similar products (for customers).
		$this->intent_patterns['related_products'] = array(
			'keywords'  => array( 'similar', 'like this', 'recommend', 'suggestion', 'alternative' ),
			'patterns'  => array(
				'/(?:similar|related|like\s+this)\s+products?/i',
				'/(?:recommend|suggest)\s+(?:me\s+)?(?:some\s+)?products?/i',
				'/(?:any|what)\s+(?:alternatives?|other\s+options?)/i',
				'/(?:show|find)\s+(?:me\s+)?(?:similar|related)/i',
			),
			'ability'   => 'afw/products/related',
			'extractor' => 'extract_related_products_params',
			'priority'  => 8,
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
			'keywords'  => array( 'coupons', 'coupon', 'discount code', 'promo code', 'list coupon', 'show coupon', 'what coupon', 'my coupon' ),
			'patterns'  => array(
				'/(?:list|show|all|active)\s*coupons?/i',
				'/discount\s*codes?/i',
				'/promo\s*codes?/i',
				'/(?:what|which)\s+coupons?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/coupons?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/(?:my|our)\s+coupons?/i',
				'/(?:give|share|tell)\s+(?:me\s+)?(?:the\s+)?coupon/i',
			),
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

		// Content categories.
		$this->intent_patterns['content_categories'] = array(
			'keywords'  => array( 'categories', 'product categories', 'category list', 'show categories' ),
			'patterns'  => array(
				'/(?:list|show|all|what)\s+(?:product\s+)?categories/i',
				'/product\s+categories/i',
				'/categories\s+(?:do\s+)?(?:we|i)\s+have/i',
				'/(?:my|our)\s+categories/i',
			),
			'ability'   => 'afw/content/categories',
			'extractor' => 'extract_content_params',
			'priority'  => 6,
		);

		// Content tags.
		$this->intent_patterns['content_tags'] = array(
			'keywords'  => array( 'tags', 'product tags', 'tag list', 'show tags' ),
			'patterns'  => array(
				'/(?:list|show|all|what)\s+(?:product\s+)?tags/i',
				'/product\s+tags/i',
				'/tags\s+(?:do\s+)?(?:we|i)\s+have/i',
				'/(?:my|our)\s+tags/i',
			),
			'ability'   => 'afw/content/tags',
			'extractor' => 'extract_content_params',
			'priority'  => 6,
		);

		// Customer search.
		$this->intent_patterns['customer_search'] = array(
			'keywords'  => array( 'search customer', 'find customer', 'look up customer', 'customer named', 'customer email' ),
			'patterns'  => array(
				'/search\s+(?:for\s+)?customers?/i',
				'/find\s+(?:a\s+)?customer/i',
				'/look\s*up\s+customer/i',
				'/customer\s+(?:named|called|with\s+email)/i',
				'/who\s+is\s+customer/i',
			),
			'ability'   => 'afw/customers/search',
			'extractor' => 'extract_customer_search_params',
			'priority'  => 8,
		);

		// Customer orders history.
		$this->intent_patterns['customer_orders'] = array(
			'keywords'  => array( 'customer order', 'orders from customer', 'order history', 'customer purchase', 'bought by' ),
			'patterns'  => array(
				'/orders?\s+(?:from|by|for)\s+customer/i',
				'/customer\s*#?\s*\d+.*orders?/i',
				'/(?:order|purchase)\s+history\s+(?:for|of)/i',
				'/what\s+(?:did|has)\s+customer.*(?:order|buy|purchase)/i',
				'/(?:orders?|purchases?)\s+(?:made\s+)?by/i',
			),
			'ability'   => 'afw/customers/orders',
			'extractor' => 'extract_customer_orders_params',
			'priority'  => 8,
		);

		// Shipping classes.
		$this->intent_patterns['shipping_classes'] = array(
			'keywords'  => array( 'shipping class', 'shipping classes', 'freight class', 'delivery class' ),
			'patterns'  => array(
				'/shipping\s+class(?:es)?/i',
				'/(?:list|show|what)\s+shipping\s+class/i',
				'/(?:freight|delivery)\s+class/i',
			),
			'ability'   => 'afw/store/shipping-classes',
			'extractor' => null,
			'priority'  => 7,
		);

		// Tax classes.
		$this->intent_patterns['tax_classes'] = array(
			'keywords'  => array( 'tax class', 'tax classes', 'vat class', 'tax type' ),
			'patterns'  => array(
				'/tax\s+class(?:es)?/i',
				'/(?:list|show|what)\s+tax\s+class/i',
				'/(?:vat|sales\s+tax)\s+class/i',
			),
			'ability'   => 'afw/store/tax-classes',
			'extractor' => null,
			'priority'  => 7,
		);

		// URL for customer profile.
		$this->intent_patterns['url_customer'] = array(
			'keywords'  => array( 'customer url', 'customer link', 'link to customer', 'customer profile' ),
			'patterns'  => array(
				'/(?:url|link)\s+(?:to|for|of)\s+(?:the\s+)?customer/i',
				'/customer\s+(?:url|link|profile\s+link)/i',
				'/(?:get|show)\s+(?:the\s+)?customer\s+(?:url|link)/i',
			),
			'ability'   => 'afw/urls/customer',
			'extractor' => 'extract_customer_id',
			'priority'  => 9,
		);

		// URL for page.
		$this->intent_patterns['url_page'] = array(
			'keywords'  => array( 'page url', 'page link', 'link to page', 'wordpress page' ),
			'patterns'  => array(
				'/(?:url|link)\s+(?:to|for|of)\s+(?:the\s+)?page/i',
				'/page\s+(?:url|link)/i',
				'/(?:get|show)\s+(?:the\s+)?(?:page\s+)?url\s+(?:for|of)\s+page/i',
			),
			'ability'   => 'afw/urls/page',
			'extractor' => 'extract_page_params',
			'priority'  => 8,
		);

		// =====================================================
		// Additional Customer-Facing Intent Patterns
		// =====================================================

		// Products on sale.
		$this->intent_patterns['products_on_sale'] = array(
			'keywords'  => array( 'on sale', 'sale items', 'discounted', 'deals', 'sales', 'what\'s on sale' ),
			'patterns'  => array(
				'/(?:what|show|list|any|are\s+there)\s+(?:products?\s+)?(?:on\s+)?sale/i',
				'/sale\s+(?:items?|products?)/i',
				'/(?:current|today\'?s?)\s+(?:sales?|deals?)/i',
				'/(?:discounted|reduced)\s+(?:items?|products?)/i',
			),
			'ability'   => 'afw/products/on-sale',
			'extractor' => 'extract_sale_products_params',
			'priority'  => 8,
		);

		// Featured products.
		$this->intent_patterns['products_featured'] = array(
			'keywords'  => array( 'featured', 'featured products', 'recommended', 'top picks', 'best products' ),
			'patterns'  => array(
				'/(?:show|list|what\s+are)\s+(?:the\s+)?featured\s+(?:products?|items?)/i',
				'/featured\s+(?:products?|items?)/i',
				'/(?:top|best)\s+(?:picks?|recommendations?)/i',
				'/(?:recommended|popular)\s+(?:products?|items?)/i',
			),
			'ability'   => 'afw/products/featured',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 7,
		);

		// Available coupons.
		$this->intent_patterns['coupons_available'] = array(
			'keywords'  => array( 'coupon', 'discount code', 'promo code', 'coupons', 'discount', 'voucher' ),
			'patterns'  => array(
				'/(?:any|available|current|valid)\s+(?:coupon|discount|promo)\s*(?:code)?s?/i',
				'/(?:do\s+you\s+have|is\s+there)\s+(?:a\s+)?(?:coupon|discount|promo)/i',
				'/(?:show|give|list)\s+(?:me\s+)?(?:coupon|discount|promo)\s*(?:code)?s?/i',
				'/(?:what\s+)?(?:coupon|discount|promo)\s*(?:code)?s?\s+(?:can\s+I\s+use|available)/i',
			),
			'ability'   => 'afw/coupons/available',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 9,
		);

		// Product by SKU.
		$this->intent_patterns['product_by_sku'] = array(
			'keywords'  => array( 'sku', 'product code', 'item code', 'part number' ),
			'patterns'  => array(
				'/sku\s*[:#]?\s*([a-zA-Z0-9_-]+)/i',
				'/product\s+(?:code|sku)\s*[:#]?\s*([a-zA-Z0-9_-]+)/i',
				'/find\s+(?:by\s+)?sku\s+([a-zA-Z0-9_-]+)/i',
			),
			'ability'   => 'afw/products/by-sku',
			'extractor' => 'extract_sku_params',
			'priority'  => 10,
		);

		// =====================================================
		// Additional Admin Analytics Intent Patterns
		// =====================================================

		// Coupon statistics.
		$this->intent_patterns['coupon_stats'] = array(
			'keywords'  => array( 'coupon stats', 'coupon usage', 'coupon performance', 'discount stats', 'coupon report' ),
			'patterns'  => array(
				'/coupon\s+(?:stats?|statistics?|usage|performance|report)/i',
				'/(?:how\s+are|which)\s+coupons?\s+(?:performing|used)/i',
				'/(?:top|best|most\s+used)\s+coupons?/i',
			),
			'ability'   => 'afw/coupons/stats',
			'extractor' => 'extract_days_params',
			'priority'  => 8,
		);

		// Sales by product.
		$this->intent_patterns['sales_by_product'] = array(
			'keywords'  => array( 'product sales', 'sales by product', 'top selling', 'best sellers', 'product performance' ),
			'patterns'  => array(
				'/(?:sales|revenue)\s+(?:by|per)\s+product/i',
				'/product\s+(?:sales|performance|stats?)/i',
				'/(?:top|best)\s+sell(?:ing|ers?)/i',
				'/which\s+products?\s+(?:are\s+)?sell(?:ing|s?)\s+(?:best|most)/i',
			),
			'ability'   => 'afw/analytics/by-product',
			'extractor' => 'extract_sales_by_product_params',
			'priority'  => 8,
		);

		// Sales by location.
		$this->intent_patterns['sales_by_location'] = array(
			'keywords'  => array( 'sales by location', 'geographic sales', 'sales by country', 'regional sales', 'where are customers' ),
			'patterns'  => array(
				'/(?:sales|revenue|orders?)\s+(?:by|per)\s+(?:location|country|region|state)/i',
				'/(?:geographic|regional)\s+(?:sales|breakdown)/i',
				'/where\s+(?:are\s+)?(?:my\s+)?(?:customers?|orders?)\s+(?:from|coming)/i',
				'/(?:top|main)\s+(?:countries?|locations?|regions?)/i',
			),
			'ability'   => 'afw/analytics/by-location',
			'extractor' => 'extract_days_params',
			'priority'  => 8,
		);

		// Processing orders count.
		$this->intent_patterns['processing_count'] = array(
			'keywords'  => array( 'processing count', 'how many orders', 'orders to ship', 'orders waiting', 'unfulfilled' ),
			'patterns'  => array(
				'/how\s+many\s+(?:orders?\s+)?(?:to\s+)?(?:process|ship|fulfill)/i',
				'/(?:unfulfilled|unshipped|pending)\s+orders?\s+count/i',
				'/orders?\s+(?:to\s+be\s+)?(?:processed|shipped|fulfilled)/i',
				'/processing\s+(?:orders?\s+)?count/i',
			),
			'ability'   => 'afw/orders/processing-count',
			'extractor' => 'extract_empty_params',
			'priority'  => 7,
		);

		// Tax collected.
		$this->intent_patterns['tax_collected'] = array(
			'keywords'  => array( 'tax collected', 'tax report', 'taxes', 'tax summary', 'sales tax' ),
			'patterns'  => array(
				'/(?:tax|taxes)\s+(?:collected|report|summary)/i',
				'/how\s+much\s+tax\s+(?:collected|received)/i',
				'/(?:sales|total)\s+tax\s+(?:collected|amount)/i',
			),
			'ability'   => 'afw/analytics/tax-collected',
			'extractor' => 'extract_days_params',
			'priority'  => 7,
		);

		// Most stocked products.
		$this->intent_patterns['most_stocked'] = array(
			'keywords'  => array( 'most stocked', 'highest stock', 'most inventory', 'overstocked' ),
			'patterns'  => array(
				'/(?:most|highest)\s+(?:stocked?|inventory)/i',
				'/products?\s+with\s+(?:most|highest)\s+stock/i',
				'/(?:overstocked?|excess)\s+(?:products?|inventory)/i',
			),
			'ability'   => 'afw/products/most-stocked',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 7,
		);

		// Low stock products.
		$this->intent_patterns['low_stock'] = array(
			'keywords'  => array( 'low stock', 'running low', 'almost out', 'need restock', 'low inventory' ),
			'patterns'  => array(
				'/(?:low|running\s+low)\s+(?:on\s+)?stock/i',
				'/(?:products?|items?)\s+(?:running|almost)\s+(?:low|out)/i',
				'/(?:need|needs?)\s+(?:to\s+)?restock/i',
				'/low\s+(?:stock|inventory)\s+(?:products?|items?|alert)/i',
			),
			'ability'   => 'afw/products/low-stock',
			'extractor' => 'extract_low_stock_params',
			'priority'  => 8,
		);

		// Customer lifetime value.
		$this->intent_patterns['customer_lifetime'] = array(
			'keywords'  => array( 'lifetime value', 'customer value', 'total spent', 'customer history', 'clv', 'ltv' ),
			'patterns'  => array(
				'/(?:customer|user)\s+(?:lifetime\s+)?value/i',
				'/(?:how\s+much|total)\s+(?:has\s+)?(?:customer|user)\s+spent/i',
				'/(?:clv|ltv)\s+(?:for|of)\s+(?:customer|user)/i',
				'/customer\s+(?:purchase|order)\s+history/i',
			),
			'ability'   => 'afw/customers/lifetime-value',
			'extractor' => 'extract_customer_lookup_params',
			'priority'  => 8,
		);

		// ========================================
		// Content Generation Intents (Sprint 4.1)
		// ========================================

		// Generate product title.
		$this->intent_patterns['content_product_title'] = array(
			'keywords'  => array( 'generate title', 'create title', 'write title', 'product title', 'new title', 'better title' ),
			'patterns'  => array(
				'/(?:generate|create|write|make)\s+(?:a\s+)?(?:product\s+)?title/i',
				'/(?:new|better|seo|optimized)\s+(?:product\s+)?title/i',
				'/title\s+(?:for|of)\s+(?:product|item)/i',
				'/product\s+(?:#?\d+|id\s*\d+).*?title/i',
			),
			'ability'   => 'afw/content/product-title',
			'extractor' => 'extract_content_generation_params',
			'priority'  => 9,
		);

		// Generate product description.
		$this->intent_patterns['content_product_description'] = array(
			'keywords'  => array( 'generate description', 'create description', 'write description', 'product description', 'new description' ),
			'patterns'  => array(
				'/(?:generate|create|write|make)\s+(?:a\s+)?(?:product\s+)?description/i',
				'/(?:new|better|seo|optimized|full)\s+(?:product\s+)?description/i',
				'/description\s+(?:for|of)\s+(?:product|item)/i',
				'/product\s+(?:#?\d+|id\s*\d+).*?description/i',
			),
			'ability'   => 'afw/content/product-description',
			'extractor' => 'extract_content_generation_params',
			'priority'  => 9,
		);

		// Generate short description.
		$this->intent_patterns['content_short_description'] = array(
			'keywords'  => array( 'short description', 'excerpt', 'summary', 'brief description' ),
			'patterns'  => array(
				'/(?:generate|create|write|make)\s+(?:a\s+)?short\s+description/i',
				'/short\s+description\s+(?:for|of)/i',
				'/(?:product|item)\s+(?:excerpt|summary)/i',
				'/brief\s+description/i',
			),
			'ability'   => 'afw/content/short-description',
			'extractor' => 'extract_content_generation_params',
			'priority'  => 9,
		);

		// Generate meta description.
		$this->intent_patterns['content_meta_description'] = array(
			'keywords'  => array( 'meta description', 'seo description', 'search description', 'yoast', 'rankmath' ),
			'patterns'  => array(
				'/(?:generate|create|write|make)\s+(?:a\s+)?meta\s+description/i',
				'/(?:seo|search|google)\s+description/i',
				'/meta\s+(?:desc|description)\s+(?:for|of)/i',
				'/(?:yoast|rankmath|seo)\s+(?:meta\s+)?description/i',
			),
			'ability'   => 'afw/content/meta-description',
			'extractor' => 'extract_content_generation_params',
			'priority'  => 9,
		);

		// Generate product tags.
		$this->intent_patterns['content_product_tags'] = array(
			'keywords'  => array( 'generate tags', 'create tags', 'product tags', 'suggest tags', 'new tags' ),
			'patterns'  => array(
				'/(?:generate|create|suggest|make)\s+(?:product\s+)?tags/i',
				'/(?:new|better|seo)\s+tags\s+(?:for|of)/i',
				'/tags\s+(?:for|of)\s+(?:product|item)/i',
				'/product\s+(?:#?\d+|id\s*\d+).*?tags/i',
			),
			'ability'   => 'afw/content/product-tags',
			'extractor' => 'extract_content_generation_params',
			'priority'  => 9,
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
	 * Extract last order parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_last_order_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array();

		// Check if filtering by status.
		$statuses = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
		foreach ( $statuses as $status ) {
			if ( strpos( $message_lower, $status ) !== false ) {
				$params['status'] = $status;
				break;
			}
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
	 * Extract daily summary parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_daily_summary_params( $message ) {
		$params = array();

		// Check for yesterday.
		if ( strpos( strtolower( $message ), 'yesterday' ) !== false ) {
			$params['date'] = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		}

		// Check for specific date pattern.
		if ( preg_match( '/(\d{4}-\d{2}-\d{2})/', $message, $matches ) ) {
			$params['date'] = $matches[1];
		}

		return $params;
	}

	/**
	 * Extract basic limit parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_basic_limit_params( $message ) {
		$params = array();

		// Extract limit if mentioned.
		if ( preg_match( '/(?:top|first|show|limit)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 100 );
		}

		return $params;
	}

	/**
	 * Extract days parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_days_params( $message ) {
		$params = array();

		// Extract days if mentioned.
		if ( preg_match( '/(?:last|past)\s+(\d+)\s+days?/i', $message, $matches ) ) {
			$params['days'] = min( (int) $matches[1], 365 );
		} elseif ( strpos( strtolower( $message ), 'this week' ) !== false ) {
			$params['days'] = 7;
		} elseif ( strpos( strtolower( $message ), 'this month' ) !== false ) {
			$params['days'] = 30;
		}

		return $params;
	}

	/**
	 * Extract period parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_period_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'period' => 'month' );

		if ( strpos( $message_lower, 'this week' ) !== false || strpos( $message_lower, 'week' ) !== false ) {
			$params['period'] = 'week';
		} elseif ( strpos( $message_lower, 'this year' ) !== false || strpos( $message_lower, 'year' ) !== false ) {
			$params['period'] = 'year';
		}

		return $params;
	}

	/**
	 * Extract repeat customers parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_repeat_customers_params( $message ) {
		$params = array(
			'min_orders' => 2,
			'limit'      => 20,
		);

		// Check for minimum orders.
		if ( preg_match( '/(\d+)\+?\s+orders?/i', $message, $matches ) ) {
			$params['min_orders'] = max( 2, (int) $matches[1] );
		}

		// Extract limit.
		if ( preg_match( '/(?:top|first|show)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 100 );
		}

		return $params;
	}

	/**
	 * Extract comparison parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_comparison_params( $message ) {
		$message_lower = strtolower( $message );
		$params        = array( 'period' => 'week' );

		if ( strpos( $message_lower, 'month' ) !== false ) {
			$params['period'] = 'month';
		} elseif ( strpos( $message_lower, 'quarter' ) !== false ) {
			$params['period'] = 'quarter';
		} elseif ( strpos( $message_lower, 'year' ) !== false ) {
			$params['period'] = 'year';
		}

		return $params;
	}

	/**
	 * Extract product availability parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_product_availability_params( $message ) {
		$params = array();

		// Try to extract product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		}

		// Try to extract product name (quoted or after keywords).
		if ( preg_match( '/"([^"]+)"/i', $message, $matches ) ) {
			$params['product_name'] = $matches[1];
		} elseif ( preg_match( '/(?:is|are|do\s+you\s+have|check)\s+(.+?)(?:\s+(?:in\s+stock|available)|$)/i', $message, $matches ) ) {
			// Clean up the extracted name.
			$name = trim( $matches[1] );
			$name = preg_replace( '/^(?:the|any|some)\s+/i', '', $name );
			if ( strlen( $name ) > 2 ) {
				$params['product_name'] = $name;
			}
		}

		return $params;
	}

	/**
	 * Extract related products parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_related_products_params( $message ) {
		$params = array( 'limit' => 5 );

		// Try to extract product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		}

		// Try to extract category.
		if ( preg_match( '/(?:in|from|category)\s+([a-zA-Z\s]+?)(?:\s+category)?$/i', $message, $matches ) ) {
			$params['category'] = trim( $matches[1] );
		}

		// Extract limit.
		if ( preg_match( '/(?:show|give|find)\s+(?:me\s+)?(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 20 );
		}

		return $params;
	}

	/**
	 * Return empty parameters array.
	 *
	 * @param string $message The message (unused but required by extractor interface).
	 * @return array Empty parameters.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	 */
	private function extract_empty_params( $message ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by extractor interface.
		return array();
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
			'shipping'     => 'shipping',
			'tax'          => 'tax',
			'payment'      => 'payments',
			'checkout'     => 'payments',
			'email'        => 'emails',
			'notification' => 'emails',
			'account'      => 'accounts',
			'product'      => 'products',
			'inventory'    => 'products',
			'advanced'     => 'advanced',
			'integration'  => 'integration',
			'api'          => 'advanced',
			'webhook'      => 'advanced',
			'assistify'    => 'assistify',
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

	// =========================================================================
	// Additional Extractor Methods for New Abilities
	// =========================================================================

	/**
	 * Extract sale products parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_sale_products_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for limit.
		if ( preg_match( '/(?:top|first|show\s+me|list)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = (int) $matches[1];
		}

		// Check for category.
		if ( preg_match( '/(?:in|from|under)\s+(?:the\s+)?(?:category\s+)?["\']?([^"\']+)["\']?\s+category/i', $message, $matches ) ) {
			$params['category'] = trim( $matches[1] );
		} elseif ( preg_match( '/(?:category|categories):\s*["\']?([^"\']+)["\']?/i', $message, $matches ) ) {
			$params['category'] = trim( $matches[1] );
		}

		return $params;
	}

	/**
	 * Extract SKU parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_sku_params( $message ) {
		$params = array();

		// Extract SKU from various patterns.
		if ( preg_match( '/sku\s*[:#]?\s*([a-zA-Z0-9_-]+)/i', $message, $matches ) ) {
			$params['sku'] = trim( $matches[1] );
		} elseif ( preg_match( '/product\s+(?:code|sku)\s*[:#]?\s*([a-zA-Z0-9_-]+)/i', $message, $matches ) ) {
			$params['sku'] = trim( $matches[1] );
		} elseif ( preg_match( '/find\s+(?:by\s+)?sku\s+([a-zA-Z0-9_-]+)/i', $message, $matches ) ) {
			$params['sku'] = trim( $matches[1] );
		}

		return $params;
	}

	/**
	 * Extract sales by product parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_sales_by_product_params( $message ) {
		$params = array(
			'days'  => 30,
			'limit' => 10,
		);

		// Check for specific product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		}

		// Check for days.
		if ( preg_match( '/(?:last|past)\s+(\d+)\s+days?/i', $message, $matches ) ) {
			$params['days'] = (int) $matches[1];
		} elseif ( preg_match( '/this\s+week/i', $message ) ) {
			$params['days'] = 7;
		} elseif ( preg_match( '/this\s+month/i', $message ) ) {
			$params['days'] = 30;
		}

		// Check for limit.
		if ( preg_match( '/(?:top|first|best)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract customer lookup parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_lookup_params( $message ) {
		$params = array();

		// Check for customer ID.
		if ( preg_match( '/(?:customer|user)\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['customer_id'] = (int) $matches[1];
		}

		// Check for email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $matches ) ) {
			$params['email'] = trim( $matches[1] );
		}

		return $params;
	}

	/**
	 * Extract content generation parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_content_generation_params( $message ) {
		$params = array();

		// Check for product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		} elseif ( preg_match( '/(?:id|ID)\s*[:=]?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		} elseif ( preg_match( '/\b(\d{2,})\b/', $message, $matches ) ) {
			// Assume 2+ digit number could be product ID if no other context.
			$params['product_id'] = (int) $matches[1];
		}

		// Check for tone.
		$tones         = array( 'professional', 'casual', 'luxury', 'playful' );
		$message_lower = strtolower( $message );
		foreach ( $tones as $tone ) {
			if ( strpos( $message_lower, $tone ) !== false ) {
				$params['tone'] = $tone;
				break;
			}
		}

		// Check for length (for descriptions).
		if ( preg_match( '/\b(short|brief|concise)\b/i', $message ) ) {
			$params['length'] = 'short';
		} elseif ( preg_match( '/\b(long|detailed|comprehensive)\b/i', $message ) ) {
			$params['length'] = 'long';
		} elseif ( preg_match( '/\b(medium|standard)\b/i', $message ) ) {
			$params['length'] = 'medium';
		}

		// Check for keywords.
		if ( preg_match( '/keywords?[:=]?\s*["\']?([^"\']+)["\']?/i', $message, $matches ) ) {
			$params['keywords'] = trim( $matches[1] );
		}

		// Check for apply flag.
		if ( preg_match( '/\b(?:apply|save|update)\b/i', $message ) ) {
			$params['apply'] = true;
		}

		// Check for number of tags.
		if ( preg_match( '/(\d+)\s*tags?/i', $message, $matches ) ) {
			$params['count'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract customer search parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_search_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $matches ) ) {
			$params['email'] = trim( $matches[1] );
		}

		// Check for name in quotes.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			$params['search'] = trim( $matches[1] );
		}

		// Check for "named/called X".
		if ( preg_match( '/(?:named|called)\s+([a-zA-Z\s]+)/i', $message, $matches ) ) {
			$params['search'] = trim( $matches[1] );
		}

		return $params;
	}

	/**
	 * Extract customer orders parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_orders_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for customer ID.
		if ( preg_match( '/customer\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['customer_id'] = (int) $matches[1];
		}

		// Check for email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $matches ) ) {
			$params['email'] = trim( $matches[1] );
		}

		// Check for limit.
		if ( preg_match( '/(?:last|recent|top)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract page parameters from message.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_page_params( $message ) {
		$params = array();

		// Check for page ID.
		if ( preg_match( '/page\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['page_id'] = (int) $matches[1];
		}

		// Check for quoted page name.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			$params['page_name'] = trim( $matches[1] );
		}

		// Check for "page X" or "X page".
		if ( preg_match( '/(?:page\s+|for\s+(?:the\s+)?page\s+)([a-zA-Z\s-]+)/i', $message, $matches ) ) {
			$name = trim( $matches[1] );
			if ( strlen( $name ) > 2 && ! preg_match( '/^(url|link)$/i', $name ) ) {
				$params['page_name'] = $name;
			}
		}

		return $params;
	}
}
