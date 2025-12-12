<?php
/**
 * Behavior Tracker
 *
 * Tracks customer behavior: add-to-cart, checkout abandonment, searches.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Behavior Tracker Class
 *
 * @since 1.0.0
 */
class Behavior_Tracker {

	/**
	 * Singleton instance.
	 *
	 * @var Behavior_Tracker|null
	 */
	private static $instance = null;

	/**
	 * Option key for search queries.
	 *
	 * @var string
	 */
	const SEARCH_OPTION = 'assistify_search_queries';

	/**
	 * Option key for cart events.
	 *
	 * @var string
	 */
	const CART_EVENTS_OPTION = 'assistify_cart_events';

	/**
	 * Option key for checkout starts.
	 *
	 * @var string
	 */
	const CHECKOUT_STARTS_OPTION = 'assistify_checkout_starts';

	/**
	 * Get singleton instance.
	 *
	 * @return Behavior_Tracker
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
		// Track add to cart events.
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart' ), 10, 6 );

		// Track checkout starts.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'track_checkout_start' ) );

		// Track search queries.
		add_action( 'pre_get_posts', array( $this, 'track_search_query' ) );

		// Track completed orders (to calculate abandonment).
		add_action( 'woocommerce_thankyou', array( $this, 'track_order_complete' ) );

		// Daily cleanup.
		add_action( 'assistify_daily_cleanup', array( $this, 'cleanup_old_data' ) );
	}

	/**
	 * Track add to cart event.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation ID.
	 * @param array  $variation     Variation data.
	 * @param array  $cart_item_data Cart item data.
	 * @return void
	 */
	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Skip admin and bots.
		if ( is_admin() || $this->is_bot() ) {
			return;
		}

		$today  = gmdate( 'Y-m-d' );
		$events = get_option( self::CART_EVENTS_OPTION, array() );

		if ( ! isset( $events[ $today ] ) ) {
			$events[ $today ] = array(
				'add_to_cart_count' => 0,
				'products'          => array(),
			);
		}

		++$events[ $today ]['add_to_cart_count'];

		// Track product frequency.
		$pid = $variation_id > 0 ? $variation_id : $product_id;
		if ( ! isset( $events[ $today ]['products'][ $pid ] ) ) {
			$events[ $today ]['products'][ $pid ] = 0;
		}
		++$events[ $today ]['products'][ $pid ];

