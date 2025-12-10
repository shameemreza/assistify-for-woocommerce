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
				'description' => __( 'List products with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'products',
				'callback'    => array( $this, 'ability_products_list' ),
				'parameters'  => array(
					'category' => array(
						'type'        => 'string',
						'description' => __( 'Filter by category slug.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'status'   => array(
						'type'        => 'string',
						'description' => __( 'Filter by product status.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'publish',
					),
					'limit'    => array(
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
		$args = array(
			'limit'   => isset( $params['limit'] ) ? absint( $params['limit'] ) : 10,
			'status'  => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'publish',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( isset( $params['category'] ) ) {
			$args['category'] = array( sanitize_text_field( $params['category'] ) );
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
				'stock_status'   => $product->get_stock_status(),
				'stock_quantity' => $product->get_stock_quantity(),
			);
		}

		return array(
			'products' => $results,
			'count'    => count( $results ),
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
}

