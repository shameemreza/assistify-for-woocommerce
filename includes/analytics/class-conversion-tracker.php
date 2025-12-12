<?php
/**
 * Product Conversion Tracker
 *
 * Tracks product views and calculates conversion rates.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Conversion Tracker Class
 *
 * @since 1.0.0
 */
class Conversion_Tracker {

	/**
	 * Singleton instance.
	 *
	 * @var Conversion_Tracker|null
	 */
	private static $instance = null;

	/**
	 * Meta key for storing view counts.
	 *
	 * @var string
	 */
	const VIEW_COUNT_META = '_assistify_view_count';

	/**
	 * Meta key for storing daily views.
	 *
	 * @var string
	 */
	const DAILY_VIEWS_META = '_assistify_daily_views';

	/**
	 * Get singleton instance.
	 *
	 * @return Conversion_Tracker
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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Track product views on single product page.
		add_action( 'template_redirect', array( $this, 'track_product_view' ) );

		// AJAX endpoint for tracking (for cached pages).
		add_action( 'wp_ajax_assistify_track_view', array( $this, 'ajax_track_view' ) );
		add_action( 'wp_ajax_nopriv_assistify_track_view', array( $this, 'ajax_track_view' ) );

		// Enqueue tracking script on product pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );

		// Daily cleanup of old view data.
		add_action( 'assistify_daily_cleanup', array( $this, 'cleanup_old_views' ) );

		// Schedule daily cleanup if not scheduled.
		if ( ! wp_next_scheduled( 'assistify_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'assistify_daily_cleanup' );
		}
	}

	/**
	 * Track product view on page load.
	 *
	 * @return void
	 */
	public function track_product_view() {
		// Only track single product pages.
		if ( ! is_product() ) {
			return;
		}

		// Skip if bot or crawler.
		if ( $this->is_bot() ) {
			return;
		}

		// Skip if admin user (optional - can be toggled).
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}

