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

		// ========================================
		// ACTION Intents (Require Confirmation)
		// ========================================

		// Update order status (ACTION).
		$this->intent_patterns['action_update_order_status'] = array(
			'keywords'  => array( 'update order', 'change order', 'mark order', 'set order', 'order to processing', 'order to completed', 'order to shipped' ),
			'patterns'  => array(
				'/(?:update|change|set|mark)\s+order\s*#?\s*(\d+)\s+(?:to|as|status)\s+(\w+)/i',
				'/order\s*#?\s*(\d+)\s+(?:to|as)\s+(?:processing|completed|shipped|on-hold|cancelled|refunded)/i',
				'/mark\s+order\s*#?\s*(\d+)\s+(?:as\s+)?(\w+)/i',
				'/(?:ship|complete|cancel)\s+order\s*#?\s*(\d+)/i',
			),
			'ability'   => 'afw/orders/update-status',
			'extractor' => 'extract_order_status_update_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Process refund (ACTION).
		$this->intent_patterns['action_refund_order'] = array(
			'keywords'  => array( 'refund order', 'process refund', 'issue refund', 'give refund', 'refund customer' ),
			'patterns'  => array(
				'/refund\s+order\s*#?\s*(\d+)/i',
				'/(?:process|issue|give)\s+(?:a\s+)?refund\s+(?:for\s+)?order\s*#?\s*(\d+)/i',
				'/refund\s+\$?(\d+(?:\.\d{2})?)\s+(?:from|for|to)\s+order\s*#?\s*(\d+)/i',
			),
			'ability'   => 'afw/orders/refund',
			'extractor' => 'extract_refund_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Create product (ACTION).
		$this->intent_patterns['action_create_product'] = array(
			'keywords'  => array( 'create product', 'add product', 'new product', 'make product' ),
			'patterns'  => array(
				'/(?:create|add|make)\s+(?:a\s+)?(?:new\s+)?product\s+(?:called|named)\s+["\']?(.+?)["\']?$/i',
				'/(?:create|add)\s+(?:a\s+)?(?:new\s+)?product/i',
				'/new\s+product\s+["\']?(.+?)["\']?/i',
			),
			'ability'   => 'afw/products/create',
			'extractor' => 'extract_create_product_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Update product (ACTION).
		$this->intent_patterns['action_update_product'] = array(
			'keywords'  => array( 'update product', 'change product', 'edit product', 'modify product', 'set price', 'change price' ),
			'patterns'  => array(
				'/(?:update|change|edit|modify)\s+product\s*#?\s*(\d+)/i',
				'/(?:set|change)\s+(?:the\s+)?price\s+(?:of\s+)?product\s*#?\s*(\d+)\s+to\s+\$?(\d+(?:\.\d{2})?)/i',
				'/product\s*#?\s*(\d+)\s+price\s+(?:to|=)\s+\$?(\d+(?:\.\d{2})?)/i',
			),
			'ability'   => 'afw/products/update',
			'extractor' => 'extract_update_product_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Create coupon (ACTION).
		$this->intent_patterns['action_create_coupon'] = array(
			'keywords'  => array( 'create coupon', 'add coupon', 'new coupon', 'make coupon', 'generate coupon' ),
			'patterns'  => array(
				'/(?:create|add|make|generate)\s+(?:a\s+)?(?:new\s+)?coupon\s+(?:code\s+)?["\']?([A-Za-z0-9_-]+)["\']?/i',
				'/(?:create|add)\s+(?:a\s+)?(?:new\s+)?coupon/i',
				'/(?:new\s+)?coupon\s+(?:for|with)\s+(\d+)\s*%?\s+(?:off|discount)/i',
			),
			'ability'   => 'afw/coupons/create',
			'extractor' => 'extract_create_coupon_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Update coupon (ACTION).
		$this->intent_patterns['action_update_coupon'] = array(
			'keywords'  => array( 'update coupon', 'change coupon', 'edit coupon', 'modify coupon' ),
			'patterns'  => array(
				'/(?:update|change|edit|modify)\s+coupon\s+(?:code\s+)?["\']?([A-Za-z0-9_-]+)["\']?/i',
				'/(?:set|change)\s+coupon\s+["\']?([A-Za-z0-9_-]+)["\']?\s+(?:to|discount)\s+(\d+)/i',
			),
			'ability'   => 'afw/coupons/update',
			'extractor' => 'extract_update_coupon_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Delete coupon (ACTION).
		$this->intent_patterns['action_delete_coupon'] = array(
			'keywords'  => array( 'delete coupon', 'remove coupon', 'trash coupon' ),
			'patterns'  => array(
				'/(?:delete|remove|trash)\s+coupon\s+(?:code\s+)?["\']?([A-Za-z0-9_-]+)["\']?/i',
				'/(?:delete|remove)\s+coupon\s*#?\s*(\d+)/i',
			),
			'ability'   => 'afw/coupons/delete',
			'extractor' => 'extract_delete_coupon_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Add order note (ACTION).
		$this->intent_patterns['action_add_order_note'] = array(
			'keywords'  => array( 'add note', 'order note', 'note to order' ),
			'patterns'  => array(
				'/add\s+(?:a\s+)?note\s+to\s+order\s*#?\s*(\d+)/i',
				'/order\s*#?\s*(\d+)\s+note[:]\s*["\']?(.+?)["\']?$/i',
			),
			'ability'   => 'afw/orders/add-note',
			'extractor' => 'extract_order_note_params',
			'priority'  => 15,
			'is_action' => true,
		);

		// Register subscription intents (WooCommerce Subscriptions integration).
		$this->register_subscription_intents();

		// Register booking intents (WooCommerce Bookings integration).
		$this->register_booking_intents();

		// Register membership intents (WooCommerce Memberships integration).
		$this->register_membership_intents();

		// Register hotel booking intents (Hotel Booking for WooCommerce integration).
		$this->register_hotel_booking_intents();

		/**
		 * Filter intent patterns to allow customization.
		 *
		 * @since 1.0.0
		 * @param array $intent_patterns The intent patterns array.
		 */
		$this->intent_patterns = apply_filters( 'assistify_intent_patterns', $this->intent_patterns );
	}

	/**
	 * Register subscription intent patterns.
	 *
	 * Patterns for WooCommerce Subscriptions integration.
	 * These only work when WC Subscriptions is installed and active.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_subscription_intents() {
		// Check if WC Subscriptions is active.
		if ( ! class_exists( 'WC_Subscriptions' ) && ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}

		// Admin: List subscriptions.
		$this->intent_patterns['subscription_list'] = array(
			'keywords'  => array( 'subscriptions', 'list subscription', 'show subscription', 'all subscription', 'active subscription' ),
			'patterns'  => array(
				'/(?:list|show|all|active|cancelled|on-hold|expired)\s*subscriptions?/i',
				'/subscriptions?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/how\s+many\s+subscriptions?/i',
				'/(?:what|which)\s+subscriptions?/i',
			),
			'ability'   => 'afw/subscriptions/list',
			'extractor' => 'extract_subscription_list_params',
			'priority'  => 6,
		);

		// Admin: Get subscription details.
		$this->intent_patterns['subscription_get'] = array(
			'keywords'  => array( 'subscription #', 'subscription id', 'show subscription', 'get subscription' ),
			'patterns'  => array(
				'/subscription\s*#?\s*(\d+)/i',
				'/show\s+subscription\s*#?\s*(\d+)/i',
				'/get\s+subscription\s*#?\s*(\d+)/i',
			),
			'ability'   => 'afw/subscriptions/get',
			'extractor' => 'extract_subscription_id',
			'priority'  => 10,
		);

		// Admin: Search subscriptions.
		$this->intent_patterns['subscription_search'] = array(
			'keywords'  => array( 'search subscription', 'find subscription', 'subscription for' ),
			'patterns'  => array(
				'/search\s+subscriptions?\s+(?:for\s+)?(.+)/i',
				'/find\s+subscriptions?\s+(.+)/i',
				'/subscriptions?\s+for\s+(.+)/i',
			),
			'ability'   => 'afw/subscriptions/search',
			'extractor' => 'extract_subscription_search_params',
			'priority'  => 8,
		);

		// Admin: Subscription analytics.
		$this->intent_patterns['subscription_analytics'] = array(
			'keywords'  => array( 'mrr', 'monthly recurring', 'subscription analytics', 'churn rate', 'ltv', 'lifetime value', 'subscription revenue' ),
			'patterns'  => array(
				'/\b(?:mrr|arr)\b/i',
				'/monthly\s+recurring\s+revenue/i',
				'/subscription\s+(?:analytics?|stats?|metrics?|revenue)/i',
				'/churn\s+(?:rate|percentage)/i',
				'/(?:customer\s+)?(?:ltv|lifetime\s+value)/i',
				'/(?:how\s+many\s+)?(?:active\s+)?subscribers?/i',
			),
			'ability'   => 'afw/subscriptions/analytics',
			'extractor' => 'extract_period_params',
			'priority'  => 9,
		);

		// Admin: Churn risk.
		$this->intent_patterns['subscription_churn_risk'] = array(
			'keywords'  => array( 'churn risk', 'at risk', 'risk of churn', 'likely to cancel' ),
			'patterns'  => array(
				'/(?:churn|churning)\s+risk/i',
				'/(?:at\s+risk|risky)\s+subscriptions?/i',
				'/subscriptions?\s+(?:at\s+)?risk/i',
				'/(?:likely|about)\s+to\s+(?:cancel|churn)/i',
				'/(?:who|which\s+customers?)\s+(?:might|will)\s+(?:cancel|churn)/i',
			),
			'ability'   => 'afw/subscriptions/churn-risk',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 9,
		);

		// Admin: Failed subscription payments.
		$this->intent_patterns['subscription_failed_payments'] = array(
			'keywords'  => array( 'failed subscription', 'subscription failed', 'failed renewal', 'renewal failed' ),
			'patterns'  => array(
				'/failed\s+subscription\s+(?:payments?|renewals?)/i',
				'/subscription\s+(?:payment|renewal)\s+(?:failures?|failed)/i',
				'/(?:renewals?|subscriptions?)\s+(?:that\s+)?failed/i',
				'/(?:on-hold|on\s+hold)\s+subscriptions?/i',
			),
			'ability'   => 'afw/subscriptions/failed-payments',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 9,
		);

		// Admin: Expiring subscriptions.
		$this->intent_patterns['subscription_expiring'] = array(
			'keywords'  => array( 'expiring subscription', 'subscription expiring', 'ending soon' ),
			'patterns'  => array(
				'/(?:expiring|ending)\s+subscriptions?/i',
				'/subscriptions?\s+(?:expiring|ending)\s+(?:soon|this)/i',
				'/subscriptions?\s+(?:about\s+to|going\s+to)\s+(?:expire|end)/i',
			),
			'ability'   => 'afw/subscriptions/expiring-soon',
			'extractor' => 'extract_subscription_expiring_params',
			'priority'  => 9,
		);

		// Customer: My subscriptions.
		$this->intent_patterns['my_subscriptions'] = array(
			'keywords'  => array( 'my subscription', 'my membership', 'my plan', 'subscription status' ),
			'patterns'  => array(
				'/(?:my|view\s+my)\s+subscriptions?/i',
				'/(?:what|which)\s+subscriptions?\s+do\s+i\s+have/i',
				'/(?:my|view\s+my)\s+(?:membership|plan)/i',
				'/subscription\s+status/i',
			),
			'ability'   => 'afw/subscription/my-subscriptions',
			'extractor' => 'extract_customer_subscription_params',
			'priority'  => 7,
			'scope'     => 'customer',
		);

		// Customer: Next payment.
		$this->intent_patterns['subscription_next_payment'] = array(
			'keywords'  => array( 'next payment', 'when charged', 'next bill', 'payment due', 'renewal date' ),
			'patterns'  => array(
				'/(?:when\s+is\s+)?(?:my\s+)?next\s+(?:payment|billing|charge|renewal)/i',
				'/(?:when\s+)?(?:will\s+)?(?:i\s+)?(?:be\s+)?(?:charged|billed)/i',
				'/(?:payment|renewal)\s+(?:date|due)/i',
				'/how\s+much\s+(?:is\s+)?(?:my\s+)?next\s+(?:payment|bill)/i',
			),
			'ability'   => 'afw/subscription/next-payment',
			'extractor' => 'extract_customer_subscription_params',
			'priority'  => 8,
			'scope'     => 'customer',
		);

		// Customer: Pause subscription (ACTION).
		$this->intent_patterns['subscription_pause'] = array(
			'keywords'  => array( 'pause subscription', 'pause my subscription', 'put on hold', 'suspend subscription' ),
			'patterns'  => array(
				'/pause\s+(?:my\s+)?subscription/i',
				'/(?:put|place)\s+(?:my\s+)?subscription\s+on\s+hold/i',
				'/(?:suspend|hold)\s+(?:my\s+)?subscription/i',
				'/(?:i\s+)?want\s+to\s+pause/i',
			),
			'ability'   => 'afw/subscription/pause',
			'extractor' => 'extract_customer_subscription_action_params',
			'priority'  => 10,
			'scope'     => 'customer',
			'is_action' => true,
		);

		// Customer: Resume subscription (ACTION).
		$this->intent_patterns['subscription_resume'] = array(
			'keywords'  => array( 'resume subscription', 'reactivate', 'unpause', 'restart subscription' ),
			'patterns'  => array(
				'/(?:resume|reactivate|unpause|restart)\s+(?:my\s+)?subscription/i',
				'/(?:take|remove)\s+(?:my\s+)?subscription\s+off\s+hold/i',
				'/(?:i\s+)?want\s+to\s+(?:resume|reactivate)/i',
			),
			'ability'   => 'afw/subscription/resume',
			'extractor' => 'extract_customer_subscription_action_params',
			'priority'  => 10,
			'scope'     => 'customer',
			'is_action' => true,
		);

		// Customer: Cancel subscription (ACTION).
		$this->intent_patterns['subscription_cancel'] = array(
			'keywords'  => array( 'cancel subscription', 'cancel my subscription', 'end subscription', 'stop subscription' ),
			'patterns'  => array(
				'/cancel\s+(?:my\s+)?subscription/i',
				'/(?:end|stop|terminate)\s+(?:my\s+)?subscription/i',
				'/(?:i\s+)?(?:want|would\s+like)\s+to\s+cancel/i',
				'/(?:don\'?t|do\s+not)\s+want\s+(?:my\s+)?subscription/i',
			),
			'ability'   => 'afw/subscription/cancel',
			'extractor' => 'extract_customer_subscription_action_params',
			'priority'  => 10,
			'scope'     => 'customer',
			'is_action' => true,
		);

		// Customer: Update payment method.
		$this->intent_patterns['subscription_update_payment'] = array(
			'keywords'  => array( 'update payment', 'change card', 'new card', 'payment method' ),
			'patterns'  => array(
				'/(?:update|change)\s+(?:my\s+)?(?:payment|card|credit\s+card)/i',
				'/(?:new|different)\s+(?:payment|card|credit\s+card)/i',
				'/(?:my\s+)?(?:payment\s+method|card)\s+(?:expired|declined|changed)/i',
			),
			'ability'   => 'afw/subscription/update-payment',
			'extractor' => 'extract_customer_subscription_params',
			'priority'  => 8,
			'scope'     => 'customer',
		);

		// Customer: Update address.
		$this->intent_patterns['subscription_update_address'] = array(
			'keywords'  => array( 'update address', 'change address', 'new address', 'shipping address' ),
			'patterns'  => array(
				'/(?:update|change)\s+(?:my\s+)?(?:shipping\s+)?address/i',
				'/(?:new|different)\s+(?:shipping\s+)?address/i',
				'/(?:i\s+)?(?:moved|moving)/i',
				'/send\s+(?:to|my\s+subscription\s+to)\s+(?:a\s+)?(?:new|different)\s+address/i',
			),
			'ability'   => 'afw/subscription/update-address',
			'extractor' => 'extract_customer_subscription_params',
			'priority'  => 8,
			'scope'     => 'customer',
		);
	}

	/**
	 * Register booking intent patterns.
	 *
	 * Patterns for WooCommerce Bookings integration.
	 * These only work when WC Bookings is installed and active.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_booking_intents() {
		// Check if WC Bookings is active.
		if ( ! class_exists( 'WC_Bookings' ) && ! function_exists( 'get_wc_booking' ) ) {
			return;
		}

		// Admin: List bookings.
		$this->intent_patterns['booking_list'] = array(
			'keywords'  => array( 'bookings', 'list booking', 'show booking', 'all booking', 'reservations', 'appointments' ),
			'patterns'  => array(
				'/(?:list|show|all|confirmed|paid|cancelled|complete)\s*bookings?/i',
				'/bookings?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/how\s+many\s+bookings?/i',
				'/(?:what|which)\s+bookings?/i',
				'/(?:list|show|all)\s*(?:reservations?|appointments?)/i',
			),
			'ability'   => 'afw/bookings/list',
			'extractor' => 'extract_booking_list_params',
			'priority'  => 6,
		);

		// Admin: Get booking details.
		$this->intent_patterns['booking_get'] = array(
			'keywords'  => array( 'booking #', 'booking id', 'show booking', 'get booking', 'reservation #' ),
			'patterns'  => array(
				'/booking\s*#?\s*(\d+)/i',
				'/show\s+booking\s*#?\s*(\d+)/i',
				'/get\s+booking\s*#?\s*(\d+)/i',
				'/reservation\s*#?\s*(\d+)/i',
			),
			'ability'   => 'afw/bookings/get',
			'extractor' => 'extract_booking_id',
			'priority'  => 10,
		);

		// Admin: Today's bookings.
		$this->intent_patterns['booking_today'] = array(
			'keywords'  => array( 'today booking', 'booking today', 'today appointment', 'today schedule', 'today reservation' ),
			'patterns'  => array(
				'/(?:today\'?s?|todays)\s*(?:bookings?|appointments?|reservations?|schedule)/i',
				'/(?:bookings?|appointments?|reservations?)\s+(?:for\s+)?today/i',
				'/what\s+(?:do\s+)?(?:i|we)\s+have\s+today/i',
				'/(?:schedule|calendar)\s+(?:for\s+)?today/i',
			),
			'ability'   => 'afw/bookings/today',
			'extractor' => 'extract_booking_today_params',
			'priority'  => 9,
		);

		// Admin: Upcoming bookings.
		$this->intent_patterns['booking_upcoming'] = array(
			'keywords'  => array( 'upcoming booking', 'next booking', 'future booking', 'upcoming appointment', 'this week booking' ),
			'patterns'  => array(
				'/(?:upcoming|next|future|scheduled)\s*(?:bookings?|appointments?|reservations?)/i',
				'/(?:bookings?|appointments?)\s+(?:this|next)\s+(?:week|month)/i',
				'/what\s+(?:bookings?|appointments?)\s+(?:are\s+)?(?:coming|next)/i',
			),
			'ability'   => 'afw/bookings/upcoming',
			'extractor' => 'extract_booking_upcoming_params',
			'priority'  => 8,
		);

		// Admin: Search bookings.
		$this->intent_patterns['booking_search'] = array(
			'keywords'  => array( 'search booking', 'find booking', 'booking for', 'reservation for' ),
			'patterns'  => array(
				'/search\s+bookings?\s+(?:for\s+)?(.+)/i',
				'/find\s+bookings?\s+(.+)/i',
				'/bookings?\s+for\s+(?:customer\s+)?(.+)/i',
				'/(?:who\s+)?booked\s+(.+)/i',
			),
			'ability'   => 'afw/bookings/search',
			'extractor' => 'extract_booking_search_params',
			'priority'  => 8,
		);

		// Admin: Booking analytics.
		$this->intent_patterns['booking_analytics'] = array(
			'keywords'  => array( 'booking analytics', 'booking stats', 'booking revenue', 'booking report', 'popular service' ),
			'patterns'  => array(
				'/booking\s+(?:analytics?|stats?|statistics?|metrics?|report)/i',
				'/(?:how\s+many|total)\s+bookings?\s+(?:this|last)/i',
				'/booking\s+revenue/i',
				'/(?:popular|top|best)\s+(?:bookable\s+)?(?:services?|products?)/i',
				'/cancellation\s+rate/i',
			),
			'ability'   => 'afw/bookings/analytics',
			'extractor' => 'extract_booking_analytics_params',
			'priority'  => 8,
		);

		// Admin: Check availability.
		$this->intent_patterns['booking_availability'] = array(
			'keywords'  => array( 'availability', 'available slot', 'check availability', 'open slot' ),
			'patterns'  => array(
				'/(?:check|what|any)\s*(?:is\s+)?availability/i',
				'/available\s+(?:slots?|times?|dates?)/i',
				'/(?:when|what\s+times?)\s+(?:is|are)\s+available/i',
				'/(?:can\s+)?(?:i|we|they)\s+book/i',
			),
			'ability'   => 'afw/bookings/availability',
			'extractor' => 'extract_booking_availability_params',
			'priority'  => 7,
		);

		// Admin: List resources.
		$this->intent_patterns['booking_resources'] = array(
			'keywords'  => array( 'resources', 'bookable resource', 'booking resource', 'staff', 'room' ),
			'patterns'  => array(
				'/(?:list|show|all)\s*(?:bookable\s+)?resources?/i',
				'/(?:what|which)\s+resources?/i',
				'/(?:staff|rooms?|equipment)\s+(?:available|list)/i',
			),
			'ability'   => 'afw/bookings/resources',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 6,
		);

		// Admin: Update booking status.
		$this->intent_patterns['booking_update_status'] = array(
			'keywords'  => array( 'confirm booking', 'cancel booking', 'complete booking', 'mark booking' ),
			'patterns'  => array(
				'/(?:confirm|approve)\s+booking\s*#?\s*(\d+)/i',
				'/(?:cancel|reject)\s+booking\s*#?\s*(\d+)/i',
				'/(?:complete|finish)\s+booking\s*#?\s*(\d+)/i',
				'/(?:mark|set)\s+booking\s*#?\s*(\d+)\s+(?:as\s+)?(\w+)/i',
			),
			'ability'   => 'afw/bookings/update-status',
			'extractor' => 'extract_booking_status_update_params',
			'priority'  => 10,
			'is_action' => true,
		);

		// Customer: My bookings.
		$this->intent_patterns['my_bookings'] = array(
			'keywords'  => array( 'my booking', 'my appointment', 'my reservation', 'view booking' ),
			'patterns'  => array(
				'/(?:my|view\s+my)\s+(?:bookings?|appointments?|reservations?)/i',
				'/(?:what|which)\s+(?:bookings?|appointments?)\s+do\s+i\s+have/i',
				'/(?:do\s+i\s+have\s+any)\s+(?:bookings?|appointments?)/i',
				'/(?:show|list)\s+(?:my\s+)?(?:bookings?|appointments?)/i',
			),
			'ability'   => 'afw/booking/my-bookings',
			'extractor' => 'extract_customer_booking_params',
			'priority'  => 7,
			'scope'     => 'customer',
		);

		// Customer: Upcoming bookings.
		$this->intent_patterns['my_upcoming_bookings'] = array(
			'keywords'  => array( 'upcoming appointment', 'next appointment', 'my next booking', 'when is my booking' ),
			'patterns'  => array(
				'/(?:my\s+)?(?:upcoming|next|future)\s+(?:bookings?|appointments?)/i',
				'/when\s+is\s+my\s+(?:next\s+)?(?:booking|appointment)/i',
				'/(?:do\s+i\s+have\s+)?(?:any\s+)?upcoming\s+(?:bookings?|appointments?)/i',
			),
			'ability'   => 'afw/booking/upcoming',
			'extractor' => 'extract_customer_booking_params',
			'priority'  => 8,
			'scope'     => 'customer',
		);

		// Customer: Get booking details.
		$this->intent_patterns['my_booking_details'] = array(
			'keywords'  => array( 'booking details', 'appointment details', 'reservation details' ),
			'patterns'  => array(
				'/(?:details?\s+(?:of|for)\s+)?(?:my\s+)?booking\s*#?\s*(\d+)/i',
				'/(?:show|get)\s+(?:my\s+)?(?:booking|appointment)\s*#?\s*(\d+)/i',
				'/(?:what|when)\s+is\s+(?:my\s+)?booking\s*#?\s*(\d+)/i',
			),
			'ability'   => 'afw/booking/get-details',
			'extractor' => 'extract_customer_booking_details_params',
			'priority'  => 9,
			'scope'     => 'customer',
		);

		// Customer: Cancel booking (ACTION).
		$this->intent_patterns['cancel_booking'] = array(
			'keywords'  => array( 'cancel booking', 'cancel appointment', 'cancel reservation' ),
			'patterns'  => array(
				'/cancel\s+(?:my\s+)?(?:booking|appointment|reservation)/i',
				'/(?:i\s+)?(?:want|need)\s+to\s+cancel\s+(?:my\s+)?(?:booking|appointment)/i',
				'/(?:can\'?t|cannot|won\'?t)\s+(?:make|attend)\s+(?:my\s+)?(?:booking|appointment)/i',
			),
			'ability'   => 'afw/booking/cancel',
			'extractor' => 'extract_customer_booking_cancel_params',
			'priority'  => 10,
			'scope'     => 'customer',
			'is_action' => true,
		);

		// Customer: Check availability.
		$this->intent_patterns['check_booking_availability'] = array(
			'keywords'  => array( 'book', 'make appointment', 'schedule', 'available time', 'can i book' ),
			'patterns'  => array(
				'/(?:can\s+i|i\'?d?\s+like\s+to)\s+(?:book|schedule|make\s+(?:an?\s+)?(?:booking|appointment))/i',
				'/(?:is|are)\s+(?:there|any)\s+(?:available|open)\s+(?:slots?|times?)/i',
				'/(?:when\s+can\s+i|what\s+times?\s+(?:can\s+i|are\s+available\s+to))\s+book/i',
				'/(?:check|see)\s+(?:if\s+)?(?:there\'?s?\s+)?availability/i',
			),
			'ability'   => 'afw/booking/check-availability',
			'extractor' => 'extract_customer_availability_params',
			'priority'  => 7,
			'scope'     => 'customer',
		);
	}

	/**
	 * Register membership intent patterns.
	 *
	 * Patterns for WooCommerce Memberships integration.
	 * These only work when WC Memberships is installed and active.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_membership_intents() {
		// Check if WC Memberships is active.
		if ( ! function_exists( 'wc_memberships' ) && ! class_exists( 'WC_Memberships' ) ) {
			return;
		}

		// Admin: List memberships.
		$this->intent_patterns['membership_list'] = array(
			'keywords'  => array( 'memberships', 'list membership', 'show membership', 'all membership', 'active member' ),
			'patterns'  => array(
				'/(?:list|show|all|active|paused|expired|cancelled)\s*memberships?/i',
				'/memberships?\s+(?:do\s+)?(?:i|we)\s+have/i',
				'/how\s+many\s+memberships?/i',
				'/(?:what|which)\s+memberships?/i',
				'/(?:list|show|all)\s*members?/i',
			),
			'ability'   => 'afw/memberships/list',
			'extractor' => 'extract_membership_list_params',
			'priority'  => 6,
		);

		// Admin: Get membership details.
		$this->intent_patterns['membership_get'] = array(
			'keywords'  => array( 'membership #', 'membership id', 'show membership', 'get membership' ),
			'patterns'  => array(
				'/membership\s*#?\s*(\d+)/i',
				'/show\s+membership\s*#?\s*(\d+)/i',
				'/get\s+membership\s*#?\s*(\d+)/i',
				'/user\s+membership\s*#?\s*(\d+)/i',
			),
			'ability'   => 'afw/memberships/get',
			'extractor' => 'extract_membership_id',
			'priority'  => 10,
		);

		// Admin: List membership plans.
		$this->intent_patterns['membership_plans'] = array(
			'keywords'  => array( 'membership plan', 'plan', 'available plan', 'membership level', 'tier' ),
			'patterns'  => array(
				'/(?:list|show|all|available)\s*(?:membership\s+)?plans?/i',
				'/membership\s+(?:plans?|levels?|tiers?)/i',
				'/(?:what|which)\s+(?:membership\s+)?plans?/i',
			),
			'ability'   => 'afw/memberships/plans',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 7,
		);

		// Admin: Search memberships.
		$this->intent_patterns['membership_search'] = array(
			'keywords'  => array( 'search membership', 'find membership', 'membership for', 'member named' ),
			'patterns'  => array(
				'/search\s+memberships?\s+(?:for\s+)?(.+)/i',
				'/find\s+memberships?\s+(.+)/i',
				'/memberships?\s+for\s+(?:customer\s+)?(.+)/i',
				'/(?:is\s+)?(.+)\s+a\s+member/i',
			),
			'ability'   => 'afw/memberships/search',
			'extractor' => 'extract_membership_search_params',
			'priority'  => 8,
		);

		// Admin: Membership analytics.
		$this->intent_patterns['membership_analytics'] = array(
			'keywords'  => array( 'membership analytics', 'membership stats', 'membership report', 'member count' ),
			'patterns'  => array(
				'/membership\s+(?:analytics?|stats?|statistics?|metrics?|report)/i',
				'/(?:how\s+many|total)\s+(?:active\s+)?members?/i',
				'/member\s+(?:count|statistics)/i',
				'/(?:popular|top)\s+(?:membership\s+)?plans?/i',
			),
			'ability'   => 'afw/memberships/analytics',
			'extractor' => 'extract_membership_analytics_params',
			'priority'  => 8,
		);

		// Admin: Expiring memberships.
		$this->intent_patterns['membership_expiring'] = array(
			'keywords'  => array( 'expiring membership', 'membership expiring', 'ending soon', 'about to expire' ),
			'patterns'  => array(
				'/(?:expiring|ending)\s+memberships?/i',
				'/memberships?\s+(?:expiring|ending)\s+(?:soon|this)/i',
				'/memberships?\s+(?:about\s+to|going\s+to)\s+expire/i',
				'/(?:who|which\s+members?)\s+(?:are\s+)?expiring/i',
			),
			'ability'   => 'afw/memberships/expiring',
			'extractor' => 'extract_membership_expiring_params',
			'priority'  => 9,
		);

		// Admin: Members by plan.
		$this->intent_patterns['membership_by_plan'] = array(
			'keywords'  => array( 'members of plan', 'who is in plan', 'members in', 'plan members' ),
			'patterns'  => array(
				'/members?\s+(?:of|in|on)\s+(?:plan\s+)?(.+)/i',
				'/(?:who|which\s+users?)\s+(?:are|is)\s+(?:in|on)\s+(?:the\s+)?(.+)\s+plan/i',
				'/(.+)\s+plan\s+members?/i',
			),
			'ability'   => 'afw/memberships/members-by-plan',
			'extractor' => 'extract_membership_by_plan_params',
			'priority'  => 8,
		);

		// Customer: My memberships.
		$this->intent_patterns['my_memberships'] = array(
			'keywords'  => array( 'my membership', 'my plan', 'membership status', 'am i a member' ),
			'patterns'  => array(
				'/(?:my|view\s+my)\s+memberships?/i',
				'/(?:what|which)\s+memberships?\s+do\s+i\s+have/i',
				'/(?:my|view\s+my)\s+(?:membership\s+)?plan/i',
				'/am\s+i\s+(?:a\s+)?member/i',
				'/(?:do\s+i\s+have\s+)?(?:a\s+)?membership/i',
			),
			'ability'   => 'afw/membership/my-memberships',
			'extractor' => 'extract_customer_membership_params',
			'priority'  => 7,
			'scope'     => 'customer',
		);

		// Customer: Membership benefits.
		$this->intent_patterns['membership_benefits'] = array(
			'keywords'  => array( 'membership benefit', 'member perk', 'what do i get', 'membership include' ),
			'patterns'  => array(
				'/(?:my\s+)?membership\s+(?:benefits?|perks?|privileges?)/i',
				'/what\s+(?:do\s+)?(?:i\s+)?get\s+(?:with|as)\s+(?:a\s+)?member/i',
				'/(?:what\'?s?\s+)?included\s+(?:in|with)\s+(?:my\s+)?membership/i',
				'/member\s+(?:benefits?|perks?|discounts?)/i',
			),
			'ability'   => 'afw/membership/benefits',
			'extractor' => 'extract_customer_membership_params',
			'priority'  => 8,
			'scope'     => 'customer',
		);

		// Customer: Check membership access.
		$this->intent_patterns['membership_check_access'] = array(
			'keywords'  => array( 'do i have access', 'can i access', 'am i eligible', 'member access' ),
			'patterns'  => array(
				'/(?:do\s+)?(?:i\s+)?have\s+access\s+to/i',
				'/(?:can\s+)?(?:i\s+)?access\s+(?:the\s+)?(.+)/i',
				'/am\s+i\s+(?:eligible|allowed)/i',
				'/(?:is\s+)?(?:my\s+)?membership\s+(?:active|valid)/i',
			),
			'ability'   => 'afw/membership/check-access',
			'extractor' => 'extract_customer_membership_params',
			'priority'  => 7,
			'scope'     => 'customer',
		);

		// Customer: Cancel membership (ACTION).
		$this->intent_patterns['cancel_membership'] = array(
			'keywords'  => array( 'cancel membership', 'cancel my membership', 'end membership', 'stop membership' ),
			'patterns'  => array(
				'/cancel\s+(?:my\s+)?membership/i',
				'/(?:end|stop|terminate)\s+(?:my\s+)?membership/i',
				'/(?:i\s+)?(?:want|would\s+like)\s+to\s+cancel\s+(?:my\s+)?membership/i',
				'/(?:don\'?t|do\s+not)\s+want\s+(?:my\s+)?membership/i',
			),
			'ability'   => 'afw/membership/cancel',
			'extractor' => 'extract_customer_membership_cancel_params',
			'priority'  => 10,
			'scope'     => 'customer',
			'is_action' => true,
		);
	}

	/**
	 * Register hotel booking intent patterns.
	 *
	 * Patterns for Hotel Booking for WooCommerce integration.
	 * These only work when Hotel Booking for WooCommerce is installed and active.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_hotel_booking_intents() {
		// Check if Hotel Booking for WooCommerce is active.
		if ( ! defined( 'HBFWC_VERSION' ) && ! class_exists( 'HBFWC_WC_Product_Accommodation' ) ) {
			return;
		}

		// Admin: List rooms.
		$this->intent_patterns['hotel_rooms_list'] = array(
			'keywords'  => array( 'rooms', 'accommodation', 'list rooms', 'show rooms', 'all rooms', 'hotel rooms' ),
			'patterns'  => array(
				'/(?:list|show|all)\s*(?:hotel\s+)?rooms?/i',
				'/(?:list|show|all)\s*accommodations?/i',
				'/(?:what|which)\s+rooms?\s+(?:do\s+)?(?:we|i)\s+have/i',
				'/(?:available|our)\s+rooms?/i',
				'/room\s+(?:inventory|types?)/i',
			),
			'ability'   => 'afw/hotel/rooms/list',
			'extractor' => 'extract_basic_limit_params',
			'priority'  => 6,
		);

		// Admin: Get room details.
		$this->intent_patterns['hotel_room_get'] = array(
			'keywords'  => array( 'room #', 'room id', 'show room', 'get room', 'room details' ),
			'patterns'  => array(
				'/room\s*#?\s*(\d+)/i',
				'/show\s+room\s*#?\s*(\d+)/i',
				'/get\s+room\s*#?\s*(\d+)/i',
				'/room\s+(?:id\s+)?(\d+)\s+details?/i',
			),
			'ability'   => 'afw/hotel/rooms/get',
			'extractor' => 'extract_hotel_room_id',
			'priority'  => 10,
		);

		// Admin: Check availability.
		$this->intent_patterns['hotel_availability_check'] = array(
			'keywords'  => array( 'room availability', 'check availability', 'available rooms', 'vacancy' ),
			'patterns'  => array(
				'/(?:check|what|any)\s*(?:room\s+)?availability/i',
				'/(?:is|are)\s+(?:the\s+)?room(?:s)?\s+available/i',
				'/(?:room|hotel)\s+vacancy/i',
				'/available\s+rooms?\s+(?:for|on|from)/i',
			),
			'ability'   => 'afw/hotel/availability/check',
			'extractor' => 'extract_hotel_availability_params',
			'priority'  => 8,
		);

		// Admin: List reservations.
		$this->intent_patterns['hotel_reservations_list'] = array(
			'keywords'  => array( 'hotel reservations', 'hotel bookings', 'room reservations', 'guest reservations' ),
			'patterns'  => array(
				'/(?:list|show|all)\s*(?:hotel\s+)?reservations?/i',
				'/(?:list|show|all)\s*(?:room|guest)\s+(?:bookings?|reservations?)/i',
				'/(?:hotel|room)\s+bookings?/i',
				'/(?:what|which)\s+reservations?\s+(?:do\s+)?(?:we|i)\s+have/i',
			),
			'ability'   => 'afw/hotel/reservations/list',
			'extractor' => 'extract_hotel_reservations_params',
			'priority'  => 7,
		);

		// Admin: Today's check-ins/check-outs.
		$this->intent_patterns['hotel_today'] = array(
			'keywords'  => array( 'check-in today', 'check-out today', 'today arrivals', 'today departures', 'today guests' ),
			'patterns'  => array(
				'/(?:today\'?s?|todays)\s*(?:check[\s-]*ins?|check[\s-]*outs?|arrivals?|departures?)/i',
				'/(?:check[\s-]*ins?|check[\s-]*outs?|arrivals?|departures?)\s+(?:for\s+)?today/i',
				'/(?:who|which\s+guests?)\s+(?:is|are)\s+(?:checking\s+in|checking\s+out|arriving|leaving)\s+today/i',
				'/(?:today\'?s?\s+)?(?:hotel\s+)?(?:guests?|arrivals?)/i',
			),
			'ability'   => 'afw/hotel/reservations/today',
			'extractor' => null,
			'priority'  => 9,
		);

		// Admin: Upcoming reservations.
		$this->intent_patterns['hotel_upcoming'] = array(
			'keywords'  => array( 'upcoming reservations', 'upcoming check-ins', 'future reservations', 'this week reservations' ),
			'patterns'  => array(
				'/(?:upcoming|next|future|scheduled)\s*(?:hotel\s+)?reservations?/i',
				'/(?:upcoming|next)\s*(?:check[\s-]*ins?|arrivals?)/i',
				'/(?:reservations?|bookings?)\s+(?:this|next)\s+(?:week|month)/i',
				'/(?:what|which)\s+reservations?\s+(?:are\s+)?(?:coming|next)/i',
			),
			'ability'   => 'afw/hotel/reservations/upcoming',
			'extractor' => 'extract_hotel_upcoming_params',
			'priority'  => 8,
		);

		// Admin: Get reservation details.
		$this->intent_patterns['hotel_reservation_get'] = array(
			'keywords'  => array( 'reservation #', 'reservation id', 'show reservation', 'get reservation', 'guest booking' ),
			'patterns'  => array(
				'/(?:hotel\s+)?reservation\s*#?\s*(\d+)/i',
				'/show\s+(?:hotel\s+)?reservation\s*#?\s*(\d+)/i',
				'/(?:guest|room)\s+booking\s*#?\s*(\d+)/i',
				'/order\s*#?\s*(\d+)\s+(?:hotel\s+)?reservation/i',
			),
			'ability'   => 'afw/hotel/reservations/get',
			'extractor' => 'extract_hotel_reservation_id',
			'priority'  => 10,
		);

		// Admin: Hotel analytics.
		$this->intent_patterns['hotel_analytics'] = array(
			'keywords'  => array( 'hotel analytics', 'occupancy rate', 'hotel revenue', 'hotel report', 'popular rooms' ),
			'patterns'  => array(
				'/(?:hotel|room|accommodation)\s+(?:analytics?|stats?|statistics?|metrics?|report)/i',
				'/occupancy\s+rate/i',
				'/(?:hotel|room)\s+revenue/i',
				'/(?:popular|top|best)\s+rooms?/i',
				'/(?:how\s+many|total)\s+(?:reservations?|bookings?)\s+(?:this|last)/i',
			),
			'ability'   => 'afw/hotel/analytics',
			'extractor' => 'extract_hotel_analytics_params',
			'priority'  => 8,
		);

		// Admin: List rate plans.
		$this->intent_patterns['hotel_rates_list'] = array(
			'keywords'  => array( 'rate plans', 'pricing plans', 'room rates', 'hotel rates' ),
			'patterns'  => array(
				'/(?:list|show|all)\s*rate\s+plans?/i',
				'/(?:list|show|all)\s*(?:hotel|room)\s+rates?/i',
				'/(?:what|which)\s+rate\s+plans?/i',
				'/pricing\s+plans?/i',
			),
			'ability'   => 'afw/hotel/rates/list',
			'extractor' => null,
			'priority'  => 6,
		);

		// Customer: Search available rooms.
		$this->intent_patterns['hotel_search'] = array(
			'keywords'  => array( 'book room', 'find room', 'search room', 'available room', 'room for', 'stay for' ),
			'patterns'  => array(
				'/(?:find|search|book|get)\s+(?:a\s+)?(?:hotel\s+)?room/i',
				'/(?:available|any)\s+rooms?\s+(?:for|from|on)/i',
				'/(?:do\s+you\s+have|is\s+there)\s+(?:a\s+)?room/i',
				'/(?:i\s+)?(?:want|need|would\s+like)\s+(?:a\s+)?room/i',
				'/room\s+for\s+(\d+)\s+(?:adults?|guests?|people|persons?)/i',
				'/(?:looking\s+for|need)\s+(?:a\s+)?(?:hotel\s+)?(?:room|accommodation)/i',
				'/stay\s+(?:for\s+)?(\d+)\s+nights?/i',
			),
			'ability'   => 'afw/hotel/search',
			'extractor' => 'extract_hotel_search_params',
			'priority'  => 9,
			'scope'     => 'customer',
		);

		// Customer: Room details.
		$this->intent_patterns['hotel_room_details'] = array(
			'keywords'  => array( 'room information', 'room amenities', 'tell me about room', 'what does room have' ),
			'patterns'  => array(
				'/(?:tell\s+me\s+about|show\s+me|what\s+is)\s+(?:the\s+)?(.+)\s+room/i',
				'/(.+)\s+room\s+(?:amenities|features|details?|information)/i',
				'/(?:what|which)\s+amenities\s+(?:does|in)\s+(?:the\s+)?(.+)\s+room/i',
				'/(?:what\'?s?\s+)?(?:in|included\s+in)\s+(?:the\s+)?(.+)\s+room/i',
			),
			'ability'   => 'afw/hotel/room-details',
			'extractor' => 'extract_hotel_room_name_or_id',
			'priority'  => 7,
			'scope'     => 'customer',
		);

		// Customer: Check availability.
		$this->intent_patterns['hotel_customer_availability'] = array(
			'keywords'  => array( 'is room available', 'room available', 'can i book', 'availability for' ),
			'patterns'  => array(
				'/(?:is|are)\s+(?:the\s+)?(.+)\s+(?:room\s+)?available/i',
				'/(?:can\s+)?(?:i\s+)?book\s+(?:the\s+)?(.+)\s+room/i',
				'/(?:check|what\'?s?\s+the)\s+availability\s+(?:for|of)\s+(?:the\s+)?(.+)/i',
				'/(?:do\s+you\s+have)\s+(?:the\s+)?(.+)\s+(?:room\s+)?available/i',
			),
			'ability'   => 'afw/hotel/check-availability',
			'extractor' => 'extract_hotel_customer_availability_params',
			'priority'  => 8,
			'scope'     => 'customer',
		);

		// Customer: Get pricing.
		$this->intent_patterns['hotel_pricing'] = array(
			'keywords'  => array( 'room price', 'how much', 'cost', 'rate for room', 'pricing' ),
			'patterns'  => array(
				'/(?:how\s+much|what\'?s?\s+the\s+(?:price|cost|rate))\s+(?:for|of)\s+(?:the\s+)?(.+)\s+room/i',
				'/(.+)\s+room\s+(?:price|cost|rate)/i',
				'/(?:price|cost|rate)\s+(?:for|of)\s+(?:a\s+)?room/i',
				'/(?:what|how\s+much)\s+(?:does|would)\s+(?:a\s+)?room\s+cost/i',
			),
			'ability'   => 'afw/hotel/get-pricing',
			'extractor' => 'extract_hotel_pricing_params',
			'priority'  => 8,
			'scope'     => 'customer',
		);

		// Customer: My reservations.
		$this->intent_patterns['hotel_my_reservations'] = array(
			'keywords'  => array( 'my reservation', 'my hotel booking', 'my room booking', 'view reservation' ),
			'patterns'  => array(
				'/(?:my|view\s+my)\s+(?:hotel\s+)?reservations?/i',
				'/(?:my|view\s+my)\s+(?:room\s+)?bookings?/i',
				'/(?:what|which)\s+(?:hotel\s+)?reservations?\s+do\s+i\s+have/i',
				'/(?:do\s+i\s+have\s+(?:a|any))\s+(?:hotel\s+)?reservations?/i',
				'/(?:show|list)\s+(?:my\s+)?(?:hotel\s+)?reservations?/i',
			),
			'ability'   => 'afw/hotel/my-reservations',
			'extractor' => 'extract_hotel_customer_reservations_params',
			'priority'  => 7,
			'scope'     => 'customer',
		);

		// Customer: Suggest alternatives.
		$this->intent_patterns['hotel_alternatives'] = array(
			'keywords'  => array( 'alternative room', 'other room', 'different dates', 'suggest room', 'another option' ),
			'patterns'  => array(
				'/(?:any|other|alternative)\s+(?:available\s+)?rooms?/i',
				'/(?:suggest|recommend)\s+(?:a\s+)?(?:different|another)\s+room/i',
				'/(?:what|which)\s+(?:other|else)\s+(?:rooms?\s+)?(?:is|are)\s+available/i',
				'/(?:different|other|alternative)\s+dates?/i',
				'/(?:if\s+)?(?:that\'?s?\s+)?not\s+available/i',
			),
			'ability'   => 'afw/hotel/suggest-alternatives',
			'extractor' => 'extract_hotel_alternatives_params',
			'priority'  => 7,
			'scope'     => 'customer',
		);
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
	 * Actions that require confirmation will return a confirmation request
	 * instead of executing immediately.
	 *
	 * @since 1.0.0
	 * @param string $message The user message.
	 * @return array Array of ability results or action confirmation requests.
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

			// Check if this is an ACTION that requires confirmation.
			$is_action = isset( $this->intent_patterns[ $match['intent'] ]['is_action'] )
						&& true === $this->intent_patterns[ $match['intent'] ]['is_action'];

			if ( $is_action ) {
				// Return confirmation request instead of executing.
				$confirmation = $this->build_action_confirmation( $match, $registry );
				if ( ! empty( $confirmation ) ) {
					$results['pending_action'] = $confirmation;
					// Only one action at a time.
					break;
				}
			} else {
				// Regular read-only ability - execute immediately.
				$result = $registry->execute( $match['ability'], $match['params'] );

				if ( ! is_wp_error( $result ) ) {
					$results[ $match['intent'] ] = $result;
					$executed[]                  = $match['ability'];
				}
			}
		}

		return $results;
	}

	/**
	 * Build an action confirmation request.
	 *
	 * @since 1.0.0
	 * @param array                        $matched_intent The matched intent.
	 * @param Abilities\Abilities_Registry $registry       The abilities registry.
	 * @return array Confirmation data or empty if action is invalid.
	 */
	private function build_action_confirmation( $matched_intent, $registry ) {
		$ability_info = $registry->get_ability( $matched_intent['ability'] );

		if ( empty( $ability_info ) ) {
			return array();
		}

		// Build human-readable preview of what will happen.
		$preview = $this->build_action_preview( $matched_intent, $ability_info );

		// Generate unique confirmation token.
		$confirmation_token = wp_generate_password( 32, false );

		// Store pending action in transient (expires in 5 minutes).
		$pending_action = array(
			'ability'   => $matched_intent['ability'],
			'params'    => $matched_intent['params'],
			'preview'   => $preview,
			'timestamp' => time(),
		);

		set_transient(
			'assistify_pending_action_' . $confirmation_token,
			$pending_action,
			5 * MINUTE_IN_SECONDS
		);

		return array(
			'requires_confirmation' => true,
			'action_name'           => $ability_info['name'] ?? $matched_intent['ability'],
			'action_description'    => $ability_info['description'] ?? '',
			'preview'               => $preview,
			'confirmation_token'    => $confirmation_token,
			'is_destructive'        => $this->is_destructive_action( $matched_intent['ability'] ),
		);
	}

	/**
	 * Build a human-readable preview of what an action will do.
	 *
	 * @since 1.0.0
	 * @param array $matched_intent The matched intent.
	 * @param array $ability_info   The ability information.
	 * @return string Human-readable preview.
	 */
	private function build_action_preview( $matched_intent, $ability_info ) {
		$params = $matched_intent['params'];

		switch ( $matched_intent['ability'] ) {
			case 'afw/orders/update-status':
				$order_id = $params['order_id'] ?? '?';
				$status   = $params['status'] ?? '?';
				return sprintf(
					/* translators: %1$s: order ID, %2$s: new status */
					__( 'Change Order #%1$s status to "%2$s"', 'assistify-for-woocommerce' ),
					$order_id,
					ucfirst( $status )
				);

			case 'afw/orders/refund':
				$order_id = $params['order_id'] ?? '?';
				$amount   = isset( $params['amount'] ) ? wc_price( $params['amount'] ) : __( 'full amount', 'assistify-for-woocommerce' );
				return sprintf(
					/* translators: %1$s: amount, %2$s: order ID */
					__( 'Refund %1$s from Order #%2$s', 'assistify-for-woocommerce' ),
					$amount,
					$order_id
				);

			case 'afw/products/create':
				$name = $params['name'] ?? __( 'New Product', 'assistify-for-woocommerce' );
				return sprintf(
					/* translators: %s: product name */
					__( 'Create new product: "%s"', 'assistify-for-woocommerce' ),
					$name
				);

			case 'afw/products/update':
				$product_id = $params['product_id'] ?? '?';
				$changes    = array();
				if ( isset( $params['price'] ) ) {
					/* translators: %s: formatted price */
					$changes[] = sprintf( __( 'price to %s', 'assistify-for-woocommerce' ), wc_price( $params['price'] ) );
				}
				if ( isset( $params['stock_quantity'] ) ) {
					/* translators: %d: stock quantity */
					$changes[] = sprintf( __( 'stock to %d', 'assistify-for-woocommerce' ), $params['stock_quantity'] );
				}
				if ( isset( $params['status'] ) ) {
					/* translators: %s: product status */
					$changes[] = sprintf( __( 'status to %s', 'assistify-for-woocommerce' ), $params['status'] );
				}
				$changes_str = ! empty( $changes ) ? implode( ', ', $changes ) : __( 'details', 'assistify-for-woocommerce' );
				return sprintf(
					/* translators: %1$s: product ID, %2$s: changes list */
					__( 'Update Product #%1$s: %2$s', 'assistify-for-woocommerce' ),
					$product_id,
					$changes_str
				);

			case 'afw/coupons/create':
				$code   = $params['code'] ?? __( 'NEW_COUPON', 'assistify-for-woocommerce' );
				$amount = $params['amount'] ?? '?';
				$type   = $params['discount_type'] ?? 'percent';
				$suffix = 'percent' === $type ? '%' : '';
				return sprintf(
					/* translators: %1$s: coupon code, %2$s: discount amount, %3$s: suffix (% or empty) */
					__( 'Create coupon "%1$s" for %2$s%3$s off', 'assistify-for-woocommerce' ),
					$code,
					$amount,
					$suffix
				);

			case 'afw/coupons/update':
				$coupon_id = $params['coupon_id'] ?? '?';
				return sprintf(
					/* translators: %s: coupon ID */
					__( 'Update Coupon #%s', 'assistify-for-woocommerce' ),
					$coupon_id
				);

			case 'afw/coupons/delete':
				$coupon_id = $params['coupon_id'] ?? '?';
				$force     = ! empty( $params['force'] );
				$action    = $force ? __( 'Permanently delete', 'assistify-for-woocommerce' ) : __( 'Move to trash', 'assistify-for-woocommerce' );
				return sprintf(
					/* translators: %1$s: action (delete/trash), %2$s: coupon ID */
					__( '%1$s Coupon #%2$s', 'assistify-for-woocommerce' ),
					$action,
					$coupon_id
				);

			case 'afw/orders/add-note':
				$order_id = $params['order_id'] ?? '?';
				return sprintf(
					/* translators: %s: order ID */
					__( 'Add note to Order #%s', 'assistify-for-woocommerce' ),
					$order_id
				);

			default:
				return $ability_info['description'] ?? __( 'Perform action', 'assistify-for-woocommerce' );
		}
	}

	/**
	 * Check if an action is destructive (requires double confirmation).
	 *
	 * @since 1.0.0
	 * @param string $ability The ability name.
	 * @return bool True if destructive.
	 */
	private function is_destructive_action( $ability ) {
		$destructive_actions = array(
			'afw/orders/refund',
			'afw/coupons/delete',
			'afw/products/delete',
		);

		return in_array( $ability, $destructive_actions, true );
	}

	/**
	 * Execute a confirmed action.
	 *
	 * @since 1.0.0
	 * @param string $confirmation_token The confirmation token.
	 * @return array|\WP_Error Result or error.
	 */
	public function execute_confirmed_action( $confirmation_token ) {
		$transient_key  = 'assistify_pending_action_' . $confirmation_token;
		$pending_action = get_transient( $transient_key );

		if ( empty( $pending_action ) ) {
			return new \WP_Error(
				'action_expired',
				__( 'This action has expired. Please try again.', 'assistify-for-woocommerce' )
			);
		}

		// Delete the transient to prevent re-use.
		delete_transient( $transient_key );

		// Execute the action.
		$registry = Abilities\Abilities_Registry::instance();
		$result   = $registry->execute( $pending_action['ability'], $pending_action['params'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: action preview */
				__( 'Action completed: %s', 'assistify-for-woocommerce' ),
				$pending_action['preview']
			),
			'result'  => $result,
		);
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

	// ==========================================================================
	// ACTION Parameter Extractors
	// ==========================================================================

	/**
	 * Extract order status update parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_order_status_update_params( $message ) {
		$params = array();

		// Extract order ID.
		if ( preg_match( '/order\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['order_id'] = (int) $matches[1];
		}

		// Extract status.
		$status_map = array(
			'processing' => 'processing',
			'completed'  => 'completed',
			'complete'   => 'completed',
			'shipped'    => 'completed',
			'on-hold'    => 'on-hold',
			'on hold'    => 'on-hold',
			'hold'       => 'on-hold',
			'pending'    => 'pending',
			'cancelled'  => 'cancelled',
			'cancel'     => 'cancelled',
			'refunded'   => 'refunded',
			'failed'     => 'failed',
		);

		$message_lower = strtolower( $message );
		foreach ( $status_map as $keyword => $status ) {
			if ( strpos( $message_lower, $keyword ) !== false ) {
				$params['status'] = $status;
				break;
			}
		}

		// Also check regex patterns.
		if ( empty( $params['status'] ) && preg_match( '/(?:to|as|status)\s+(\w+)/i', $message, $matches ) ) {
			$status_key = strtolower( $matches[1] );
			if ( isset( $status_map[ $status_key ] ) ) {
				$params['status'] = $status_map[ $status_key ];
			}
		}

		return $params;
	}

	/**
	 * Extract refund parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_refund_params( $message ) {
		$params = array();

		// Extract order ID.
		if ( preg_match( '/order\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['order_id'] = (int) $matches[1];
		}

		// Extract amount if specified.
		if ( preg_match( '/\$?(\d+(?:\.\d{2})?)\s*(?:refund|from|for)/i', $message, $matches ) ) {
			$params['amount'] = (float) $matches[1];
		} elseif ( preg_match( '/refund\s*\$?(\d+(?:\.\d{2})?)/i', $message, $matches ) ) {
			$params['amount'] = (float) $matches[1];
		}

		// Check for reason.
		if ( preg_match( '/(?:reason|because|for)[:\s]+["\']?([^"\']+)["\']?$/i', $message, $matches ) ) {
			$params['reason'] = trim( $matches[1] );
		}

		// Default restock to true.
		$params['restock_items'] = true;

		return $params;
	}

	/**
	 * Extract create product parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_create_product_params( $message ) {
		$params = array();

		// Extract product name.
		if ( preg_match( '/(?:called|named|:)\s*["\']?([^"\']+?)["\']?(?:\s+(?:at|for|with|price)|$)/i', $message, $matches ) ) {
			$params['name'] = trim( $matches[1] );
		} elseif ( preg_match( '/product\s+["\']([^"\']+)["\']/', $message, $matches ) ) {
			$params['name'] = trim( $matches[1] );
		}

		// Extract price if mentioned.
		if ( preg_match( '/(?:price|at|for)\s*\$?(\d+(?:\.\d{2})?)/i', $message, $matches ) ) {
			$params['regular_price'] = (string) $matches[1];
		}

		// Default to draft status for safety.
		$params['status'] = 'draft';

		return $params;
	}

	/**
	 * Extract update product parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_update_product_params( $message ) {
		$params = array();

		// Extract product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		}

		// Extract new price.
		if ( preg_match( '/(?:price|to)\s*\$?(\d+(?:\.\d{2})?)/i', $message, $matches ) ) {
			$params['price'] = (string) $matches[1];
		}

		// Extract stock quantity.
		if ( preg_match( '/stock\s*(?:to|=|:)?\s*(\d+)/i', $message, $matches ) ) {
			$params['stock_quantity'] = (int) $matches[1];
		}

		// Extract status.
		if ( preg_match( '/(?:status|set)\s+(?:to\s+)?(publish|draft|pending|private)/i', $message, $matches ) ) {
			$params['status'] = strtolower( $matches[1] );
		}

		return $params;
	}

	/**
	 * Extract create coupon parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_create_coupon_params( $message ) {
		$params = array();

		// Extract coupon code.
		if ( preg_match( '/(?:code|coupon)\s*[:\s]*["\']?([A-Za-z0-9_-]+)["\']?/i', $message, $matches ) ) {
			$params['code'] = strtoupper( trim( $matches[1] ) );
		}

		// Extract discount amount and type.
		if ( preg_match( '/(\d+(?:\.\d{2})?)\s*%/i', $message, $matches ) ) {
			$params['amount']        = (string) $matches[1];
			$params['discount_type'] = 'percent';
		} elseif ( preg_match( '/\$(\d+(?:\.\d{2})?)/i', $message, $matches ) ) {
			$params['amount']        = (string) $matches[1];
			$params['discount_type'] = 'fixed_cart';
		} elseif ( preg_match( '/(\d+(?:\.\d{2})?)\s*(?:off|discount)/i', $message, $matches ) ) {
			$params['amount']        = (string) $matches[1];
			$params['discount_type'] = 'percent'; // Default to percent if no symbol.
		}

		return $params;
	}

	/**
	 * Extract update coupon parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_update_coupon_params( $message ) {
		$params = array();

		// Try to get coupon by ID.
		if ( preg_match( '/coupon\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['coupon_id'] = (int) $matches[1];
		}

		// Try to get coupon by code.
		if ( preg_match( '/coupon\s+(?:code\s+)?["\']?([A-Za-z0-9_-]+)["\']?/i', $message, $matches ) ) {
			// Look up coupon ID by code.
			$code   = strtoupper( trim( $matches[1] ) );
			$coupon = new \WC_Coupon( $code );
			if ( $coupon->get_id() > 0 ) {
				$params['coupon_id'] = $coupon->get_id();
			}
		}

		// Extract new amount.
		if ( preg_match( '/(?:to|=|:)\s*(\d+(?:\.\d{2})?)\s*%?/i', $message, $matches ) ) {
			$params['amount'] = (string) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract delete coupon parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_delete_coupon_params( $message ) {
		$params = array();

		// Try to get coupon by ID.
		if ( preg_match( '/coupon\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['coupon_id'] = (int) $matches[1];
		}

		// Try to get coupon by code.
		if ( preg_match( '/coupon\s+(?:code\s+)?["\']?([A-Za-z0-9_-]+)["\']?/i', $message, $matches ) ) {
			$code   = strtoupper( trim( $matches[1] ) );
			$coupon = new \WC_Coupon( $code );
			if ( $coupon->get_id() > 0 ) {
				$params['coupon_id'] = $coupon->get_id();
			}
		}

		// Check for permanent deletion.
		$params['force'] = (bool) preg_match( '/(?:permanent|forever|complete)/i', $message );

		return $params;
	}

	/**
	 * Extract order note parameters.
	 *
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_order_note_params( $message ) {
		$params = array();

		// Extract order ID.
		if ( preg_match( '/order\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['order_id'] = (int) $matches[1];
		}

		// Extract note content.
		if ( preg_match( '/note[:\s]+["\']?(.+?)["\']?$/i', $message, $matches ) ) {
			$params['note'] = trim( $matches[1] );
		} elseif ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			$params['note'] = trim( $matches[1] );
		}

		// Check if customer should be notified.
		$params['is_customer_note'] = (bool) preg_match( '/(?:notify|email|customer)/i', $message );

		return $params;
	}

	// ==========================================================================
	// SUBSCRIPTION Parameter Extractors
	// ==========================================================================

	/**
	 * Extract subscription ID from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_subscription_id( $message ) {
		if ( preg_match( '/subscription\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'subscription_id' => (int) $matches[1] );
		}
		if ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			return array( 'subscription_id' => (int) $matches[1] );
		}
		return array();
	}

	/**
	 * Extract subscription list parameters from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_subscription_list_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for status.
		$statuses = array( 'active', 'on-hold', 'cancelled', 'expired', 'pending-cancel', 'pending' );
		foreach ( $statuses as $status ) {
			if ( stripos( $message, $status ) !== false ) {
				$params['status'] = $status;
				break;
			}
		}

		// Check for limit.
		if ( preg_match( '/(?:last|recent|top|first)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract subscription search parameters from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_subscription_search_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for customer email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $matches ) ) {
			$params['query'] = trim( $matches[1] );
		}

		// Check for quoted search term.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			$params['query'] = trim( $matches[1] );
		}

		// Check for "for X" or "named X".
		if ( preg_match( '/(?:for|named|from)\s+([a-zA-Z\s]+)/i', $message, $matches ) ) {
			$query = trim( $matches[1] );
			if ( strlen( $query ) > 2 ) {
				$params['query'] = $query;
			}
		}

		return $params;
	}

	/**
	 * Extract subscription expiring parameters from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_subscription_expiring_params( $message ) {
		$params = array(
			'days'  => 30,
			'limit' => 10,
		);

		// Check for number of days.
		if ( preg_match( '/(?:next|within|in)\s+(\d+)\s+days?/i', $message, $matches ) ) {
			$params['days'] = (int) $matches[1];
		} elseif ( preg_match( '/this\s+week/i', $message ) ) {
			$params['days'] = 7;
		} elseif ( preg_match( '/this\s+month/i', $message ) ) {
			$params['days'] = 30;
		}

		// Check for limit.
		if ( preg_match( '/(?:top|first)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract customer subscription parameters from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_subscription_params( $message ) {
		$params = array();

		// Check for subscription ID.
		if ( preg_match( '/subscription\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['subscription_id'] = (int) $matches[1];
		}

		// Check for status filter.
		$statuses = array( 'active', 'on-hold', 'cancelled', 'expired', 'paused' );
		foreach ( $statuses as $status ) {
			if ( stripos( $message, $status ) !== false ) {
				$params['status'] = ( 'paused' === $status ) ? 'on-hold' : $status;
				break;
			}
		}

		return $params;
	}

	/**
	 * Extract customer subscription action parameters from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_subscription_action_params( $message ) {
		$params = array();

		// Check for subscription ID.
		if ( preg_match( '/subscription\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['subscription_id'] = (int) $matches[1];
		}

		return $params;
	}

	// ==========================================================================
	// BOOKING Parameter Extractors
	// ==========================================================================

	/**
	 * Extract booking ID from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_id( $message ) {
		if ( preg_match( '/booking\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'booking_id' => (int) $matches[1] );
		}
		if ( preg_match( '/reservation\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'booking_id' => (int) $matches[1] );
		}
		if ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			return array( 'booking_id' => (int) $matches[1] );
		}
		return array();
	}

	/**
	 * Extract booking list parameters from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_list_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for status.
		$statuses = array( 'unpaid', 'pending-confirmation', 'confirmed', 'paid', 'cancelled', 'complete' );
		foreach ( $statuses as $status ) {
			if ( stripos( $message, $status ) !== false ) {
				$params['status'] = $status;
				break;
			}
		}

		// Check for limit.
		if ( preg_match( '/(?:last|recent|top|first)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract today's booking parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_today_params( $message ) {
		$params = array();

		// Check for status filter.
		if ( preg_match( '/confirmed/i', $message ) ) {
			$params['status'] = 'confirmed';
		} elseif ( preg_match( '/paid/i', $message ) ) {
			$params['status'] = 'paid';
		}

		return $params;
	}

	/**
	 * Extract upcoming booking parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_upcoming_params( $message ) {
		$params = array(
			'days'  => 7,
			'limit' => 20,
		);

		// Check for days.
		if ( preg_match( '/(?:next|within|in)\s+(\d+)\s+days?/i', $message, $matches ) ) {
			$params['days'] = (int) $matches[1];
		} elseif ( preg_match( '/this\s+week/i', $message ) ) {
			$params['days'] = 7;
		} elseif ( preg_match( '/this\s+month/i', $message ) ) {
			$params['days'] = 30;
		} elseif ( preg_match( '/next\s+week/i', $message ) ) {
			$params['days'] = 14;
		}

		// Check for limit.
		if ( preg_match( '/(?:last|recent|top|first)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract booking search parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_search_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for customer email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $matches ) ) {
			$params['query'] = trim( $matches[1] );
		}

		// Check for quoted search term.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			$params['query'] = trim( $matches[1] );
		}

		// Check for "for X" pattern.
		if ( empty( $params['query'] ) && preg_match( '/(?:for|named|from)\s+([a-zA-Z\s]+)/i', $message, $matches ) ) {
			$query = trim( $matches[1] );
			if ( strlen( $query ) > 2 ) {
				$params['query'] = $query;
			}
		}

		return $params;
	}

	/**
	 * Extract booking analytics parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_analytics_params( $message ) {
		$params = array( 'period' => '30days' );

		// Check for period.
		if ( preg_match( '/(?:last|past)\s+(\d+)\s+days?/i', $message, $matches ) ) {
			$days             = (int) $matches[1];
			$params['period'] = $days . 'days';
		} elseif ( preg_match( '/this\s+week/i', $message ) ) {
			$params['period'] = '7days';
		} elseif ( preg_match( '/this\s+month/i', $message ) ) {
			$params['period'] = '30days';
		} elseif ( preg_match( '/(?:this|last)\s+year/i', $message ) ) {
			$params['period'] = 'year';
		} elseif ( preg_match( '/(?:last\s+)?(?:quarter|90\s*days?)/i', $message ) ) {
			$params['period'] = '90days';
		}

		return $params;
	}

	/**
	 * Extract booking availability parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_availability_params( $message ) {
		$params = array();

		// Check for product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		}

		// Check for date.
		if ( preg_match( '/(\d{4}-\d{2}-\d{2})/', $message, $matches ) ) {
			$params['date'] = $matches[1];
		} elseif ( preg_match( '/(?:on\s+)?(?:for\s+)?(tomorrow)/i', $message, $matches ) ) {
			$params['date'] = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		} elseif ( preg_match( '/(?:on\s+)?(?:for\s+)?(today)/i', $message, $matches ) ) {
			$params['date'] = gmdate( 'Y-m-d' );
		} elseif ( preg_match( '/(?:on|for)\s+(?:the\s+)?(\d{1,2})(?:st|nd|rd|th)?(?:\s+of)?\s*(\w+)?/i', $message, $matches ) ) {
			$day   = (int) $matches[1];
			$month = ! empty( $matches[2] ) ? $matches[2] : gmdate( 'F' );
			$year  = gmdate( 'Y' );
			$date  = strtotime( "{$day} {$month} {$year}" );
			if ( $date ) {
				$params['date'] = gmdate( 'Y-m-d', $date );
			}
		}

		return $params;
	}

	/**
	 * Extract booking status update parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_booking_status_update_params( $message ) {
		$params = array();

		// Extract booking ID.
		if ( preg_match( '/booking\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['booking_id'] = (int) $matches[1];
		}

		// Detect status from action words.
		if ( preg_match( '/(?:confirm|approve)/i', $message ) ) {
			$params['status'] = 'confirmed';
		} elseif ( preg_match( '/(?:cancel|reject)/i', $message ) ) {
			$params['status'] = 'cancelled';
		} elseif ( preg_match( '/(?:complete|finish)/i', $message ) ) {
			$params['status'] = 'complete';
		} elseif ( preg_match( '/(?:mark|set)\s+.*?\s+(?:as\s+)?(\w+)/i', $message, $matches ) ) {
			$status_map = array(
				'confirmed' => 'confirmed',
				'paid'      => 'paid',
				'cancelled' => 'cancelled',
				'complete'  => 'complete',
				'unpaid'    => 'unpaid',
			);
			$status     = strtolower( $matches[1] );
			if ( isset( $status_map[ $status ] ) ) {
				$params['status'] = $status_map[ $status ];
			}
		}

		return $params;
	}

	/**
	 * Extract customer booking parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_booking_params( $message ) {
		$params = array();

		// Check for status filter.
		if ( preg_match( '/confirmed/i', $message ) ) {
			$params['status'] = 'confirmed';
		} elseif ( preg_match( '/cancelled/i', $message ) ) {
			$params['status'] = 'cancelled';
		} elseif ( preg_match( '/upcoming|future/i', $message ) ) {
			$params['status'] = 'confirmed';
		}

		return $params;
	}

	/**
	 * Extract customer booking details parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_booking_details_params( $message ) {
		$params = array();

		// Extract booking ID.
		if ( preg_match( '/booking\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['booking_id'] = (int) $matches[1];
		} elseif ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			$params['booking_id'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract customer booking cancel parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_booking_cancel_params( $message ) {
		$params = array();

		// Extract booking ID if specified.
		if ( preg_match( '/booking\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['booking_id'] = (int) $matches[1];
		} elseif ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			$params['booking_id'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract customer availability check parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_availability_params( $message ) {
		$params = array();

		// Check for product ID.
		if ( preg_match( '/product\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['product_id'] = (int) $matches[1];
		}

		// Check for date.
		if ( preg_match( '/(\d{4}-\d{2}-\d{2})/', $message, $matches ) ) {
			$params['date'] = $matches[1];
		} elseif ( preg_match( '/tomorrow/i', $message ) ) {
			$params['date'] = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		} elseif ( preg_match( '/today/i', $message ) ) {
			$params['date'] = gmdate( 'Y-m-d' );
		} elseif ( preg_match( '/next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $message, $matches ) ) {
			$params['date'] = gmdate( 'Y-m-d', strtotime( 'next ' . $matches[1] ) );
		}

		// Check for service/product name.
		if ( preg_match( '/book\s+(?:a\s+)?(?:an?\s+)?([a-zA-Z\s]+?)(?:\s+(?:on|for|tomorrow|today|next))/i', $message, $matches ) ) {
			$service = trim( $matches[1] );
			if ( strlen( $service ) > 2 ) {
				$params['service_name'] = $service;
			}
		}

		return $params;
	}

	// ==========================================================================
	// MEMBERSHIP Parameter Extractors
	// ==========================================================================

	/**
	 * Extract membership ID from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_membership_id( $message ) {
		if ( preg_match( '/membership\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return array( 'membership_id' => (int) $matches[1] );
		}
		if ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			return array( 'membership_id' => (int) $matches[1] );
		}
		return array();
	}

	/**
	 * Extract membership list parameters from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_membership_list_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for status.
		$statuses = array( 'active', 'paused', 'expired', 'cancelled', 'pending', 'free_trial', 'complimentary' );
		foreach ( $statuses as $status ) {
			if ( stripos( $message, $status ) !== false ) {
				$params['status'] = $status;
				break;
			}
		}

		// Check for limit.
		if ( preg_match( '/(?:last|recent|top|first)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract membership search parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_membership_search_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for customer email.
		if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $matches ) ) {
			$params['query'] = trim( $matches[1] );
		}

		// Check for quoted search term.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			$params['query'] = trim( $matches[1] );
		}

		// Check for "for X" pattern.
		if ( empty( $params['query'] ) && preg_match( '/(?:for|named|from)\s+([a-zA-Z\s]+)/i', $message, $matches ) ) {
			$query = trim( $matches[1] );
			if ( strlen( $query ) > 2 ) {
				$params['query'] = $query;
			}
		}

		return $params;
	}

	/**
	 * Extract membership analytics parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_membership_analytics_params( $message ) {
		$params = array( 'period' => '30days' );

		// Check for period.
		if ( preg_match( '/(?:last|past)\s+(\d+)\s+days?/i', $message, $matches ) ) {
			$days             = (int) $matches[1];
			$params['period'] = $days . 'days';
		} elseif ( preg_match( '/this\s+week/i', $message ) ) {
			$params['period'] = '7days';
		} elseif ( preg_match( '/this\s+month/i', $message ) ) {
			$params['period'] = '30days';
		} elseif ( preg_match( '/(?:this|last)\s+year/i', $message ) ) {
			$params['period'] = 'year';
		} elseif ( preg_match( '/(?:last\s+)?(?:quarter|90\s*days?)/i', $message ) ) {
			$params['period'] = '90days';
		}

		return $params;
	}

	/**
	 * Extract expiring membership parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_membership_expiring_params( $message ) {
		$params = array(
			'days'  => 30,
			'limit' => 20,
		);

		// Check for number of days.
		if ( preg_match( '/(?:next|within|in)\s+(\d+)\s+days?/i', $message, $matches ) ) {
			$params['days'] = (int) $matches[1];
		} elseif ( preg_match( '/this\s+week/i', $message ) ) {
			$params['days'] = 7;
		} elseif ( preg_match( '/this\s+month/i', $message ) ) {
			$params['days'] = 30;
		}

		// Check for limit.
		if ( preg_match( '/(?:last|recent|top|first)\s+(\d+)/i', $message, $matches ) ) {
			$params['limit'] = min( (int) $matches[1], 50 );
		}

		return $params;
	}

	/**
	 * Extract members by plan parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_membership_by_plan_params( $message ) {
		$params = array( 'limit' => 20 );

		// Try to extract plan ID.
		if ( preg_match( '/plan\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['plan_id'] = (int) $matches[1];
		}

		// Check for status.
		$statuses = array( 'active', 'paused', 'expired', 'cancelled' );
		foreach ( $statuses as $status ) {
			if ( stripos( $message, $status ) !== false ) {
				$params['status'] = $status;
				break;
			}
		}

		return $params;
	}

	/**
	 * Extract customer membership parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_membership_params( $message ) {
		$params = array();

		// Check for status filter.
		if ( preg_match( '/active/i', $message ) ) {
			$params['status'] = 'active';
		} elseif ( preg_match( '/expired/i', $message ) ) {
			$params['status'] = 'expired';
		} elseif ( preg_match( '/cancelled/i', $message ) ) {
			$params['status'] = 'cancelled';
		}

		return $params;
	}

	/**
	 * Extract customer membership cancel parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_customer_membership_cancel_params( $message ) {
		$params = array();

		// Extract membership ID if specified.
		if ( preg_match( '/membership\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['membership_id'] = (int) $matches[1];
		} elseif ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			$params['membership_id'] = (int) $matches[1];
		}

		return $params;
	}

	// =========================================================================
	// HOTEL BOOKING PARAMETER EXTRACTORS
	// =========================================================================

	/**
	 * Extract hotel room ID from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_room_id( $message ) {
		$params = array();

		if ( preg_match( '/room\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['room_id'] = (int) $matches[1];
		} elseif ( preg_match( '/\b(\d{2,})\b/', $message, $matches ) ) {
			$params['room_id'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract hotel availability parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_availability_params( $message ) {
		$params = array();

		// Extract room ID if specified.
		if ( preg_match( '/room\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['room_id'] = (int) $matches[1];
		}

		// Try to extract dates.
		$dates = $this->extract_date_range( $message );
		if ( ! empty( $dates['from'] ) ) {
			$params['check_in'] = $dates['from'];
		}
		if ( ! empty( $dates['to'] ) ) {
			$params['check_out'] = $dates['to'];
		}

		return $params;
	}

	/**
	 * Extract hotel reservations list parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_reservations_params( $message ) {
		$params = array( 'limit' => 10 );

		// Check for status.
		$statuses = array( 'processing', 'completed', 'on-hold', 'pending' );
		foreach ( $statuses as $status ) {
			if ( stripos( $message, str_replace( '-', ' ', $status ) ) !== false ) {
				$params['status'] = $status;
				break;
			}
		}

		// Try to extract dates.
		$dates = $this->extract_date_range( $message );
		if ( ! empty( $dates['from'] ) ) {
			$params['date_from'] = $dates['from'];
		}
		if ( ! empty( $dates['to'] ) ) {
			$params['date_to'] = $dates['to'];
		}

		return $params;
	}

	/**
	 * Extract hotel upcoming reservations parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_upcoming_params( $message ) {
		$params = array( 'days' => 7, 'limit' => 20 );

		// Check for time period.
		if ( preg_match( '/(\d+)\s*days?/i', $message, $matches ) ) {
			$params['days'] = min( (int) $matches[1], 90 );
		} elseif ( stripos( $message, 'this week' ) !== false ) {
			$params['days'] = 7;
		} elseif ( stripos( $message, 'next week' ) !== false ) {
			$params['days'] = 14;
		} elseif ( stripos( $message, 'this month' ) !== false || stripos( $message, 'next month' ) !== false ) {
			$params['days'] = 30;
		}

		return $params;
	}

	/**
	 * Extract hotel reservation ID from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_reservation_id( $message ) {
		$params = array();

		if ( preg_match( '/(?:reservation|order|booking)\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['order_id'] = (int) $matches[1];
		} elseif ( preg_match( '/\b(\d{3,})\b/', $message, $matches ) ) {
			$params['order_id'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract hotel analytics parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_analytics_params( $message ) {
		$params = array( 'period' => '30days' );

		// Check for time period.
		if ( preg_match( '/(?:last|past)\s*(\d+)\s*days?/i', $message, $matches ) ) {
			$days = (int) $matches[1];
			if ( $days <= 7 ) {
				$params['period'] = '7days';
			} elseif ( $days <= 30 ) {
				$params['period'] = '30days';
			} elseif ( $days <= 90 ) {
				$params['period'] = '90days';
			} else {
				$params['period'] = 'year';
			}
		} elseif ( stripos( $message, 'this week' ) !== false || stripos( $message, 'last week' ) !== false ) {
			$params['period'] = '7days';
		} elseif ( stripos( $message, 'this month' ) !== false || stripos( $message, 'last month' ) !== false ) {
			$params['period'] = '30days';
		} elseif ( stripos( $message, 'quarter' ) !== false ) {
			$params['period'] = '90days';
		} elseif ( stripos( $message, 'year' ) !== false ) {
			$params['period'] = 'year';
		}

		return $params;
	}

	/**
	 * Extract hotel search parameters for customer.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_search_params( $message ) {
		$params = array( 'adults' => 1, 'children' => 0 );

		// Extract dates.
		$dates = $this->extract_date_range( $message );
		if ( ! empty( $dates['from'] ) ) {
			$params['check_in'] = $dates['from'];
		}
		if ( ! empty( $dates['to'] ) ) {
			$params['check_out'] = $dates['to'];
		}

		// Extract number of nights.
		if ( preg_match( '/(\d+)\s*nights?/i', $message, $matches ) && ! empty( $params['check_in'] ) ) {
			$nights              = (int) $matches[1];
			$check_in            = strtotime( $params['check_in'] );
			$params['check_out'] = gmdate( 'Y-m-d', strtotime( "+{$nights} days", $check_in ) );
		}

		// Extract number of adults.
		if ( preg_match( '/(\d+)\s*adults?/i', $message, $matches ) ) {
			$params['adults'] = (int) $matches[1];
		} elseif ( preg_match( '/for\s*(\d+)\s*(?:people|persons?|guests?)/i', $message, $matches ) ) {
			$params['adults'] = (int) $matches[1];
		}

		// Extract number of children.
		if ( preg_match( '/(\d+)\s*child(?:ren)?/i', $message, $matches ) ) {
			$params['children'] = (int) $matches[1];
		} elseif ( preg_match( '/(\d+)\s*kids?/i', $message, $matches ) ) {
			$params['children'] = (int) $matches[1];
		}

		return $params;
	}

	/**
	 * Extract hotel room name or ID from message.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_room_name_or_id( $message ) {
		$params = array();

		// Check for room ID first.
		if ( preg_match( '/room\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$params['room_id'] = (int) $matches[1];
			return $params;
		}

		// Check for room name in quotes.
		if ( preg_match( '/["\']([^"\']+)["\']/', $message, $matches ) ) {
			// Try to find room by name.
			$room_name = trim( $matches[1] );
			$rooms     = wc_get_products(
				array(
					'type'   => 'accommodation',
					'status' => 'publish',
					'limit'  => 1,
					's'      => $room_name,
				)
			);

			if ( ! empty( $rooms ) ) {
				$params['room_id'] = $rooms[0]->get_id();
			}
		}

		// Try to extract room name from common patterns.
		$patterns = array(
			'/(?:about|show)\s+(?:the\s+)?(.+?)\s+room/i',
			'/(.+?)\s+room\s+(?:details?|amenities|features)/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $message, $matches ) ) {
				$room_name = trim( $matches[1] );
				$rooms     = wc_get_products(
					array(
						'type'   => 'accommodation',
						'status' => 'publish',
						'limit'  => 1,
						's'      => $room_name,
					)
				);

				if ( ! empty( $rooms ) ) {
					$params['room_id'] = $rooms[0]->get_id();
					break;
				}
			}
		}

		return $params;
	}

	/**
	 * Extract hotel customer availability parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_customer_availability_params( $message ) {
		$params = array();

		// Extract room ID or name.
		$room_params = $this->extract_hotel_room_name_or_id( $message );
		if ( ! empty( $room_params['room_id'] ) ) {
			$params['room_id'] = $room_params['room_id'];
		}

		// Extract dates.
		$dates = $this->extract_date_range( $message );
		if ( ! empty( $dates['from'] ) ) {
			$params['check_in'] = $dates['from'];
		}
		if ( ! empty( $dates['to'] ) ) {
			$params['check_out'] = $dates['to'];
		}

		// Extract number of nights if check-in is set but not check-out.
		if ( ! empty( $params['check_in'] ) && empty( $params['check_out'] ) ) {
			if ( preg_match( '/(\d+)\s*nights?/i', $message, $matches ) ) {
				$nights              = (int) $matches[1];
				$check_in            = strtotime( $params['check_in'] );
				$params['check_out'] = gmdate( 'Y-m-d', strtotime( "+{$nights} days", $check_in ) );
			}
		}

		return $params;
	}

	/**
	 * Extract hotel pricing parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_pricing_params( $message ) {
		$params = array( 'rooms' => 1 );

		// Extract room ID or name.
		$room_params = $this->extract_hotel_room_name_or_id( $message );
		if ( ! empty( $room_params['room_id'] ) ) {
			$params['room_id'] = $room_params['room_id'];
		}

		// Extract dates.
		$dates = $this->extract_date_range( $message );
		if ( ! empty( $dates['from'] ) ) {
			$params['check_in'] = $dates['from'];
		}
		if ( ! empty( $dates['to'] ) ) {
			$params['check_out'] = $dates['to'];
		}

		// Extract number of rooms.
		if ( preg_match( '/(\d+)\s*rooms?/i', $message, $matches ) ) {
			$params['rooms'] = (int) $matches[1];
		}

		// Extract number of nights if check-in is set but not check-out.
		if ( ! empty( $params['check_in'] ) && empty( $params['check_out'] ) ) {
			if ( preg_match( '/(\d+)\s*nights?/i', $message, $matches ) ) {
				$nights              = (int) $matches[1];
				$check_in            = strtotime( $params['check_in'] );
				$params['check_out'] = gmdate( 'Y-m-d', strtotime( "+{$nights} days", $check_in ) );
			}
		}

		return $params;
	}

	/**
	 * Extract hotel customer reservations parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_customer_reservations_params( $message ) {
		$params = array( 'status' => 'all' );

		// Check for status filter.
		if ( preg_match( '/upcoming|future|next/i', $message ) ) {
			$params['status'] = 'upcoming';
		} elseif ( preg_match( '/past|previous|completed/i', $message ) ) {
			$params['status'] = 'past';
		}

		return $params;
	}

	/**
	 * Extract hotel alternatives parameters.
	 *
	 * @since 1.1.0
	 * @param string $message The message.
	 * @return array Parameters.
	 */
	private function extract_hotel_alternatives_params( $message ) {
		$params = array( 'adults' => 1 );

		// Extract room ID if mentioned.
		$room_params = $this->extract_hotel_room_name_or_id( $message );
		if ( ! empty( $room_params['room_id'] ) ) {
			$params['room_id'] = $room_params['room_id'];
		}

		// Extract dates.
		$dates = $this->extract_date_range( $message );
		if ( ! empty( $dates['from'] ) ) {
			$params['check_in'] = $dates['from'];
		}
		if ( ! empty( $dates['to'] ) ) {
			$params['check_out'] = $dates['to'];
		}

		// Extract number of adults.
		if ( preg_match( '/(\d+)\s*adults?/i', $message, $matches ) ) {
			$params['adults'] = (int) $matches[1];
		} elseif ( preg_match( '/for\s*(\d+)\s*(?:people|persons?|guests?)/i', $message, $matches ) ) {
			$params['adults'] = (int) $matches[1];
		}

		return $params;
	}
}
