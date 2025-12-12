<?php
/**
 * Analytics Module - WooCommerce Data Integration
 *
 * Integrates with WooCommerce analytics to provide revenue trends,
 * product performance, customer insights, and business metrics.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics Class
 *
 * @since 1.0.0
 */
class Analytics {

	/**
	 * Singleton instance.
	 *
	 * @var Analytics|null
	 */
	private static $instance = null;

	/**
	 * Cache expiry in seconds.
	 *
	 * @var int
	 */
	private $cache_expiry = 300; // 5 minutes.

	/**
	 * Get singleton instance.
	 *
	 * @return Analytics
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Nothing to initialize.
	}

	/**
	 * Get revenue trends for specified periods.
	 *
	 * @param string $period Period type: 'daily', 'weekly', 'monthly'.
	 * @param int    $count  Number of periods to retrieve.
	 * @return array Revenue data.
	 */
	public function get_revenue_trends( $period = 'daily', $count = 7 ) {
		$cache_key = 'assistify_revenue_' . $period . '_' . $count;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = array();

		switch ( $period ) {
			case 'daily':
				$data = $this->get_daily_revenue( $count );
				break;
			case 'weekly':
				$data = $this->get_weekly_revenue( $count );
				break;
			case 'monthly':
				$data = $this->get_monthly_revenue( $count );
				break;
		}

		set_transient( $cache_key, $data, $this->cache_expiry );
		return $data;
	}

