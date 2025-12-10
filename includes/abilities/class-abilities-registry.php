<?php
/**
 * Abilities Registry Class.
 *
 * Central registry for all AI abilities (callable functions).
 * Based on WordPress Abilities API standard.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Abilities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abilities Registry Class.
 *
 * @since 1.0.0
 */
class Abilities_Registry {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Abilities_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered abilities.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $abilities = array();

	/**
	 * Ability categories.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $categories = array(
		'orders'    => 'Orders',
		'products'  => 'Products',
		'customers' => 'Customers',
		'coupons'   => 'Coupons',
		'analytics' => 'Analytics',
		'content'   => 'Content',
		'image'     => 'Image',
		'store'     => 'Store',
	);

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return Abilities_Registry Instance.
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
	 * @since 1.0.0
	 */
	private function __construct() {
		// Load core abilities.
		add_action( 'init', array( $this, 'register_core_abilities' ), 5 );

		// Allow plugins to register abilities.
		add_action( 'init', array( $this, 'trigger_abilities_registration' ), 10 );
	}

	/**
	 * Register core abilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_core_abilities() {
		// Order abilities.
		$this->register(
			'afw/orders/get',
			array(
				'name'        => __( 'Get Order', 'assistify-for-woocommerce' ),
				'description' => __( 'Get details of a specific order.', 'assistify-for-woocommerce' ),
				'category'    => 'orders',
				'callback'    => array( $this, 'ability_orders_get' ),
				'parameters'  => array(
					'order_id' => array(
						'type'        => 'integer',
						'description' => __( 'The order ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/orders/list',
			array(
				'name'        => __( 'List Orders', 'assistify-for-woocommerce' ),
				'description' => __( 'List orders with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'orders',
				'callback'    => array( $this, 'ability_orders_list' ),
				'parameters'  => array(
					'status'   => array(
						'type'        => 'string',
						'description' => __( 'Filter by order status.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'    => array(
						'type'        => 'integer',
						'description' => __( 'Number of orders to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
					'customer' => array(
						'type'        => 'integer',
						'description' => __( 'Filter by customer ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/orders/search',
			array(
				'name'        => __( 'Search Orders', 'assistify-for-woocommerce' ),
				'description' => __( 'Search orders by various criteria.', 'assistify-for-woocommerce' ),
				'category'    => 'orders',
				'callback'    => array( $this, 'ability_orders_search' ),
				'parameters'  => array(
					'query' => array(
						'type'        => 'string',
						'description' => __( 'Search query (order number, customer name, email).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of results to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/orders/update-status',
			array(
				'name'             => __( 'Update Order Status', 'assistify-for-woocommerce' ),
				'description'      => __( 'Update the status of an order.', 'assistify-for-woocommerce' ),
				'category'         => 'orders',
				'callback'         => array( $this, 'ability_orders_update_status' ),
				'parameters'       => array(
					'order_id' => array(
						'type'        => 'integer',
						'description' => __( 'The order ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status'   => array(
						'type'        => 'string',
						'description' => __( 'New order status (pending, processing, on-hold, completed, cancelled, refunded, failed).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'note'     => array(
						'type'        => 'string',
						'description' => __( 'Optional note to add with the status change.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'       => 'manage_woocommerce',
				'requires_confirm' => true,
			)
		);

		$this->register(
			'afw/orders/add-note',
			array(
				'name'        => __( 'Add Order Note', 'assistify-for-woocommerce' ),
				'description' => __( 'Add a note to an order.', 'assistify-for-woocommerce' ),
				'category'    => 'orders',
				'callback'    => array( $this, 'ability_orders_add_note' ),
				'parameters'  => array(
					'order_id'      => array(
						'type'        => 'integer',
						'description' => __( 'The order ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'note'          => array(
						'type'        => 'string',
						'description' => __( 'The note content.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'customer_note' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether this is a note for the customer (true) or private (false).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/orders/refund',
			array(
				'name'             => __( 'Process Refund', 'assistify-for-woocommerce' ),
				'description'      => __( 'Process a refund for an order. Can refund full or partial amount.', 'assistify-for-woocommerce' ),
				'category'         => 'orders',
				'callback'         => array( $this, 'ability_orders_refund' ),
				'parameters'       => array(
					'order_id'         => array(
						'type'        => 'integer',
						'description' => __( 'The order ID to refund.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'amount'           => array(
						'type'        => 'number',
						'description' => __( 'Refund amount. Leave empty for full refund.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'reason'           => array(
						'type'        => 'string',
						'description' => __( 'Reason for the refund.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => '',
					),
					'restock_items'    => array(
						'type'        => 'boolean',
						'description' => __( 'Whether to restock refunded items.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => true,
					),
					'refund_payment'   => array(
						'type'        => 'boolean',
						'description' => __( 'Whether to attempt to refund payment via gateway.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
				),
				'capability'       => 'manage_woocommerce',
				'requires_confirm' => true,
				'is_destructive'   => true,
			)
		);

		// Product abilities.
		$this->register(
			'afw/products/get',
			array(
				'name'        => __( 'Get Product', 'assistify-for-woocommerce' ),
				'description' => __( 'Get details of a specific product.', 'assistify-for-woocommerce' ),
				'category'    => 'products',
				'callback'    => array( $this, 'ability_products_get' ),
				'parameters'  => array(
					'product_id' => array(
						'type'        => 'integer',
						'description' => __( 'The product ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/products/list',
			array(
				'name'        => __( 'List Products', 'assistify-for-woocommerce' ),
				'description' => __( 'List products with optional filters including category, status, product type, virtual, and downloadable.', 'assistify-for-woocommerce' ),
				'category'    => 'products',
				'callback'    => array( $this, 'ability_products_list' ),
				'parameters'  => array(
					'category'     => array(
						'type'        => 'string',
						'description' => __( 'Filter by category slug.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'status'       => array(
						'type'        => 'string',
						'description' => __( 'Filter by product status (publish, draft, pending, private).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'publish',
					),
					'type'         => array(
						'type'        => 'string',
						'description' => __( 'Filter by product type (simple, variable, grouped, external, subscription, variable-subscription).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'virtual'      => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by virtual products (true = only virtual, false = only non-virtual). Virtual products do not require shipping.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'downloadable' => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by downloadable products (true = only downloadable, false = only non-downloadable). Downloadable products are digital files.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'        => array(
						'type'        => 'integer',
						'description' => __( 'Number of products to return. Use -1 for all products (be careful with large catalogs).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
					'orderby'      => array(
						'type'        => 'string',
						'description' => __( 'Order by: date, title, price, popularity, rating.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'date',
					),
					'order'        => array(
						'type'        => 'string',
						'description' => __( 'Order direction: ASC or DESC.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'DESC',
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/products/search',
			array(
				'name'        => __( 'Search Products', 'assistify-for-woocommerce' ),
				'description' => __( 'Search products by name, SKU, or description with optional filters including virtual and downloadable.', 'assistify-for-woocommerce' ),
				'category'    => 'products',
				'callback'    => array( $this, 'ability_products_search' ),
				'parameters'  => array(
					'query'        => array(
						'type'        => 'string',
						'description' => __( 'Search query (product name, SKU, or description).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'type'         => array(
						'type'        => 'string',
						'description' => __( 'Filter by product type (simple, variable, grouped, external, subscription).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'category'     => array(
						'type'        => 'string',
						'description' => __( 'Filter by category slug.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'virtual'      => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by virtual products (true = only virtual, false = only non-virtual).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'downloadable' => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by downloadable products (true = only downloadable, false = only non-downloadable).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'status'       => array(
						'type'        => 'string',
						'description' => __( 'Filter by product status.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'publish',
					),
					'limit'        => array(
						'type'        => 'integer',
						'description' => __( 'Number of results to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/products/update',
			array(
				'name'             => __( 'Update Product', 'assistify-for-woocommerce' ),
				'description'      => __( 'Update an existing product\'s details including virtual/downloadable status, shipping dimensions, and tax settings.', 'assistify-for-woocommerce' ),
				'category'         => 'products',
				'callback'         => array( $this, 'ability_products_update' ),
				'parameters'       => array(
					'product_id'         => array(
						'type'        => 'integer',
						'description' => __( 'The product ID to update.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'name'               => array(
						'type'        => 'string',
						'description' => __( 'New product name.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'regular_price'      => array(
						'type'        => 'string',
						'description' => __( 'New regular price.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'sale_price'         => array(
						'type'        => 'string',
						'description' => __( 'New sale price (empty to remove sale).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'description'        => array(
						'type'        => 'string',
						'description' => __( 'New product description.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'short_description'  => array(
						'type'        => 'string',
						'description' => __( 'New short description.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'sku'                => array(
						'type'        => 'string',
						'description' => __( 'New SKU.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'stock_quantity'     => array(
						'type'        => 'integer',
						'description' => __( 'New stock quantity.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'stock_status'       => array(
						'type'        => 'string',
						'description' => __( 'New stock status (instock, outofstock, onbackorder).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'status'             => array(
						'type'        => 'string',
						'description' => __( 'New product status (publish, draft, pending, private).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'featured'           => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the product is featured.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'catalog_visibility' => array(
						'type'        => 'string',
						'description' => __( 'Catalog visibility (visible, catalog, search, hidden).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'virtual'            => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the product is virtual (no shipping required). Set to true for services, memberships, etc.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'downloadable'       => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the product is downloadable (digital file). Set to true for eBooks, software, etc.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'weight'             => array(
						'type'        => 'string',
						'description' => __( 'Product weight for shipping calculations.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'length'             => array(
						'type'        => 'string',
						'description' => __( 'Product length for shipping calculations.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'width'              => array(
						'type'        => 'string',
						'description' => __( 'Product width for shipping calculations.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'height'             => array(
						'type'        => 'string',
						'description' => __( 'Product height for shipping calculations.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'tax_status'         => array(
						'type'        => 'string',
						'description' => __( 'Tax status (taxable, shipping, none).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'tax_class'          => array(
						'type'        => 'string',
						'description' => __( 'Tax class (standard, reduced-rate, zero-rate, or custom class).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'       => 'manage_woocommerce',
				'requires_confirm' => true,
			)
		);

		$this->register(
			'afw/products/create',
			array(
				'name'             => __( 'Create Product', 'assistify-for-woocommerce' ),
				'description'      => __( 'Create a new product in WooCommerce.', 'assistify-for-woocommerce' ),
				'category'         => 'products',
				'callback'         => array( $this, 'ability_products_create' ),
				'parameters'       => array(
					'name'              => array(
						'type'        => 'string',
						'description' => __( 'Product name.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'type'              => array(
						'type'        => 'string',
						'description' => __( 'Product type (simple, variable, grouped, external). Default: simple.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'simple',
					),
					'regular_price'     => array(
						'type'        => 'string',
						'description' => __( 'Regular price.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'sale_price'        => array(
						'type'        => 'string',
						'description' => __( 'Sale price.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'description'       => array(
						'type'        => 'string',
						'description' => __( 'Product description.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'short_description' => array(
						'type'        => 'string',
						'description' => __( 'Short description.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'sku'               => array(
						'type'        => 'string',
						'description' => __( 'Product SKU.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'manage_stock'      => array(
						'type'        => 'boolean',
						'description' => __( 'Whether to manage stock.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
					'stock_quantity'    => array(
						'type'        => 'integer',
						'description' => __( 'Stock quantity (if manage_stock is true).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'stock_status'      => array(
						'type'        => 'string',
						'description' => __( 'Stock status (instock, outofstock, onbackorder).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'instock',
					),
					'categories'        => array(
						'type'        => 'array',
						'description' => __( 'Array of category IDs or slugs.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'status'            => array(
						'type'        => 'string',
						'description' => __( 'Product status (publish, draft, pending, private). Default: draft.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'draft',
					),
					'virtual'           => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the product is virtual.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
					'downloadable'      => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the product is downloadable.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
				),
				'capability'       => 'manage_woocommerce',
				'requires_confirm' => true,
			)
		);

		$this->register(
			'afw/products/count',
			array(
				'name'        => __( 'Count Products', 'assistify-for-woocommerce' ),
				'description' => __( 'Count products matching specific criteria. Useful for questions like "how many virtual products do I have?"', 'assistify-for-woocommerce' ),
				'category'    => 'products',
				'callback'    => array( $this, 'ability_products_count' ),
				'parameters'  => array(
					'type'         => array(
						'type'        => 'string',
						'description' => __( 'Filter by product type (simple, variable, grouped, external).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'virtual'      => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by virtual products (true = only virtual, false = only physical).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'downloadable' => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by downloadable products (true = only downloadable, false = only non-downloadable).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'category'     => array(
						'type'        => 'string',
						'description' => __( 'Filter by category slug.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'status'       => array(
						'type'        => 'string',
						'description' => __( 'Filter by product status (publish, draft, pending, private). Default: publish.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'publish',
					),
					'stock_status' => array(
						'type'        => 'string',
						'description' => __( 'Filter by stock status (instock, outofstock, onbackorder).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'featured'     => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by featured products.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'on_sale'      => array(
						'type'        => 'boolean',
						'description' => __( 'Filter by on-sale products.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/products/low-stock',
			array(
				'name'        => __( 'Low Stock Products', 'assistify-for-woocommerce' ),
				'description' => __( 'Get products with low stock levels.', 'assistify-for-woocommerce' ),
				'category'    => 'products',
				'callback'    => array( $this, 'ability_products_low_stock' ),
				'parameters'  => array(
					'threshold' => array(
						'type'        => 'integer',
						'description' => __( 'Stock threshold to consider as low.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 5,
					),
					'limit'     => array(
						'type'        => 'integer',
						'description' => __( 'Number of products to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Analytics abilities.
		$this->register(
			'afw/analytics/sales',
			array(
				'name'        => __( 'Sales Analytics', 'assistify-for-woocommerce' ),
				'description' => __( 'Get sales data for a date range.', 'assistify-for-woocommerce' ),
				'category'    => 'analytics',
				'callback'    => array( $this, 'ability_analytics_sales' ),
				'parameters'  => array(
					'period'     => array(
						'type'        => 'string',
						'description' => __( 'Period: today, week, month, year.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'month',
					),
					'start_date' => array(
						'type'        => 'string',
						'description' => __( 'Start date (YYYY-MM-DD).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'end_date'   => array(
						'type'        => 'string',
						'description' => __( 'End date (YYYY-MM-DD).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/analytics/top-products',
			array(
				'name'        => __( 'Top Products', 'assistify-for-woocommerce' ),
				'description' => __( 'Get top selling products.', 'assistify-for-woocommerce' ),
				'category'    => 'analytics',
				'callback'    => array( $this, 'ability_analytics_top_products' ),
				'parameters'  => array(
					'period' => array(
						'type'        => 'string',
						'description' => __( 'Period: week, month, year, all.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'month',
					),
					'limit'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of products to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/analytics/revenue',
			array(
				'name'        => __( 'Revenue Analytics', 'assistify-for-woocommerce' ),
				'description' => __( 'Get detailed revenue metrics including gross, net, taxes, shipping, and refunds.', 'assistify-for-woocommerce' ),
				'category'    => 'analytics',
				'callback'    => array( $this, 'ability_analytics_revenue' ),
				'parameters'  => array(
					'period'     => array(
						'type'        => 'string',
						'description' => __( 'Period: today, week, month, quarter, year.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'month',
					),
					'start_date' => array(
						'type'        => 'string',
						'description' => __( 'Custom start date (YYYY-MM-DD).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'end_date'   => array(
						'type'        => 'string',
						'description' => __( 'Custom end date (YYYY-MM-DD).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'compare'    => array(
						'type'        => 'boolean',
						'description' => __( 'Include comparison with previous period.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/analytics/top-customers',
			array(
				'name'        => __( 'Top Customers', 'assistify-for-woocommerce' ),
				'description' => __( 'Get top customers by total spent or order count.', 'assistify-for-woocommerce' ),
				'category'    => 'analytics',
				'callback'    => array( $this, 'ability_analytics_top_customers' ),
				'parameters'  => array(
					'period'  => array(
						'type'        => 'string',
						'description' => __( 'Period: week, month, year, all.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'month',
					),
					'orderby' => array(
						'type'        => 'string',
						'description' => __( 'Order by: total_spent, order_count.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'total_spent',
					),
					'limit'   => array(
						'type'        => 'integer',
						'description' => __( 'Number of customers to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Customer abilities.
		$this->register(
			'afw/customers/get',
			array(
				'name'        => __( 'Get Customer', 'assistify-for-woocommerce' ),
				'description' => __( 'Get details of a specific customer.', 'assistify-for-woocommerce' ),
				'category'    => 'customers',
				'callback'    => array( $this, 'ability_customers_get' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer/user ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/customers/list',
			array(
				'name'        => __( 'List Customers', 'assistify-for-woocommerce' ),
				'description' => __( 'List customers with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'customers',
				'callback'    => array( $this, 'ability_customers_list' ),
				'parameters'  => array(
					'orderby' => array(
						'type'        => 'string',
						'description' => __( 'Order by: registered, order_count, total_spent.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'registered',
					),
					'order'   => array(
						'type'        => 'string',
						'description' => __( 'Order direction: ASC or DESC.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'DESC',
					),
					'limit'   => array(
						'type'        => 'integer',
						'description' => __( 'Number of customers to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/customers/search',
			array(
				'name'        => __( 'Search Customers', 'assistify-for-woocommerce' ),
				'description' => __( 'Search customers by name or email.', 'assistify-for-woocommerce' ),
				'category'    => 'customers',
				'callback'    => array( $this, 'ability_customers_search' ),
				'parameters'  => array(
					'query' => array(
						'type'        => 'string',
						'description' => __( 'Search query (name, email, or username).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of results to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/customers/orders',
			array(
				'name'        => __( 'Get Customer Orders', 'assistify-for-woocommerce' ),
				'description' => __( 'Get orders for a specific customer.', 'assistify-for-woocommerce' ),
				'category'    => 'customers',
				'callback'    => array( $this, 'ability_customers_orders' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer/user ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'limit'       => array(
						'type'        => 'integer',
						'description' => __( 'Number of orders to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// =====================================================
		// Store Settings & Configuration Abilities
		// =====================================================

		$this->register(
			'afw/store/settings',
			array(
				'name'        => __( 'Get Store Settings', 'assistify-for-woocommerce' ),
				'description' => __( 'Get WooCommerce store settings including general, tax, shipping, and checkout settings.', 'assistify-for-woocommerce' ),
				'category'    => 'store',
				'callback'    => array( $this, 'ability_store_settings' ),
				'parameters'  => array(
					'group' => array(
						'type'        => 'string',
						'description' => __( 'Settings group: general, products, tax, shipping, checkout, accounts, emails, advanced, or all.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'all',
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/store/status',
			array(
				'name'        => __( 'Get System Status', 'assistify-for-woocommerce' ),
				'description' => __( 'Get WooCommerce system status including environment info, database status, active plugins, and theme info.', 'assistify-for-woocommerce' ),
				'category'    => 'store',
				'callback'    => array( $this, 'ability_store_status' ),
				'parameters'  => array(),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/store/payment-gateways',
			array(
				'name'        => __( 'Get Payment Gateways', 'assistify-for-woocommerce' ),
				'description' => __( 'List all available payment gateways and their status (enabled/disabled).', 'assistify-for-woocommerce' ),
				'category'    => 'store',
				'callback'    => array( $this, 'ability_store_payment_gateways' ),
				'parameters'  => array(),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/store/shipping-zones',
			array(
				'name'        => __( 'Get Shipping Zones', 'assistify-for-woocommerce' ),
				'description' => __( 'List all shipping zones and their methods.', 'assistify-for-woocommerce' ),
				'category'    => 'store',
				'callback'    => array( $this, 'ability_store_shipping_zones' ),
				'parameters'  => array(),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/store/shipping-classes',
			array(
				'name'        => __( 'Get Shipping Classes', 'assistify-for-woocommerce' ),
				'description' => __( 'List all product shipping classes.', 'assistify-for-woocommerce' ),
				'category'    => 'store',
				'callback'    => array( $this, 'ability_store_shipping_classes' ),
				'parameters'  => array(),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/store/tax-rates',
			array(
				'name'        => __( 'Get Tax Rates', 'assistify-for-woocommerce' ),
				'description' => __( 'List all tax rates configured in the store.', 'assistify-for-woocommerce' ),
				'category'    => 'store',
				'callback'    => array( $this, 'ability_store_tax_rates' ),
				'parameters'  => array(
					'class' => array(
						'type'        => 'string',
						'description' => __( 'Filter by tax class (standard, reduced-rate, zero-rate, or custom).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/store/tax-classes',
			array(
				'name'        => __( 'Get Tax Classes', 'assistify-for-woocommerce' ),
				'description' => __( 'List all available tax classes.', 'assistify-for-woocommerce' ),
				'category'    => 'store',
				'callback'    => array( $this, 'ability_store_tax_classes' ),
				'parameters'  => array(),
				'capability'  => 'manage_woocommerce',
			)
		);

		// =====================================================
		// Coupon Abilities
		// =====================================================

		$this->register(
			'afw/coupons/list',
			array(
				'name'        => __( 'List Coupons', 'assistify-for-woocommerce' ),
				'description' => __( 'List all coupons with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'coupons',
				'callback'    => array( $this, 'ability_coupons_list' ),
				'parameters'  => array(
					'status' => array(
						'type'        => 'string',
						'description' => __( 'Filter by status: publish, draft, pending, trash.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'publish',
					),
					'limit'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of coupons to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/coupons/get',
			array(
				'name'        => __( 'Get Coupon', 'assistify-for-woocommerce' ),
				'description' => __( 'Get details of a specific coupon by ID or code.', 'assistify-for-woocommerce' ),
				'category'    => 'coupons',
				'callback'    => array( $this, 'ability_coupons_get' ),
				'parameters'  => array(
					'coupon_id' => array(
						'type'        => 'integer',
						'description' => __( 'The coupon ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'code'      => array(
						'type'        => 'string',
						'description' => __( 'The coupon code.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/coupons/create',
			array(
				'name'             => __( 'Create Coupon', 'assistify-for-woocommerce' ),
				'description'      => __( 'Create a new coupon with specified settings.', 'assistify-for-woocommerce' ),
				'category'         => 'coupons',
				'callback'         => array( $this, 'ability_coupons_create' ),
				'parameters'       => array(
					'code'                        => array(
						'type'        => 'string',
						'description' => __( 'Coupon code (required).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'discount_type'               => array(
						'type'        => 'string',
						'description' => __( 'Discount type: percent, fixed_cart, fixed_product.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'percent',
					),
					'amount'                      => array(
						'type'        => 'string',
						'description' => __( 'Discount amount.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'description'                 => array(
						'type'        => 'string',
						'description' => __( 'Coupon description.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'date_expires'                => array(
						'type'        => 'string',
						'description' => __( 'Expiration date (YYYY-MM-DD).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'usage_limit'                 => array(
						'type'        => 'integer',
						'description' => __( 'Total usage limit.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'usage_limit_per_user'        => array(
						'type'        => 'integer',
						'description' => __( 'Usage limit per user.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'free_shipping'               => array(
						'type'        => 'boolean',
						'description' => __( 'Whether coupon grants free shipping.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
					'minimum_amount'              => array(
						'type'        => 'string',
						'description' => __( 'Minimum order amount required.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'maximum_amount'              => array(
						'type'        => 'string',
						'description' => __( 'Maximum order amount allowed.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'individual_use'              => array(
						'type'        => 'boolean',
						'description' => __( 'Whether coupon can only be used alone.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
					'exclude_sale_items'          => array(
						'type'        => 'boolean',
						'description' => __( 'Whether to exclude sale items.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
				),
				'capability'       => 'manage_woocommerce',
				'requires_confirm' => true,
			)
		);

		$this->register(
			'afw/coupons/update',
			array(
				'name'             => __( 'Update Coupon', 'assistify-for-woocommerce' ),
				'description'      => __( 'Update an existing coupon\'s settings.', 'assistify-for-woocommerce' ),
				'category'         => 'coupons',
				'callback'         => array( $this, 'ability_coupons_update' ),
				'parameters'       => array(
					'coupon_id'                   => array(
						'type'        => 'integer',
						'description' => __( 'The coupon ID to update.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'code'                        => array(
						'type'        => 'string',
						'description' => __( 'New coupon code.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'amount'                      => array(
						'type'        => 'string',
						'description' => __( 'New discount amount.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'discount_type'               => array(
						'type'        => 'string',
						'description' => __( 'New discount type.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'date_expires'                => array(
						'type'        => 'string',
						'description' => __( 'New expiration date (YYYY-MM-DD) or empty to remove.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'usage_limit'                 => array(
						'type'        => 'integer',
						'description' => __( 'New total usage limit.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'status'                      => array(
						'type'        => 'string',
						'description' => __( 'New status: publish, draft, pending.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'       => 'manage_woocommerce',
				'requires_confirm' => true,
			)
		);

		$this->register(
			'afw/coupons/delete',
			array(
				'name'             => __( 'Delete Coupon', 'assistify-for-woocommerce' ),
				'description'      => __( 'Delete a coupon (move to trash or permanently delete).', 'assistify-for-woocommerce' ),
				'category'         => 'coupons',
				'callback'         => array( $this, 'ability_coupons_delete' ),
				'parameters'       => array(
					'coupon_id' => array(
						'type'        => 'integer',
						'description' => __( 'The coupon ID to delete.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'force'     => array(
						'type'        => 'boolean',
						'description' => __( 'Permanently delete instead of moving to trash.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => false,
					),
				),
				'capability'       => 'manage_woocommerce',
				'requires_confirm' => true,
				'is_destructive'   => true,
			)
		);

		// =========================================================================
		// WordPress Content Abilities
		// =========================================================================

		$this->register(
			'afw/content/pages',
			array(
				'name'        => __( 'List Pages', 'assistify-for-woocommerce' ),
				'description' => __( 'List WordPress pages with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'content',
				'callback'    => array( $this, 'ability_content_pages' ),
				'parameters'  => array(
					'search' => array(
						'type'        => 'string',
						'description' => __( 'Search pages by title or content.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of pages to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
					'status' => array(
						'type'        => 'string',
						'description' => __( 'Page status (publish, draft, private).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'publish',
					),
				),
				'capability'  => 'read',
			)
		);

		$this->register(
			'afw/content/posts',
			array(
				'name'        => __( 'List Posts', 'assistify-for-woocommerce' ),
				'description' => __( 'List WordPress blog posts with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'content',
				'callback'    => array( $this, 'ability_content_posts' ),
				'parameters'  => array(
					'search'   => array(
						'type'        => 'string',
						'description' => __( 'Search posts by title or content.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'category' => array(
						'type'        => 'string',
						'description' => __( 'Filter by category slug or ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'tag'      => array(
						'type'        => 'string',
						'description' => __( 'Filter by tag slug.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'    => array(
						'type'        => 'integer',
						'description' => __( 'Number of posts to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'read',
			)
		);

		$this->register(
			'afw/content/categories',
			array(
				'name'        => __( 'List Categories', 'assistify-for-woocommerce' ),
				'description' => __( 'List WordPress post categories.', 'assistify-for-woocommerce' ),
				'category'    => 'content',
				'callback'    => array( $this, 'ability_content_categories' ),
				'parameters'  => array(
					'hide_empty' => array(
						'type'        => 'boolean',
						'description' => __( 'Hide categories with no posts.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => true,
					),
				),
				'capability'  => 'read',
			)
		);

		$this->register(
			'afw/content/tags',
			array(
				'name'        => __( 'List Tags', 'assistify-for-woocommerce' ),
				'description' => __( 'List WordPress post tags.', 'assistify-for-woocommerce' ),
				'category'    => 'content',
				'callback'    => array( $this, 'ability_content_tags' ),
				'parameters'  => array(
					'hide_empty' => array(
						'type'        => 'boolean',
						'description' => __( 'Hide tags with no posts.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => true,
					),
					'limit'      => array(
						'type'        => 'integer',
						'description' => __( 'Number of tags to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 50,
					),
				),
				'capability'  => 'read',
			)
		);

		$this->register(
			'afw/content/menus',
			array(
				'name'        => __( 'List Navigation Menus', 'assistify-for-woocommerce' ),
				'description' => __( 'List WordPress navigation menus and their items.', 'assistify-for-woocommerce' ),
				'category'    => 'content',
				'callback'    => array( $this, 'ability_content_menus' ),
				'parameters'  => array(
					'menu_id' => array(
						'type'        => 'integer',
						'description' => __( 'Specific menu ID to get items for.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
			)
		);

		// =========================================================================
		// URL Generation Abilities
		// =========================================================================

		$this->register(
			'afw/urls/product',
			array(
				'name'        => __( 'Get Product URL', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the frontend URL for a product.', 'assistify-for-woocommerce' ),
				'category'    => 'urls',
				'callback'    => array( $this, 'ability_urls_product' ),
				'parameters'  => array(
					'product_id' => array(
						'type'        => 'integer',
						'description' => __( 'The product ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'product_name' => array(
						'type'        => 'string',
						'description' => __( 'Search by product name to get its URL.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
			)
		);

		$this->register(
			'afw/urls/product-edit',
			array(
				'name'        => __( 'Get Product Edit URL', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the admin edit URL for a product.', 'assistify-for-woocommerce' ),
				'category'    => 'urls',
				'callback'    => array( $this, 'ability_urls_product_edit' ),
				'parameters'  => array(
					'product_id' => array(
						'type'        => 'integer',
						'description' => __( 'The product ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'product_name' => array(
						'type'        => 'string',
						'description' => __( 'Search by product name to get its edit URL.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/urls/order',
			array(
				'name'        => __( 'Get Order URL', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the admin URL for viewing/editing an order.', 'assistify-for-woocommerce' ),
				'category'    => 'urls',
				'callback'    => array( $this, 'ability_urls_order' ),
				'parameters'  => array(
					'order_id' => array(
						'type'        => 'integer',
						'description' => __( 'The order ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/urls/customer',
			array(
				'name'        => __( 'Get Customer URL', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the admin URL for viewing a customer profile.', 'assistify-for-woocommerce' ),
				'category'    => 'urls',
				'callback'    => array( $this, 'ability_urls_customer' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer/user ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/urls/category',
			array(
				'name'        => __( 'Get Category URL', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the frontend URL for a product category.', 'assistify-for-woocommerce' ),
				'category'    => 'urls',
				'callback'    => array( $this, 'ability_urls_category' ),
				'parameters'  => array(
					'category_id' => array(
						'type'        => 'integer',
						'description' => __( 'The category ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'category_name' => array(
						'type'        => 'string',
						'description' => __( 'Search by category name.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
			)
		);

		$this->register(
			'afw/urls/settings',
			array(
				'name'        => __( 'Get WooCommerce Settings URL', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the admin URL for various WooCommerce settings pages.', 'assistify-for-woocommerce' ),
				'category'    => 'urls',
				'callback'    => array( $this, 'ability_urls_settings' ),
				'parameters'  => array(
					'section' => array(
						'type'        => 'string',
						'description' => __( 'Settings section: general, products, tax, shipping, payments, accounts, emails, integration, advanced, assistify.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		$this->register(
			'afw/urls/page',
			array(
				'name'        => __( 'Get Page URL', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the URL for a WordPress page.', 'assistify-for-woocommerce' ),
				'category'    => 'urls',
				'callback'    => array( $this, 'ability_urls_page' ),
				'parameters'  => array(
					'page_id' => array(
						'type'        => 'integer',
						'description' => __( 'The page ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'page_title' => array(
						'type'        => 'string',
						'description' => __( 'Search by page title.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'page_slug' => array(
						'type'        => 'string',
						'description' => __( 'Search by page slug.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
			)
		);
	}

	/**
	 * Trigger abilities registration hook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function trigger_abilities_registration() {
		/**
		 * Action: Register custom abilities.
		 *
		 * @since 1.0.0
		 * @param Abilities_Registry $registry The abilities registry instance.
		 */
		do_action( 'assistify_register_abilities', $this );
	}

	/**
	 * Register an ability.
	 *
	 * @since 1.0.0
	 * @param string $ability_id Unique ability identifier (e.g., 'afw/orders/get').
	 * @param array  $args       Ability arguments.
	 * @return bool True on success, false on failure.
	 */
	public function register( $ability_id, $args ) {
		$defaults = array(
			'name'            => '',
			'description'     => '',
			'category'        => 'store',
			'callback'        => null,
			'parameters'      => array(),
			'capability'      => 'manage_woocommerce',
			'requires_confirm' => false,
			'is_destructive'  => false,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate required fields.
		if ( empty( $args['name'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}

		$this->abilities[ $ability_id ] = $args;

		return true;
	}

	/**
	 * Unregister an ability.
	 *
	 * @since 1.0.0
	 * @param string $ability_id Ability identifier.
	 * @return bool True if removed, false if not found.
	 */
	public function unregister( $ability_id ) {
		if ( isset( $this->abilities[ $ability_id ] ) ) {
			unset( $this->abilities[ $ability_id ] );
			return true;
		}
		return false;
	}

	/**
	 * Get all registered abilities.
	 *
	 * @since 1.0.0
	 * @param string $category Optional. Filter by category.
	 * @return array Registered abilities.
	 */
	public function get_abilities( $category = '' ) {
		if ( empty( $category ) ) {
			return $this->abilities;
		}

		return array_filter(
			$this->abilities,
			function ( $ability ) use ( $category ) {
				return $ability['category'] === $category;
			}
		);
	}

	/**
	 * Get a specific ability.
	 *
	 * @since 1.0.0
	 * @param string $ability_id Ability identifier.
	 * @return array|null Ability data or null if not found.
	 */
	public function get_ability( $ability_id ) {
		return isset( $this->abilities[ $ability_id ] ) ? $this->abilities[ $ability_id ] : null;
	}

	/**
	 * Execute an ability.
	 *
	 * @since 1.0.0
	 * @param string $ability_id Ability identifier.
	 * @param array  $params     Parameters to pass to the ability.
	 * @return mixed|\WP_Error Result or WP_Error on failure.
	 */
	public function execute( $ability_id, $params = array() ) {
		$ability = $this->get_ability( $ability_id );

		if ( ! $ability ) {
			return new \WP_Error(
				'assistify_ability_not_found',
				sprintf(
					/* translators: %s: ability ID. */
					__( 'Ability not found: %s', 'assistify-for-woocommerce' ),
					$ability_id
				)
			);
		}

		// Check capability.
		if ( ! current_user_can( $ability['capability'] ) ) {
			return new \WP_Error(
				'assistify_ability_forbidden',
				__( 'You do not have permission to execute this ability.', 'assistify-for-woocommerce' )
			);
		}

		// Validate required parameters.
		$validation = $this->validate_parameters( $ability, $params );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Log the action attempt.
		$this->log_action( $ability_id, $params, 'pending' );

		// Execute the ability.
		try {
			$result = call_user_func( $ability['callback'], $params );

			// Log success.
			$this->log_action( $ability_id, $params, 'success', $result );

			return $result;
		} catch ( \Exception $e ) {
			// Log failure.
			$this->log_action( $ability_id, $params, 'failed', $e->getMessage() );

			return new \WP_Error(
				'assistify_ability_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Validate ability parameters.
	 *
	 * @since 1.0.0
	 * @param array $ability Ability configuration.
	 * @param array $params  Provided parameters.
	 * @return bool|\WP_Error True if valid, WP_Error on failure.
	 */
	private function validate_parameters( $ability, $params ) {
		foreach ( $ability['parameters'] as $param_name => $param_config ) {
			if ( ! empty( $param_config['required'] ) && ! isset( $params[ $param_name ] ) ) {
				return new \WP_Error(
					'assistify_missing_parameter',
					sprintf(
						/* translators: %s: parameter name. */
						__( 'Missing required parameter: %s', 'assistify-for-woocommerce' ),
						$param_name
					)
				);
			}

			// Type validation.
			if ( isset( $params[ $param_name ] ) && isset( $param_config['type'] ) ) {
				$value = $params[ $param_name ];
				$valid = true;

				switch ( $param_config['type'] ) {
					case 'integer':
						$valid = is_numeric( $value );
						break;
					case 'string':
						$valid = is_string( $value );
						break;
					case 'boolean':
						$valid = is_bool( $value ) || in_array( $value, array( 'true', 'false', '0', '1', 0, 1 ), true );
						break;
					case 'array':
						$valid = is_array( $value );
						break;
				}

				if ( ! $valid ) {
					return new \WP_Error(
						'assistify_invalid_parameter',
						sprintf(
							/* translators: %1$s: parameter name, %2$s: expected type. */
							__( 'Invalid type for parameter %1$s. Expected %2$s.', 'assistify-for-woocommerce' ),
							$param_name,
							$param_config['type']
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Log an ability execution.
	 *
	 * @since 1.0.0
	 * @param string $ability_id Ability identifier.
	 * @param array  $params     Parameters.
	 * @param string $status     Status (pending, success, failed).
	 * @param mixed  $result     Result or error message.
	 * @return void
	 */
	private function log_action( $ability_id, $params, $status, $result = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'afw_actions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Audit logging.
		$wpdb->insert(
			$table_name,
			array(
				'user_id'      => get_current_user_id(),
				'ability'      => $ability_id,
				'input_params' => wp_json_encode( $params ),
				'result'       => $result ? wp_json_encode( $result ) : null,
				'status'       => $status,
				'executed_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get abilities schema for AI context.
	 *
	 * Returns abilities in a format suitable for AI function calling.
	 *
	 * @since 1.0.0
	 * @return array Abilities schema.
	 */
	public function get_abilities_schema() {
		$schema = array();

		foreach ( $this->abilities as $ability_id => $ability ) {
			// Skip abilities user can't access.
			if ( ! current_user_can( $ability['capability'] ) ) {
				continue;
			}

			$params = array(
				'type'       => 'object',
				'properties' => array(),
				'required'   => array(),
			);

			foreach ( $ability['parameters'] as $param_name => $param_config ) {
				$params['properties'][ $param_name ] = array(
					'type'        => $param_config['type'] ?? 'string',
					'description' => $param_config['description'] ?? '',
				);

				if ( ! empty( $param_config['required'] ) ) {
					$params['required'][] = $param_name;
				}
			}

			$schema[] = array(
				'name'        => $ability_id,
				'description' => $ability['description'],
				'parameters'  => $params,
			);
		}

		return $schema;
	}

	// =====================================================
	// Core Ability Implementations
	// =====================================================

	/**
	 * Get order details.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'order_id'.
	 * @return array|\WP_Error Order data or error.
	 */
	public function ability_orders_get( $params ) {
		$order = wc_get_order( absint( $params['order_id'] ) );

		if ( ! $order ) {
			return new \WP_Error(
				'assistify_order_not_found',
				__( 'Order not found.', 'assistify-for-woocommerce' )
			);
		}

		return array(
			'id'               => $order->get_id(),
			'number'           => $order->get_order_number(),
			'status'           => $order->get_status(),
			'date_created'     => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
			'total'            => $order->get_total(),
			'currency'         => $order->get_currency(),
			'customer'         => array(
				'id'    => $order->get_customer_id(),
				'name'  => $order->get_formatted_billing_full_name(),
				'email' => $order->get_billing_email(),
			),
			'items'            => $this->format_order_items( $order ),
			'shipping_method'  => $order->get_shipping_method(),
			'payment_method'   => $order->get_payment_method_title(),
			'notes'            => $this->get_order_notes( $order->get_id() ),
		);
	}

	/**
	 * List orders.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Orders list.
	 */
	public function ability_orders_list( $params ) {
		$args = array(
			'limit'   => isset( $params['limit'] ) ? absint( $params['limit'] ) : 10,
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( isset( $params['status'] ) ) {
			$args['status'] = sanitize_text_field( $params['status'] );
		}

		if ( isset( $params['customer'] ) ) {
			$args['customer'] = absint( $params['customer'] );
		}

		$orders  = wc_get_orders( $args );
		$results = array();

		foreach ( $orders as $order ) {
			$results[] = array(
				'id'           => $order->get_id(),
				'number'       => $order->get_order_number(),
				'status'       => $order->get_status(),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
				'total'        => $order->get_total(),
				'customer'     => $order->get_formatted_billing_full_name(),
			);
		}

		return array(
			'orders' => $results,
			'count'  => count( $results ),
		);
	}

	/**
	 * Search orders.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'query'.
	 * @return array Search results.
	 */
	public function ability_orders_search( $params ) {
		$query = sanitize_text_field( $params['query'] );
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		// Search by order number first.
		$order = wc_get_order( $query );
		if ( $order ) {
			return array(
				'orders' => array( $this->ability_orders_get( array( 'order_id' => $order->get_id() ) ) ),
				'count'  => 1,
			);
		}

		// Search by customer email or name.
		$args = array(
			'limit'          => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'billing_email'  => $query,
		);

		$orders = wc_get_orders( $args );

		if ( empty( $orders ) ) {
			// Try searching by name.
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Search query.
			$order_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT order_id FROM {$wpdb->prefix}wc_orders_meta 
					WHERE meta_key IN ('_billing_first_name', '_billing_last_name', '_shipping_first_name', '_shipping_last_name')
					AND meta_value LIKE %s
					LIMIT %d",
					'%' . $wpdb->esc_like( $query ) . '%',
					$limit
				)
			);

			$orders = array_map( 'wc_get_order', $order_ids );
			$orders = array_filter( $orders );
		}

		$results = array();
		foreach ( $orders as $order ) {
			if ( $order ) {
				$results[] = array(
					'id'           => $order->get_id(),
					'number'       => $order->get_order_number(),
					'status'       => $order->get_status(),
					'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
					'total'        => $order->get_total(),
					'customer'     => $order->get_formatted_billing_full_name(),
				);
			}
		}

		return array(
			'orders' => $results,
			'count'  => count( $results ),
			'query'  => $query,
		);
	}

	/**
	 * Get product details.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'product_id'.
	 * @return array|\WP_Error Product data or error.
	 */
	public function ability_products_get( $params ) {
		$product = wc_get_product( absint( $params['product_id'] ) );

		if ( ! $product ) {
			return new \WP_Error(
				'assistify_product_not_found',
				__( 'Product not found.', 'assistify-for-woocommerce' )
			);
		}

		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'stock_status'      => $product->get_stock_status(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'virtual'           => $product->is_virtual(),
			'downloadable'      => $product->is_downloadable(),
			'categories'        => $this->get_product_categories( $product ),
			'short_description' => $product->get_short_description(),
			'description'       => $product->get_description(),
		);
	}

	/**
	 * List products.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Products list.
	 */
	public function ability_products_list( $params ) {
		$limit = isset( $params['limit'] ) ? intval( $params['limit'] ) : 10;
		// Allow -1 for all products but cap at 100 for safety.
		if ( -1 === $limit ) {
			$limit = 100;
		}

		$args = array(
			'limit'   => $limit,
			'status'  => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'publish',
			'orderby' => isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'date',
			'order'   => isset( $params['order'] ) ? strtoupper( sanitize_text_field( $params['order'] ) ) : 'DESC',
		);

		// Validate order direction.
		$args['order'] = in_array( $args['order'], array( 'ASC', 'DESC' ), true ) ? $args['order'] : 'DESC';

		// Filter by category.
		if ( isset( $params['category'] ) && ! empty( $params['category'] ) ) {
			$args['category'] = array( sanitize_text_field( $params['category'] ) );
		}

		// Filter by product type.
		if ( isset( $params['type'] ) && ! empty( $params['type'] ) ) {
			$args['type'] = sanitize_text_field( $params['type'] );
		}

		// Filter by virtual status.
		if ( isset( $params['virtual'] ) ) {
			$args['virtual'] = filter_var( $params['virtual'], FILTER_VALIDATE_BOOLEAN );
		}

		// Filter by downloadable status.
		if ( isset( $params['downloadable'] ) ) {
			$args['downloadable'] = filter_var( $params['downloadable'], FILTER_VALIDATE_BOOLEAN );
		}

		$products = wc_get_products( $args );
		$results  = array();

		foreach ( $products as $product ) {
			$results[] = array(
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'type'           => $product->get_type(),
				'sku'            => $product->get_sku(),
				'price'          => $product->get_price(),
				'regular_price'  => $product->get_regular_price(),
				'sale_price'     => $product->get_sale_price(),
				'stock_status'   => $product->get_stock_status(),
				'stock_quantity' => $product->get_stock_quantity(),
				'virtual'        => $product->is_virtual(),
				'downloadable'   => $product->is_downloadable(),
				'categories'     => $this->get_product_categories( $product ),
			);
		}

		return array(
			'products' => $results,
			'count'    => count( $results ),
			'filters'  => array(
				'type'         => $params['type'] ?? null,
				'category'     => $params['category'] ?? null,
				'status'       => $args['status'],
				'virtual'      => $params['virtual'] ?? null,
				'downloadable' => $params['downloadable'] ?? null,
			),
		);
	}

	/**
	 * Count products matching criteria.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters for filtering.
	 * @return array Count data with breakdown.
	 */
	public function ability_products_count( $params ) {
		$args = array(
			'limit'  => -1,
			'return' => 'ids',
			'status' => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'publish',
		);

		// Filter by product type.
		if ( isset( $params['type'] ) && ! empty( $params['type'] ) ) {
			$args['type'] = sanitize_text_field( $params['type'] );
		}

		// Filter by virtual status.
		if ( isset( $params['virtual'] ) ) {
			$args['virtual'] = filter_var( $params['virtual'], FILTER_VALIDATE_BOOLEAN );
		}

		// Filter by downloadable status.
		if ( isset( $params['downloadable'] ) ) {
			$args['downloadable'] = filter_var( $params['downloadable'], FILTER_VALIDATE_BOOLEAN );
		}

		// Filter by category.
		if ( isset( $params['category'] ) && ! empty( $params['category'] ) ) {
			$args['category'] = array( sanitize_text_field( $params['category'] ) );
		}

		// Filter by stock status.
		if ( isset( $params['stock_status'] ) && ! empty( $params['stock_status'] ) ) {
			$args['stock_status'] = sanitize_text_field( $params['stock_status'] );
		}

		// Filter by featured.
		if ( isset( $params['featured'] ) ) {
			$args['featured'] = filter_var( $params['featured'], FILTER_VALIDATE_BOOLEAN );
		}

		// Filter by on sale.
		if ( isset( $params['on_sale'] ) ) {
			$on_sale = filter_var( $params['on_sale'], FILTER_VALIDATE_BOOLEAN );
			if ( $on_sale ) {
				$args['include'] = wc_get_product_ids_on_sale();
				if ( empty( $args['include'] ) ) {
					// No products on sale, return 0.
					return array(
						'count'   => 0,
						'filters' => $this->build_count_filters( $params ),
						'message' => __( 'No products found matching the criteria.', 'assistify-for-woocommerce' ),
					);
				}
			}
		}

		$product_ids = wc_get_products( $args );
		$count       = count( $product_ids );

		// Build descriptive message.
		$description = $this->build_count_description( $params, $count );

		return array(
			'count'       => $count,
			'filters'     => $this->build_count_filters( $params ),
			'message'     => $description,
			'product_ids' => $count <= 20 ? $product_ids : array_slice( $product_ids, 0, 20 ),
		);
	}

	/**
	 * Build filters array for count response.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Filters applied.
	 */
	private function build_count_filters( $params ) {
		return array(
			'type'         => $params['type'] ?? null,
			'virtual'      => $params['virtual'] ?? null,
			'downloadable' => $params['downloadable'] ?? null,
			'category'     => $params['category'] ?? null,
			'status'       => $params['status'] ?? 'publish',
			'stock_status' => $params['stock_status'] ?? null,
			'featured'     => $params['featured'] ?? null,
			'on_sale'      => $params['on_sale'] ?? null,
		);
	}

	/**
	 * Build description for count response.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @param int   $count  Product count.
	 * @return string Description.
	 */
	private function build_count_description( $params, $count ) {
		$parts = array();

		if ( isset( $params['virtual'] ) && $params['virtual'] ) {
			$parts[] = __( 'virtual', 'assistify-for-woocommerce' );
		}
		if ( isset( $params['downloadable'] ) && $params['downloadable'] ) {
			$parts[] = __( 'downloadable', 'assistify-for-woocommerce' );
		}
		if ( isset( $params['featured'] ) && $params['featured'] ) {
			$parts[] = __( 'featured', 'assistify-for-woocommerce' );
		}
		if ( isset( $params['on_sale'] ) && $params['on_sale'] ) {
			$parts[] = __( 'on sale', 'assistify-for-woocommerce' );
		}
		if ( isset( $params['type'] ) ) {
			$parts[] = $params['type'];
		}
		if ( isset( $params['stock_status'] ) ) {
			$parts[] = str_replace( array( 'instock', 'outofstock', 'onbackorder' ), array( 'in stock', 'out of stock', 'on backorder' ), $params['stock_status'] );
		}
		if ( isset( $params['category'] ) ) {
			/* translators: %s: category name */
			$parts[] = sprintf( __( 'in category "%s"', 'assistify-for-woocommerce' ), $params['category'] );
		}

		$product_type = empty( $parts ) ? __( 'products', 'assistify-for-woocommerce' ) : implode( ' ', $parts ) . ' ' . __( 'products', 'assistify-for-woocommerce' );

		return sprintf(
			/* translators: %1$d: count, %2$s: product type description */
			_n(
				'Found %1$d %2$s.',
				'Found %1$d %2$s.',
				$count,
				'assistify-for-woocommerce'
			),
			$count,
			$product_type
		);
	}

	/**
	 * Get low stock products.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Low stock products.
	 */
	public function ability_products_low_stock( $params ) {
		$threshold = isset( $params['threshold'] ) ? absint( $params['threshold'] ) : 5;
		$limit     = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;

		$args = array(
			'limit'        => $limit,
			'stock_status' => array( 'instock', 'onbackorder' ),
			'manage_stock' => true,
			'orderby'      => 'meta_value_num',
			'meta_key'     => '_stock', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'        => 'ASC',
		);

		$products = wc_get_products( $args );
		$results  = array();

		foreach ( $products as $product ) {
			$stock = $product->get_stock_quantity();
			if ( $stock !== null && $stock <= $threshold ) {
				$results[] = array(
					'id'             => $product->get_id(),
					'name'           => $product->get_name(),
					'sku'            => $product->get_sku(),
					'stock_quantity' => $stock,
					'stock_status'   => $product->get_stock_status(),
				);
			}
		}

		return array(
			'products'  => $results,
			'count'     => count( $results ),
			'threshold' => $threshold,
		);
	}

	/**
	 * Get sales analytics.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Sales data.
	 */
	public function ability_analytics_sales( $params ) {
		$period = isset( $params['period'] ) ? sanitize_text_field( $params['period'] ) : 'month';

		// Calculate date range.
		$end_date   = current_time( 'Y-m-d' );
		$start_date = $end_date;

		switch ( $period ) {
			case 'today':
				$start_date = $end_date;
				break;
			case 'week':
				$start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				break;
			case 'month':
				$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				break;
			case 'year':
				$start_date = gmdate( 'Y-m-d', strtotime( '-365 days' ) );
				break;
		}

		// Override with custom dates if provided.
		if ( isset( $params['start_date'] ) ) {
			$start_date = sanitize_text_field( $params['start_date'] );
		}
		if ( isset( $params['end_date'] ) ) {
			$end_date = sanitize_text_field( $params['end_date'] );
		}

		// Get orders in date range.
		$args = array(
			'limit'      => -1,
			'status'     => array( 'completed', 'processing' ),
			'date_after' => $start_date . ' 00:00:00',
			'date_before' => $end_date . ' 23:59:59',
		);

		$orders = wc_get_orders( $args );

		$total_sales    = 0;
		$total_orders   = count( $orders );
		$total_items    = 0;
		$average_order  = 0;

		foreach ( $orders as $order ) {
			$total_sales += $order->get_total();
			$total_items += $order->get_item_count();
		}

		if ( $total_orders > 0 ) {
			$average_order = $total_sales / $total_orders;
		}

		return array(
			'period'        => $period,
			'start_date'    => $start_date,
			'end_date'      => $end_date,
			'total_sales'   => wc_format_decimal( $total_sales, 2 ),
			'total_orders'  => $total_orders,
			'total_items'   => $total_items,
			'average_order' => wc_format_decimal( $average_order, 2 ),
			'currency'      => get_woocommerce_currency(),
		);
	}

	/**
	 * Get top selling products.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Top products.
	 */
	public function ability_analytics_top_products( $params ) {
		global $wpdb;

		$limit  = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;
		$period = isset( $params['period'] ) ? sanitize_text_field( $params['period'] ) : 'month';

		// Calculate date cutoff based on period.
		$date_cutoff = null;
		switch ( $period ) {
			case 'week':
				$date_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				break;
			case 'month':
				$date_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				break;
			case 'year':
				$date_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-365 days' ) );
				break;
		}

		// Build query based on whether we have a date filter.
		$order_items_table    = $wpdb->prefix . 'woocommerce_order_items';
		$order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$orders_table         = $wpdb->prefix . 'wc_orders';

		if ( $date_cutoff ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query with date filter.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT oi.order_item_name as product_name,
						pm.meta_value as product_id,
						SUM(oim.meta_value) as total_quantity,
						SUM(oim2.meta_value) as total_sales
					FROM %i oi
					INNER JOIN %i pm ON oi.order_item_id = pm.order_item_id AND pm.meta_key = '_product_id'
					INNER JOIN %i oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_qty'
					INNER JOIN %i oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_line_total'
					INNER JOIN %i o ON oi.order_id = o.id
					WHERE oi.order_item_type = 'line_item'
					AND o.status IN ('wc-completed', 'wc-processing')
					AND o.date_created_gmt >= %s
					GROUP BY pm.meta_value
					ORDER BY total_quantity DESC
					LIMIT %d",
					$order_items_table,
					$order_itemmeta_table,
					$order_itemmeta_table,
					$order_itemmeta_table,
					$orders_table,
					$date_cutoff,
					$limit
				),
				ARRAY_A
			);
		} else {
			// All time - no date filter.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for all time.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT oi.order_item_name as product_name,
						pm.meta_value as product_id,
						SUM(oim.meta_value) as total_quantity,
						SUM(oim2.meta_value) as total_sales
					FROM %i oi
					INNER JOIN %i pm ON oi.order_item_id = pm.order_item_id AND pm.meta_key = '_product_id'
					INNER JOIN %i oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_qty'
					INNER JOIN %i oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_line_total'
					INNER JOIN %i o ON oi.order_id = o.id
					WHERE oi.order_item_type = 'line_item'
					AND o.status IN ('wc-completed', 'wc-processing')
					GROUP BY pm.meta_value
					ORDER BY total_quantity DESC
					LIMIT %d",
					$order_items_table,
					$order_itemmeta_table,
					$order_itemmeta_table,
					$order_itemmeta_table,
					$orders_table,
					$limit
				),
				ARRAY_A
			);
		}

		$products = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$product    = wc_get_product( absint( $row['product_id'] ) );
				$products[] = array(
					'id'             => absint( $row['product_id'] ),
					'name'           => $row['product_name'],
					'sku'            => $product ? $product->get_sku() : '',
					'total_quantity' => absint( $row['total_quantity'] ),
					'total_sales'    => wc_format_decimal( $row['total_sales'], 2 ),
				);
			}
		}

		return array(
			'products' => $products,
			'period'   => $period,
			'count'    => count( $products ),
		);
	}

	/**
	 * Update order status.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'order_id' and 'status'.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_orders_update_status( $params ) {
		$order = wc_get_order( absint( $params['order_id'] ) );

		if ( ! $order ) {
			return new \WP_Error(
				'assistify_order_not_found',
				__( 'Order not found.', 'assistify-for-woocommerce' )
			);
		}

		$new_status = sanitize_text_field( $params['status'] );

		// Validate status.
		$valid_statuses = array_keys( wc_get_order_statuses() );
		// Remove 'wc-' prefix for comparison if needed.
		$status_key = 'wc-' . $new_status;
		if ( ! in_array( $status_key, $valid_statuses, true ) && ! in_array( 'wc-' . $new_status, $valid_statuses, true ) ) {
			return new \WP_Error(
				'assistify_invalid_status',
				sprintf(
					/* translators: %s: status name. */
					__( 'Invalid order status: %s', 'assistify-for-woocommerce' ),
					$new_status
				)
			);
		}

		$old_status = $order->get_status();

		// Update the status.
		$order->set_status( $new_status );

		// Add note if provided.
		if ( ! empty( $params['note'] ) ) {
			$order->add_order_note(
				sanitize_textarea_field( $params['note'] ),
				false,
				true
			);
		}

		$order->save();

		return array(
			'success'    => true,
			'order_id'   => $order->get_id(),
			'old_status' => $old_status,
			'new_status' => $order->get_status(),
			'message'    => sprintf(
				/* translators: %1$d: order ID, %2$s: old status, %3$s: new status. */
				__( 'Order #%1$d status changed from %2$s to %3$s.', 'assistify-for-woocommerce' ),
				$order->get_id(),
				wc_get_order_status_name( $old_status ),
				wc_get_order_status_name( $order->get_status() )
			),
		);
	}

	/**
	 * Add note to order.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'order_id' and 'note'.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_orders_add_note( $params ) {
		$order = wc_get_order( absint( $params['order_id'] ) );

		if ( ! $order ) {
			return new \WP_Error(
				'assistify_order_not_found',
				__( 'Order not found.', 'assistify-for-woocommerce' )
			);
		}

		$note          = sanitize_textarea_field( $params['note'] );
		$customer_note = ! empty( $params['customer_note'] );

		$note_id = $order->add_order_note( $note, $customer_note, true );

		if ( ! $note_id ) {
			return new \WP_Error(
				'assistify_note_failed',
				__( 'Failed to add note to order.', 'assistify-for-woocommerce' )
			);
		}

		return array(
			'success'       => true,
			'order_id'      => $order->get_id(),
			'note_id'       => $note_id,
			'customer_note' => $customer_note,
			'message'       => sprintf(
				/* translators: %d: order ID. */
				__( 'Note added to order #%d successfully.', 'assistify-for-woocommerce' ),
				$order->get_id()
			),
		);
	}

	/**
	 * Get customer details.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'customer_id'.
	 * @return array|\WP_Error Customer data or error.
	 */
	public function ability_customers_get( $params ) {
		$customer_id = absint( $params['customer_id'] );
		$customer    = new \WC_Customer( $customer_id );

		if ( ! $customer->get_id() ) {
			return new \WP_Error(
				'assistify_customer_not_found',
				__( 'Customer not found.', 'assistify-for-woocommerce' )
			);
		}

		return array(
			'id'            => $customer->get_id(),
			'email'         => $customer->get_email(),
			'first_name'    => $customer->get_first_name(),
			'last_name'     => $customer->get_last_name(),
			'display_name'  => $customer->get_display_name(),
			'username'      => $customer->get_username(),
			'date_created'  => $customer->get_date_created() ? $customer->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
			'total_spent'   => $customer->get_total_spent(),
			'order_count'   => $customer->get_order_count(),
			'billing'       => array(
				'first_name' => $customer->get_billing_first_name(),
				'last_name'  => $customer->get_billing_last_name(),
				'company'    => $customer->get_billing_company(),
				'address_1'  => $customer->get_billing_address_1(),
				'address_2'  => $customer->get_billing_address_2(),
				'city'       => $customer->get_billing_city(),
				'state'      => $customer->get_billing_state(),
				'postcode'   => $customer->get_billing_postcode(),
				'country'    => $customer->get_billing_country(),
				'email'      => $customer->get_billing_email(),
				'phone'      => $customer->get_billing_phone(),
			),
			'shipping'      => array(
				'first_name' => $customer->get_shipping_first_name(),
				'last_name'  => $customer->get_shipping_last_name(),
				'company'    => $customer->get_shipping_company(),
				'address_1'  => $customer->get_shipping_address_1(),
				'address_2'  => $customer->get_shipping_address_2(),
				'city'       => $customer->get_shipping_city(),
				'state'      => $customer->get_shipping_state(),
				'postcode'   => $customer->get_shipping_postcode(),
				'country'    => $customer->get_shipping_country(),
			),
		);
	}

	/**
	 * List customers.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Customers list.
	 */
	public function ability_customers_list( $params ) {
		$limit   = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;
		$orderby = isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'registered';
		$order   = isset( $params['order'] ) ? strtoupper( sanitize_text_field( $params['order'] ) ) : 'DESC';

		// Validate order direction.
		$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

		// Map orderby to WP_User_Query compatible values.
		$orderby_map = array(
			'registered'  => 'user_registered',
			'order_count' => 'meta_value_num',
			'total_spent' => 'meta_value_num',
		);

		$args = array(
			'role'    => 'customer',
			'number'  => $limit,
			'orderby' => isset( $orderby_map[ $orderby ] ) ? $orderby_map[ $orderby ] : 'user_registered',
			'order'   => $order,
		);

		// Add meta query for sorting by WooCommerce customer data.
		if ( in_array( $orderby, array( 'order_count', 'total_spent' ), true ) ) {
			$meta_key            = 'order_count' === $orderby ? '_order_count' : '_money_spent';
			$args['meta_key']    = $meta_key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_query']  = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$user_query = new \WP_User_Query( $args );
		$customers  = $user_query->get_results();
		$results    = array();

		foreach ( $customers as $user ) {
			$customer = new \WC_Customer( $user->ID );
			if ( $customer->get_id() ) {
				$results[] = array(
					'id'           => $customer->get_id(),
					'email'        => $customer->get_email(),
					'name'         => $customer->get_display_name(),
					'date_created' => $customer->get_date_created() ? $customer->get_date_created()->format( 'Y-m-d' ) : '',
					'total_spent'  => $customer->get_total_spent(),
					'order_count'  => $customer->get_order_count(),
				);
			}
		}

		return array(
			'customers' => $results,
			'count'     => count( $results ),
		);
	}

	/**
	 * Search customers.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'query'.
	 * @return array Search results.
	 */
	public function ability_customers_search( $params ) {
		$query = sanitize_text_field( $params['query'] );
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$args = array(
			'role'   => 'customer',
			'number' => $limit,
			'search' => '*' . $query . '*',
			'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
		);

		$user_query = new \WP_User_Query( $args );
		$users      = $user_query->get_results();

		// Also search in billing/shipping names via meta query if no results.
		if ( empty( $users ) ) {
			$meta_args = array(
				'role'       => 'customer',
				'number'     => $limit,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => 'billing_first_name',
						'value'   => $query,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'billing_last_name',
						'value'   => $query,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'billing_email',
						'value'   => $query,
						'compare' => 'LIKE',
					),
				),
			);

			$user_query = new \WP_User_Query( $meta_args );
			$users      = $user_query->get_results();
		}

		$results = array();
		foreach ( $users as $user ) {
			$customer = new \WC_Customer( $user->ID );
			if ( $customer->get_id() ) {
				$results[] = array(
					'id'          => $customer->get_id(),
					'email'       => $customer->get_email(),
					'name'        => $customer->get_display_name(),
					'total_spent' => $customer->get_total_spent(),
					'order_count' => $customer->get_order_count(),
				);
			}
		}

		return array(
			'customers' => $results,
			'count'     => count( $results ),
			'query'     => $query,
		);
	}

	/**
	 * Get customer orders.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'customer_id'.
	 * @return array|\WP_Error Customer orders or error.
	 */
	public function ability_customers_orders( $params ) {
		$customer_id = absint( $params['customer_id'] );
		$limit       = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		// Verify customer exists.
		$customer = new \WC_Customer( $customer_id );
		if ( ! $customer->get_id() ) {
			return new \WP_Error(
				'assistify_customer_not_found',
				__( 'Customer not found.', 'assistify-for-woocommerce' )
			);
		}

		$orders  = wc_get_orders(
			array(
				'customer' => $customer_id,
				'limit'    => $limit,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);
		$results = array();

		foreach ( $orders as $order ) {
			$results[] = array(
				'id'           => $order->get_id(),
				'number'       => $order->get_order_number(),
				'status'       => $order->get_status(),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
				'total'        => $order->get_total(),
				'items_count'  => $order->get_item_count(),
			);
		}

		return array(
			'customer_id'   => $customer_id,
			'customer_name' => $customer->get_display_name(),
			'orders'        => $results,
			'count'         => count( $results ),
			'total_orders'  => $customer->get_order_count(),
			'total_spent'   => $customer->get_total_spent(),
		);
	}

	/**
	 * Process order refund.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'order_id', 'amount', 'reason'.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_orders_refund( $params ) {
		$order = wc_get_order( absint( $params['order_id'] ) );

		if ( ! $order ) {
			return new \WP_Error(
				'assistify_order_not_found',
				__( 'Order not found.', 'assistify-for-woocommerce' )
			);
		}

		// Check if order can be refunded.
		if ( ! $order->get_total() ) {
			return new \WP_Error(
				'assistify_invalid_order',
				__( 'Order has no total to refund.', 'assistify-for-woocommerce' )
			);
		}

		// Calculate refund amount.
		$order_total    = floatval( $order->get_total() );
		$refunded_total = floatval( $order->get_total_refunded() );
		$max_refund     = $order_total - $refunded_total;

		if ( $max_refund <= 0 ) {
			return new \WP_Error(
				'assistify_already_refunded',
				__( 'Order has already been fully refunded.', 'assistify-for-woocommerce' )
			);
		}

		$refund_amount = isset( $params['amount'] ) && is_numeric( $params['amount'] )
			? floatval( $params['amount'] )
			: $max_refund;

		// Validate refund amount.
		if ( $refund_amount <= 0 ) {
			return new \WP_Error(
				'assistify_invalid_amount',
				__( 'Invalid refund amount.', 'assistify-for-woocommerce' )
			);
		}

		if ( $refund_amount > $max_refund ) {
			return new \WP_Error(
				'assistify_amount_exceeds',
				sprintf(
					/* translators: %s: maximum refund amount. */
					__( 'Refund amount exceeds maximum available: %s', 'assistify-for-woocommerce' ),
					wc_price( $max_refund )
				)
			);
		}

		$reason         = isset( $params['reason'] ) ? sanitize_textarea_field( $params['reason'] ) : '';
		$restock_items  = isset( $params['restock_items'] ) ? (bool) $params['restock_items'] : true;
		$refund_payment = isset( $params['refund_payment'] ) ? (bool) $params['refund_payment'] : false;

		// Create the refund.
		$refund = wc_create_refund(
			array(
				'amount'         => $refund_amount,
				'reason'         => $reason,
				'order_id'       => $order->get_id(),
				'refund_payment' => $refund_payment,
				'restock_items'  => $restock_items,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		// Add order note.
		$order->add_order_note(
			sprintf(
				/* translators: %1$s: refund amount, %2$s: reason. */
				__( 'Refund of %1$s processed via Assistify. Reason: %2$s', 'assistify-for-woocommerce' ),
				wc_price( $refund_amount ),
				$reason ? $reason : __( 'No reason provided', 'assistify-for-woocommerce' )
			),
			false,
			true
		);

		return array(
			'success'         => true,
			'order_id'        => $order->get_id(),
			'refund_id'       => $refund->get_id(),
			'refund_amount'   => wc_format_decimal( $refund_amount, 2 ),
			'reason'          => $reason,
			'restock_items'   => $restock_items,
			'refund_payment'  => $refund_payment,
			'remaining_total' => wc_format_decimal( $max_refund - $refund_amount, 2 ),
			'message'         => sprintf(
				/* translators: %1$s: refund amount, %2$d: order ID. */
				__( 'Successfully refunded %1$s for order #%2$d.', 'assistify-for-woocommerce' ),
				wc_price( $refund_amount ),
				$order->get_id()
			),
		);
	}

	/**
	 * Search products.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'query'.
	 * @return array Search results.
	 */
	public function ability_products_search( $params ) {
		$query    = sanitize_text_field( $params['query'] );
		$limit    = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;
		$status   = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'publish';
		$type     = isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : '';
		$category = isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : '';
		$virtual  = isset( $params['virtual'] ) ? filter_var( $params['virtual'], FILTER_VALIDATE_BOOLEAN ) : null;
		$downloadable = isset( $params['downloadable'] ) ? filter_var( $params['downloadable'], FILTER_VALIDATE_BOOLEAN ) : null;

		// Search by SKU first (exact match).
		$sku_product = wc_get_product_id_by_sku( $query );
		if ( $sku_product ) {
			$product = wc_get_product( $sku_product );
			if ( $product ) {
				// Check filters.
				$matches_filters = true;

				if ( ! empty( $type ) && $product->get_type() !== $type ) {
					$matches_filters = false;
				}
				if ( null !== $virtual && $product->is_virtual() !== $virtual ) {
					$matches_filters = false;
				}
				if ( null !== $downloadable && $product->is_downloadable() !== $downloadable ) {
					$matches_filters = false;
				}

				if ( $matches_filters ) {
					return array(
						'products' => array(
							array(
								'id'             => $product->get_id(),
								'name'           => $product->get_name(),
								'type'           => $product->get_type(),
								'sku'            => $product->get_sku(),
								'price'          => $product->get_price(),
								'regular_price'  => $product->get_regular_price(),
								'sale_price'     => $product->get_sale_price(),
								'stock_status'   => $product->get_stock_status(),
								'stock_quantity' => $product->get_stock_quantity(),
								'virtual'        => $product->is_virtual(),
								'downloadable'   => $product->is_downloadable(),
								'categories'     => $this->get_product_categories( $product ),
								'match_type'     => 'sku',
							),
						),
						'count'    => 1,
						'query'    => $query,
					);
				}
			}
		}

		// Build search args.
		$args = array(
			'limit'  => $limit,
			'status' => $status,
			's'      => $query,
		);

		// Add type filter.
		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		// Add category filter.
		if ( ! empty( $category ) ) {
			$args['category'] = array( $category );
		}

		// Add virtual filter.
		if ( null !== $virtual ) {
			$args['virtual'] = $virtual;
		}

		// Add downloadable filter.
		if ( null !== $downloadable ) {
			$args['downloadable'] = $downloadable;
		}

		$products = wc_get_products( $args );
		$results  = array();

		foreach ( $products as $product ) {
			$results[] = array(
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'type'           => $product->get_type(),
				'sku'            => $product->get_sku(),
				'price'          => $product->get_price(),
				'regular_price'  => $product->get_regular_price(),
				'sale_price'     => $product->get_sale_price(),
				'stock_status'   => $product->get_stock_status(),
				'stock_quantity' => $product->get_stock_quantity(),
				'virtual'        => $product->is_virtual(),
				'downloadable'   => $product->is_downloadable(),
				'categories'     => $this->get_product_categories( $product ),
				'match_type'     => 'name',
			);
		}

		return array(
			'products' => $results,
			'count'    => count( $results ),
			'query'    => $query,
			'filters'  => array(
				'type'         => $type ?: null,
				'category'     => $category ?: null,
				'status'       => $status,
				'virtual'      => $virtual,
				'downloadable' => $downloadable,
			),
		);
	}

	/**
	 * Update product.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'product_id' and fields to update.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_products_update( $params ) {
		$product = wc_get_product( absint( $params['product_id'] ) );

		if ( ! $product ) {
			return new \WP_Error(
				'assistify_product_not_found',
				__( 'Product not found.', 'assistify-for-woocommerce' )
			);
		}

		$updated_fields = array();

		// Update name.
		if ( isset( $params['name'] ) && ! empty( $params['name'] ) ) {
			$product->set_name( sanitize_text_field( $params['name'] ) );
			$updated_fields[] = 'name';
		}

		// Update prices.
		if ( isset( $params['regular_price'] ) ) {
			$product->set_regular_price( sanitize_text_field( $params['regular_price'] ) );
			$updated_fields[] = 'regular_price';
		}

		if ( array_key_exists( 'sale_price', $params ) ) {
			$sale_price = $params['sale_price'];
			$product->set_sale_price( '' === $sale_price ? '' : sanitize_text_field( $sale_price ) );
			$updated_fields[] = 'sale_price';
		}

		// Update descriptions.
		if ( isset( $params['description'] ) ) {
			$product->set_description( wp_kses_post( $params['description'] ) );
			$updated_fields[] = 'description';
		}

		if ( isset( $params['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $params['short_description'] ) );
			$updated_fields[] = 'short_description';
		}

		// Update SKU.
		if ( isset( $params['sku'] ) ) {
			$new_sku = sanitize_text_field( $params['sku'] );
			// Check for duplicate SKU.
			$existing = wc_get_product_id_by_sku( $new_sku );
			if ( $existing && $existing !== $product->get_id() ) {
				return new \WP_Error(
					'assistify_duplicate_sku',
					sprintf(
						/* translators: %s: SKU. */
						__( 'SKU "%s" is already in use by another product.', 'assistify-for-woocommerce' ),
						$new_sku
					)
				);
			}
			$product->set_sku( $new_sku );
			$updated_fields[] = 'sku';
		}

		// Update stock.
		if ( isset( $params['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( absint( $params['stock_quantity'] ) );
			$updated_fields[] = 'stock_quantity';
		}

		if ( isset( $params['stock_status'] ) ) {
			$valid_statuses = array( 'instock', 'outofstock', 'onbackorder' );
			$stock_status   = sanitize_text_field( $params['stock_status'] );
			if ( in_array( $stock_status, $valid_statuses, true ) ) {
				$product->set_stock_status( $stock_status );
				$updated_fields[] = 'stock_status';
			}
		}

		// Update status.
		if ( isset( $params['status'] ) ) {
			$valid_statuses = array( 'publish', 'draft', 'pending', 'private' );
			$status         = sanitize_text_field( $params['status'] );
			if ( in_array( $status, $valid_statuses, true ) ) {
				$product->set_status( $status );
				$updated_fields[] = 'status';
			}
		}

		// Update featured.
		if ( isset( $params['featured'] ) ) {
			$product->set_featured( (bool) $params['featured'] );
			$updated_fields[] = 'featured';
		}

		// Update catalog visibility.
		if ( isset( $params['catalog_visibility'] ) ) {
			$valid_visibility = array( 'visible', 'catalog', 'search', 'hidden' );
			$visibility       = sanitize_text_field( $params['catalog_visibility'] );
			if ( in_array( $visibility, $valid_visibility, true ) ) {
				$product->set_catalog_visibility( $visibility );
				$updated_fields[] = 'catalog_visibility';
			}
		}

		// Update virtual status.
		if ( isset( $params['virtual'] ) ) {
			$product->set_virtual( filter_var( $params['virtual'], FILTER_VALIDATE_BOOLEAN ) );
			$updated_fields[] = 'virtual';
		}

		// Update downloadable status.
		if ( isset( $params['downloadable'] ) ) {
			$product->set_downloadable( filter_var( $params['downloadable'], FILTER_VALIDATE_BOOLEAN ) );
			$updated_fields[] = 'downloadable';
		}

		// Update weight.
		if ( isset( $params['weight'] ) ) {
			$product->set_weight( sanitize_text_field( $params['weight'] ) );
			$updated_fields[] = 'weight';
		}

		// Update dimensions.
		if ( isset( $params['length'] ) ) {
			$product->set_length( sanitize_text_field( $params['length'] ) );
			$updated_fields[] = 'length';
		}
		if ( isset( $params['width'] ) ) {
			$product->set_width( sanitize_text_field( $params['width'] ) );
			$updated_fields[] = 'width';
		}
		if ( isset( $params['height'] ) ) {
			$product->set_height( sanitize_text_field( $params['height'] ) );
			$updated_fields[] = 'height';
		}

		// Update tax status.
		if ( isset( $params['tax_status'] ) ) {
			$valid_tax_statuses = array( 'taxable', 'shipping', 'none' );
			$tax_status         = sanitize_text_field( $params['tax_status'] );
			if ( in_array( $tax_status, $valid_tax_statuses, true ) ) {
				$product->set_tax_status( $tax_status );
				$updated_fields[] = 'tax_status';
			}
		}

		// Update tax class.
		if ( isset( $params['tax_class'] ) ) {
			$product->set_tax_class( sanitize_text_field( $params['tax_class'] ) );
			$updated_fields[] = 'tax_class';
		}

		if ( empty( $updated_fields ) ) {
			return new \WP_Error(
				'assistify_no_updates',
				__( 'No valid fields provided to update.', 'assistify-for-woocommerce' )
			);
		}

		$product->save();

		return array(
			'success'        => true,
			'product_id'     => $product->get_id(),
			'updated_fields' => $updated_fields,
			'product'        => array(
				'id'                => $product->get_id(),
				'name'              => $product->get_name(),
				'type'              => $product->get_type(),
				'sku'               => $product->get_sku(),
				'price'             => $product->get_price(),
				'regular_price'     => $product->get_regular_price(),
				'sale_price'        => $product->get_sale_price(),
				'stock_status'      => $product->get_stock_status(),
				'stock_quantity'    => $product->get_stock_quantity(),
				'status'            => $product->get_status(),
			),
			'message'        => sprintf(
				/* translators: %1$d: product ID, %2$s: updated fields. */
				__( 'Product #%1$d updated. Fields changed: %2$s', 'assistify-for-woocommerce' ),
				$product->get_id(),
				implode( ', ', $updated_fields )
			),
		);
	}

	/**
	 * Create product.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters for new product.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_products_create( $params ) {
		$product_type = isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : 'simple';

		// Create product based on type.
		switch ( $product_type ) {
			case 'variable':
				$product = new \WC_Product_Variable();
				break;
			case 'grouped':
				$product = new \WC_Product_Grouped();
				break;
			case 'external':
				$product = new \WC_Product_External();
				break;
			case 'simple':
			default:
				$product = new \WC_Product_Simple();
				break;
		}

		// Set name (required).
		$product->set_name( sanitize_text_field( $params['name'] ) );

		// Set prices.
		if ( isset( $params['regular_price'] ) ) {
			$product->set_regular_price( sanitize_text_field( $params['regular_price'] ) );
		}

		if ( isset( $params['sale_price'] ) ) {
			$product->set_sale_price( sanitize_text_field( $params['sale_price'] ) );
		}

		// Set descriptions.
		if ( isset( $params['description'] ) ) {
			$product->set_description( wp_kses_post( $params['description'] ) );
		}

		if ( isset( $params['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $params['short_description'] ) );
		}

		// Set SKU.
		if ( isset( $params['sku'] ) && ! empty( $params['sku'] ) ) {
			$sku = sanitize_text_field( $params['sku'] );
			// Check for duplicate SKU.
			$existing = wc_get_product_id_by_sku( $sku );
			if ( $existing ) {
				return new \WP_Error(
					'assistify_duplicate_sku',
					sprintf(
						/* translators: %s: SKU. */
						__( 'SKU "%s" is already in use by another product.', 'assistify-for-woocommerce' ),
						$sku
					)
				);
			}
			$product->set_sku( $sku );
		}

		// Set stock management.
		$manage_stock = isset( $params['manage_stock'] ) ? (bool) $params['manage_stock'] : false;
		$product->set_manage_stock( $manage_stock );

		if ( $manage_stock && isset( $params['stock_quantity'] ) ) {
			$product->set_stock_quantity( absint( $params['stock_quantity'] ) );
		}

		$stock_status = isset( $params['stock_status'] ) ? sanitize_text_field( $params['stock_status'] ) : 'instock';
		$product->set_stock_status( $stock_status );

		// Set categories.
		if ( isset( $params['categories'] ) && is_array( $params['categories'] ) ) {
			$category_ids = array();
			foreach ( $params['categories'] as $cat ) {
				if ( is_numeric( $cat ) ) {
					$category_ids[] = absint( $cat );
				} else {
					// Try to find by slug.
					$term = get_term_by( 'slug', sanitize_text_field( $cat ), 'product_cat' );
					if ( $term ) {
						$category_ids[] = $term->term_id;
					}
				}
			}
			if ( ! empty( $category_ids ) ) {
				$product->set_category_ids( $category_ids );
			}
		}

		// Set status.
		$status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'draft';
		$product->set_status( $status );

		// Set virtual/downloadable.
		if ( isset( $params['virtual'] ) ) {
			$product->set_virtual( (bool) $params['virtual'] );
		}

		if ( isset( $params['downloadable'] ) ) {
			$product->set_downloadable( (bool) $params['downloadable'] );
		}

		// Save the product.
		$product_id = $product->save();

		if ( ! $product_id ) {
			return new \WP_Error(
				'assistify_product_create_failed',
				__( 'Failed to create product.', 'assistify-for-woocommerce' )
			);
		}

		return array(
			'success'    => true,
			'product_id' => $product_id,
			'product'    => array(
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'type'           => $product->get_type(),
				'sku'            => $product->get_sku(),
				'price'          => $product->get_price(),
				'regular_price'  => $product->get_regular_price(),
				'sale_price'     => $product->get_sale_price(),
				'stock_status'   => $product->get_stock_status(),
				'stock_quantity' => $product->get_stock_quantity(),
				'status'         => $product->get_status(),
				'permalink'      => get_permalink( $product_id ),
				'edit_link'      => admin_url( 'post.php?post=' . $product_id . '&action=edit' ),
			),
			'message'    => sprintf(
				/* translators: %1$s: product name, %2$d: product ID. */
				__( 'Product "%1$s" created successfully with ID #%2$d.', 'assistify-for-woocommerce' ),
				$product->get_name(),
				$product_id
			),
		);
	}

	/**
	 * Get revenue analytics.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Revenue data.
	 */
	public function ability_analytics_revenue( $params ) {
		$period  = isset( $params['period'] ) ? sanitize_text_field( $params['period'] ) : 'month';
		$compare = isset( $params['compare'] ) ? (bool) $params['compare'] : false;

		// Calculate date range.
		$end_date   = current_time( 'Y-m-d' );
		$start_date = $end_date;
		$days       = 30;

		switch ( $period ) {
			case 'today':
				$start_date = $end_date;
				$days       = 1;
				break;
			case 'week':
				$start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				$days       = 7;
				break;
			case 'month':
				$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				$days       = 30;
				break;
			case 'quarter':
				$start_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
				$days       = 90;
				break;
			case 'year':
				$start_date = gmdate( 'Y-m-d', strtotime( '-365 days' ) );
				$days       = 365;
				break;
		}

		// Override with custom dates if provided.
		if ( isset( $params['start_date'] ) ) {
			$start_date = sanitize_text_field( $params['start_date'] );
		}
		if ( isset( $params['end_date'] ) ) {
			$end_date = sanitize_text_field( $params['end_date'] );
		}

		// Get current period data.
		$current_data = $this->calculate_revenue_for_period( $start_date, $end_date );

		$result = array(
			'period'      => $period,
			'start_date'  => $start_date,
			'end_date'    => $end_date,
			'currency'    => get_woocommerce_currency(),
			'current'     => $current_data,
		);

		// Add comparison data if requested.
		if ( $compare ) {
			$prev_end_date   = gmdate( 'Y-m-d', strtotime( $start_date . ' -1 day' ) );
			$prev_start_date = gmdate( 'Y-m-d', strtotime( $prev_end_date . ' -' . $days . ' days' ) );

			$previous_data = $this->calculate_revenue_for_period( $prev_start_date, $prev_end_date );

			$result['previous'] = $previous_data;
			$result['comparison'] = array(
				'period_start' => $prev_start_date,
				'period_end'   => $prev_end_date,
				'changes'      => array(
					'gross_sales'   => $this->calculate_percentage_change(
						$previous_data['gross_sales'],
						$current_data['gross_sales']
					),
					'net_sales'     => $this->calculate_percentage_change(
						$previous_data['net_sales'],
						$current_data['net_sales']
					),
					'total_orders'  => $this->calculate_percentage_change(
						$previous_data['total_orders'],
						$current_data['total_orders']
					),
					'average_order' => $this->calculate_percentage_change(
						$previous_data['average_order'],
						$current_data['average_order']
					),
				),
			);
		}

		return $result;
	}

	/**
	 * Calculate revenue for a date period.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Revenue data.
	 */
	private function calculate_revenue_for_period( $start_date, $end_date ) {
		$args = array(
			'limit'       => -1,
			'status'      => array( 'completed', 'processing', 'on-hold' ),
			'date_after'  => $start_date . ' 00:00:00',
			'date_before' => $end_date . ' 23:59:59',
		);

		$orders = wc_get_orders( $args );

		$gross_sales     = 0;
		$total_tax       = 0;
		$total_shipping  = 0;
		$total_refunds   = 0;
		$total_discounts = 0;
		$total_orders    = count( $orders );
		$total_items     = 0;

		foreach ( $orders as $order ) {
			$gross_sales     += floatval( $order->get_total() );
			$total_tax       += floatval( $order->get_total_tax() );
			$total_shipping  += floatval( $order->get_shipping_total() );
			$total_refunds   += floatval( $order->get_total_refunded() );
			$total_discounts += floatval( $order->get_total_discount() );
			$total_items     += $order->get_item_count();
		}

		$net_sales     = $gross_sales - $total_refunds - $total_tax - $total_shipping;
		$average_order = $total_orders > 0 ? $gross_sales / $total_orders : 0;

		return array(
			'gross_sales'     => wc_format_decimal( $gross_sales, 2 ),
			'net_sales'       => wc_format_decimal( $net_sales, 2 ),
			'total_tax'       => wc_format_decimal( $total_tax, 2 ),
			'total_shipping'  => wc_format_decimal( $total_shipping, 2 ),
			'total_refunds'   => wc_format_decimal( $total_refunds, 2 ),
			'total_discounts' => wc_format_decimal( $total_discounts, 2 ),
			'total_orders'    => $total_orders,
			'total_items'     => $total_items,
			'average_order'   => wc_format_decimal( $average_order, 2 ),
		);
	}

	/**
	 * Calculate percentage change between two values.
	 *
	 * @since 1.0.0
	 * @param float $old_value Previous value.
	 * @param float $new_value Current value.
	 * @return array Change data.
	 */
	private function calculate_percentage_change( $old_value, $new_value ) {
		$old_value = floatval( $old_value );
		$new_value = floatval( $new_value );

		if ( 0 === $old_value ) {
			$percentage = $new_value > 0 ? 100 : 0;
		} else {
			$percentage = ( ( $new_value - $old_value ) / $old_value ) * 100;
		}

		return array(
			'value'      => wc_format_decimal( $new_value - $old_value, 2 ),
			'percentage' => round( $percentage, 2 ),
			'direction'  => $percentage >= 0 ? 'up' : 'down',
		);
	}

	/**
	 * Get top customers.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Top customers data.
	 */
	public function ability_analytics_top_customers( $params ) {
		global $wpdb;

		$period  = isset( $params['period'] ) ? sanitize_text_field( $params['period'] ) : 'month';
		$orderby = isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'total_spent';
		$limit   = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		// Calculate date cutoff.
		$date_cutoff = null;
		switch ( $period ) {
			case 'week':
				$date_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				break;
			case 'month':
				$date_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				break;
			case 'year':
				$date_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-365 days' ) );
				break;
			// 'all' - no date filter.
		}

		$orders_table = $wpdb->prefix . 'wc_orders';

		// Build the query based on orderby preference (whitelisted values only).
		// Using separate queries for each orderby option to avoid SQL injection.
		if ( $date_cutoff ) {
			if ( 'total_spent' === $orderby ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT 
							customer_id,
							COUNT(id) as order_count,
							SUM(total_amount) as total_spent
						FROM %i
						WHERE status IN ('wc-completed', 'wc-processing')
						AND customer_id > 0
						AND date_created_gmt >= %s
						GROUP BY customer_id
						ORDER BY total_spent DESC
						LIMIT %d",
						$orders_table,
						$date_cutoff,
						$limit
					),
					ARRAY_A
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT 
							customer_id,
							COUNT(id) as order_count,
							SUM(total_amount) as total_spent
						FROM %i
						WHERE status IN ('wc-completed', 'wc-processing')
						AND customer_id > 0
						AND date_created_gmt >= %s
						GROUP BY customer_id
						ORDER BY order_count DESC
						LIMIT %d",
						$orders_table,
						$date_cutoff,
						$limit
					),
					ARRAY_A
				);
			}
		} else {
			// All time - no date filter.
			if ( 'total_spent' === $orderby ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT 
							customer_id,
							COUNT(id) as order_count,
							SUM(total_amount) as total_spent
						FROM %i
						WHERE status IN ('wc-completed', 'wc-processing')
						AND customer_id > 0
						GROUP BY customer_id
						ORDER BY total_spent DESC
						LIMIT %d",
						$orders_table,
						$limit
					),
					ARRAY_A
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT 
							customer_id,
							COUNT(id) as order_count,
							SUM(total_amount) as total_spent
						FROM %i
						WHERE status IN ('wc-completed', 'wc-processing')
						AND customer_id > 0
						GROUP BY customer_id
						ORDER BY order_count DESC
						LIMIT %d",
						$orders_table,
						$limit
					),
					ARRAY_A
				);
			}
		}

		$customers = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$customer_id = absint( $row['customer_id'] );
				$customer    = new \WC_Customer( $customer_id );

				if ( $customer->get_id() ) {
					$customers[] = array(
						'id'           => $customer_id,
						'name'         => $customer->get_display_name(),
						'email'        => $customer->get_email(),
						'order_count'  => absint( $row['order_count'] ),
						'total_spent'  => wc_format_decimal( $row['total_spent'], 2 ),
						'average_order' => wc_format_decimal(
							absint( $row['order_count'] ) > 0
								? floatval( $row['total_spent'] ) / absint( $row['order_count'] )
								: 0,
							2
						),
						'date_created' => $customer->get_date_created()
							? $customer->get_date_created()->format( 'Y-m-d' )
							: '',
					);
				}
			}
		}

		return array(
			'customers' => $customers,
			'count'     => count( $customers ),
			'period'    => $period,
			'orderby'   => $orderby,
			'currency'  => get_woocommerce_currency(),
		);
	}

	// =====================================================
	// Store Settings & Configuration Abilities
	// =====================================================

	/**
	 * Get store settings.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with optional 'group'.
	 * @return array Store settings.
	 */
	public function ability_store_settings( $params ) {
		$group = isset( $params['group'] ) ? sanitize_text_field( $params['group'] ) : 'all';

		$settings = array();

		// General settings.
		if ( 'all' === $group || 'general' === $group ) {
			$settings['general'] = array(
				'store_address'    => WC()->countries->get_base_address(),
				'store_address_2'  => WC()->countries->get_base_address_2(),
				'store_city'       => WC()->countries->get_base_city(),
				'store_postcode'   => WC()->countries->get_base_postcode(),
				'store_country'    => WC()->countries->get_base_country(),
				'store_state'      => WC()->countries->get_base_state(),
				'currency'         => get_woocommerce_currency(),
				'currency_symbol'  => get_woocommerce_currency_symbol(),
				'currency_pos'     => get_option( 'woocommerce_currency_pos' ),
				'thousand_sep'     => wc_get_price_thousand_separator(),
				'decimal_sep'      => wc_get_price_decimal_separator(),
				'num_decimals'     => wc_get_price_decimals(),
			);
		}

		// Product settings.
		if ( 'all' === $group || 'products' === $group ) {
			$settings['products'] = array(
				'weight_unit'           => get_option( 'woocommerce_weight_unit' ),
				'dimension_unit'        => get_option( 'woocommerce_dimension_unit' ),
				'enable_reviews'        => 'yes' === get_option( 'woocommerce_enable_reviews' ),
				'review_rating_required' => 'yes' === get_option( 'woocommerce_review_rating_required' ),
				'manage_stock'          => 'yes' === get_option( 'woocommerce_manage_stock' ),
				'hold_stock_minutes'    => get_option( 'woocommerce_hold_stock_minutes' ),
				'notify_low_stock'      => 'yes' === get_option( 'woocommerce_notify_low_stock' ),
				'notify_no_stock'       => 'yes' === get_option( 'woocommerce_notify_no_stock' ),
				'low_stock_amount'      => get_option( 'woocommerce_notify_low_stock_amount' ),
				'out_of_stock_visibility' => get_option( 'woocommerce_hide_out_of_stock_items' ),
			);
		}

		// Tax settings.
		if ( 'all' === $group || 'tax' === $group ) {
			$settings['tax'] = array(
				'enabled'              => wc_tax_enabled(),
				'calc_taxes'           => 'yes' === get_option( 'woocommerce_calc_taxes' ),
				'prices_include_tax'   => 'yes' === get_option( 'woocommerce_prices_include_tax' ),
				'tax_based_on'         => get_option( 'woocommerce_tax_based_on' ),
				'shipping_tax_class'   => get_option( 'woocommerce_shipping_tax_class' ),
				'tax_round_at_subtotal' => 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ),
				'tax_display_shop'     => get_option( 'woocommerce_tax_display_shop' ),
				'tax_display_cart'     => get_option( 'woocommerce_tax_display_cart' ),
				'tax_total_display'    => get_option( 'woocommerce_tax_total_display' ),
			);
		}

		// Shipping settings.
		if ( 'all' === $group || 'shipping' === $group ) {
			$settings['shipping'] = array(
				'enabled'                 => 'yes' === get_option( 'woocommerce_ship_to_countries' ) || ! empty( get_option( 'woocommerce_specific_ship_to_countries' ) ),
				'ship_to_countries'       => get_option( 'woocommerce_ship_to_countries' ),
				'specific_ship_to'        => get_option( 'woocommerce_specific_ship_to_countries' ),
				'shipping_cost_requires_address' => 'yes' === get_option( 'woocommerce_shipping_cost_requires_address' ),
				'shipping_debug_mode'     => 'yes' === get_option( 'woocommerce_shipping_debug_mode' ),
			);
		}

		// Checkout settings.
		if ( 'all' === $group || 'checkout' === $group ) {
			$settings['checkout'] = array(
				'enable_coupons'        => 'yes' === get_option( 'woocommerce_enable_coupons' ),
				'calc_discounts_seq'    => 'yes' === get_option( 'woocommerce_calc_discounts_sequentially' ),
				'enable_guest_checkout' => 'yes' === get_option( 'woocommerce_enable_guest_checkout' ),
				'enable_checkout_login' => 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' ),
				'enable_signup_and_login' => 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ),
				'registration_privacy_policy' => get_option( 'woocommerce_registration_privacy_policy_text' ),
				'checkout_privacy_policy' => get_option( 'woocommerce_checkout_privacy_policy_text' ),
				'terms_page_id'         => get_option( 'woocommerce_terms_page_id' ),
			);
		}

		return array(
			'group'    => $group,
			'settings' => $settings,
		);
	}

	/**
	 * Get system status.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array System status.
	 */
	public function ability_store_status( $params ) {
		global $wpdb;

		// Get WooCommerce system status.
		$environment = array(
			'wp_version'          => get_bloginfo( 'version' ),
			'wc_version'          => WC()->version,
			'php_version'         => phpversion(),
			'mysql_version'       => $wpdb->db_version(),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'memory_limit'        => ini_get( 'memory_limit' ),
			'wp_memory_limit'     => WP_MEMORY_LIMIT,
			'wp_debug'            => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'multisite'           => is_multisite(),
			'site_url'            => get_site_url(),
			'home_url'            => get_home_url(),
		);

		// Active plugins.
		$active_plugins = array();
		$plugins        = get_plugins();
		$active         = get_option( 'active_plugins', array() );

		foreach ( $active as $plugin ) {
			if ( isset( $plugins[ $plugin ] ) ) {
				$active_plugins[] = array(
					'name'    => $plugins[ $plugin ]['Name'],
					'version' => $plugins[ $plugin ]['Version'],
					'author'  => $plugins[ $plugin ]['Author'],
				);
			}
		}

		// Theme info.
		$theme      = wp_get_theme();
		$theme_info = array(
			'name'         => $theme->get( 'Name' ),
			'version'      => $theme->get( 'Version' ),
			'author'       => $theme->get( 'Author' ),
			'is_child'     => is_child_theme(),
			'parent_theme' => is_child_theme() ? wp_get_theme()->parent()->get( 'Name' ) : '',
		);

		// Database info.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking table existence.
		$database = array(
			'wc_database_version' => get_option( 'woocommerce_db_version' ),
			'database_prefix'     => $wpdb->prefix,
			'database_tables'     => array(
				'woocommerce_sessions'      => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'woocommerce_sessions' ) ) ? 'exists' : 'missing',
				'woocommerce_api_keys'      => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'woocommerce_api_keys' ) ) ? 'exists' : 'missing',
				'wc_orders'                 => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_orders' ) ) ? 'exists' : 'missing',
				'wc_order_stats'            => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_order_stats' ) ) ? 'exists' : 'missing',
			),
		);
		// phpcs:enable

		return array(
			'environment'    => $environment,
			'active_plugins' => $active_plugins,
			'plugin_count'   => count( $active_plugins ),
			'theme'          => $theme_info,
			'database'       => $database,
		);
	}

	/**
	 * Get payment gateways.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Payment gateways.
	 */
	public function ability_store_payment_gateways( $params ) {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$results  = array();

		foreach ( $gateways as $gateway ) {
			$results[] = array(
				'id'                 => $gateway->id,
				'title'              => $gateway->get_title(),
				'description'        => $gateway->get_description(),
				'enabled'            => 'yes' === $gateway->enabled,
				'method_title'       => $gateway->get_method_title(),
				'method_description' => $gateway->get_method_description(),
				'supports'           => $gateway->supports,
			);
		}

		$enabled_count = count( array_filter( $results, function( $g ) {
			return $g['enabled'];
		} ) );

		return array(
			'gateways'       => $results,
			'total_count'    => count( $results ),
			'enabled_count'  => $enabled_count,
			'disabled_count' => count( $results ) - $enabled_count,
		);
	}

	/**
	 * Get shipping zones.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Shipping zones.
	 */
	public function ability_store_shipping_zones( $params ) {
		$zones   = \WC_Shipping_Zones::get_zones();
		$results = array();

		// Add "Rest of the World" zone.
		$rest_of_world                  = new \WC_Shipping_Zone( 0 );
		$zones[ $rest_of_world->get_id() ] = $rest_of_world->get_data();
		$zones[ $rest_of_world->get_id() ]['zone_id'] = $rest_of_world->get_id();
		$zones[ $rest_of_world->get_id() ]['shipping_methods'] = $rest_of_world->get_shipping_methods();

		foreach ( $zones as $zone_data ) {
			$zone_id = $zone_data['zone_id'] ?? $zone_data['id'];
			$zone    = new \WC_Shipping_Zone( $zone_id );

			$methods = array();
			foreach ( $zone->get_shipping_methods() as $method ) {
				$methods[] = array(
					'instance_id' => $method->get_instance_id(),
					'id'          => $method->id,
					'title'       => $method->get_title(),
					'enabled'     => 'yes' === $method->enabled,
					'method_title' => $method->get_method_title(),
				);
			}

			$results[] = array(
				'id'        => $zone->get_id(),
				'name'      => $zone->get_zone_name(),
				'order'     => $zone->get_zone_order(),
				'locations' => $zone->get_zone_locations(),
				'methods'   => $methods,
			);
		}

		return array(
			'zones' => $results,
			'count' => count( $results ),
		);
	}

	/**
	 * Get shipping classes.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Shipping classes.
	 */
	public function ability_store_shipping_classes( $params ) {
		$classes = WC()->shipping()->get_shipping_classes();
		$results = array();

		foreach ( $classes as $class ) {
			$results[] = array(
				'id'          => $class->term_id,
				'name'        => $class->name,
				'slug'        => $class->slug,
				'description' => $class->description,
				'count'       => $class->count,
			);
		}

		return array(
			'shipping_classes' => $results,
			'count'            => count( $results ),
		);
	}

	/**
	 * Get tax rates.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with optional 'class'.
	 * @return array Tax rates.
	 */
	public function ability_store_tax_rates( $params ) {
		global $wpdb;

		$tax_class = isset( $params['class'] ) ? sanitize_text_field( $params['class'] ) : '';

		// Build query based on whether we have a tax class filter.
		if ( ! empty( $tax_class ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tax rates query with class filter.
			$rates = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = %s ORDER BY tax_rate_order, tax_rate_id",
					$tax_class
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tax rates query without filter.
			$rates = $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_order, tax_rate_id"
			);
		}

		$results = array();
		foreach ( $rates as $rate ) {
			$results[] = array(
				'id'        => absint( $rate->tax_rate_id ),
				'country'   => $rate->tax_rate_country,
				'state'     => $rate->tax_rate_state,
				'postcode'  => $rate->tax_rate_postcode ?? '*',
				'city'      => $rate->tax_rate_city ?? '*',
				'rate'      => $rate->tax_rate,
				'name'      => $rate->tax_rate_name,
				'priority'  => absint( $rate->tax_rate_priority ),
				'compound'  => (bool) $rate->tax_rate_compound,
				'shipping'  => (bool) $rate->tax_rate_shipping,
				'class'     => $rate->tax_rate_class ?: 'standard',
			);
		}

		return array(
			'tax_rates' => $results,
			'count'     => count( $results ),
			'filter'    => array(
				'class' => $tax_class ?: 'all',
			),
		);
	}

	/**
	 * Get tax classes.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Tax classes.
	 */
	public function ability_store_tax_classes( $params ) {
		$classes = \WC_Tax::get_tax_classes();
		$results = array(
			array(
				'slug' => 'standard',
				'name' => __( 'Standard', 'assistify-for-woocommerce' ),
			),
		);

		foreach ( $classes as $class ) {
			$results[] = array(
				'slug' => sanitize_title( $class ),
				'name' => $class,
			);
		}

		return array(
			'tax_classes' => $results,
			'count'       => count( $results ),
		);
	}

	// =====================================================
	// Coupon Abilities
	// =====================================================

	/**
	 * List coupons.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Coupons list.
	 */
	public function ability_coupons_list( $params ) {
		$status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'publish';
		$limit  = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;

		$args = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$coupons = get_posts( $args );
		$results = array();

		foreach ( $coupons as $coupon_post ) {
			$coupon    = new \WC_Coupon( $coupon_post->ID );
			$results[] = array(
				'id'              => $coupon->get_id(),
				'code'            => $coupon->get_code(),
				'discount_type'   => $coupon->get_discount_type(),
				'amount'          => $coupon->get_amount(),
				'date_expires'    => $coupon->get_date_expires() ? $coupon->get_date_expires()->format( 'Y-m-d' ) : null,
				'usage_count'     => $coupon->get_usage_count(),
				'usage_limit'     => $coupon->get_usage_limit(),
				'free_shipping'   => $coupon->get_free_shipping(),
				'status'          => $coupon_post->post_status,
			);
		}

		return array(
			'coupons' => $results,
			'count'   => count( $results ),
		);
	}

	/**
	 * Get coupon details.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'coupon_id' or 'code'.
	 * @return array|\WP_Error Coupon data or error.
	 */
	public function ability_coupons_get( $params ) {
		$coupon = null;

		if ( isset( $params['coupon_id'] ) ) {
			$coupon = new \WC_Coupon( absint( $params['coupon_id'] ) );
		} elseif ( isset( $params['code'] ) ) {
			$coupon = new \WC_Coupon( sanitize_text_field( $params['code'] ) );
		} else {
			return new \WP_Error(
				'assistify_missing_param',
				__( 'Either coupon_id or code is required.', 'assistify-for-woocommerce' )
			);
		}

		if ( ! $coupon->get_id() ) {
			return new \WP_Error(
				'assistify_coupon_not_found',
				__( 'Coupon not found.', 'assistify-for-woocommerce' )
			);
		}

		return array(
			'id'                   => $coupon->get_id(),
			'code'                 => $coupon->get_code(),
			'description'          => $coupon->get_description(),
			'discount_type'        => $coupon->get_discount_type(),
			'amount'               => $coupon->get_amount(),
			'date_created'         => $coupon->get_date_created() ? $coupon->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			'date_expires'         => $coupon->get_date_expires() ? $coupon->get_date_expires()->format( 'Y-m-d' ) : null,
			'usage_count'          => $coupon->get_usage_count(),
			'usage_limit'          => $coupon->get_usage_limit(),
			'usage_limit_per_user' => $coupon->get_usage_limit_per_user(),
			'individual_use'       => $coupon->get_individual_use(),
			'free_shipping'        => $coupon->get_free_shipping(),
			'minimum_amount'       => $coupon->get_minimum_amount(),
			'maximum_amount'       => $coupon->get_maximum_amount(),
			'exclude_sale_items'   => $coupon->get_exclude_sale_items(),
			'product_ids'          => $coupon->get_product_ids(),
			'excluded_product_ids' => $coupon->get_excluded_product_ids(),
			'product_categories'   => $coupon->get_product_categories(),
			'excluded_product_categories' => $coupon->get_excluded_product_categories(),
			'email_restrictions'   => $coupon->get_email_restrictions(),
		);
	}

	/**
	 * Create coupon.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters for new coupon.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_coupons_create( $params ) {
		$code = sanitize_text_field( $params['code'] );

		// Check if coupon code already exists.
		$existing = wc_get_coupon_id_by_code( $code );
		if ( $existing ) {
			return new \WP_Error(
				'assistify_coupon_exists',
				sprintf(
					/* translators: %s: coupon code. */
					__( 'Coupon code "%s" already exists.', 'assistify-for-woocommerce' ),
					$code
				)
			);
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_amount( sanitize_text_field( $params['amount'] ) );
		$coupon->set_discount_type( isset( $params['discount_type'] ) ? sanitize_text_field( $params['discount_type'] ) : 'percent' );

		if ( isset( $params['description'] ) ) {
			$coupon->set_description( sanitize_textarea_field( $params['description'] ) );
		}

		if ( isset( $params['date_expires'] ) && ! empty( $params['date_expires'] ) ) {
			$coupon->set_date_expires( strtotime( sanitize_text_field( $params['date_expires'] ) ) );
		}

		if ( isset( $params['usage_limit'] ) ) {
			$coupon->set_usage_limit( absint( $params['usage_limit'] ) );
		}

		if ( isset( $params['usage_limit_per_user'] ) ) {
			$coupon->set_usage_limit_per_user( absint( $params['usage_limit_per_user'] ) );
		}

		if ( isset( $params['free_shipping'] ) ) {
			$coupon->set_free_shipping( filter_var( $params['free_shipping'], FILTER_VALIDATE_BOOLEAN ) );
		}

		if ( isset( $params['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( sanitize_text_field( $params['minimum_amount'] ) );
		}

		if ( isset( $params['maximum_amount'] ) ) {
			$coupon->set_maximum_amount( sanitize_text_field( $params['maximum_amount'] ) );
		}

		if ( isset( $params['individual_use'] ) ) {
			$coupon->set_individual_use( filter_var( $params['individual_use'], FILTER_VALIDATE_BOOLEAN ) );
		}

		if ( isset( $params['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( filter_var( $params['exclude_sale_items'], FILTER_VALIDATE_BOOLEAN ) );
		}

		$coupon_id = $coupon->save();

		if ( ! $coupon_id ) {
			return new \WP_Error(
				'assistify_coupon_create_failed',
				__( 'Failed to create coupon.', 'assistify-for-woocommerce' )
			);
		}

		return array(
			'success'   => true,
			'coupon_id' => $coupon_id,
			'code'      => $coupon->get_code(),
			'message'   => sprintf(
				/* translators: %s: coupon code. */
				__( 'Coupon "%s" created successfully.', 'assistify-for-woocommerce' ),
				$coupon->get_code()
			),
		);
	}

	/**
	 * Update coupon.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'coupon_id'.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_coupons_update( $params ) {
		$coupon = new \WC_Coupon( absint( $params['coupon_id'] ) );

		if ( ! $coupon->get_id() ) {
			return new \WP_Error(
				'assistify_coupon_not_found',
				__( 'Coupon not found.', 'assistify-for-woocommerce' )
			);
		}

		$updated_fields = array();

		if ( isset( $params['code'] ) ) {
			$new_code = sanitize_text_field( $params['code'] );
			$existing = wc_get_coupon_id_by_code( $new_code );
			if ( $existing && $existing !== $coupon->get_id() ) {
				return new \WP_Error(
					'assistify_coupon_exists',
					__( 'Coupon code already in use.', 'assistify-for-woocommerce' )
				);
			}
			$coupon->set_code( $new_code );
			$updated_fields[] = 'code';
		}

		if ( isset( $params['amount'] ) ) {
			$coupon->set_amount( sanitize_text_field( $params['amount'] ) );
			$updated_fields[] = 'amount';
		}

		if ( isset( $params['discount_type'] ) ) {
			$coupon->set_discount_type( sanitize_text_field( $params['discount_type'] ) );
			$updated_fields[] = 'discount_type';
		}

		if ( array_key_exists( 'date_expires', $params ) ) {
			if ( empty( $params['date_expires'] ) ) {
				$coupon->set_date_expires( null );
			} else {
				$coupon->set_date_expires( strtotime( sanitize_text_field( $params['date_expires'] ) ) );
			}
			$updated_fields[] = 'date_expires';
		}

		if ( isset( $params['usage_limit'] ) ) {
			$coupon->set_usage_limit( absint( $params['usage_limit'] ) );
			$updated_fields[] = 'usage_limit';
		}

		if ( isset( $params['status'] ) ) {
			$coupon_post = array(
				'ID'          => $coupon->get_id(),
				'post_status' => sanitize_text_field( $params['status'] ),
			);
			wp_update_post( $coupon_post );
			$updated_fields[] = 'status';
		}

		if ( empty( $updated_fields ) ) {
			return new \WP_Error(
				'assistify_no_updates',
				__( 'No valid fields provided to update.', 'assistify-for-woocommerce' )
			);
		}

		$coupon->save();

		return array(
			'success'        => true,
			'coupon_id'      => $coupon->get_id(),
			'code'           => $coupon->get_code(),
			'updated_fields' => $updated_fields,
			'message'        => sprintf(
				/* translators: %s: coupon code. */
				__( 'Coupon "%s" updated successfully.', 'assistify-for-woocommerce' ),
				$coupon->get_code()
			),
		);
	}

	/**
	 * Delete coupon.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters with 'coupon_id'.
	 * @return array|\WP_Error Result or error.
	 */
	public function ability_coupons_delete( $params ) {
		$coupon = new \WC_Coupon( absint( $params['coupon_id'] ) );

		if ( ! $coupon->get_id() ) {
			return new \WP_Error(
				'assistify_coupon_not_found',
				__( 'Coupon not found.', 'assistify-for-woocommerce' )
			);
		}

		$code  = $coupon->get_code();
		$force = isset( $params['force'] ) ? filter_var( $params['force'], FILTER_VALIDATE_BOOLEAN ) : false;

		$coupon->delete( $force );

		if ( $force ) {
			/* translators: %s: coupon code. */
			$message = sprintf( __( 'Coupon "%s" permanently deleted.', 'assistify-for-woocommerce' ), $code );
		} else {
			/* translators: %s: coupon code. */
			$message = sprintf( __( 'Coupon "%s" moved to trash.', 'assistify-for-woocommerce' ), $code );
		}

		return array(
			'success'   => true,
			'coupon_id' => absint( $params['coupon_id'] ),
			'code'      => $code,
			'deleted'   => $force ? 'permanently' : 'trashed',
			'message'   => $message,
		);
	}

	// =====================================================
	// Helper Methods
	// =====================================================

	/**
	 * Format order items.
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return array Formatted items.
	 */
	private function format_order_items( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
			);
		}

		return $items;
	}

	/**
	 * Get order notes.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 * @return array Order notes.
	 */
	private function get_order_notes( $order_id ) {
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order_id,
				'limit'    => 5,
			)
		);

		$formatted = array();
		foreach ( $notes as $note ) {
			$formatted[] = array(
				'content'  => $note->content,
				'date'     => $note->date_created->format( 'Y-m-d H:i:s' ),
				'customer' => $note->customer_note,
			);
		}

		return $formatted;
	}

	/**
	 * Get product categories.
	 *
	 * @since 1.0.0
	 * @param \WC_Product $product Product object.
	 * @return array Category names.
	 */
	private function get_product_categories( $product ) {
		$categories   = array();
		$category_ids = $product->get_category_ids();

		foreach ( $category_ids as $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$categories[] = $term->name;
			}
		}

		return $categories;
	}

	// =========================================================================
	// WordPress Content Ability Callbacks
	// =========================================================================

	/**
	 * List WordPress pages.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error Pages data.
	 */
	public function ability_content_pages( $params ) {
		$args = array(
			'post_type'      => 'page',
			'posts_per_page' => isset( $params['limit'] ) ? absint( $params['limit'] ) : 20,
			'post_status'    => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		if ( ! empty( $params['search'] ) ) {
			$args['s'] = sanitize_text_field( $params['search'] );
		}

		$pages = get_posts( $args );
		$data  = array();

		foreach ( $pages as $page ) {
			$data[] = array(
				'id'        => $page->ID,
				'title'     => $page->post_title,
				'slug'      => $page->post_name,
				'url'       => get_permalink( $page->ID ),
				'status'    => $page->post_status,
				'parent_id' => $page->post_parent,
				'excerpt'   => wp_trim_words( $page->post_content, 30, '...' ),
			);
		}

		return array(
			'success' => true,
			'pages'   => $data,
			'count'   => count( $data ),
		);
	}

	/**
	 * List WordPress posts.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error Posts data.
	 */
	public function ability_content_posts( $params ) {
		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => isset( $params['limit'] ) ? absint( $params['limit'] ) : 10,
			'post_status'    => 'publish',
		);

		if ( ! empty( $params['search'] ) ) {
			$args['s'] = sanitize_text_field( $params['search'] );
		}

		if ( ! empty( $params['category'] ) ) {
			if ( is_numeric( $params['category'] ) ) {
				$args['cat'] = absint( $params['category'] );
			} else {
				$args['category_name'] = sanitize_text_field( $params['category'] );
			}
		}

		if ( ! empty( $params['tag'] ) ) {
			$args['tag'] = sanitize_text_field( $params['tag'] );
		}

		$posts = get_posts( $args );
		$data  = array();

		foreach ( $posts as $post ) {
			$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
			$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

			$data[] = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'slug'       => $post->post_name,
				'url'        => get_permalink( $post->ID ),
				'date'       => $post->post_date,
				'excerpt'    => wp_trim_words( $post->post_content, 30, '...' ),
				'categories' => $categories,
				'tags'       => $tags,
				'author'     => get_the_author_meta( 'display_name', $post->post_author ),
			);
		}

		return array(
			'success' => true,
			'posts'   => $data,
			'count'   => count( $data ),
		);
	}

	/**
	 * List WordPress categories.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Categories data.
	 */
	public function ability_content_categories( $params ) {
		$hide_empty = isset( $params['hide_empty'] ) ? (bool) $params['hide_empty'] : true;

		$categories = get_categories(
			array(
				'hide_empty' => $hide_empty,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$data = array();
		foreach ( $categories as $cat ) {
			$data[] = array(
				'id'          => $cat->term_id,
				'name'        => $cat->name,
				'slug'        => $cat->slug,
				'description' => $cat->description,
				'count'       => $cat->count,
				'url'         => get_category_link( $cat->term_id ),
			);
		}

		return array(
			'success'    => true,
			'categories' => $data,
			'count'      => count( $data ),
		);
	}

	/**
	 * List WordPress tags.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Tags data.
	 */
	public function ability_content_tags( $params ) {
		$hide_empty = isset( $params['hide_empty'] ) ? (bool) $params['hide_empty'] : true;
		$limit      = isset( $params['limit'] ) ? absint( $params['limit'] ) : 50;

		$tags = get_tags(
			array(
				'hide_empty' => $hide_empty,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		$data = array();
		if ( ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				$data[] = array(
					'id'    => $tag->term_id,
					'name'  => $tag->name,
					'slug'  => $tag->slug,
					'count' => $tag->count,
					'url'   => get_tag_link( $tag->term_id ),
				);
			}
		}

		return array(
			'success' => true,
			'tags'    => $data,
			'count'   => count( $data ),
		);
	}

	/**
	 * List navigation menus.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array Menus data.
	 */
	public function ability_content_menus( $params ) {
		$menus = wp_get_nav_menus();
		$data  = array();

		foreach ( $menus as $menu ) {
			$menu_data = array(
				'id'    => $menu->term_id,
				'name'  => $menu->name,
				'slug'  => $menu->slug,
				'count' => $menu->count,
			);

			// If specific menu requested, get items.
			if ( isset( $params['menu_id'] ) && absint( $params['menu_id'] ) === $menu->term_id ) {
				$items              = wp_get_nav_menu_items( $menu->term_id );
				$menu_data['items'] = array();

				if ( $items ) {
					foreach ( $items as $item ) {
						$menu_data['items'][] = array(
							'id'     => $item->ID,
							'title'  => $item->title,
							'url'    => $item->url,
							'type'   => $item->type,
							'parent' => $item->menu_item_parent,
						);
					}
				}
			}

			$data[] = $menu_data;
		}

		return array(
			'success' => true,
			'menus'   => $data,
			'count'   => count( $data ),
		);
	}

	// =========================================================================
	// URL Generation Ability Callbacks
	// =========================================================================

	/**
	 * Get product URL.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error URL data.
	 */
	public function ability_urls_product( $params ) {
		$product = null;

		if ( ! empty( $params['product_id'] ) ) {
			$product = wc_get_product( absint( $params['product_id'] ) );
		} elseif ( ! empty( $params['product_name'] ) ) {
			// Search by name.
			$products = wc_get_products(
				array(
					's'      => sanitize_text_field( $params['product_name'] ),
					'limit'  => 1,
					'status' => 'publish',
				)
			);
			if ( ! empty( $products ) ) {
				$product = $products[0];
			}
		}

		if ( ! $product ) {
			return new \WP_Error( 'not_found', __( 'Product not found.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success'      => true,
			'product_id'   => $product->get_id(),
			'product_name' => $product->get_name(),
			'url'          => $product->get_permalink(),
			'price'        => html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ) ),
		);
	}

	/**
	 * Get product edit URL.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error URL data.
	 */
	public function ability_urls_product_edit( $params ) {
		$product = null;

		if ( ! empty( $params['product_id'] ) ) {
			$product = wc_get_product( absint( $params['product_id'] ) );
		} elseif ( ! empty( $params['product_name'] ) ) {
			$products = wc_get_products(
				array(
					's'     => sanitize_text_field( $params['product_name'] ),
					'limit' => 1,
				)
			);
			if ( ! empty( $products ) ) {
				$product = $products[0];
			}
		}

		if ( ! $product ) {
			return new \WP_Error( 'not_found', __( 'Product not found.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success'      => true,
			'product_id'   => $product->get_id(),
			'product_name' => $product->get_name(),
			'edit_url'     => admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ),
			'view_url'     => $product->get_permalink(),
		);
	}

	/**
	 * Get order URL.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error URL data.
	 */
	public function ability_urls_order( $params ) {
		if ( empty( $params['order_id'] ) ) {
			return new \WP_Error( 'missing_param', __( 'Order ID is required.', 'assistify-for-woocommerce' ) );
		}

		$order = wc_get_order( absint( $params['order_id'] ) );

		if ( ! $order ) {
			return new \WP_Error( 'not_found', __( 'Order not found.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success'      => true,
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'edit_url'     => $order->get_edit_order_url(),
			'status'       => $order->get_status(),
		);
	}

	/**
	 * Get customer URL.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error URL data.
	 */
	public function ability_urls_customer( $params ) {
		if ( empty( $params['customer_id'] ) ) {
			return new \WP_Error( 'missing_param', __( 'Customer ID is required.', 'assistify-for-woocommerce' ) );
		}

		$user = get_user_by( 'id', absint( $params['customer_id'] ) );

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'Customer not found.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success'       => true,
			'customer_id'   => $user->ID,
			'customer_name' => $user->display_name,
			'email'         => $user->user_email,
			'edit_url'      => admin_url( 'user-edit.php?user_id=' . $user->ID ),
			'orders_url'    => admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $user->ID ),
		);
	}

	/**
	 * Get category URL.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error URL data.
	 */
	public function ability_urls_category( $params ) {
		$term = null;

		if ( ! empty( $params['category_id'] ) ) {
			$term = get_term( absint( $params['category_id'] ), 'product_cat' );
		} elseif ( ! empty( $params['category_name'] ) ) {
			$term = get_term_by( 'name', sanitize_text_field( $params['category_name'] ), 'product_cat' );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $params['category_name'] ), 'product_cat' );
			}
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( 'not_found', __( 'Category not found.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success'       => true,
			'category_id'   => $term->term_id,
			'category_name' => $term->name,
			'url'           => get_term_link( $term ),
			'edit_url'      => admin_url( 'term.php?taxonomy=product_cat&tag_ID=' . $term->term_id ),
			'product_count' => $term->count,
		);
	}

	/**
	 * Get WooCommerce settings URL.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error URL data.
	 */
	public function ability_urls_settings( $params ) {
		if ( empty( $params['section'] ) ) {
			return new \WP_Error( 'missing_param', __( 'Section is required.', 'assistify-for-woocommerce' ) );
		}

		$section = sanitize_text_field( $params['section'] );
		$url     = '';
		$title   = '';

		$settings_map = array(
			'general'     => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=general' ),
				'title' => __( 'General Settings', 'assistify-for-woocommerce' ),
			),
			'products'    => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=products' ),
				'title' => __( 'Product Settings', 'assistify-for-woocommerce' ),
			),
			'tax'         => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=tax' ),
				'title' => __( 'Tax Settings', 'assistify-for-woocommerce' ),
			),
			'shipping'    => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
				'title' => __( 'Shipping Settings', 'assistify-for-woocommerce' ),
			),
			'payments'    => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
				'title' => __( 'Payment Settings', 'assistify-for-woocommerce' ),
			),
			'accounts'    => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=account' ),
				'title' => __( 'Account Settings', 'assistify-for-woocommerce' ),
			),
			'emails'      => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=email' ),
				'title' => __( 'Email Settings', 'assistify-for-woocommerce' ),
			),
			'integration' => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=integration' ),
				'title' => __( 'Integration Settings', 'assistify-for-woocommerce' ),
			),
			'advanced'    => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=advanced' ),
				'title' => __( 'Advanced Settings', 'assistify-for-woocommerce' ),
			),
			'assistify'   => array(
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=assistify' ),
				'title' => __( 'Assistify Settings', 'assistify-for-woocommerce' ),
			),
		);

		if ( ! isset( $settings_map[ $section ] ) ) {
			$available = implode( ', ', array_keys( $settings_map ) );
			return new \WP_Error(
				'invalid_section',
				/* translators: %s: Available sections. */
				sprintf( __( 'Invalid section. Available: %s', 'assistify-for-woocommerce' ), $available )
			);
		}

		return array(
			'success' => true,
			'section' => $section,
			'title'   => $settings_map[ $section ]['title'],
			'url'     => $settings_map[ $section ]['url'],
		);
	}

	/**
	 * Get page URL.
	 *
	 * @since 1.0.0
	 * @param array $params Parameters.
	 * @return array|WP_Error URL data.
	 */
	public function ability_urls_page( $params ) {
		$page = null;

		if ( ! empty( $params['page_id'] ) ) {
			$page = get_post( absint( $params['page_id'] ) );
		} elseif ( ! empty( $params['page_slug'] ) ) {
			$page = get_page_by_path( sanitize_title( $params['page_slug'] ) );
		} elseif ( ! empty( $params['page_title'] ) ) {
			// Use WP_Query instead of deprecated get_page_by_title (WP 6.2+).
			$query = new \WP_Query(
				array(
					'post_type'              => 'page',
					'title'                  => sanitize_text_field( $params['page_title'] ),
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
				)
			);

			if ( $query->have_posts() ) {
				$page = $query->posts[0];
			}
		}

		if ( ! $page || 'page' !== $page->post_type ) {
			return new \WP_Error( 'not_found', __( 'Page not found.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success'    => true,
			'page_id'    => $page->ID,
			'page_title' => $page->post_title,
			'url'        => get_permalink( $page->ID ),
			'edit_url'   => admin_url( 'post.php?post=' . $page->ID . '&action=edit' ),
		);
	}
}

