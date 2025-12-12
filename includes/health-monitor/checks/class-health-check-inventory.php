<?php
/**
 * Health Check - Inventory
 *
 * Monitors product stock levels and inventory issues.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Inventory Health Check Class
 *
 * @since 1.0.0
 */
class Health_Check_Inventory {

	/**
	 * Initialize the check.
	 *
	 * @return void
	 */
	public static function init() {
		$monitor = \Assistify\Health_Monitor\Health_Monitor::get_instance();

		$monitor->register_check(
			'low_stock',
			array(
				'label'    => 'Low Stock Products',
				'category' => 'inventory',
				'callback' => array( __CLASS__, 'check_low_stock' ),
				'priority' => 1,
			)
		);

		$monitor->register_check(
			'out_of_stock',
			array(
				'label'    => 'Out of Stock Products',
				'category' => 'inventory',
				'callback' => array( __CLASS__, 'check_out_of_stock' ),
				'priority' => 2,
			)
		);
	}

	/**
	 * Check for low stock products.
	 *
	 * @return array Check results.
	 */
	public static function check_low_stock() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Get low stock threshold.
		$low_stock_threshold = absint( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );

		// Query low stock products.
		$args = array(
			'status'       => 'publish',
			'stock_status' => 'instock',
			'manage_stock' => true,
			'limit'        => 100,
		);

		$products           = wc_get_products( $args );
		$low_stock_products = array();

		foreach ( $products as $product ) {
			$stock = $product->get_stock_quantity();

			if ( null !== $stock && $stock <= $low_stock_threshold && $stock > 0 ) {
				$low_stock_products[] = array(
					'id'    => $product->get_id(),
					'name'  => $product->get_name(),
					'stock' => $stock,
					'sku'   => $product->get_sku(),
					'url'   => get_edit_post_link( $product->get_id(), 'raw' ),
				);
			}
		}

		if ( ! empty( $low_stock_products ) ) {
			$count    = count( $low_stock_products );
			$severity = $count >= 10 ? 'warning' : 'info';

			$result['issues'][] = array(
				'severity' => $severity,
				'title'    => 'Products Low in Stock',
				'message'  => sprintf( '%d product(s) have low stock levels.', $count ),
				'fix'      => 'Review inventory and restock products to avoid lost sales.',
				'data'     => array(
					'count'     => $count,
					'threshold' => $low_stock_threshold,
					'products'  => array_slice( $low_stock_products, 0, 10 ),
				),
			);

			// Send alert if significant.
			if ( $count >= 5 ) {
				self::maybe_send_low_stock_alert( $low_stock_products );
			}
		}

		return $result;
	}

	/**
	 * Check for out of stock products.
	 *
	 * @return array Check results.
	 */
	public static function check_out_of_stock() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Query out of stock products.
		$args = array(
			'status'       => 'publish',
			'stock_status' => 'outofstock',
			'limit'        => 100,
		);

		$products = wc_get_products( $args );

		if ( empty( $products ) ) {
			return $result;
		}

		$out_of_stock = array();

		foreach ( $products as $product ) {
			// Check if it has sales (popular product).
			$sales_count = $product->get_total_sales();

			$out_of_stock[] = array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'sku'   => $product->get_sku(),
				'sales' => $sales_count,
				'url'   => get_edit_post_link( $product->get_id(), 'raw' ),
			);
		}

		// Sort by sales (most popular first).
		usort(
			$out_of_stock,
			function ( $a, $b ) {
				return $b['sales'] - $a['sales'];
			}
		);

		$count       = count( $out_of_stock );
		$popular_out = array_filter(
			$out_of_stock,
			function ( $p ) {
				return $p['sales'] > 10;
			}
		);

		// Severity based on popular products out of stock.
		$severity = 'info';
		if ( count( $popular_out ) >= 3 ) {
			$severity = 'warning';
		}

		$result['issues'][] = array(
			'severity' => $severity,
			'title'    => 'Products Out of Stock',
			'message'  => sprintf(
				'%d product(s) are out of stock%s.',
				$count,
				count( $popular_out ) > 0 ? sprintf( ' (%d are popular sellers)', count( $popular_out ) ) : ''
			),
			'fix'      => 'Restock popular products to avoid losing sales.',
			'data'     => array(
				'count'         => $count,
				'popular_count' => count( $popular_out ),
				'products'      => array_slice( $out_of_stock, 0, 10 ),
			),
		);

		return $result;
	}

	/**
	 * Maybe send low stock alert.
	 *
	 * @param array $products Low stock products.
	 * @return void
	 */
	private static function maybe_send_low_stock_alert( $products ) {
		// Check if we've sent an alert recently.
		$last_alert = get_transient( 'assistify_low_stock_alert' );
		if ( false !== $last_alert ) {
			return;
		}

		$alerts = \Assistify\Health_Monitor\Health_Alerts::get_instance();
		$alerts->send_low_stock_alert( $products );

		// Set cooldown (24 hours between alerts).
		set_transient( 'assistify_low_stock_alert', time(), DAY_IN_SECONDS );
	}

	/**
	 * Get inventory summary for reporting.
	 *
	 * @return array Inventory summary.
	 */
	public static function get_inventory_summary() {
		global $wpdb;

		$low_stock_threshold = absint( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );

		// Count products by stock status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$counts = $wpdb->get_results(
			"SELECT 
				meta_value as stock_status, 
				COUNT(*) as count 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_stock_status'
			AND p.post_type = 'product'
			AND p.post_status = 'publish'
			GROUP BY meta_value"
		);

		$summary = array(
			'total'        => 0,
			'in_stock'     => 0,
			'out_of_stock' => 0,
			'on_backorder' => 0,
			'low_stock'    => 0,
		);

		foreach ( $counts as $row ) {
			$summary['total'] += $row->count;

			switch ( $row->stock_status ) {
				case 'instock':
					$summary['in_stock'] = $row->count;
					break;
				case 'outofstock':
					$summary['out_of_stock'] = $row->count;
					break;
				case 'onbackorder':
					$summary['on_backorder'] = $row->count;
					break;
			}
		}

		// Count low stock separately.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$low_stock = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_stock_status'
				WHERE pm.meta_key = '_stock'
				AND p.post_type = 'product'
				AND p.post_status = 'publish'
				AND pm2.meta_value = 'instock'
				AND CAST(pm.meta_value AS SIGNED) > 0
				AND CAST(pm.meta_value AS SIGNED) <= %d",
				$low_stock_threshold
			)
		);

		$summary['low_stock'] = (int) $low_stock;

		return $summary;
	}
}

// Initialize the check.
Health_Check_Inventory::init();