	/**
	 * Get daily revenue data.
	 *
	 * @param int $days Number of days.
	 * @return array Daily revenue.
	 */
	private function get_daily_revenue( $days = 7 ) {
		$results = array();
		$today   = current_time( 'Y-m-d' );

		for ( $i = 0; $i < $days; $i++ ) {
			$date      = gmdate( 'Y-m-d', strtotime( "-{$i} days", strtotime( $today ) ) );
			$date_next = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $date ) ) );

			// Use WooCommerce functions for HPOS compatibility.
			$orders = wc_get_orders(
				array(
					'status'       => array( 'completed', 'processing' ),
					'date_created' => $date . '...' . $date_next,
					'limit'        => -1,
				)
			);

			$revenue     = 0;
			$order_count = count( $orders );

			foreach ( $orders as $order ) {
				$revenue += (float) $order->get_total();
			}

			$results[] = array(
				'date'        => $date,
				'label'       => gmdate( 'M j', strtotime( $date ) ),
				'revenue'     => $revenue,
				'order_count' => $order_count,
			);
		}

		return array_reverse( $results );
	}

	/**
	 * Get weekly revenue data.
	 *
	 * @param int $weeks Number of weeks.
	 * @return array Weekly revenue.
	 */
	private function get_weekly_revenue( $weeks = 4 ) {
		$results = array();

		for ( $i = 0; $i < $weeks; $i++ ) {
			$week_start = gmdate( 'Y-m-d', strtotime( "monday -{$i} weeks" ) );
			$week_end   = gmdate( 'Y-m-d', strtotime( '+7 days', strtotime( $week_start ) ) );

			// Use WooCommerce functions for HPOS compatibility.
			$orders = wc_get_orders(
				array(
					'status'       => array( 'completed', 'processing' ),
					'date_created' => $week_start . '...' . $week_end,
					'limit'        => -1,
				)
			);

			$revenue     = 0;
			$order_count = count( $orders );

			foreach ( $orders as $order ) {
				$revenue += (float) $order->get_total();
			}

			$results[] = array(
				'start'       => $week_start,
				'end'         => gmdate( 'Y-m-d', strtotime( '+6 days', strtotime( $week_start ) ) ),
				'label'       => 'Week ' . gmdate( 'W', strtotime( $week_start ) ),
				'revenue'     => $revenue,
				'order_count' => $order_count,
			);
		}

		return array_reverse( $results );
	}

	/**
	 * Get monthly revenue data.
	 *
	 * @param int $months Number of months.
	 * @return array Monthly revenue.
	 */
	private function get_monthly_revenue( $months = 6 ) {
		$results = array();

		for ( $i = 0; $i < $months; $i++ ) {
			$month_start = gmdate( 'Y-m-01', strtotime( "-{$i} months" ) );
			$month_end   = gmdate( 'Y-m-t', strtotime( $month_start ) ) . ' 23:59:59';

			// Use WooCommerce functions for HPOS compatibility.
			$orders = wc_get_orders(
				array(
					'status'       => array( 'completed', 'processing' ),
					'date_created' => $month_start . '...' . $month_end,
					'limit'        => -1,
				)
			);

			$revenue     = 0;
			$order_count = count( $orders );

			foreach ( $orders as $order ) {
				$revenue += (float) $order->get_total();
			}

			$results[] = array(
				'month'       => $month_start,
				'label'       => gmdate( 'M Y', strtotime( $month_start ) ),
				'revenue'     => $revenue,
				'order_count' => $order_count,
			);
		}

		return array_reverse( $results );
	}

	/**
	 * Get top selling products.
	 *
	 * @param int    $limit  Number of products.
	 * @param string $period Period: 'week', 'month', 'year', 'all'.
	 * @return array Top products.
	 */
	public function get_top_products( $limit = 10, $period = 'month' ) {
		global $wpdb;

		$cache_key = 'assistify_top_products_' . $period . '_' . $limit;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$date_start = '';
		switch ( $period ) {
			case 'week':
				$date_start = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				break;
			case 'month':
				$date_start = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				break;
			case 'year':
				$date_start = gmdate( 'Y-m-d', strtotime( '-1 year' ) );
				break;
		}

		// Build query based on whether we have a date filter.
		$base_query = "SELECT 
				oim_product.meta_value as product_id,
				oi.order_item_name as product_name,
				SUM(oim_qty.meta_value) as quantity_sold,
				SUM(oim_total.meta_value) as total_revenue,
				COUNT(DISTINCT p.ID) as order_count
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_product 
				ON oi.order_item_id = oim_product.order_item_id AND oim_product.meta_key = '_product_id'
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty 
				ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total 
				ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
			INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
			WHERE oi.order_item_type = 'line_item'
			AND p.post_type = 'shop_order'
			AND p.post_status IN ('wc-completed', 'wc-processing')";

		if ( ! empty( $date_start ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare( $base_query . ' AND p.post_date >= %s GROUP BY oim_product.meta_value, oi.order_item_name ORDER BY total_revenue DESC LIMIT %d', $date_start, $limit )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare( $base_query . ' GROUP BY oim_product.meta_value, oi.order_item_name ORDER BY total_revenue DESC LIMIT %d', $limit )
			);
		}

		$products = array();
		foreach ( $results as $row ) {
			$product = wc_get_product( $row->product_id );

			$products[] = array(
				'id'            => (int) $row->product_id,
				'name'          => $row->product_name,
				'quantity_sold' => (int) $row->quantity_sold,
				'revenue'       => (float) $row->total_revenue,
				'order_count'   => (int) $row->order_count,
				'in_stock'      => $product ? $product->is_in_stock() : null,
				'stock_qty'     => $product ? $product->get_stock_quantity() : null,
			);
		}

		set_transient( $cache_key, $products, $this->cache_expiry );
		return $products;
	}

	/**
	 * Get customer retention metrics.
	 *
	 * @return array Retention data.
	 */
	public function get_customer_retention() {
		global $wpdb;

		$cache_key = 'assistify_customer_retention';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get total customers with orders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_customers = $wpdb->get_var(
			"SELECT COUNT(DISTINCT meta_email.meta_value)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} meta_email ON p.ID = meta_email.post_id AND meta_email.meta_key = '_billing_email'
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ('wc-completed', 'wc-processing')"
		);

		// Get repeat customers (more than 1 order).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$repeat_customers = $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT meta_email.meta_value as email, COUNT(*) as order_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} meta_email ON p.ID = meta_email.post_id AND meta_email.meta_key = '_billing_email'
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing')
				GROUP BY meta_email.meta_value
				HAVING order_count > 1
			) as repeat_orders"
		);

		// Get new customers this month.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$new_this_month = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT meta_email.meta_value)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} meta_email ON p.ID = meta_email.post_id AND meta_email.meta_key = '_billing_email'
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing')
				AND p.post_date >= %s
				AND meta_email.meta_value NOT IN (
					SELECT DISTINCT meta_email2.meta_value
					FROM {$wpdb->posts} p2
					INNER JOIN {$wpdb->postmeta} meta_email2 ON p2.ID = meta_email2.post_id AND meta_email2.meta_key = '_billing_email'
					WHERE p2.post_type = 'shop_order'
					AND p2.post_status IN ('wc-completed', 'wc-processing')
					AND p2.post_date < %s
				)",
				gmdate( 'Y-m-01' ),
				gmdate( 'Y-m-01' )
			)
		);

		$total_customers  = (int) $total_customers;
		$repeat_customers = (int) $repeat_customers;
		$retention_rate   = $total_customers > 0 ? round( ( $repeat_customers / $total_customers ) * 100, 1 ) : 0;

		$data = array(
			'total_customers'  => $total_customers,
			'repeat_customers' => $repeat_customers,
			'one_time_buyers'  => $total_customers - $repeat_customers,
			'retention_rate'   => $retention_rate,
			'new_this_month'   => (int) $new_this_month,
		);

		set_transient( $cache_key, $data, $this->cache_expiry );
		return $data;
	}

	/**
	 * Get average order value trends.
	 *
	 * @param int $months Number of months.
	 * @return array AOV trends.
	 */
	public function get_aov_trends( $months = 6 ) {
		$cache_key = 'assistify_aov_trends_' . $months;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$results = array();

		for ( $i = 0; $i < $months; $i++ ) {
			$month_start = gmdate( 'Y-m-01', strtotime( "-{$i} months" ) );
			$month_end   = gmdate( 'Y-m-t', strtotime( $month_start ) ) . ' 23:59:59';

			// Use WooCommerce functions for HPOS compatibility.
			$orders = wc_get_orders(
				array(
					'status'       => array( 'completed', 'processing' ),
					'date_created' => $month_start . '...' . $month_end,
					'limit'        => -1,
				)
			);

			$revenue     = 0;
			$order_count = count( $orders );

			foreach ( $orders as $order ) {
				$revenue += (float) $order->get_total();
			}

			$aov = $order_count > 0 ? round( $revenue / $order_count, 2 ) : 0;

			$results[] = array(
				'month'       => $month_start,
				'label'       => gmdate( 'M Y', strtotime( $month_start ) ),
				'aov'         => $aov,
				'order_count' => $order_count,
				'revenue'     => $revenue,
			);
		}

		$data = array_reverse( $results );
		set_transient( $cache_key, $data, $this->cache_expiry );
		return $data;
	}

	/**
	 * Get refund patterns.
	 *
	 * @param int $months Number of months.
	 * @return array Refund data.
	 */
	public function get_refund_patterns( $months = 6 ) {
		global $wpdb;

		$cache_key = 'assistify_refund_patterns_' . $months;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$results = array();

		for ( $i = 0; $i < $months; $i++ ) {
			$month_start = gmdate( 'Y-m-01', strtotime( "-{$i} months" ) );
			$month_end   = gmdate( 'Y-m-t', strtotime( $month_start ) );

			// Get refunds for this month.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$refund_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
						COUNT(*) as refund_count,
						COALESCE(SUM(ABS(meta_total.meta_value)), 0) as refund_total
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} meta_total ON p.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
					WHERE p.post_type = 'shop_order_refund'
					AND p.post_date >= %s
					AND p.post_date <= %s",
					$month_start . ' 00:00:00',
					$month_end . ' 23:59:59'
				)
			);

			// Get total orders for this month.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$order_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
						COUNT(*) as order_count,
						COALESCE(SUM(meta_total.meta_value), 0) as order_total
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} meta_total ON p.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
					WHERE p.post_type = 'shop_order'
					AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-refunded')
					AND p.post_date >= %s
					AND p.post_date <= %s",
					$month_start . ' 00:00:00',
					$month_end . ' 23:59:59'
				)
			);

			$refund_count = (int) ( $refund_data->refund_count ?? 0 );
			$order_count  = (int) ( $order_data->order_count ?? 0 );
			$refund_rate  = $order_count > 0 ? round( ( $refund_count / $order_count ) * 100, 1 ) : 0;

			$results[] = array(
				'month'        => $month_start,
				'label'        => gmdate( 'M Y', strtotime( $month_start ) ),
				'refund_count' => $refund_count,
				'refund_total' => (float) ( $refund_data->refund_total ?? 0 ),
				'order_count'  => $order_count,
				'refund_rate'  => $refund_rate,
			);
		}

		$data = array_reverse( $results );
		set_transient( $cache_key, $data, $this->cache_expiry );
		return $data;
	}

	/**
	 * Get comprehensive analytics summary.
	 *
	 * @return array Analytics summary.
	 */
	public function get_summary() {
		$daily_revenue   = $this->get_revenue_trends( 'daily', 7 );
		$monthly_revenue = $this->get_revenue_trends( 'monthly', 2 );
		$top_products    = $this->get_top_products( 5, 'month' );
		$retention       = $this->get_customer_retention();
		$aov             = $this->get_aov_trends( 2 );
		$refunds         = $this->get_refund_patterns( 2 );

		// Calculate trends.
		$today_revenue     = $daily_revenue[ count( $daily_revenue ) - 1 ]['revenue'] ?? 0;
		$yesterday_revenue = $daily_revenue[ count( $daily_revenue ) - 2 ]['revenue'] ?? 0;
		$daily_change      = $yesterday_revenue > 0 ? round( ( ( $today_revenue - $yesterday_revenue ) / $yesterday_revenue ) * 100, 1 ) : 0;

		$this_month_revenue = $monthly_revenue[ count( $monthly_revenue ) - 1 ]['revenue'] ?? 0;
		$last_month_revenue = $monthly_revenue[ count( $monthly_revenue ) - 2 ]['revenue'] ?? 0;
		$monthly_change     = $last_month_revenue > 0 ? round( ( ( $this_month_revenue - $last_month_revenue ) / $last_month_revenue ) * 100, 1 ) : 0;

		$current_aov  = $aov[ count( $aov ) - 1 ]['aov'] ?? 0;
		$previous_aov = $aov[ count( $aov ) - 2 ]['aov'] ?? 0;
		$aov_change   = $previous_aov > 0 ? round( ( ( $current_aov - $previous_aov ) / $previous_aov ) * 100, 1 ) : 0;

		return array(
			'today'            => array(
				'revenue'      => $today_revenue,
				'orders'       => $daily_revenue[ count( $daily_revenue ) - 1 ]['order_count'] ?? 0,
				'change'       => $daily_change,
				'change_label' => $daily_change >= 0 ? 'up' : 'down',
			),
			'this_month'       => array(
				'revenue'      => $this_month_revenue,
				'orders'       => $monthly_revenue[ count( $monthly_revenue ) - 1 ]['order_count'] ?? 0,
				'change'       => $monthly_change,
				'change_label' => $monthly_change >= 0 ? 'up' : 'down',
			),
			'aov'              => array(
				'current'      => $current_aov,
				'change'       => $aov_change,
				'change_label' => $aov_change >= 0 ? 'up' : 'down',
			),
			'retention_rate'   => $retention['retention_rate'],
			'new_customers'    => $retention['new_this_month'],
			'repeat_customers' => $retention['repeat_customers'],
			'refund_rate'      => $refunds[ count( $refunds ) - 1 ]['refund_rate'] ?? 0,
			'top_products'     => array_slice( $top_products, 0, 3 ),
		);
	}

	/**
	 * Clear all analytics caches.
	 *
	 * @return void
	 */
	public function clear_cache() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_assistify_%' OR option_name LIKE '_transient_timeout_assistify_%'"
		);
	}
}
