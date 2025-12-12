<?php
/**
 * Admin Tools for Agentic AI Assistant
 *
 * Defines callable tools/functions that the AI can execute.
 * This enables the AI to actually perform actions, not just describe them.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Tools class.
 *
 * Registers and executes tools for the agentic admin assistant.
 *
 * @since 1.0.0
 */
class Admin_Tools {

	/**
	 * Singleton instance.
	 *
	 * @var Admin_Tools|null
	 */
	private static $instance = null;

	/**
	 * Registered tools.
	 *
	 * @var array
	 */
	private $tools = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Admin_Tools
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_tools();
	}

	/**
	 * Register all available tools.
	 */
	private function register_tools() {
		// WooCommerce Settings Tools.
		$this->register_tool(
			'update_wc_setting',
			array(
				'description' => 'Update a WooCommerce setting. Use this to enable/disable features like guest checkout, payment methods, etc.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'setting_id' => array(
							'type'        => 'string',
							'description' => 'The setting ID (e.g., "woocommerce_enable_guest_checkout", "woocommerce_enable_coupons")',
						),
						'value'      => array(
							'type'        => 'string',
							'description' => 'The new value (e.g., "yes", "no", or specific value)',
						),
					),
					'required'   => array( 'setting_id', 'value' ),
				),
				'callback'    => array( $this, 'tool_update_wc_setting' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'enable_payment_gateway',
			array(
				'description' => 'Enable or disable a payment gateway (e.g., PayPal, Stripe, Cash on Delivery, Check Payments, Bank Transfer)',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'gateway_id' => array(
							'type'        => 'string',
							'description' => 'The gateway ID (e.g., "cod" for Cash on Delivery, "cheque" for Check Payments, "bacs" for Bank Transfer, "paypal", "stripe")',
						),
						'enabled'    => array(
							'type'        => 'boolean',
							'description' => 'True to enable, false to disable',
						),
					),
					'required'   => array( 'gateway_id', 'enabled' ),
				),
				'callback'    => array( $this, 'tool_enable_payment_gateway' ),
				'destructive' => false,
			)
		);

		// Coupon Tools (Enhanced with full WooCommerce options).
		$this->register_tool(
			'create_coupon',
			array(
				'description' => 'Create a new WooCommerce coupon/discount code with full options including product/category restrictions, usage limits, and email restrictions',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'code'                        => array(
							'type'        => 'string',
							'description' => 'The coupon code (e.g., "SAVE20", "SUMMER2024")',
						),
						'discount_type'               => array(
							'type'        => 'string',
							'enum'        => array( 'percent', 'fixed_cart', 'fixed_product' ),
							'description' => 'Type of discount: percent, fixed_cart (fixed amount off cart), or fixed_product (fixed amount off product)',
						),
						'amount'                      => array(
							'type'        => 'number',
							'description' => 'Discount amount (percentage or fixed amount)',
						),
						'description'                 => array(
							'type'        => 'string',
							'description' => 'Internal description for admin reference (optional)',
						),
						'minimum_amount'              => array(
							'type'        => 'number',
							'description' => 'Minimum cart total required to use coupon (optional)',
						),
						'maximum_amount'              => array(
							'type'        => 'number',
							'description' => 'Maximum cart total allowed to use coupon (optional)',
						),
						'usage_limit'                 => array(
							'type'        => 'integer',
							'description' => 'Total number of times coupon can be used across all customers (optional)',
						),
						'usage_limit_per_user'        => array(
							'type'        => 'integer',
							'description' => 'Maximum times each customer can use this coupon (optional)',
						),
						'limit_usage_to_x_items'      => array(
							'type'        => 'integer',
							'description' => 'Max items the coupon applies to in cart (for product-level discounts, optional)',
						),
						'expiry_date'                 => array(
							'type'        => 'string',
							'description' => 'Expiry date in YYYY-MM-DD format (optional)',
						),
						'free_shipping'               => array(
							'type'        => 'boolean',
							'description' => 'Whether coupon grants free shipping (optional)',
						),
						'individual_use'              => array(
							'type'        => 'boolean',
							'description' => 'If true, coupon cannot be combined with others (optional)',
						),
						'exclude_sale_items'          => array(
							'type'        => 'boolean',
							'description' => 'If true, coupon will not apply to items on sale (optional)',
						),
						'product_ids'                 => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of product IDs the coupon applies to (optional, leave empty for all)',
						),
						'excluded_product_ids'        => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of product IDs to exclude from the coupon (optional)',
						),
						'product_categories'          => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of category IDs the coupon applies to (optional)',
						),
						'excluded_product_categories' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of category IDs to exclude (optional)',
						),
						'email_restrictions'          => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Array of email addresses that can use this coupon (optional)',
						),
					),
					'required'   => array( 'code', 'discount_type', 'amount' ),
				),
				'callback'    => array( $this, 'tool_create_coupon' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'update_coupon',
			array(
				'description' => 'Update an existing WooCommerce coupon by code. Only provide the fields you want to change.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'coupon_code'                 => array(
							'type'        => 'string',
							'description' => 'The existing coupon code to update',
						),
						'new_code'                    => array(
							'type'        => 'string',
							'description' => 'Change the coupon code to this (optional)',
						),
						'discount_type'               => array(
							'type'        => 'string',
							'enum'        => array( 'percent', 'fixed_cart', 'fixed_product' ),
							'description' => 'Type of discount (optional)',
						),
						'amount'                      => array(
							'type'        => 'number',
							'description' => 'Discount amount (optional)',
						),
						'description'                 => array(
							'type'        => 'string',
							'description' => 'Internal description (optional)',
						),
						'minimum_amount'              => array(
							'type'        => 'number',
							'description' => 'Minimum cart total (optional, set to 0 to remove)',
						),
						'maximum_amount'              => array(
							'type'        => 'number',
							'description' => 'Maximum cart total (optional, set to 0 to remove)',
						),
						'usage_limit'                 => array(
							'type'        => 'integer',
							'description' => 'Total usage limit (optional)',
						),
						'usage_limit_per_user'        => array(
							'type'        => 'integer',
							'description' => 'Per customer usage limit (optional)',
						),
						'expiry_date'                 => array(
							'type'        => 'string',
							'description' => 'Expiry date YYYY-MM-DD (optional, set to empty to remove)',
						),
						'free_shipping'               => array(
							'type'        => 'boolean',
							'description' => 'Whether coupon grants free shipping (optional)',
						),
						'individual_use'              => array(
							'type'        => 'boolean',
							'description' => 'Cannot combine with others (optional)',
						),
						'exclude_sale_items'          => array(
							'type'        => 'boolean',
							'description' => 'Exclude sale items (optional)',
						),
						'product_ids'                 => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Product IDs to restrict to (optional)',
						),
						'excluded_product_ids'        => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Product IDs to exclude (optional)',
						),
						'product_categories'          => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Category IDs to restrict to (optional)',
						),
						'excluded_product_categories' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Category IDs to exclude (optional)',
						),
						'email_restrictions'          => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Allowed email addresses (optional)',
						),
					),
					'required'   => array( 'coupon_code' ),
				),
				'callback'    => array( $this, 'tool_update_coupon' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'delete_coupon',
			array(
				'description' => 'Delete an existing coupon by code or ID',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'coupon_code' => array(
							'type'        => 'string',
							'description' => 'The coupon code to delete',
						),
					),
					'required'   => array( 'coupon_code' ),
				),
				'callback'    => array( $this, 'tool_delete_coupon' ),
				'destructive' => true,
			)
		);

		// Order Tools.
		$this->register_tool(
			'update_order_status',
			array(
				'description' => 'Update the status of an order',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'order_id' => array(
							'type'        => 'integer',
							'description' => 'The order ID',
						),
						'status'   => array(
							'type'        => 'string',
							'enum'        => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
							'description' => 'The new order status',
						),
						'note'     => array(
							'type'        => 'string',
							'description' => 'Optional note to add to the order',
						),
					),
					'required'   => array( 'order_id', 'status' ),
				),
				'callback'    => array( $this, 'tool_update_order_status' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'add_order_note',
			array(
				'description' => 'Add a note to an order',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'order_id'    => array(
							'type'        => 'integer',
							'description' => 'The order ID',
						),
						'note'        => array(
							'type'        => 'string',
							'description' => 'The note content',
						),
						'is_customer' => array(
							'type'        => 'boolean',
							'description' => 'If true, note is visible to customer. Default false (private note).',
						),
					),
					'required'   => array( 'order_id', 'note' ),
				),
				'callback'    => array( $this, 'tool_add_order_note' ),
				'destructive' => false,
			)
		);

		// Product Tools.
		$this->register_tool(
			'update_product_stock',
			array(
				'description' => 'Update product stock quantity',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'The product ID',
						),
						'quantity'   => array(
							'type'        => 'integer',
							'description' => 'New stock quantity',
						),
					),
					'required'   => array( 'product_id', 'quantity' ),
				),
				'callback'    => array( $this, 'tool_update_product_stock' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'update_product_price',
			array(
				'description' => 'Update product regular and/or sale price',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'    => array(
							'type'        => 'integer',
							'description' => 'The product ID',
						),
						'regular_price' => array(
							'type'        => 'number',
							'description' => 'New regular price (optional)',
						),
						'sale_price'    => array(
							'type'        => 'number',
							'description' => 'New sale price (optional, set to 0 or empty to remove sale)',
						),
					),
					'required'   => array( 'product_id' ),
				),
				'callback'    => array( $this, 'tool_update_product_price' ),
				'destructive' => false,
			)
		);

		// Comprehensive product update tool.
		$this->register_tool(
			'update_product',
			array(
				'description' => 'Update any product field including name, description, SKU, status, visibility, and more. Only provide the fields you want to change.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'         => array(
							'type'        => 'integer',
							'description' => 'The product ID to update',
						),
						'name'               => array(
							'type'        => 'string',
							'description' => 'New product name/title (optional)',
						),
						'description'        => array(
							'type'        => 'string',
							'description' => 'Full product description (optional)',
						),
						'short_description'  => array(
							'type'        => 'string',
							'description' => 'Short/excerpt description (optional)',
						),
						'sku'                => array(
							'type'        => 'string',
							'description' => 'Product SKU (optional)',
						),
						'regular_price'      => array(
							'type'        => 'number',
							'description' => 'Regular price (optional)',
						),
						'sale_price'         => array(
							'type'        => 'number',
							'description' => 'Sale price (optional, set to 0 to remove)',
						),
						'status'             => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
							'description' => 'Product status (optional)',
						),
						'catalog_visibility' => array(
							'type'        => 'string',
							'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
							'description' => 'Where product appears (optional)',
						),
						'featured'           => array(
							'type'        => 'boolean',
							'description' => 'Set as featured product (optional)',
						),
						'virtual'            => array(
							'type'        => 'boolean',
							'description' => 'Is virtual product (optional)',
						),
						'downloadable'       => array(
							'type'        => 'boolean',
							'description' => 'Is downloadable product (optional)',
						),
						'tax_status'         => array(
							'type'        => 'string',
							'enum'        => array( 'taxable', 'shipping', 'none' ),
							'description' => 'Tax status (optional)',
						),
						'tax_class'          => array(
							'type'        => 'string',
							'description' => 'Tax class (standard, reduced-rate, zero-rate, optional)',
						),
						'stock_quantity'     => array(
							'type'        => 'integer',
							'description' => 'Stock quantity (optional)',
						),
						'stock_status'       => array(
							'type'        => 'string',
							'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
							'description' => 'Stock status (optional)',
						),
						'manage_stock'       => array(
							'type'        => 'boolean',
							'description' => 'Enable stock management (optional)',
						),
						'backorders'         => array(
							'type'        => 'string',
							'enum'        => array( 'no', 'notify', 'yes' ),
							'description' => 'Allow backorders (optional)',
						),
						'weight'             => array(
							'type'        => 'string',
							'description' => 'Product weight (optional)',
						),
						'length'             => array(
							'type'        => 'string',
							'description' => 'Product length (optional)',
						),
						'width'              => array(
							'type'        => 'string',
							'description' => 'Product width (optional)',
						),
						'height'             => array(
							'type'        => 'string',
							'description' => 'Product height (optional)',
						),
						'category_ids'       => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of category IDs (optional, replaces existing)',
						),
						'tag_ids'            => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of tag IDs (optional, replaces existing)',
						),
					),
					'required'   => array( 'product_id' ),
				),
				'callback'    => array( $this, 'tool_update_product' ),
				'destructive' => false,
			)
		);

		// Publish product tool.
		$this->register_tool(
			'publish_product',
			array(
				'description' => 'Publish a draft or pending product, making it visible on the store',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'The product ID to publish',
						),
					),
					'required'   => array( 'product_id' ),
				),
				'callback'    => array( $this, 'tool_publish_product' ),
				'destructive' => false,
			)
		);

		// Order email tools.
		$this->register_tool(
			'resend_order_email',
			array(
				'description' => 'Resend an order email to the customer (new order, processing, completed, invoice, customer note)',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'order_id'   => array(
							'type'        => 'integer',
							'description' => 'The order ID',
						),
						'email_type' => array(
							'type'        => 'string',
							'enum'        => array( 'new_order', 'processing', 'completed', 'invoice', 'customer_note', 'cancelled', 'failed', 'on_hold' ),
							'description' => 'Type of email to send',
						),
					),
					'required'   => array( 'order_id', 'email_type' ),
				),
				'callback'    => array( $this, 'tool_resend_order_email' ),
				'destructive' => false,
			)
		);

		// Regenerate download permissions.
		$this->register_tool(
			'regenerate_downloads',
			array(
				'description' => 'Regenerate download permissions for an order (useful for digital products)',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'order_id' => array(
							'type'        => 'integer',
							'description' => 'The order ID',
						),
					),
					'required'   => array( 'order_id' ),
				),
				'callback'    => array( $this, 'tool_regenerate_downloads' ),
				'destructive' => false,
			)
		);

		// Bulk price update tool.
		$this->register_tool(
			'bulk_update_prices',
			array(
				'description' => 'Update prices for multiple products at once. Can increase or decrease by percentage or fixed amount. Optionally filter by category.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'adjustment_type' => array(
							'type'        => 'string',
							'enum'        => array( 'percentage', 'fixed' ),
							'description' => 'Type of adjustment: percentage or fixed amount',
						),
						'amount'          => array(
							'type'        => 'number',
							'description' => 'The amount to adjust (positive to increase, negative to decrease)',
						),
						'price_type'      => array(
							'type'        => 'string',
							'enum'        => array( 'regular', 'sale', 'both' ),
							'description' => 'Which price to update: regular, sale, or both. Default is regular.',
						),
						'category_id'     => array(
							'type'        => 'integer',
							'description' => 'Optional: Only update products in this category',
						),
						'round_to'        => array(
							'type'        => 'integer',
							'description' => 'Optional: Round prices to nearest value (e.g., 99 for $X.99 pricing)',
						),
					),
					'required'   => array( 'adjustment_type', 'amount' ),
				),
				'callback'    => array( $this, 'tool_bulk_update_prices' ),
				'destructive' => true,
			)
		);

		// Shipping zone tools.
		$this->register_tool(
			'create_shipping_zone',
			array(
				'description' => 'Create a new shipping zone with specified regions',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array(
							'type'        => 'string',
							'description' => 'Name of the shipping zone (e.g., "United States", "Local Delivery")',
						),
						'regions' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Array of region codes (e.g., ["US", "CA"] for countries, ["US:CA", "US:NY"] for states)',
						),
					),
					'required'   => array( 'name' ),
				),
				'callback'    => array( $this, 'tool_create_shipping_zone' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'add_shipping_method',
			array(
				'description' => 'Add a shipping method to a shipping zone',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'zone_id'     => array(
							'type'        => 'integer',
							'description' => 'The shipping zone ID',
						),
						'method_type' => array(
							'type'        => 'string',
							'enum'        => array( 'flat_rate', 'free_shipping', 'local_pickup' ),
							'description' => 'Type of shipping method',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Display title for the method (optional)',
						),
						'cost'        => array(
							'type'        => 'string',
							'description' => 'Cost for the method (for flat_rate). Can be a number or formula like "10 + (2 * [qty])"',
						),
						'min_amount'  => array(
							'type'        => 'number',
							'description' => 'Minimum order amount for free_shipping (optional)',
						),
					),
					'required'   => array( 'zone_id', 'method_type' ),
				),
				'callback'    => array( $this, 'tool_add_shipping_method' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'delete_shipping_zone',
			array(
				'description' => 'Delete a shipping zone (will also remove all its methods)',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'zone_id' => array(
							'type'        => 'integer',
							'description' => 'The shipping zone ID to delete',
						),
					),
					'required'   => array( 'zone_id' ),
				),
				'callback'    => array( $this, 'tool_delete_shipping_zone' ),
				'destructive' => true,
			)
		);

		$this->register_tool(
			'get_shipping_zones',
			array(
				'description' => 'List all shipping zones and their methods',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'callback'    => array( $this, 'tool_get_shipping_zones' ),
				'destructive' => false,
			)
		);

		// Tax tools.
		$this->register_tool(
			'create_tax_rate',
			array(
				'description' => 'Create a new tax rate',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'country'  => array(
							'type'        => 'string',
							'description' => 'Country code (e.g., "US", "GB", "CA")',
						),
						'state'    => array(
							'type'        => 'string',
							'description' => 'State code (e.g., "CA", "NY") or empty for whole country',
						),
						'rate'     => array(
							'type'        => 'number',
							'description' => 'Tax rate percentage (e.g., 8.25 for 8.25%)',
						),
						'name'     => array(
							'type'        => 'string',
							'description' => 'Tax name (e.g., "Sales Tax", "VAT")',
						),
						'priority' => array(
							'type'        => 'integer',
							'description' => 'Priority for tax calculation order (default 1)',
						),
						'compound' => array(
							'type'        => 'boolean',
							'description' => 'Whether tax is compounded (applied on top of other taxes)',
						),
						'shipping' => array(
							'type'        => 'boolean',
							'description' => 'Whether tax applies to shipping',
						),
						'class'    => array(
							'type'        => 'string',
							'description' => 'Tax class (standard, reduced-rate, zero-rate). Default is standard.',
						),
					),
					'required'   => array( 'country', 'rate', 'name' ),
				),
				'callback'    => array( $this, 'tool_create_tax_rate' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'delete_tax_rate',
			array(
				'description' => 'Delete a tax rate by ID',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'rate_id' => array(
							'type'        => 'integer',
							'description' => 'The tax rate ID to delete',
						),
					),
					'required'   => array( 'rate_id' ),
				),
				'callback'    => array( $this, 'tool_delete_tax_rate' ),
				'destructive' => true,
			)
		);

		$this->register_tool(
			'get_tax_rates',
			array(
				'description' => 'List all configured tax rates',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'callback'    => array( $this, 'tool_get_tax_rates' ),
				'destructive' => false,
			)
		);

		// Order creation tools.
		$this->register_tool(
			'create_order',
			array(
				'description' => 'Create a new manual order',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'customer_id'    => array(
							'type'        => 'integer',
							'description' => 'Customer user ID (0 for guest)',
						),
						'status'         => array(
							'type'        => 'string',
							'enum'        => array( 'pending', 'processing', 'on-hold', 'completed' ),
							'description' => 'Initial order status (default pending)',
						),
						'payment_method' => array(
							'type'        => 'string',
							'description' => 'Payment method ID (e.g., "cod", "bacs", "cheque")',
						),
						'billing_email'  => array(
							'type'        => 'string',
							'description' => 'Customer billing email',
						),
						'billing_name'   => array(
							'type'        => 'string',
							'description' => 'Customer billing name (first and last)',
						),
						'items'          => array(
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'product_id' => array( 'type' => 'integer' ),
									'quantity'   => array( 'type' => 'integer' ),
								),
							),
							'description' => 'Array of items with product_id and quantity',
						),
						'note'           => array(
							'type'        => 'string',
							'description' => 'Optional order note',
						),
					),
					'required'   => array( 'items' ),
				),
				'callback'    => array( $this, 'tool_create_order' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'apply_coupon_to_order',
			array(
				'description' => 'Apply a coupon code to an existing order',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'order_id'    => array(
							'type'        => 'integer',
							'description' => 'The order ID',
						),
						'coupon_code' => array(
							'type'        => 'string',
							'description' => 'The coupon code to apply',
						),
					),
					'required'   => array( 'order_id', 'coupon_code' ),
				),
				'callback'    => array( $this, 'tool_apply_coupon_to_order' ),
				'destructive' => false,
			)
		);

		// Read Tools (for getting specific data).
		$this->register_tool(
			'get_order_details',
			array(
				'description' => 'Get detailed information about a specific order',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'order_id' => array(
							'type'        => 'integer',
							'description' => 'The order ID to look up',
						),
					),
					'required'   => array( 'order_id' ),
				),
				'callback'    => array( $this, 'tool_get_order_details' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_recent_orders',
			array(
				'description' => 'Get a list of recent orders',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Number of orders to return (default 10)',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'Filter by status (optional)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_recent_orders' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_product_details',
			array(
				'description' => 'Get detailed information about a specific product',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'The product ID to look up',
						),
					),
					'required'   => array( 'product_id' ),
				),
				'callback'    => array( $this, 'tool_get_product_details' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'search_products',
			array(
				'description' => 'Search for products by name or SKU',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'Search query (product name or SKU)',
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Number of results (default 10)',
						),
					),
					'required'   => array( 'query' ),
				),
				'callback'    => array( $this, 'tool_search_products' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_coupons',
			array(
				'description' => 'Get list of existing coupons',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Number of coupons to return (default 20)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_coupons' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_payment_gateways',
			array(
				'description' => 'Get list of all payment gateways and their status',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(), // Empty object for OpenAI compatibility.
					'additionalProperties' => false,
				),
				'callback'    => array( $this, 'tool_get_payment_gateways' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_store_settings',
			array(
				'description' => 'Get WooCommerce store settings including checkout options, guest checkout status, etc.',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(), // Empty object for OpenAI compatibility.
					'additionalProperties' => false,
				),
				'callback'    => array( $this, 'tool_get_store_settings' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_sales_summary',
			array(
				'description' => 'Get sales summary for a time period',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'days' => array(
							'type'        => 'integer',
							'description' => 'Number of days to include (default 7)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_sales_summary' ),
				'destructive' => false,
			)
		);

		// Enhanced analytics tools.
		$this->register_tool(
			'get_sales_by_date',
			array(
				'description' => 'Get detailed sales breakdown by date range with grouping options',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'start_date' => array(
							'type'        => 'string',
							'description' => 'Start date in YYYY-MM-DD format',
						),
						'end_date'   => array(
							'type'        => 'string',
							'description' => 'End date in YYYY-MM-DD format',
						),
						'group_by'   => array(
							'type'        => 'string',
							'enum'        => array( 'day', 'week', 'month' ),
							'description' => 'How to group results (default: day)',
						),
					),
					'required'   => array( 'start_date', 'end_date' ),
				),
				'callback'    => array( $this, 'tool_get_sales_by_date' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_top_products',
			array(
				'description' => 'Get best-selling products by revenue or quantity',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'period' => array(
							'type'        => 'string',
							'enum'        => array( 'week', 'month', 'quarter', 'year', 'all' ),
							'description' => 'Time period to analyze (default: month)',
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Number of products to return (default: 10)',
						),
						'by'     => array(
							'type'        => 'string',
							'enum'        => array( 'revenue', 'quantity' ),
							'description' => 'Sort by revenue or quantity sold (default: revenue)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_top_products' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_top_customers',
			array(
				'description' => 'Get best customers by total spent or order count',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'period' => array(
							'type'        => 'string',
							'enum'        => array( 'month', 'quarter', 'year', 'all' ),
							'description' => 'Time period to analyze (default: year)',
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Number of customers to return (default: 10)',
						),
						'by'     => array(
							'type'        => 'string',
							'enum'        => array( 'spent', 'orders' ),
							'description' => 'Sort by total spent or order count (default: spent)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_top_customers' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_orders_by_status',
			array(
				'description' => 'Get order count and totals grouped by status',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'period' => array(
							'type'        => 'string',
							'enum'        => array( 'today', 'week', 'month', 'year', 'all' ),
							'description' => 'Time period to analyze (default: month)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_orders_by_status' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_low_stock_products',
			array(
				'description' => 'Get products that are low in stock or out of stock',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'threshold'            => array(
							'type'        => 'integer',
							'description' => 'Stock threshold to consider low (default: 5)',
						),
						'include_out_of_stock' => array(
							'type'        => 'boolean',
							'description' => 'Include out of stock products (default: true)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_low_stock_products' ),
				'destructive' => false,
			)
		);

		$this->register_tool(
			'get_category_performance',
			array(
				'description' => 'Get sales performance by product category',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'period' => array(
							'type'        => 'string',
							'enum'        => array( 'week', 'month', 'quarter', 'year' ),
							'description' => 'Time period to analyze (default: month)',
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Number of categories to return (default: 10)',
						),
					),
					'required'   => array(),
				),
				'callback'    => array( $this, 'tool_get_category_performance' ),
				'destructive' => false,
			)
		);

		/**
		 * Filter to allow adding custom tools.
		 *
		 * @since 1.0.0
		 * @param Admin_Tools $instance The tools instance.
		 */
		do_action( 'assistify_register_admin_tools', $this );
	}

	/**
	 * Register a tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Tool arguments.
	 */
	public function register_tool( $name, $args ) {
		$this->tools[ $name ] = wp_parse_args(
			$args,
			array(
				'description' => '',
				'parameters'  => array(),
				'callback'    => null,
				'destructive' => false,
			)
		);
	}

	/**
	 * Get all tools formatted for OpenAI API.
	 *
	 * @return array Tools in OpenAI format.
	 */
	public function get_tools_for_openai() {
		$tools = array();

		foreach ( $this->tools as $name => $tool ) {
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $name,
					'description' => $tool['description'],
					'parameters'  => $tool['parameters'],
				),
			);
		}

		return $tools;
	}

	/**
	 * Get all tools formatted for Anthropic API.
	 *
	 * @return array Tools in Anthropic format.
	 */
	public function get_tools_for_anthropic() {
		$tools = array();

		foreach ( $this->tools as $name => $tool ) {
			$tools[] = array(
				'name'         => $name,
				'description'  => $tool['description'],
				'input_schema' => $tool['parameters'],
			);
		}

		return $tools;
	}

	/**
	 * Check if a tool is destructive (requires extra confirmation).
	 *
	 * @param string $name Tool name.
	 * @return bool True if destructive.
	 */
	public function is_destructive( $name ) {
		return isset( $this->tools[ $name ] ) && ! empty( $this->tools[ $name ]['destructive'] );
	}

	/**
	 * Execute a tool.
	 *
	 * @param string $name      Tool name.
	 * @param array  $arguments Tool arguments.
	 * @return array|\WP_Error Result or error.
	 */
	public function execute( $name, $arguments = array() ) {
		if ( ! isset( $this->tools[ $name ] ) ) {
			return new \WP_Error( 'invalid_tool', sprintf( 'Tool "%s" not found.', $name ) );
		}

		$tool = $this->tools[ $name ];

		if ( ! is_callable( $tool['callback'] ) ) {
			return new \WP_Error( 'invalid_callback', sprintf( 'Tool "%s" has no valid callback.', $name ) );
		}

		// Log tool execution.
		\Assistify_For_WooCommerce\Assistify_Logger::info(
			sprintf( 'Executing tool: %s', $name ),
			'admin-tools',
			array( 'arguments' => $arguments )
		);

		try {
			$result = call_user_func( $tool['callback'], $arguments );

			\Assistify_For_WooCommerce\Assistify_Logger::info(
				sprintf( 'Tool %s executed successfully', $name ),
				'admin-tools'
			);

			return $result;
		} catch ( \Exception $e ) {
			\Assistify_For_WooCommerce\Assistify_Logger::error(
				sprintf( 'Tool %s failed: %s', $name, $e->getMessage() ),
				'admin-tools'
			);

			return new \WP_Error( 'tool_error', $e->getMessage() );
		}
	}

	// =========================================================================
	// Tool Callbacks
	// =========================================================================

	/**
	 * Update WooCommerce setting.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_update_wc_setting( $args ) {
		$setting_id = sanitize_text_field( $args['setting_id'] ?? '' );
		$value      = sanitize_text_field( $args['value'] ?? '' );

		if ( empty( $setting_id ) ) {
			return array(
				'success' => false,
				'message' => 'Setting ID is required.',
			);
		}

		// Map common names to actual option names.
		$setting_map = array(
			'guest_checkout'        => 'woocommerce_enable_guest_checkout',
			'enable_guest_checkout' => 'woocommerce_enable_guest_checkout',
			'coupons'               => 'woocommerce_enable_coupons',
			'enable_coupons'        => 'woocommerce_enable_coupons',
			'reviews'               => 'woocommerce_enable_reviews',
			'enable_reviews'        => 'woocommerce_enable_reviews',
			'stock_management'      => 'woocommerce_manage_stock',
		);

		if ( isset( $setting_map[ $setting_id ] ) ) {
			$setting_id = $setting_map[ $setting_id ];
		}

		// Ensure it starts with woocommerce_.
		if ( strpos( $setting_id, 'woocommerce_' ) !== 0 ) {
			$setting_id = 'woocommerce_' . $setting_id;
		}

		$old_value = get_option( $setting_id );
		update_option( $setting_id, $value );

		return array(
			'success'   => true,
			'message'   => sprintf( 'Setting "%s" updated from "%s" to "%s".', $setting_id, $old_value, $value ),
			'setting'   => $setting_id,
			'old_value' => $old_value,
			'new_value' => $value,
		);
	}

	/**
	 * Enable or disable a payment gateway.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_enable_payment_gateway( $args ) {
		$gateway_id = sanitize_text_field( $args['gateway_id'] ?? '' );
		$enabled    = ! empty( $args['enabled'] );

		if ( empty( $gateway_id ) ) {
			return array(
				'success' => false,
				'message' => 'Gateway ID is required.',
			);
		}

		// Map common names to gateway IDs.
		$gateway_map = array(
			'cash_on_delivery' => 'cod',
			'cash on delivery' => 'cod',
			'cod'              => 'cod',
			'check'            => 'cheque',
			'cheque'           => 'cheque',
			'check_payments'   => 'cheque',
			'check payments'   => 'cheque',
			'bank'             => 'bacs',
			'bacs'             => 'bacs',
			'bank_transfer'    => 'bacs',
			'bank transfer'    => 'bacs',
			'paypal'           => 'paypal',
			'stripe'           => 'stripe',
		);

		$gateway_id_lower = strtolower( $gateway_id );
		if ( isset( $gateway_map[ $gateway_id_lower ] ) ) {
			$gateway_id = $gateway_map[ $gateway_id_lower ];
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( 'Payment gateway "%s" not found.', $gateway_id ),
				'available' => array_keys( $gateways ),
			);
		}

		$gateway      = $gateways[ $gateway_id ];
		$gateway_name = $gateway->get_method_title();

		// Update the gateway setting.
		$settings_key        = $gateway->get_option_key();
		$settings            = get_option( $settings_key, array() );
		$settings['enabled'] = $enabled ? 'yes' : 'no';
		update_option( $settings_key, $settings );

		$action = $enabled ? 'enabled' : 'disabled';

		return array(
			'success'      => true,
			'message'      => sprintf( '%s has been %s.', $gateway_name, $action ),
			'gateway_id'   => $gateway_id,
			'gateway_name' => $gateway_name,
			'enabled'      => $enabled,
		);
	}

	/**
	 * Create a new coupon with full WooCommerce options.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_create_coupon( $args ) {
		$code = sanitize_text_field( $args['code'] ?? '' );

		if ( empty( $code ) ) {
			return array(
				'success' => false,
				'message' => 'Coupon code is required.',
			);
		}

		// Check if coupon already exists.
		$existing = wc_get_coupon_id_by_code( $code );
		if ( $existing ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Coupon code "%s" already exists.', $code ),
			);
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );

		// Discount type.
		$discount_type = sanitize_text_field( $args['discount_type'] ?? 'percent' );
		$coupon->set_discount_type( $discount_type );

		// Amount.
		$amount = floatval( $args['amount'] ?? 0 );
		$coupon->set_amount( $amount );

		// Description.
		if ( ! empty( $args['description'] ) ) {
			$coupon->set_description( sanitize_textarea_field( $args['description'] ) );
		}

		// Minimum amount.
		if ( isset( $args['minimum_amount'] ) && $args['minimum_amount'] > 0 ) {
			$coupon->set_minimum_amount( floatval( $args['minimum_amount'] ) );
		}

		// Maximum amount.
		if ( isset( $args['maximum_amount'] ) && $args['maximum_amount'] > 0 ) {
			$coupon->set_maximum_amount( floatval( $args['maximum_amount'] ) );
		}

		// Usage limit (total).
		if ( isset( $args['usage_limit'] ) ) {
			$coupon->set_usage_limit( intval( $args['usage_limit'] ) );
		}

		// Usage limit per user.
		if ( isset( $args['usage_limit_per_user'] ) ) {
			$coupon->set_usage_limit_per_user( intval( $args['usage_limit_per_user'] ) );
		}

		// Limit usage to X items.
		if ( isset( $args['limit_usage_to_x_items'] ) ) {
			$coupon->set_limit_usage_to_x_items( intval( $args['limit_usage_to_x_items'] ) );
		}

		// Expiry date.
		if ( ! empty( $args['expiry_date'] ) ) {
			$coupon->set_date_expires( strtotime( $args['expiry_date'] ) );
		}

		// Free shipping.
		if ( isset( $args['free_shipping'] ) ) {
			$coupon->set_free_shipping( (bool) $args['free_shipping'] );
		}

		// Individual use.
		if ( isset( $args['individual_use'] ) ) {
			$coupon->set_individual_use( (bool) $args['individual_use'] );
		}

		// Exclude sale items.
		if ( isset( $args['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( (bool) $args['exclude_sale_items'] );
		}

		// Product restrictions.
		if ( ! empty( $args['product_ids'] ) && is_array( $args['product_ids'] ) ) {
			$coupon->set_product_ids( array_map( 'intval', $args['product_ids'] ) );
		}

		// Excluded products.
		if ( ! empty( $args['excluded_product_ids'] ) && is_array( $args['excluded_product_ids'] ) ) {
			$coupon->set_excluded_product_ids( array_map( 'intval', $args['excluded_product_ids'] ) );
		}

		// Category restrictions.
		if ( ! empty( $args['product_categories'] ) && is_array( $args['product_categories'] ) ) {
			$coupon->set_product_categories( array_map( 'intval', $args['product_categories'] ) );
		}

		// Excluded categories.
		if ( ! empty( $args['excluded_product_categories'] ) && is_array( $args['excluded_product_categories'] ) ) {
			$coupon->set_excluded_product_categories( array_map( 'intval', $args['excluded_product_categories'] ) );
		}

		// Email restrictions.
		if ( ! empty( $args['email_restrictions'] ) && is_array( $args['email_restrictions'] ) ) {
			$coupon->set_email_restrictions( array_map( 'sanitize_email', $args['email_restrictions'] ) );
		}

		$coupon_id = $coupon->save();

		if ( ! $coupon_id ) {
			return array(
				'success' => false,
				'message' => 'Failed to create coupon.',
			);
		}

		// Build description.
		$desc = $this->build_coupon_description( $coupon, $discount_type, $amount, $args );

		return array(
			'success'     => true,
			'message'     => sprintf( 'Coupon "%s" created successfully! (%s)', $code, $desc ),
			'coupon_id'   => $coupon_id,
			'coupon_code' => $code,
			'discount'    => $desc,
			'edit_url'    => admin_url( 'post.php?post=' . $coupon_id . '&action=edit' ),
		);
	}

	/**
	 * Update an existing coupon.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_update_coupon( $args ) {
		$code = sanitize_text_field( $args['coupon_code'] ?? '' );

		if ( empty( $code ) ) {
			return array(
				'success' => false,
				'message' => 'Coupon code is required.',
			);
		}

		$coupon_id = wc_get_coupon_id_by_code( $code );
		if ( ! $coupon_id ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Coupon "%s" not found.', $code ),
			);
		}

		$coupon  = new \WC_Coupon( $coupon_id );
		$changes = array();

		// New code.
		if ( ! empty( $args['new_code'] ) ) {
			$new_code = sanitize_text_field( $args['new_code'] );
			// Check if new code already exists.
			$existing = wc_get_coupon_id_by_code( $new_code );
			if ( $existing && $existing !== $coupon_id ) {
				return array(
					'success' => false,
					'message' => sprintf( 'Coupon code "%s" already exists.', $new_code ),
				);
			}
			$coupon->set_code( $new_code );
			$changes[] = 'code changed to ' . $new_code;
		}

		// Discount type.
		if ( isset( $args['discount_type'] ) ) {
			$coupon->set_discount_type( sanitize_text_field( $args['discount_type'] ) );
			$changes[] = 'discount type';
		}

		// Amount.
		if ( isset( $args['amount'] ) ) {
			$coupon->set_amount( floatval( $args['amount'] ) );
			$changes[] = 'amount';
		}

		// Description.
		if ( isset( $args['description'] ) ) {
			$coupon->set_description( sanitize_textarea_field( $args['description'] ) );
			$changes[] = 'description';
		}

		// Minimum amount.
		if ( isset( $args['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( floatval( $args['minimum_amount'] ) );
			$changes[] = 'minimum amount';
		}

		// Maximum amount.
		if ( isset( $args['maximum_amount'] ) ) {
			$coupon->set_maximum_amount( floatval( $args['maximum_amount'] ) );
			$changes[] = 'maximum amount';
		}

		// Usage limit.
		if ( isset( $args['usage_limit'] ) ) {
			$coupon->set_usage_limit( intval( $args['usage_limit'] ) );
			$changes[] = 'usage limit';
		}

		// Usage limit per user.
		if ( isset( $args['usage_limit_per_user'] ) ) {
			$coupon->set_usage_limit_per_user( intval( $args['usage_limit_per_user'] ) );
			$changes[] = 'per-user limit';
		}

		// Expiry date.
		if ( isset( $args['expiry_date'] ) ) {
			if ( empty( $args['expiry_date'] ) ) {
				$coupon->set_date_expires( null );
				$changes[] = 'expiry removed';
			} else {
				$coupon->set_date_expires( strtotime( $args['expiry_date'] ) );
				$changes[] = 'expiry date';
			}
		}

		// Free shipping.
		if ( isset( $args['free_shipping'] ) ) {
			$coupon->set_free_shipping( (bool) $args['free_shipping'] );
			$changes[] = 'free shipping';
		}

		// Individual use.
		if ( isset( $args['individual_use'] ) ) {
			$coupon->set_individual_use( (bool) $args['individual_use'] );
			$changes[] = 'individual use';
		}

		// Exclude sale items.
		if ( isset( $args['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( (bool) $args['exclude_sale_items'] );
			$changes[] = 'exclude sale items';
		}

		// Product restrictions.
		if ( isset( $args['product_ids'] ) ) {
			$coupon->set_product_ids( is_array( $args['product_ids'] ) ? array_map( 'intval', $args['product_ids'] ) : array() );
			$changes[] = 'product restrictions';
		}

		// Excluded products.
		if ( isset( $args['excluded_product_ids'] ) ) {
			$coupon->set_excluded_product_ids( is_array( $args['excluded_product_ids'] ) ? array_map( 'intval', $args['excluded_product_ids'] ) : array() );
			$changes[] = 'excluded products';
		}

		// Category restrictions.
		if ( isset( $args['product_categories'] ) ) {
			$coupon->set_product_categories( is_array( $args['product_categories'] ) ? array_map( 'intval', $args['product_categories'] ) : array() );
			$changes[] = 'category restrictions';
		}

		// Excluded categories.
		if ( isset( $args['excluded_product_categories'] ) ) {
			$coupon->set_excluded_product_categories( is_array( $args['excluded_product_categories'] ) ? array_map( 'intval', $args['excluded_product_categories'] ) : array() );
			$changes[] = 'excluded categories';
		}

		// Email restrictions.
		if ( isset( $args['email_restrictions'] ) ) {
			$coupon->set_email_restrictions( is_array( $args['email_restrictions'] ) ? array_map( 'sanitize_email', $args['email_restrictions'] ) : array() );
			$changes[] = 'email restrictions';
		}

		if ( empty( $changes ) ) {
			return array(
				'success' => false,
				'message' => 'No changes specified.',
			);
		}

		$coupon->save();

		return array(
			'success'     => true,
			'message'     => sprintf( 'Coupon "%s" updated: %s.', $coupon->get_code(), implode( ', ', $changes ) ),
			'coupon_id'   => $coupon_id,
			'coupon_code' => $coupon->get_code(),
			'changes'     => $changes,
			'edit_url'    => admin_url( 'post.php?post=' . $coupon_id . '&action=edit' ),
		);
	}

	/**
	 * Build a human-readable coupon description.
	 *
	 * @param \WC_Coupon $coupon        The coupon object.
	 * @param string     $discount_type Discount type.
	 * @param float      $amount        Discount amount.
	 * @param array      $args          Original arguments.
	 * @return string Description.
	 */
	private function build_coupon_description( $coupon, $discount_type, $amount, $args ) {
		$desc_parts = array();

		// Main discount.
		if ( 'percent' === $discount_type ) {
			$desc_parts[] = sprintf( '%s%% off', $amount );
		} else {
			$desc_parts[] = sprintf( '%s off', wc_price( $amount ) );
		}

		// Minimum amount.
		if ( isset( $args['minimum_amount'] ) && $args['minimum_amount'] > 0 ) {
			$desc_parts[] = sprintf( 'min order %s', wc_price( $args['minimum_amount'] ) );
		}

		// Product restrictions.
		if ( ! empty( $args['product_ids'] ) ) {
			$desc_parts[] = sprintf( 'for %d product(s)', count( $args['product_ids'] ) );
		}

		// Category restrictions.
		if ( ! empty( $args['product_categories'] ) ) {
			$desc_parts[] = sprintf( 'in %d category(ies)', count( $args['product_categories'] ) );
		}

		// Free shipping.
		if ( ! empty( $args['free_shipping'] ) ) {
			$desc_parts[] = 'free shipping';
		}

		// Usage limit.
		if ( ! empty( $args['usage_limit'] ) ) {
			$desc_parts[] = sprintf( 'limit %d uses', $args['usage_limit'] );
		}

		// Per user limit.
		if ( ! empty( $args['usage_limit_per_user'] ) ) {
			$desc_parts[] = sprintf( '%d per customer', $args['usage_limit_per_user'] );
		}

		// Expiry.
		if ( ! empty( $args['expiry_date'] ) ) {
			$desc_parts[] = sprintf( 'expires %s', $args['expiry_date'] );
		}

		return implode( ', ', $desc_parts );
	}

	/**
	 * Delete a coupon.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_delete_coupon( $args ) {
		$code = sanitize_text_field( $args['coupon_code'] ?? '' );

		if ( empty( $code ) ) {
			return array(
				'success' => false,
				'message' => 'Coupon code is required.',
			);
		}

		$coupon_id = wc_get_coupon_id_by_code( $code );

		if ( ! $coupon_id ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Coupon "%s" not found.', $code ),
			);
		}

		wp_trash_post( $coupon_id );

		return array(
			'success' => true,
			'message' => sprintf( 'Coupon "%s" has been deleted.', $code ),
		);
	}

	/**
	 * Update order status.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_update_order_status( $args ) {
		$order_id = intval( $args['order_id'] ?? 0 );
		$status   = sanitize_text_field( $args['status'] ?? '' );
		$note     = sanitize_text_field( $args['note'] ?? '' );

		if ( ! $order_id ) {
			return array(
				'success' => false,
				'message' => 'Order ID is required.',
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Order #%d not found.', $order_id ),
			);
		}

		$old_status = $order->get_status();

		// Remove 'wc-' prefix if present.
		$status = str_replace( 'wc-', '', $status );

		$order->update_status( $status, $note );

		return array(
			'success'    => true,
			'message'    => sprintf( 'Order #%d status changed from "%s" to "%s".', $order_id, $old_status, $status ),
			'order_id'   => $order_id,
			'old_status' => $old_status,
			'new_status' => $status,
			'edit_url'   => $order->get_edit_order_url(),
		);
	}

	/**
	 * Add order note.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_add_order_note( $args ) {
		$order_id    = intval( $args['order_id'] ?? 0 );
		$note        = sanitize_text_field( $args['note'] ?? '' );
		$is_customer = ! empty( $args['is_customer'] );

		if ( ! $order_id ) {
			return array(
				'success' => false,
				'message' => 'Order ID is required.',
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Order #%d not found.', $order_id ),
			);
		}

		$note_id = $order->add_order_note( $note, $is_customer ? 1 : 0 );

		$type = $is_customer ? 'customer' : 'private';

		return array(
			'success'  => true,
			'message'  => sprintf( 'Added %s note to order #%d.', $type, $order_id ),
			'order_id' => $order_id,
			'note_id'  => $note_id,
			'note'     => $note,
		);
	}

	/**
	 * Update product stock.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_update_product_stock( $args ) {
		$product_id = intval( $args['product_id'] ?? 0 );
		$quantity   = intval( $args['quantity'] ?? 0 );

		if ( ! $product_id ) {
			return array(
				'success' => false,
				'message' => 'Product ID is required.',
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Product #%d not found.', $product_id ),
			);
		}

		$old_stock = $product->get_stock_quantity();
		$product->set_stock_quantity( $quantity );
		$product->set_manage_stock( true );
		$product->save();

		return array(
			'success'      => true,
			'message'      => sprintf( 'Stock for "%s" updated from %d to %d.', $product->get_name(), $old_stock ?? 0, $quantity ),
			'product_id'   => $product_id,
			'product_name' => $product->get_name(),
			'old_stock'    => $old_stock,
			'new_stock'    => $quantity,
		);
	}

	/**
	 * Update product price.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_update_product_price( $args ) {
		$product_id = intval( $args['product_id'] ?? 0 );

		if ( ! $product_id ) {
			return array(
				'success' => false,
				'message' => 'Product ID is required.',
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Product #%d not found.', $product_id ),
			);
		}

		$changes = array();

		if ( isset( $args['regular_price'] ) ) {
			$old_regular = $product->get_regular_price();
			$product->set_regular_price( floatval( $args['regular_price'] ) );
			$changes[] = sprintf( 'regular price from %s to %s', wc_price( $old_regular ), wc_price( $args['regular_price'] ) );
		}

		if ( isset( $args['sale_price'] ) ) {
			$old_sale = $product->get_sale_price();
			$new_sale = floatval( $args['sale_price'] );
			if ( $new_sale > 0 ) {
				$product->set_sale_price( $new_sale );
				$changes[] = sprintf( 'sale price from %s to %s', wc_price( $old_sale ), wc_price( $new_sale ) );
			} else {
				$product->set_sale_price( '' );
				$changes[] = 'removed sale price';
			}
		}

		$product->save();

		return array(
			'success'      => true,
			'message'      => sprintf( 'Updated "%s": %s.', $product->get_name(), implode( ', ', $changes ) ),
			'product_id'   => $product_id,
			'product_name' => $product->get_name(),
		);
	}

	/**
	 * Comprehensive product update.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_update_product( $args ) {
		$product_id = intval( $args['product_id'] ?? 0 );

		if ( ! $product_id ) {
			return array(
				'success' => false,
				'message' => 'Product ID is required.',
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Product #%d not found.', $product_id ),
			);
		}

		$changes = array();

		// Name/Title.
		if ( isset( $args['name'] ) && '' !== $args['name'] ) {
			$product->set_name( sanitize_text_field( $args['name'] ) );
			$changes[] = 'name';
		}

		// Description.
		if ( isset( $args['description'] ) ) {
			$product->set_description( wp_kses_post( $args['description'] ) );
			$changes[] = 'description';
		}

		// Short description.
		if ( isset( $args['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $args['short_description'] ) );
			$changes[] = 'short description';
		}

		// SKU.
		if ( isset( $args['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $args['sku'] ) );
			$changes[] = 'SKU';
		}

		// Regular price.
		if ( isset( $args['regular_price'] ) ) {
			$product->set_regular_price( floatval( $args['regular_price'] ) );
			$changes[] = 'regular price';
		}

		// Sale price.
		if ( isset( $args['sale_price'] ) ) {
			if ( floatval( $args['sale_price'] ) > 0 ) {
				$product->set_sale_price( floatval( $args['sale_price'] ) );
			} else {
				$product->set_sale_price( '' );
			}
			$changes[] = 'sale price';
		}

		// Status.
		if ( isset( $args['status'] ) ) {
			$product->set_status( sanitize_text_field( $args['status'] ) );
			$changes[] = 'status';
		}

		// Catalog visibility.
		if ( isset( $args['catalog_visibility'] ) ) {
			$product->set_catalog_visibility( sanitize_text_field( $args['catalog_visibility'] ) );
			$changes[] = 'visibility';
		}

		// Featured.
		if ( isset( $args['featured'] ) ) {
			$product->set_featured( (bool) $args['featured'] );
			$changes[] = 'featured';
		}

		// Virtual.
		if ( isset( $args['virtual'] ) ) {
			$product->set_virtual( (bool) $args['virtual'] );
			$changes[] = 'virtual';
		}

		// Downloadable.
		if ( isset( $args['downloadable'] ) ) {
			$product->set_downloadable( (bool) $args['downloadable'] );
			$changes[] = 'downloadable';
		}

		// Tax status.
		if ( isset( $args['tax_status'] ) ) {
			$product->set_tax_status( sanitize_text_field( $args['tax_status'] ) );
			$changes[] = 'tax status';
		}

		// Tax class.
		if ( isset( $args['tax_class'] ) ) {
			$product->set_tax_class( sanitize_text_field( $args['tax_class'] ) );
			$changes[] = 'tax class';
		}

		// Stock quantity.
		if ( isset( $args['stock_quantity'] ) ) {
			$product->set_stock_quantity( intval( $args['stock_quantity'] ) );
			$changes[] = 'stock';
		}

		// Stock status.
		if ( isset( $args['stock_status'] ) ) {
			$product->set_stock_status( sanitize_text_field( $args['stock_status'] ) );
			$changes[] = 'stock status';
		}

		// Manage stock.
		if ( isset( $args['manage_stock'] ) ) {
			$product->set_manage_stock( (bool) $args['manage_stock'] );
			$changes[] = 'stock management';
		}

		// Backorders.
		if ( isset( $args['backorders'] ) ) {
			$product->set_backorders( sanitize_text_field( $args['backorders'] ) );
			$changes[] = 'backorders';
		}

		// Weight.
		if ( isset( $args['weight'] ) ) {
			$product->set_weight( sanitize_text_field( $args['weight'] ) );
			$changes[] = 'weight';
		}

		// Dimensions.
		if ( isset( $args['length'] ) ) {
			$product->set_length( sanitize_text_field( $args['length'] ) );
			$changes[] = 'length';
		}
		if ( isset( $args['width'] ) ) {
			$product->set_width( sanitize_text_field( $args['width'] ) );
			$changes[] = 'width';
		}
		if ( isset( $args['height'] ) ) {
			$product->set_height( sanitize_text_field( $args['height'] ) );
			$changes[] = 'height';
		}

		// Categories.
		if ( isset( $args['category_ids'] ) && is_array( $args['category_ids'] ) ) {
			$product->set_category_ids( array_map( 'intval', $args['category_ids'] ) );
			$changes[] = 'categories';
		}

		// Tags.
		if ( isset( $args['tag_ids'] ) && is_array( $args['tag_ids'] ) ) {
			$product->set_tag_ids( array_map( 'intval', $args['tag_ids'] ) );
			$changes[] = 'tags';
		}

		if ( 0 === count( $changes ) ) {
			return array(
				'success' => false,
				'message' => 'No changes specified.',
			);
		}

		$product->save();

		return array(
			'success'      => true,
			'message'      => sprintf( 'Product "%s" updated: %s.', $product->get_name(), implode( ', ', $changes ) ),
			'product_id'   => $product_id,
			'product_name' => $product->get_name(),
			'changes'      => $changes,
			'edit_url'     => get_edit_post_link( $product_id, 'raw' ),
		);
	}

	/**
	 * Publish a draft product.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_publish_product( $args ) {
		$product_id = intval( $args['product_id'] ?? 0 );

		if ( ! $product_id ) {
			return array(
				'success' => false,
				'message' => 'Product ID is required.',
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Product #%d not found.', $product_id ),
			);
		}

		$old_status = $product->get_status();

		if ( 'publish' === $old_status ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Product "%s" is already published.', $product->get_name() ),
			);
		}

		$product->set_status( 'publish' );
		$product->save();

		return array(
			'success'      => true,
			'message'      => sprintf( 'Product "%s" is now published and visible on the store.', $product->get_name() ),
			'product_id'   => $product_id,
			'product_name' => $product->get_name(),
			'old_status'   => $old_status,
			'new_status'   => 'publish',
			'view_url'     => $product->get_permalink(),
			'edit_url'     => get_edit_post_link( $product_id, 'raw' ),
		);
	}

	/**
	 * Resend order email.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_resend_order_email( $args ) {
		$order_id   = intval( $args['order_id'] ?? 0 );
		$email_type = sanitize_text_field( $args['email_type'] ?? '' );

		if ( ! $order_id ) {
			return array(
				'success' => false,
				'message' => 'Order ID is required.',
			);
		}

		if ( empty( $email_type ) ) {
			return array(
				'success' => false,
				'message' => 'Email type is required.',
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Order #%d not found.', $order_id ),
			);
		}

		// Map email types to WooCommerce email IDs.
		$email_map = array(
			'new_order'     => 'new_order',
			'processing'    => 'customer_processing_order',
			'completed'     => 'customer_completed_order',
			'invoice'       => 'customer_invoice',
			'customer_note' => 'customer_note',
			'cancelled'     => 'cancelled_order',
			'failed'        => 'failed_order',
			'on_hold'       => 'customer_on_hold_order',
		);

		if ( ! isset( $email_map[ $email_type ] ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( 'Unknown email type: %s', $email_type ),
				'available' => array_keys( $email_map ),
			);
		}

		$email_id = $email_map[ $email_type ];

		// Get the email class.
		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();

		$email_class = '';
		foreach ( $emails as $email_key => $email_obj ) {
			if ( $email_obj->id === $email_id ) {
				$email_class = $email_key;
				break;
			}
		}

		if ( empty( $email_class ) || ! isset( $emails[ $email_class ] ) ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Email class "%s" not found or not enabled.', $email_id ),
			);
		}

		// Trigger the email.
		// @phpstan-ignore-next-line - WC_Email::trigger() exists but stubs incomplete.
		$emails[ $email_class ]->trigger( $order_id, $order );

		$email_name = $emails[ $email_class ]->get_title();

		return array(
			'success'    => true,
			'message'    => sprintf( '"%s" email sent for order #%d to %s.', $email_name, $order_id, $order->get_billing_email() ),
			'order_id'   => $order_id,
			'email_type' => $email_type,
			'email_name' => $email_name,
			'recipient'  => $order->get_billing_email(),
		);
	}

	/**
	 * Regenerate download permissions for an order.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_regenerate_downloads( $args ) {
		$order_id = intval( $args['order_id'] ?? 0 );

		if ( ! $order_id ) {
			return array(
				'success' => false,
				'message' => 'Order ID is required.',
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Order #%d not found.', $order_id ),
			);
		}

		// Check if order has downloadable items.
		$has_downloadable = false;
		foreach ( $order->get_items() as $item ) {
			// @phpstan-ignore-next-line - WC_Order_Item_Product::get_product() exists.
			$product = $item->get_product();
			if ( $product && $product->is_downloadable() ) {
				$has_downloadable = true;
				break;
			}
		}

		if ( ! $has_downloadable ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Order #%d has no downloadable products.', $order_id ),
			);
		}

		// Delete existing permissions.
		$data_store = \WC_Data_Store::load( 'customer-download' );
		// @phpstan-ignore-next-line - WC_Customer_Download_Data_Store::delete_by_order_id() exists.
		$data_store->delete_by_order_id( $order_id );

		// Regenerate permissions.
		wc_downloadable_product_permissions( $order_id, true );

		// Count new downloads.
		$downloads       = wc_get_customer_available_downloads( $order->get_customer_id() );
		$order_downloads = array_filter(
			$downloads,
			function ( $dl ) use ( $order_id ) {
				return (int) $dl['order_id'] === $order_id;
			}
		);

		return array(
			'success'         => true,
			'message'         => sprintf( 'Download permissions regenerated for order #%d. %d download(s) now available.', $order_id, count( $order_downloads ) ),
			'order_id'        => $order_id,
			'downloads_count' => count( $order_downloads ),
		);
	}

	/**
	 * Get order details.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_order_details( $args ) {
		$order_id = intval( $args['order_id'] ?? 0 );

		if ( ! $order_id ) {
			return array(
				'success' => false,
				'message' => 'Order ID is required.',
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Order #%d not found.', $order_id ),
			);
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => wc_price( $item->get_total() ),
			);
		}

		return array(
			'success'        => true,
			'order_id'       => $order_id,
			'order_number'   => $order->get_order_number(),
			'status'         => wc_get_order_status_name( $order->get_status() ),
			'date'           => $order->get_date_created()->format( 'Y-m-d H:i' ),
			'total'          => wc_price( $order->get_total() ),
			'customer'       => $order->get_formatted_billing_full_name(),
			'email'          => $order->get_billing_email(),
			'payment_method' => $order->get_payment_method_title(),
			'items'          => $items,
			'edit_url'       => $order->get_edit_order_url(),
		);
	}

	/**
	 * Get recent orders.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_recent_orders( $args ) {
		$limit  = intval( $args['limit'] ?? 10 );
		$status = sanitize_text_field( $args['status'] ?? '' );

		$query_args = array(
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( $status ) {
			$query_args['status'] = $status;
		}

		$orders  = wc_get_orders( $query_args );
		$results = array();

		foreach ( $orders as $order ) {
			$results[] = array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'status'   => wc_get_order_status_name( $order->get_status() ),
				'total'    => wc_price( $order->get_total() ),
				'customer' => $order->get_formatted_billing_full_name(),
				'date'     => $order->get_date_created()->format( 'Y-m-d H:i' ),
				'edit_url' => $order->get_edit_order_url(),
			);
		}

		return array(
			'success' => true,
			'count'   => count( $results ),
			'orders'  => $results,
		);
	}

	/**
	 * Get product details.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_product_details( $args ) {
		$product_id = intval( $args['product_id'] ?? 0 );

		if ( ! $product_id ) {
			return array(
				'success' => false,
				'message' => 'Product ID is required.',
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Product #%d not found.', $product_id ),
			);
		}

		return array(
			'success'       => true,
			'product_id'    => $product_id,
			'name'          => $product->get_name(),
			'sku'           => $product->get_sku(),
			'price'         => wc_price( $product->get_price() ),
			'regular_price' => wc_price( $product->get_regular_price() ),
			'sale_price'    => $product->get_sale_price() ? wc_price( $product->get_sale_price() ) : null,
			'stock_status'  => $product->get_stock_status(),
			'stock_qty'     => $product->get_stock_quantity(),
			'type'          => $product->get_type(),
			'edit_url'      => get_edit_post_link( $product_id, '' ),
		);
	}

	/**
	 * Search products.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_search_products( $args ) {
		$query = sanitize_text_field( $args['query'] ?? '' );
		$limit = intval( $args['limit'] ?? 10 );

		if ( empty( $query ) ) {
			return array(
				'success' => false,
				'message' => 'Search query is required.',
			);
		}

		$products = wc_get_products(
			array(
				'limit'  => $limit,
				's'      => $query,
				'status' => 'publish',
			)
		);

		$results = array();
		foreach ( $products as $product ) {
			$results[] = array(
				'id'       => $product->get_id(),
				'name'     => $product->get_name(),
				'sku'      => $product->get_sku(),
				'price'    => wc_price( $product->get_price() ),
				'stock'    => $product->get_stock_quantity(),
				'edit_url' => get_edit_post_link( $product->get_id(), '' ),
			);
		}

		return array(
			'success'  => true,
			'query'    => $query,
			'count'    => count( $results ),
			'products' => $results,
		);
	}

	/**
	 * Get coupons.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_coupons( $args ) {
		$limit = intval( $args['limit'] ?? 20 );

		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
			)
		);

		$results = array();
		foreach ( $coupons as $coupon_post ) {
			$coupon = new \WC_Coupon( $coupon_post->ID );

			$discount_desc = '';
			if ( 'percent' === $coupon->get_discount_type() ) {
				$discount_desc = $coupon->get_amount() . '% off';
			} else {
				$discount_desc = wc_price( $coupon->get_amount() ) . ' off';
			}

			$results[] = array(
				'id'             => $coupon_post->ID,
				'code'           => $coupon->get_code(),
				'discount'       => $discount_desc,
				'discount_type'  => $coupon->get_discount_type(),
				'amount'         => $coupon->get_amount(),
				'minimum_amount' => $coupon->get_minimum_amount(),
				'usage_count'    => $coupon->get_usage_count(),
				'expiry_date'    => $coupon->get_date_expires() ? $coupon->get_date_expires()->format( 'Y-m-d' ) : null,
			);
		}

		return array(
			'success' => true,
			'count'   => count( $results ),
			'coupons' => $results,
		);
	}

	/**
	 * Get payment gateways.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_payment_gateways( $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$gateways = WC()->payment_gateways()->payment_gateways();
		$results  = array();

		foreach ( $gateways as $gateway ) {
			$results[] = array(
				'id'          => $gateway->id,
				'title'       => $gateway->get_method_title(),
				'description' => $gateway->get_method_description(),
				'enabled'     => 'yes' === $gateway->enabled,
			);
		}

		return array(
			'success'  => true,
			'count'    => count( $results ),
			'gateways' => $results,
		);
	}

	/**
	 * Get store settings.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_store_settings( $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array(
			'success'                => true,
			'guest_checkout_enabled' => 'yes' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ),
			'coupons_enabled'        => 'yes' === get_option( 'woocommerce_enable_coupons', 'yes' ),
			'reviews_enabled'        => 'yes' === get_option( 'woocommerce_enable_reviews', 'yes' ),
			'stock_management'       => 'yes' === get_option( 'woocommerce_manage_stock', 'yes' ),
			'currency'               => get_woocommerce_currency(),
			'currency_symbol'        => html_entity_decode( get_woocommerce_currency_symbol() ),
			'store_address'          => array(
				'address'  => get_option( 'woocommerce_store_address' ),
				'city'     => get_option( 'woocommerce_store_city' ),
				'postcode' => get_option( 'woocommerce_store_postcode' ),
				'country'  => get_option( 'woocommerce_default_country' ),
			),
			'settings_url'           => admin_url( 'admin.php?page=wc-settings' ),
		);
	}

	/**
	 * Get sales summary.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_sales_summary( $args ) {
		$days       = intval( $args['days'] ?? 7 );
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$end_date   = gmdate( 'Y-m-d' );

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'completed', 'processing' ),
				'date_created' => $start_date . '...' . $end_date,
			)
		);

		$total_sales  = 0;
		$total_orders = count( $orders );

		foreach ( $orders as $order ) {
			$total_sales += $order->get_total();
		}

		$avg_order = $total_orders > 0 ? $total_sales / $total_orders : 0;

		return array(
			'success'       => true,
			'period'        => sprintf( 'Last %d days', $days ),
			'total_orders'  => $total_orders,
			'total_sales'   => wc_price( $total_sales ),
			'average_order' => wc_price( $avg_order ),
		);
	}

	/**
	 * Bulk update product prices.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_bulk_update_prices( $args ) {
		$adjustment_type = sanitize_text_field( $args['adjustment_type'] ?? 'percentage' );
		$amount          = floatval( $args['amount'] ?? 0 );
		$price_type      = sanitize_text_field( $args['price_type'] ?? 'regular' );
		$category_id     = intval( $args['category_id'] ?? 0 );
		$round_to        = isset( $args['round_to'] ) ? intval( $args['round_to'] ) : null;

		if ( 0 === $amount ) {
			return array(
				'success' => false,
				'message' => 'Amount is required and cannot be zero.',
			);
		}

		// Build query args.
		$query_args = array(
			'limit'  => -1,
			'status' => 'publish',
			'type'   => array( 'simple', 'variable' ),
		);

		if ( $category_id > 0 ) {
			$query_args['category'] = array( get_term( $category_id )->slug );
		}

		$products = wc_get_products( $query_args );
		$updated  = 0;

		foreach ( $products as $product ) {
			$changed = false;

			// Update regular price.
			if ( in_array( $price_type, array( 'regular', 'both' ), true ) ) {
				$current_price = floatval( $product->get_regular_price() );
				if ( $current_price > 0 ) {
					$new_price = $this->calculate_new_price( $current_price, $adjustment_type, $amount, $round_to );
					$product->set_regular_price( $new_price );
					$changed = true;
				}
			}

			// Update sale price.
			if ( in_array( $price_type, array( 'sale', 'both' ), true ) ) {
				$current_sale = floatval( $product->get_sale_price() );
				if ( $current_sale > 0 ) {
					$new_sale = $this->calculate_new_price( $current_sale, $adjustment_type, $amount, $round_to );
					$product->set_sale_price( $new_sale );
					$changed = true;
				}
			}

			if ( $changed ) {
				$product->save();
				++$updated;
			}
		}

		$direction  = $amount > 0 ? 'increased' : 'decreased';
		$abs_amount = abs( $amount );

		if ( 'percentage' === $adjustment_type ) {
			$change_desc = sprintf( '%s by %s%%', $direction, $abs_amount );
		} else {
			$change_desc = sprintf( '%s by %s', $direction, wc_price( $abs_amount ) );
		}

		return array(
			'success'        => true,
			'message'        => sprintf( '%d product(s) %s.', $updated, $change_desc ),
			'products_count' => $updated,
			'adjustment'     => $change_desc,
			'price_type'     => $price_type,
		);
	}

	/**
	 * Calculate new price after adjustment.
	 *
	 * @param float    $current_price Current price.
	 * @param string   $type          Adjustment type (percentage or fixed).
	 * @param float    $amount        Amount to adjust.
	 * @param int|null $round_to      Optional rounding value.
	 * @return float New price.
	 */
	private function calculate_new_price( $current_price, $type, $amount, $round_to = null ) {
		if ( 'percentage' === $type ) {
			$new_price = $current_price * ( 1 + ( $amount / 100 ) );
		} else {
			$new_price = $current_price + $amount;
		}

		// Ensure price doesn't go negative.
		$new_price = max( 0, $new_price );

		// Apply rounding if specified.
		if ( null !== $round_to && $round_to > 0 ) {
			$new_price = floor( $new_price ) + ( $round_to / 100 );
		}

		return round( $new_price, 2 );
	}

	/**
	 * Create a shipping zone.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_create_shipping_zone( $args ) {
		$name    = sanitize_text_field( $args['name'] ?? '' );
		$regions = isset( $args['regions'] ) && is_array( $args['regions'] ) ? $args['regions'] : array();

		if ( empty( $name ) ) {
			return array(
				'success' => false,
				'message' => 'Zone name is required.',
			);
		}

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( $name );
		$zone->save();

		// Add regions.
		foreach ( $regions as $region ) {
			$region = sanitize_text_field( $region );
			if ( strpos( $region, ':' ) !== false ) {
				// State format: US:CA.
				list( $country, $state ) = explode( ':', $region );
				$zone->add_location( $country . ':' . $state, 'state' );
			} else {
				// Country format: US.
				$zone->add_location( $region, 'country' );
			}
		}

		$zone->save();

		return array(
			'success'   => true,
			'message'   => sprintf( 'Shipping zone "%s" created.', $name ),
			'zone_id'   => $zone->get_id(),
			'zone_name' => $name,
			'regions'   => $regions,
			'edit_url'  => admin_url( 'admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone->get_id() ),
		);
	}

	/**
	 * Add a shipping method to a zone.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_add_shipping_method( $args ) {
		$zone_id     = intval( $args['zone_id'] ?? 0 );
		$method_type = sanitize_text_field( $args['method_type'] ?? '' );
		$title       = sanitize_text_field( $args['title'] ?? '' );
		$cost        = sanitize_text_field( $args['cost'] ?? '' );
		$min_amount  = floatval( $args['min_amount'] ?? 0 );

		if ( ! $zone_id ) {
			return array(
				'success' => false,
				'message' => 'Zone ID is required.',
			);
		}

		$zone = \WC_Shipping_Zones::get_zone( $zone_id );
		if ( ! $zone ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Shipping zone %d not found.', $zone_id ),
			);
		}

		// Add the method.
		$instance_id = $zone->add_shipping_method( $method_type );
		if ( ! $instance_id ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Failed to add %s method.', $method_type ),
			);
		}

		// Get the method instance to update settings.
		$methods = $zone->get_shipping_methods();
		$method  = isset( $methods[ $instance_id ] ) ? $methods[ $instance_id ] : null;

		if ( $method ) {
			$settings = array();

			if ( '' !== $title ) {
				$settings['title'] = $title;
			}

			if ( 'flat_rate' === $method_type && '' !== $cost ) {
				$settings['cost'] = $cost;
			}

			if ( 'free_shipping' === $method_type && $min_amount > 0 ) {
				$settings['requires']   = 'min_amount';
				$settings['min_amount'] = $min_amount;
			}

			if ( ! empty( $settings ) ) {
				foreach ( $settings as $key => $value ) {
					$method->instance_settings[ $key ] = $value;
				}
				update_option( $method->get_instance_option_key(), $method->instance_settings );
			}
		}

		$method_names = array(
			'flat_rate'     => 'Flat Rate',
			'free_shipping' => 'Free Shipping',
			'local_pickup'  => 'Local Pickup',
		);

		return array(
			'success'     => true,
			'message'     => sprintf( '%s method added to zone "%s".', $method_names[ $method_type ] ?? $method_type, $zone->get_zone_name() ),
			'zone_id'     => $zone_id,
			'instance_id' => $instance_id,
			'method_type' => $method_type,
		);
	}

	/**
	 * Delete a shipping zone.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_delete_shipping_zone( $args ) {
		$zone_id = intval( $args['zone_id'] ?? 0 );

		if ( ! $zone_id ) {
			return array(
				'success' => false,
				'message' => 'Zone ID is required.',
			);
		}

		$zone = \WC_Shipping_Zones::get_zone( $zone_id );
		if ( ! $zone ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Shipping zone %d not found.', $zone_id ),
			);
		}

		$zone_name = $zone->get_zone_name();
		$zone->delete();

		return array(
			'success'   => true,
			'message'   => sprintf( 'Shipping zone "%s" deleted.', $zone_name ),
			'zone_id'   => $zone_id,
			'zone_name' => $zone_name,
		);
	}

	/**
	 * Get all shipping zones.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_shipping_zones( $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$zones   = \WC_Shipping_Zones::get_zones();
		$results = array();

		// Add the "Rest of the World" zone (ID 0).
		$rest_zone = \WC_Shipping_Zones::get_zone( 0 );
		$results[] = array(
			'id'      => 0,
			'name'    => $rest_zone->get_zone_name(),
			'regions' => 'Locations not covered by other zones',
			'methods' => $this->format_zone_methods( $rest_zone->get_shipping_methods() ),
		);

		foreach ( $zones as $zone_data ) {
			$zone = \WC_Shipping_Zones::get_zone( $zone_data['id'] );

			$region_names = array();
			foreach ( $zone->get_zone_locations() as $location ) {
				$region_names[] = $location->code;
			}

			$results[] = array(
				'id'      => $zone->get_id(),
				'name'    => $zone->get_zone_name(),
				'regions' => implode( ', ', $region_names ),
				'methods' => $this->format_zone_methods( $zone->get_shipping_methods() ),
			);
		}

		return array(
			'success' => true,
			'zones'   => $results,
			'count'   => count( $results ),
		);
	}

	/**
	 * Format shipping methods for output.
	 *
	 * @param array $methods Shipping methods.
	 * @return array Formatted methods.
	 */
	private function format_zone_methods( $methods ) {
		$formatted = array();
		foreach ( $methods as $method ) {
			$formatted[] = array(
				'id'      => $method->instance_id,
				'type'    => $method->id,
				'title'   => $method->get_title(),
				'enabled' => 'yes' === $method->enabled,
			);
		}
		return $formatted;
	}

	/**
	 * Create a tax rate.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_create_tax_rate( $args ) {
		$country  = sanitize_text_field( $args['country'] ?? '' );
		$state    = sanitize_text_field( $args['state'] ?? '' );
		$rate     = floatval( $args['rate'] ?? 0 );
		$name     = sanitize_text_field( $args['name'] ?? '' );
		$priority = intval( $args['priority'] ?? 1 );
		$compound = ! empty( $args['compound'] );
		$shipping = isset( $args['shipping'] ) ? ! empty( $args['shipping'] ) : true;
		$class    = sanitize_text_field( $args['class'] ?? '' );

		if ( empty( $country ) ) {
			return array(
				'success' => false,
				'message' => 'Country is required.',
			);
		}

		if ( empty( $name ) ) {
			return array(
				'success' => false,
				'message' => 'Tax name is required.',
			);
		}

		global $wpdb;

		$tax_rate_data = array(
			'tax_rate_country'  => strtoupper( $country ),
			'tax_rate_state'    => strtoupper( $state ),
			'tax_rate'          => $rate,
			'tax_rate_name'     => $name,
			'tax_rate_priority' => $priority,
			'tax_rate_compound' => $compound ? 1 : 0,
			'tax_rate_shipping' => $shipping ? 1 : 0,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => $class,
		);

		$wpdb->insert( $wpdb->prefix . 'woocommerce_tax_rates', $tax_rate_data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rate_id = $wpdb->insert_id;

		if ( ! $rate_id ) {
			return array(
				'success' => false,
				'message' => 'Failed to create tax rate.',
			);
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Tax rate "%s" (%s%%) created for %s%s.', $name, $rate, $country, $state ? '/' . $state : '' ),
			'rate_id' => $rate_id,
			'rate'    => $rate,
			'name'    => $name,
		);
	}

	/**
	 * Delete a tax rate.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_delete_tax_rate( $args ) {
		$rate_id = intval( $args['rate_id'] ?? 0 );

		if ( ! $rate_id ) {
			return array(
				'success' => false,
				'message' => 'Rate ID is required.',
			);
		}

		global $wpdb;

		// Get rate info first.
		$rate = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d", $rate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $rate ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Tax rate %d not found.', $rate_id ),
			);
		}

		$wpdb->delete( $wpdb->prefix . 'woocommerce_tax_rates', array( 'tax_rate_id' => $rate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return array(
			'success' => true,
			'message' => sprintf( 'Tax rate "%s" deleted.', $rate->tax_rate_name ),
			'rate_id' => $rate_id,
		);
	}

	/**
	 * Get all tax rates.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_tax_rates( $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		global $wpdb;

		$rates = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_country, tax_rate_state" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$results = array();
		foreach ( $rates as $rate ) {
			$results[] = array(
				'id'       => $rate->tax_rate_id,
				'country'  => $rate->tax_rate_country,
				'state'    => $rate->tax_rate_state,
				'rate'     => $rate->tax_rate . '%',
				'name'     => $rate->tax_rate_name,
				'priority' => $rate->tax_rate_priority,
				'compound' => 1 === (int) $rate->tax_rate_compound,
				'shipping' => 1 === (int) $rate->tax_rate_shipping,
			);
		}

		return array(
			'success' => true,
			'rates'   => $results,
			'count'   => count( $results ),
		);
	}

	/**
	 * Create a new order.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_create_order( $args ) {
		$customer_id    = intval( $args['customer_id'] ?? 0 );
		$status         = sanitize_text_field( $args['status'] ?? 'pending' );
		$payment_method = sanitize_text_field( $args['payment_method'] ?? '' );
		$billing_email  = sanitize_email( $args['billing_email'] ?? '' );
		$billing_name   = sanitize_text_field( $args['billing_name'] ?? '' );
		$items          = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
		$note           = sanitize_textarea_field( $args['note'] ?? '' );

		if ( empty( $items ) ) {
			return array(
				'success' => false,
				'message' => 'At least one item is required.',
			);
		}

		$order = wc_create_order( array( 'customer_id' => $customer_id ) );

		if ( is_wp_error( $order ) ) {
			return array(
				'success' => false,
				'message' => $order->get_error_message(),
			);
		}

		// Add items.
		$items_added = 0;
		foreach ( $items as $item ) {
			$product_id = intval( $item['product_id'] ?? 0 );
			$quantity   = intval( $item['quantity'] ?? 1 );

			if ( $product_id > 0 ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$order->add_product( $product, $quantity );
					++$items_added;
				}
			}
		}

		if ( 0 === $items_added ) {
			$order->delete( true );
			return array(
				'success' => false,
				'message' => 'No valid products found to add to order.',
			);
		}

		// Set billing info.
		if ( '' !== $billing_email ) {
			$order->set_billing_email( $billing_email );
		}
		if ( '' !== $billing_name ) {
			$names = explode( ' ', $billing_name, 2 );
			$order->set_billing_first_name( $names[0] );
			if ( isset( $names[1] ) ) {
				$order->set_billing_last_name( $names[1] );
			}
		}

		// Set payment method.
		if ( '' !== $payment_method ) {
			$order->set_payment_method( $payment_method );
		}

		// Calculate totals.
		$order->calculate_totals();

		// Set status.
		$order->set_status( $status );

		// Add note.
		if ( '' !== $note ) {
			$order->add_order_note( $note );
		}

		$order->save();

		return array(
			'success'      => true,
			'message'      => sprintf( 'Order #%d created with %d item(s). Total: %s', $order->get_id(), $items_added, wc_price( $order->get_total() ) ),
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'total'        => wc_price( $order->get_total() ),
			'items_count'  => $items_added,
			'edit_url'     => $order->get_edit_order_url(),
		);
	}

	/**
	 * Apply a coupon to an order.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_apply_coupon_to_order( $args ) {
		$order_id    = intval( $args['order_id'] ?? 0 );
		$coupon_code = sanitize_text_field( $args['coupon_code'] ?? '' );

		if ( ! $order_id ) {
			return array(
				'success' => false,
				'message' => 'Order ID is required.',
			);
		}

		if ( empty( $coupon_code ) ) {
			return array(
				'success' => false,
				'message' => 'Coupon code is required.',
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Order #%d not found.', $order_id ),
			);
		}

		// Check if coupon exists.
		$coupon = new \WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Coupon "%s" not found.', $coupon_code ),
			);
		}

		// Check if already applied.
		$applied_coupons = $order->get_coupon_codes();
		if ( in_array( strtolower( $coupon_code ), array_map( 'strtolower', $applied_coupons ), true ) ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Coupon "%s" is already applied to this order.', $coupon_code ),
			);
		}

		$old_total = $order->get_total();
		$result    = $order->apply_coupon( $coupon_code );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		$order->calculate_totals();
		$order->save();

		$discount = $old_total - $order->get_total();

		return array(
			'success'   => true,
			'message'   => sprintf( 'Coupon "%s" applied to order #%d. Discount: %s. New total: %s', $coupon_code, $order_id, wc_price( $discount ), wc_price( $order->get_total() ) ),
			'order_id'  => $order_id,
			'coupon'    => $coupon_code,
			'discount'  => wc_price( $discount ),
			'old_total' => wc_price( $old_total ),
			'new_total' => wc_price( $order->get_total() ),
		);
	}

	/**
	 * Get sales by date range.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_sales_by_date( $args ) {
		$start_date = sanitize_text_field( $args['start_date'] ?? '' );
		$end_date   = sanitize_text_field( $args['end_date'] ?? '' );
		$group_by   = sanitize_text_field( $args['group_by'] ?? 'day' );

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return array(
				'success' => false,
				'message' => 'Start date and end date are required.',
			);
		}

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'completed', 'processing' ),
				'date_created' => $start_date . '...' . $end_date,
			)
		);

		$data         = array();
		$total_sales  = 0;
		$total_orders = 0;

		foreach ( $orders as $order ) {
			$date = $order->get_date_created();
			if ( ! $date ) {
				continue;
			}

			switch ( $group_by ) {
				case 'week':
					$key = $date->format( 'Y-W' );
					break;
				case 'month':
					$key = $date->format( 'Y-m' );
					break;
				default:
					$key = $date->format( 'Y-m-d' );
			}

			if ( ! isset( $data[ $key ] ) ) {
				$data[ $key ] = array(
					'orders' => 0,
					'sales'  => 0,
				);
			}

			++$data[ $key ]['orders'];
			$data[ $key ]['sales'] += $order->get_total();
			$total_sales           += $order->get_total();
			++$total_orders;
		}

		// Format for output.
		$formatted = array();
		foreach ( $data as $period => $values ) {
			$formatted[] = array(
				'period' => $period,
				'orders' => $values['orders'],
				'sales'  => wc_price( $values['sales'] ),
			);
		}

		return array(
			'success'      => true,
			'start_date'   => $start_date,
			'end_date'     => $end_date,
			'group_by'     => $group_by,
			'total_orders' => $total_orders,
			'total_sales'  => wc_price( $total_sales ),
			'breakdown'    => $formatted,
		);
	}

	/**
	 * Get top selling products.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_top_products( $args ) {
		$period = sanitize_text_field( $args['period'] ?? 'month' );
		$limit  = intval( $args['limit'] ?? 10 );
		$by     = sanitize_text_field( $args['by'] ?? 'revenue' );

		// Calculate date range.
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $this->get_period_start_date( $period );

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'completed', 'processing' ),
				'date_created' => $start_date . '...' . $end_date,
			)
		);

		$product_data = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				// @phpstan-ignore-next-line - WC_Order_Item_Product::get_product_id() exists.
				$product_id = $item->get_product_id();
				if ( ! isset( $product_data[ $product_id ] ) ) {
					$product_data[ $product_id ] = array(
						'name'     => $item->get_name(),
						'quantity' => 0,
						'revenue'  => 0,
					);
				}
				$product_data[ $product_id ]['quantity'] += $item->get_quantity();
				$product_data[ $product_id ]['revenue']  += $item->get_total();
			}
		}

		// Sort by chosen metric.
		uasort(
			$product_data,
			function ( $a, $b ) use ( $by ) {
				return $b[ $by ] <=> $a[ $by ];
			}
		);

		// Take top N.
		$product_data = array_slice( $product_data, 0, $limit, true );

		$results = array();
		foreach ( $product_data as $product_id => $data ) {
			$results[] = array(
				'product_id' => $product_id,
				'name'       => $data['name'],
				'quantity'   => $data['quantity'],
				'revenue'    => wc_price( $data['revenue'] ),
			);
		}

		return array(
			'success'  => true,
			'period'   => $period,
			'sort_by'  => $by,
			'products' => $results,
		);
	}

	/**
	 * Get top customers.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_top_customers( $args ) {
		$period = sanitize_text_field( $args['period'] ?? 'year' );
		$limit  = intval( $args['limit'] ?? 10 );
		$by     = sanitize_text_field( $args['by'] ?? 'spent' );

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $this->get_period_start_date( $period );

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'completed', 'processing' ),
				'date_created' => $start_date . '...' . $end_date,
			)
		);

		$customer_data = array();

		foreach ( $orders as $order ) {
			$customer_id = $order->get_customer_id();
			$email       = $order->get_billing_email();
			$key         = $customer_id > 0 ? 'user_' . $customer_id : 'guest_' . md5( $email );

			if ( ! isset( $customer_data[ $key ] ) ) {
				$customer_data[ $key ] = array(
					'name'   => $order->get_formatted_billing_full_name(),
					'email'  => $email,
					'orders' => 0,
					'spent'  => 0,
				);
			}
			++$customer_data[ $key ]['orders'];
			$customer_data[ $key ]['spent'] += $order->get_total();
		}

		// Sort.
		uasort(
			$customer_data,
			function ( $a, $b ) use ( $by ) {
				return $b[ $by ] <=> $a[ $by ];
			}
		);

		$customer_data = array_slice( $customer_data, 0, $limit, true );

		$results = array();
		foreach ( $customer_data as $data ) {
			$results[] = array(
				'name'   => $data['name'],
				'email'  => $data['email'],
				'orders' => $data['orders'],
				'spent'  => wc_price( $data['spent'] ),
			);
		}

		return array(
			'success'   => true,
			'period'    => $period,
			'sort_by'   => $by,
			'customers' => $results,
		);
	}

	/**
	 * Get orders grouped by status.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_orders_by_status( $args ) {
		$period = sanitize_text_field( $args['period'] ?? 'month' );

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $this->get_period_start_date( $period );

		$statuses     = wc_get_order_statuses();
		$status_data  = array();
		$total_orders = 0;
		$total_sales  = 0;

		foreach ( array_keys( $statuses ) as $status ) {
			$orders = wc_get_orders(
				array(
					'limit'        => -1,
					'status'       => str_replace( 'wc-', '', $status ),
					'date_created' => $start_date . '...' . $end_date,
				)
			);

			$count = count( $orders );
			$sum   = 0;
			foreach ( $orders as $order ) {
				$sum += $order->get_total();
			}

			if ( $count > 0 ) {
				$status_data[] = array(
					'status' => $statuses[ $status ],
					'count'  => $count,
					'total'  => wc_price( $sum ),
				);
				$total_orders += $count;
				$total_sales  += $sum;
			}
		}

		return array(
			'success'      => true,
			'period'       => $period,
			'total_orders' => $total_orders,
			'total_sales'  => wc_price( $total_sales ),
			'by_status'    => $status_data,
		);
	}

	/**
	 * Get low stock products.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_low_stock_products( $args ) {
		$threshold            = intval( $args['threshold'] ?? 5 );
		$include_out_of_stock = isset( $args['include_out_of_stock'] ) ? (bool) $args['include_out_of_stock'] : true;

		$low_stock = wc_get_products(
			array(
				'limit'        => -1,
				'status'       => 'publish',
				'manage_stock' => true,
				'stock_status' => 'instock',
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_stock',
						'value'   => $threshold,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$results = array();
		foreach ( $low_stock as $product ) {
			$results[] = array(
				'id'       => $product->get_id(),
				'name'     => $product->get_name(),
				'sku'      => $product->get_sku(),
				'stock'    => $product->get_stock_quantity(),
				'status'   => 'Low Stock',
				'edit_url' => get_edit_post_link( $product->get_id(), 'raw' ),
			);
		}

		// Add out of stock if requested.
		if ( $include_out_of_stock ) {
			$out_of_stock = wc_get_products(
				array(
					'limit'        => -1,
					'status'       => 'publish',
					'stock_status' => 'outofstock',
				)
			);

			foreach ( $out_of_stock as $product ) {
				$results[] = array(
					'id'       => $product->get_id(),
					'name'     => $product->get_name(),
					'sku'      => $product->get_sku(),
					'stock'    => $product->get_stock_quantity(),
					'status'   => 'Out of Stock',
					'edit_url' => get_edit_post_link( $product->get_id(), 'raw' ),
				);
			}
		}

		return array(
			'success'   => true,
			'threshold' => $threshold,
			'count'     => count( $results ),
			'products'  => $results,
		);
	}

	/**
	 * Get category performance.
	 *
	 * @param array $args Arguments.
	 * @return array Result.
	 */
	public function tool_get_category_performance( $args ) {
		$period = sanitize_text_field( $args['period'] ?? 'month' );
		$limit  = intval( $args['limit'] ?? 10 );

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $this->get_period_start_date( $period );

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'completed', 'processing' ),
				'date_created' => $start_date . '...' . $end_date,
			)
		);

		$category_data = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				// @phpstan-ignore-next-line - WC_Order_Item_Product::get_product_id() exists.
				$product_id = $item->get_product_id();
				$product    = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$categories = $product->get_category_ids();
				foreach ( $categories as $cat_id ) {
					$term = get_term( $cat_id, 'product_cat' );
					if ( ! $term || is_wp_error( $term ) ) {
						continue;
					}

					if ( ! isset( $category_data[ $cat_id ] ) ) {
						$category_data[ $cat_id ] = array(
							'name'     => $term->name,
							'orders'   => 0,
							'quantity' => 0,
							'revenue'  => 0,
						);
					}

					++$category_data[ $cat_id ]['orders'];
					$category_data[ $cat_id ]['quantity'] += $item->get_quantity();
					$category_data[ $cat_id ]['revenue']  += $item->get_total();
				}
			}
		}

		// Sort by revenue.
		uasort(
			$category_data,
			function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);

		$category_data = array_slice( $category_data, 0, $limit, true );

		$results = array();
		foreach ( $category_data as $cat_id => $data ) {
			$results[] = array(
				'category_id' => $cat_id,
				'name'        => $data['name'],
				'orders'      => $data['orders'],
				'items_sold'  => $data['quantity'],
				'revenue'     => wc_price( $data['revenue'] ),
			);
		}

		return array(
			'success'    => true,
			'period'     => $period,
			'categories' => $results,
		);
	}

	/**
	 * Get start date for a period.
	 *
	 * @param string $period Period identifier.
	 * @return string Start date in Y-m-d format.
	 */
	private function get_period_start_date( $period ) {
		switch ( $period ) {
			case 'today':
				return gmdate( 'Y-m-d' );
			case 'week':
				return gmdate( 'Y-m-d', strtotime( '-7 days' ) );
			case 'month':
				return gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			case 'quarter':
				return gmdate( 'Y-m-d', strtotime( '-90 days' ) );
			case 'year':
				return gmdate( 'Y-m-d', strtotime( '-365 days' ) );
			case 'all':
			default:
				return '2000-01-01';
		}
	}
}