		$this->record_view( $product_id );
	}

	/**
	 * AJAX handler for tracking views (for cached pages).
	 *
	 * @return void
	 */
	public function ajax_track_view() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'assistify_track_view' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( 'Invalid product' );
		}

		// Skip bots.
		if ( $this->is_bot() ) {
			wp_send_json_error( 'Bot detected' );
		}

		$this->record_view( $product_id );

		wp_send_json_success();
	}

	/**
	 * Record a product view.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function record_view( $product_id ) {
		// Increment total view count.
		$current_views = (int) get_post_meta( $product_id, self::VIEW_COUNT_META, true );
		update_post_meta( $product_id, self::VIEW_COUNT_META, $current_views + 1 );

		// Record daily view.
		$today       = gmdate( 'Y-m-d' );
		$daily_views = get_post_meta( $product_id, self::DAILY_VIEWS_META, true );

		if ( ! is_array( $daily_views ) ) {
			$daily_views = array();
		}

		if ( ! isset( $daily_views[ $today ] ) ) {
			$daily_views[ $today ] = 0;
		}

		++$daily_views[ $today ];
		update_post_meta( $product_id, self::DAILY_VIEWS_META, $daily_views );
	}

	/**
	 * Enqueue tracking script on product pages.
	 *
	 * @return void
	 */
	public function enqueue_tracking_script() {
		if ( ! is_product() ) {
			return;
		}

		// Skip for admin users.
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_enqueue_script(
			'assistify-conversion-tracker',
			ASSISTIFY_PLUGIN_URL . 'assets/js/frontend/conversion-tracker.js',
			array( 'jquery' ),
			ASSISTIFY_VERSION,
			true
		);

		wp_localize_script(
			'assistify-conversion-tracker',
			'assistifyTracker',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'assistify_track_view' ),
				'productId' => get_the_ID(),
			)
		);
	}

	/**
	 * Check if the request is from a bot.
	 *
	 * @return bool
	 */
	private function is_bot() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return true;
		}

		$user_agent   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		$bot_patterns = array(
			'bot',
			'crawl',
			'spider',
			'slurp',
			'googlebot',
			'bingbot',
			'yandex',
			'baidu',
			'facebookexternalhit',
			'twitterbot',
			'rogerbot',
			'linkedinbot',
			'embedly',
			'quora',
			'pinterest',
			'redditbot',
			'applebot',
			'semrushbot',
			'ahrefsbot',
			'mj12bot',
			'dotbot',
		);

		$user_agent_lower = strtolower( $user_agent );
		foreach ( $bot_patterns as $pattern ) {
			if ( strpos( $user_agent_lower, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cleanup old daily view data (keep last 90 days).
	 *
	 * @return void
	 */
	public function cleanup_old_views() {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );

		// Get all products with daily views.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$products = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::DAILY_VIEWS_META
			)
		);

		foreach ( $products as $product_id ) {
			$daily_views = get_post_meta( $product_id, self::DAILY_VIEWS_META, true );

			if ( ! is_array( $daily_views ) ) {
				continue;
			}

			$updated = false;
			foreach ( array_keys( $daily_views ) as $date ) {
				if ( $date < $cutoff_date ) {
					unset( $daily_views[ $date ] );
					$updated = true;
				}
			}

			if ( $updated ) {
				update_post_meta( $product_id, self::DAILY_VIEWS_META, $daily_views );
			}
		}
	}

	/**
	 * Get product view count.
	 *
	 * @param int $product_id Product ID.
	 * @return int View count.
	 */
	public function get_view_count( $product_id ) {
		return (int) get_post_meta( $product_id, self::VIEW_COUNT_META, true );
	}

	/**
	 * Get views for a specific period.
	 *
	 * @param int $product_id Product ID.
	 * @param int $days       Number of days.
	 * @return int View count for period.
	 */
	public function get_views_for_period( $product_id, $days = 30 ) {
		$daily_views = get_post_meta( $product_id, self::DAILY_VIEWS_META, true );

		if ( ! is_array( $daily_views ) ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$count  = 0;

		foreach ( $daily_views as $date => $views ) {
			if ( $date >= $cutoff ) {
				$count += (int) $views;
			}
		}

		return $count;
	}

	/**
	 * Get product sales count for a period.
	 *
	 * @param int $product_id Product ID.
	 * @param int $days       Number of days.
	 * @return int Sales count.
	 */
	public function get_sales_for_period( $product_id, $days = 30 ) {
		global $wpdb;

		$date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sales = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(oim.meta_value), 0)
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim 
					ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_qty'
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_product 
					ON oi.order_item_id = oim_product.order_item_id AND oim_product.meta_key = '_product_id'
				INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
				WHERE oi.order_item_type = 'line_item'
				AND p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing')
				AND p.post_date >= %s
				AND oim_product.meta_value = %d",
				$date_from . ' 00:00:00',
				$product_id
			)
		);

		return (int) $sales;
	}

	/**
	 * Calculate conversion rate for a product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $days       Number of days.
	 * @return float Conversion rate (0-100).
	 */
	public function get_conversion_rate( $product_id, $days = 30 ) {
		$views = $this->get_views_for_period( $product_id, $days );
		$sales = $this->get_sales_for_period( $product_id, $days );

		if ( 0 === $views ) {
			return 0;
		}

		return round( ( $sales / $views ) * 100, 2 );
	}

	/**
	 * Get high interest, low conversion products.
	 *
	 * Products with many views but few sales.
	 *
	 * @param int $min_views      Minimum views to consider.
	 * @param int $max_conversion Maximum conversion rate.
	 * @param int $days           Number of days.
	 * @param int $limit          Number of products to return.
	 * @return array Products with conversion data.
	 */
	public function get_low_conversion_products( $min_views = 50, $max_conversion = 2, $days = 30, $limit = 10 ) {
		global $wpdb;

		// Get all published products.
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$results = array();

		foreach ( $products as $product_id ) {
			$views      = $this->get_views_for_period( $product_id, $days );
			$sales      = $this->get_sales_for_period( $product_id, $days );
			$conversion = $this->get_conversion_rate( $product_id, $days );

			// Filter by criteria.
			if ( $views >= $min_views && $conversion <= $max_conversion ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$results[] = array(
					'id'              => $product_id,
					'name'            => $product->get_name(),
					'views'           => $views,
					'sales'           => $sales,
					'conversion_rate' => $conversion,
					'price'           => $product->get_price(),
					'url'             => get_permalink( $product_id ),
					'edit_url'        => get_edit_post_link( $product_id, 'raw' ),
				);
			}
		}

		// Sort by views descending (most views first).
		usort(
			$results,
			function ( $a, $b ) {
				return $b['views'] - $a['views'];
			}
		);

		return array_slice( $results, 0, $limit );
	}

	/**
	 * Get top viewed products.
	 *
	 * @param int $days  Number of days.
	 * @param int $limit Number of products.
	 * @return array Products with view data.
	 */
	public function get_top_viewed_products( $days = 30, $limit = 10 ) {
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$results = array();

		foreach ( $products as $product_id ) {
			$views = $this->get_views_for_period( $product_id, $days );

			if ( $views > 0 ) {
				$product    = wc_get_product( $product_id );
				$sales      = $this->get_sales_for_period( $product_id, $days );
				$conversion = $this->get_conversion_rate( $product_id, $days );

				$results[] = array(
					'id'              => $product_id,
					'name'            => $product ? $product->get_name() : 'Unknown',
					'views'           => $views,
					'sales'           => $sales,
					'conversion_rate' => $conversion,
				);
			}
		}

		// Sort by views descending.
		usort(
			$results,
			function ( $a, $b ) {
				return $b['views'] - $a['views'];
			}
		);

		return array_slice( $results, 0, $limit );
	}

	/**
	 * Get conversion summary for the store.
	 *
	 * @param int $days Number of days.
	 * @return array Conversion summary.
	 */
	public function get_conversion_summary( $days = 30 ) {
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$total_views         = 0;
		$total_sales         = 0;
		$products_with_views = 0;
		$conversion_rates    = array();

		foreach ( $products as $product_id ) {
			$views = $this->get_views_for_period( $product_id, $days );
			$sales = $this->get_sales_for_period( $product_id, $days );

			$total_views += $views;
			$total_sales += $sales;

			if ( $views > 0 ) {
				++$products_with_views;
				$conversion_rates[] = $this->get_conversion_rate( $product_id, $days );
			}
		}

		$avg_conversion = count( $conversion_rates ) > 0
			? round( array_sum( $conversion_rates ) / count( $conversion_rates ), 2 )
			: 0;

		$overall_conversion = $total_views > 0
			? round( ( $total_sales / $total_views ) * 100, 2 )
			: 0;

		return array(
			'total_views'         => $total_views,
			'total_sales'         => $total_sales,
			'overall_conversion'  => $overall_conversion,
			'average_conversion'  => $avg_conversion,
			'products_with_views' => $products_with_views,
			'period_days'         => $days,
		);
	}
}