		update_option( self::CART_EVENTS_OPTION, $events, false );
	}

	/**
	 * Track checkout start.
	 *
	 * @return void
	 */
	public function track_checkout_start() {
		// Skip admin and bots.
		if ( is_admin() || $this->is_bot() ) {
			return;
		}

		// Skip if cart is empty.
		if ( WC()->cart && WC()->cart->is_empty() ) {
			return;
		}

		$today  = gmdate( 'Y-m-d' );
		$starts = get_option( self::CHECKOUT_STARTS_OPTION, array() );

		if ( ! isset( $starts[ $today ] ) ) {
			$starts[ $today ] = array(
				'count'      => 0,
				'cart_value' => 0,
			);
		}

		// Use session to avoid counting same user multiple times.
		$session_key = 'assistify_checkout_tracked';
		if ( WC()->session && ! WC()->session->get( $session_key ) ) {
			++$starts[ $today ]['count'];
			$starts[ $today ]['cart_value'] += WC()->cart->get_cart_contents_total();

			WC()->session->set( $session_key, true );
			update_option( self::CHECKOUT_STARTS_OPTION, $starts, false );
		}
	}

	/**
	 * Track order completion (clears checkout tracking).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function track_order_complete( $order_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Clear session flag so next checkout is tracked.
		if ( WC()->session ) {
			WC()->session->set( 'assistify_checkout_tracked', null );
		}
	}

	/**
	 * Track search queries.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	public function track_search_query( $query ) {
		// Only track main search queries on frontend.
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		// Only track product searches.
		$post_type = $query->get( 'post_type' );
		if ( 'product' !== $post_type && ! ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
			// Check if on shop page.
			if ( ! is_shop() && ! is_product_taxonomy() ) {
				return;
			}
		}

		// Skip bots.
		if ( $this->is_bot() ) {
			return;
		}

		$search_term = $query->get( 's' );
		if ( empty( $search_term ) ) {
			return;
		}

		$search_term = sanitize_text_field( strtolower( $search_term ) );
		$today       = gmdate( 'Y-m-d' );
		$searches    = get_option( self::SEARCH_OPTION, array() );

		if ( ! isset( $searches[ $today ] ) ) {
			$searches[ $today ] = array();
		}

		if ( ! isset( $searches[ $today ][ $search_term ] ) ) {
			$searches[ $today ][ $search_term ] = 0;
		}

		++$searches[ $today ][ $search_term ];

		update_option( self::SEARCH_OPTION, $searches, false );
	}

	/**
	 * Check if request is from a bot.
	 *
	 * @return bool
	 */
	private function is_bot() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return true;
		}

		$user_agent    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		$bot_patterns  = array( 'bot', 'crawl', 'spider', 'slurp', 'googlebot' );
		$user_agent_lc = strtolower( $user_agent );

		foreach ( $bot_patterns as $pattern ) {
			if ( strpos( $user_agent_lc, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cleanup old behavior data (keep 90 days).
	 *
	 * @return void
	 */
	public function cleanup_old_data() {
		$cutoff = gmdate( 'Y-m-d', strtotime( '-90 days' ) );

		// Clean search queries.
		$searches = get_option( self::SEARCH_OPTION, array() );
		foreach ( array_keys( $searches ) as $date ) {
			if ( $date < $cutoff ) {
				unset( $searches[ $date ] );
			}
		}
		update_option( self::SEARCH_OPTION, $searches, false );

		// Clean cart events.
		$events = get_option( self::CART_EVENTS_OPTION, array() );
		foreach ( array_keys( $events ) as $date ) {
			if ( $date < $cutoff ) {
				unset( $events[ $date ] );
			}
		}
		update_option( self::CART_EVENTS_OPTION, $events, false );

		// Clean checkout starts.
		$starts = get_option( self::CHECKOUT_STARTS_OPTION, array() );
		foreach ( array_keys( $starts ) as $date ) {
			if ( $date < $cutoff ) {
				unset( $starts[ $date ] );
			}
		}
		update_option( self::CHECKOUT_STARTS_OPTION, $starts, false );
	}

	/**
	 * Get add-to-cart statistics.
	 *
	 * @param int $days Number of days.
	 * @return array Cart stats.
	 */
	public function get_cart_stats( $days = 30 ) {
		$events = get_option( self::CART_EVENTS_OPTION, array() );
		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$total_add_to_cart = 0;
		$product_counts    = array();

		foreach ( $events as $date => $data ) {
			if ( $date >= $cutoff ) {
				$total_add_to_cart += $data['add_to_cart_count'] ?? 0;

				foreach ( ( $data['products'] ?? array() ) as $product_id => $count ) {
					if ( ! isset( $product_counts[ $product_id ] ) ) {
						$product_counts[ $product_id ] = 0;
					}
					$product_counts[ $product_id ] += $count;
				}
			}
		}

		// Sort products by count.
		arsort( $product_counts );

		// Get top products.
		$top_products = array();
		foreach ( array_slice( $product_counts, 0, 10, true ) as $product_id => $count ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$top_products[] = array(
					'id'    => $product_id,
					'name'  => $product->get_name(),
					'count' => $count,
				);
			}
		}

		return array(
			'total_add_to_cart' => $total_add_to_cart,
			'top_products'      => $top_products,
			'period_days'       => $days,
		);
	}

	/**
	 * Get checkout abandonment rate.
	 *
	 * @param int $days Number of days.
	 * @return array Abandonment data.
	 */
	public function get_abandonment_rate( $days = 30 ) {
		$starts = get_option( self::CHECKOUT_STARTS_OPTION, array() );
		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$total_starts     = 0;
		$total_cart_value = 0;

		foreach ( $starts as $date => $data ) {
			if ( $date >= $cutoff ) {
				$total_starts     += $data['count'] ?? 0;
				$total_cart_value += $data['cart_value'] ?? 0;
			}
		}

		// Get completed orders in same period.
		$orders = wc_get_orders(
			array(
				'status'       => array( 'completed', 'processing' ),
				'date_created' => '>' . strtotime( "-{$days} days" ),
				'limit'        => -1,
				'return'       => 'ids',
			)
		);

		$completed_orders = count( $orders );

		// Calculate abandonment rate.
		$abandonment_rate = $total_starts > 0
			? round( ( ( $total_starts - $completed_orders ) / $total_starts ) * 100, 1 )
			: 0;

		// Estimate lost revenue.
		$avg_cart_value = $total_starts > 0 ? $total_cart_value / $total_starts : 0;
		$abandoned      = max( 0, $total_starts - $completed_orders );
		$lost_revenue   = $abandoned * $avg_cart_value;

		return array(
			'checkout_starts'  => $total_starts,
			'completed_orders' => $completed_orders,
			'abandoned'        => $abandoned,
			'abandonment_rate' => $abandonment_rate,
			'avg_cart_value'   => round( $avg_cart_value, 2 ),
			'estimated_lost'   => round( $lost_revenue, 2 ),
			'period_days'      => $days,
		);
	}

	/**
	 * Get search query statistics.
	 *
	 * @param int $days Number of days.
	 * @return array Search stats.
	 */
	public function get_search_stats( $days = 30 ) {
		$searches = get_option( self::SEARCH_OPTION, array() );
		$cutoff   = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$all_queries    = array();
		$total_searches = 0;

		foreach ( $searches as $date => $queries ) {
			if ( $date >= $cutoff ) {
				foreach ( $queries as $query => $count ) {
					$total_searches += $count;

					if ( ! isset( $all_queries[ $query ] ) ) {
						$all_queries[ $query ] = 0;
					}
					$all_queries[ $query ] += $count;
				}
			}
		}

		// Sort by count.
		arsort( $all_queries );

		// Get top searches.
		$top_searches = array();
		foreach ( array_slice( $all_queries, 0, 20, true ) as $query => $count ) {
			// Check if search has results.
			$products = wc_get_products(
				array(
					's'      => $query,
					'limit'  => 1,
					'return' => 'ids',
				)
			);

			$top_searches[] = array(
				'query'       => $query,
				'count'       => $count,
				'has_results' => ! empty( $products ),
			);
		}

		// Find searches with no results.
		$no_results = array_filter(
			$top_searches,
			function ( $search ) {
				return ! $search['has_results'];
			}
		);

		return array(
			'total_searches' => $total_searches,
			'unique_queries' => count( $all_queries ),
			'top_searches'   => array_slice( $top_searches, 0, 10 ),
			'no_results'     => array_values( array_slice( $no_results, 0, 5 ) ),
			'period_days'    => $days,
		);
	}

	/**
	 * Get behavior summary.
	 *
	 * @param int $days Number of days.
	 * @return array Behavior summary.
	 */
	public function get_behavior_summary( $days = 30 ) {
		$cart_stats   = $this->get_cart_stats( $days );
		$abandonment  = $this->get_abandonment_rate( $days );
		$search_stats = $this->get_search_stats( $days );

		return array(
			'add_to_cart'      => $cart_stats['total_add_to_cart'],
			'top_cart_product' => ! empty( $cart_stats['top_products'] ) ? $cart_stats['top_products'][0] : null,
			'abandonment_rate' => $abandonment['abandonment_rate'],
			'estimated_lost'   => $abandonment['estimated_lost'],
			'total_searches'   => $search_stats['total_searches'],
			'top_search'       => ! empty( $search_stats['top_searches'] ) ? $search_stats['top_searches'][0]['query'] : null,
			'no_results_count' => count( $search_stats['no_results'] ),
			'period_days'      => $days,
		);
	}
}
