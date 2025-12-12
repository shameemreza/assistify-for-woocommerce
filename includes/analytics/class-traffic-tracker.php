<?php
/**
 * Traffic Source Tracker
 *
 * Tracks UTM parameters and referrer sources for orders.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Traffic Tracker Class
 *
 * @since 1.0.0
 */
class Traffic_Tracker {

	/**
	 * Singleton instance.
	 *
	 * @var Traffic_Tracker|null
	 */
	private static $instance = null;

	/**
	 * Session key for storing traffic data.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'assistify_traffic_source';

	/**
	 * Order meta key for traffic source.
	 *
	 * @var string
	 */
	const ORDER_META_KEY = '_assistify_traffic_source';

	/**
	 * UTM parameters to track.
	 *
	 * @var array
	 */
	private $utm_params = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
	);

	/**
	 * Get singleton instance.
	 *
	 * @return Traffic_Tracker
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
		// Capture UTM parameters and referrer on page load.
		add_action( 'wp', array( $this, 'capture_traffic_source' ) );

		// Save traffic source to order.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_to_order' ) );

		// Also capture via AJAX for cached pages.
		add_action( 'wp_ajax_assistify_capture_traffic', array( $this, 'ajax_capture_traffic' ) );
		add_action( 'wp_ajax_nopriv_assistify_capture_traffic', array( $this, 'ajax_capture_traffic' ) );

		// Enqueue tracking script.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );

		// Display traffic source in order admin.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_in_order_admin' ) );
	}

	/**
	 * Capture traffic source from URL and referrer.
	 *
	 * @return void
	 */
	public function capture_traffic_source() {
		// Skip admin pages.
		if ( is_admin() ) {
			return;
		}

		// Check if we have UTM parameters in URL.
		$traffic_data = $this->get_traffic_from_request();

		if ( ! empty( $traffic_data ) ) {
			$this->store_traffic_data( $traffic_data );
		}
	}

	/**
	 * Get traffic data from current request.
	 *
	 * @return array Traffic data.
	 */
	private function get_traffic_from_request() {
		$data = array();

		// Capture UTM parameters.
		foreach ( $this->utm_params as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET[ $param ] ) && ! empty( $_GET[ $param ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$data[ $param ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
			}
		}

		// Capture referrer if no UTM.
		if ( empty( $data ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

			// Only track external referrers.
			if ( ! empty( $referrer ) && ! $this->is_internal_referrer( $referrer ) ) {
				$data['referrer']        = $referrer;
				$data['referrer_domain'] = wp_parse_url( $referrer, PHP_URL_HOST );
				$data['source_type']     = $this->identify_source_type( $referrer );
			}
		}

		// Add timestamp and landing page.
		if ( ! empty( $data ) ) {
			$data['timestamp']    = current_time( 'mysql' );
			$data['landing_page'] = isset( $_SERVER['REQUEST_URI'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '';
		}

		return $data;
	}

	/**
	 * Check if referrer is internal.
	 *
	 * @param string $referrer Referrer URL.
	 * @return bool True if internal.
	 */
	private function is_internal_referrer( $referrer ) {
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$referrer_host = wp_parse_url( $referrer, PHP_URL_HOST );

		return $site_host === $referrer_host;
	}

	/**
	 * Identify source type from referrer.
	 *
	 * @param string $referrer Referrer URL.
	 * @return string Source type.
	 */
	private function identify_source_type( $referrer ) {
		$host = strtolower( wp_parse_url( $referrer, PHP_URL_HOST ) );

		// Search engines.
		$search_engines = array( 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex' );
		foreach ( $search_engines as $engine ) {
			if ( strpos( $host, $engine ) !== false ) {
				return 'organic_search';
			}
		}

		// Social media.
		$social_platforms = array(
			'facebook'  => 'social_facebook',
			'instagram' => 'social_instagram',
			'twitter'   => 'social_twitter',
			'x.com'     => 'social_twitter',
			'linkedin'  => 'social_linkedin',
			'pinterest' => 'social_pinterest',
			'tiktok'    => 'social_tiktok',
			'youtube'   => 'social_youtube',
			'reddit'    => 'social_reddit',
		);

		foreach ( $social_platforms as $platform => $type ) {
			if ( strpos( $host, $platform ) !== false ) {
				return $type;
			}
		}

		// Email platforms.
		$email_platforms = array( 'mail', 'outlook', 'gmail' );
		foreach ( $email_platforms as $platform ) {
			if ( strpos( $host, $platform ) !== false ) {
				return 'email';
			}
		}

		return 'referral';
	}

	/**
	 * Store traffic data in session/cookie.
	 *
	 * @param array $data Traffic data.
	 * @return void
	 */
	private function store_traffic_data( $data ) {
		// Use WooCommerce session if available.
		if ( function_exists( 'WC' ) && WC()->session ) {
			// Only store if not already set (first touch attribution).
			$existing = WC()->session->get( self::SESSION_KEY );
			if ( empty( $existing ) ) {
				WC()->session->set( self::SESSION_KEY, $data );
			}
		}
	}

	/**
	 * Get stored traffic data.
	 *
	 * @return array Traffic data.
	 */
	public function get_stored_traffic_data() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return WC()->session->get( self::SESSION_KEY, array() );
		}
		return array();
	}

	/**
	 * AJAX handler for capturing traffic (for cached pages).
	 *
	 * @return void
	 */
	public function ajax_capture_traffic() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'assistify_traffic' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$data = array();

		// Get UTM params from POST.
		foreach ( $this->utm_params as $param ) {
			if ( isset( $_POST[ $param ] ) && ! empty( $_POST[ $param ] ) ) {
				$data[ $param ] = sanitize_text_field( wp_unslash( $_POST[ $param ] ) );
			}
		}

		// Get referrer.
		if ( isset( $_POST['referrer'] ) && ! empty( $_POST['referrer'] ) ) {
			$referrer = sanitize_url( wp_unslash( $_POST['referrer'] ) );
			if ( ! $this->is_internal_referrer( $referrer ) ) {
				$data['referrer']        = $referrer;
				$data['referrer_domain'] = wp_parse_url( $referrer, PHP_URL_HOST );
				$data['source_type']     = $this->identify_source_type( $referrer );
			}
		}

		// Get landing page.
		if ( isset( $_POST['landing_page'] ) ) {
			$data['landing_page'] = sanitize_text_field( wp_unslash( $_POST['landing_page'] ) );
		}

		if ( ! empty( $data ) ) {
			$data['timestamp'] = current_time( 'mysql' );
			$this->store_traffic_data( $data );
		}

		wp_send_json_success();
	}

	/**
	 * Enqueue tracking script.
	 *
	 * @return void
	 */
	public function enqueue_tracking_script() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'assistify-traffic-tracker',
			ASSISTIFY_PLUGIN_URL . 'assets/js/frontend/traffic-tracker.js',
			array( 'jquery' ),
			ASSISTIFY_VERSION,
			true
		);

		wp_localize_script(
			'assistify-traffic-tracker',
			'assistifyTraffic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'assistify_traffic' ),
			)
		);
	}

	/**
	 * Save traffic source to order.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function save_to_order( $order ) {
		$traffic_data = $this->get_stored_traffic_data();

		if ( ! empty( $traffic_data ) ) {
			$order->update_meta_data( self::ORDER_META_KEY, $traffic_data );
			$order->save();

			// Clear session data after order.
			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( self::SESSION_KEY, null );
			}
		}
	}

	/**
	 * Display traffic source in order admin.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function display_in_order_admin( $order ) {
		$traffic_data = $order->get_meta( self::ORDER_META_KEY );

		if ( empty( $traffic_data ) ) {
			return;
		}

		echo '<div class="assistify-traffic-source">';
		echo '<h4>' . esc_html__( 'Traffic Source', 'assistify-for-woocommerce' ) . '</h4>';

		if ( ! empty( $traffic_data['utm_source'] ) ) {
			echo '<p><strong>' . esc_html__( 'Source:', 'assistify-for-woocommerce' ) . '</strong> ';
			echo esc_html( $traffic_data['utm_source'] );

			if ( ! empty( $traffic_data['utm_medium'] ) ) {
				echo ' / ' . esc_html( $traffic_data['utm_medium'] );
			}
			echo '</p>';

			if ( ! empty( $traffic_data['utm_campaign'] ) ) {
				echo '<p><strong>' . esc_html__( 'Campaign:', 'assistify-for-woocommerce' ) . '</strong> ';
				echo esc_html( $traffic_data['utm_campaign'] ) . '</p>';
			}
		} elseif ( ! empty( $traffic_data['referrer_domain'] ) ) {
			echo '<p><strong>' . esc_html__( 'Referrer:', 'assistify-for-woocommerce' ) . '</strong> ';
			echo esc_html( $traffic_data['referrer_domain'] );
			echo ' <span class="description">(' . esc_html( $traffic_data['source_type'] ?? 'referral' ) . ')</span></p>';
		}

		if ( ! empty( $traffic_data['landing_page'] ) ) {
			echo '<p><strong>' . esc_html__( 'Landing Page:', 'assistify-for-woocommerce' ) . '</strong> ';
			echo esc_html( $traffic_data['landing_page'] ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Get traffic source statistics.
	 *
	 * @param int $days Number of days.
	 * @return array Traffic statistics.
	 */
	public function get_traffic_stats( $days = 30 ) {
		global $wpdb;

		$date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Get all orders with traffic data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orders_with_traffic = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm.meta_value as traffic_data, pm_total.meta_value as order_total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing')
				AND p.post_date >= %s",
				self::ORDER_META_KEY,
				$date_from . ' 00:00:00'
			)
		);

		$stats = array(
			'by_source'     => array(),
			'by_medium'     => array(),
			'by_campaign'   => array(),
			'by_type'       => array(),
			'total_tracked' => 0,
			'total_revenue' => 0,
		);

		foreach ( $orders_with_traffic as $order ) {
			$traffic = maybe_unserialize( $order->traffic_data );

			if ( ! is_array( $traffic ) ) {
				continue;
			}

			$revenue = (float) $order->order_total;
			++$stats['total_tracked'];
			$stats['total_revenue'] += $revenue;

			// Group by UTM source.
			$source = $traffic['utm_source'] ?? ( $traffic['referrer_domain'] ?? 'direct' );
			if ( ! isset( $stats['by_source'][ $source ] ) ) {
				$stats['by_source'][ $source ] = array(
					'orders'  => 0,
					'revenue' => 0,
				);
			}
			++$stats['by_source'][ $source ]['orders'];
			$stats['by_source'][ $source ]['revenue'] += $revenue;

			// Group by UTM medium.
			$medium = $traffic['utm_medium'] ?? ( $traffic['source_type'] ?? 'unknown' );
			if ( ! isset( $stats['by_medium'][ $medium ] ) ) {
				$stats['by_medium'][ $medium ] = array(
					'orders'  => 0,
					'revenue' => 0,
				);
			}
			++$stats['by_medium'][ $medium ]['orders'];
			$stats['by_medium'][ $medium ]['revenue'] += $revenue;

			// Group by campaign.
			if ( ! empty( $traffic['utm_campaign'] ) ) {
				$campaign = $traffic['utm_campaign'];
				if ( ! isset( $stats['by_campaign'][ $campaign ] ) ) {
					$stats['by_campaign'][ $campaign ] = array(
						'orders'  => 0,
						'revenue' => 0,
					);
				}
				++$stats['by_campaign'][ $campaign ]['orders'];
				$stats['by_campaign'][ $campaign ]['revenue'] += $revenue;
			}

			// Group by source type.
			$type = $this->get_source_type_label( $traffic );
			if ( ! isset( $stats['by_type'][ $type ] ) ) {
				$stats['by_type'][ $type ] = array(
					'orders'  => 0,
					'revenue' => 0,
				);
			}
			++$stats['by_type'][ $type ]['orders'];
			$stats['by_type'][ $type ]['revenue'] += $revenue;
		}

		// Sort by revenue descending.
		foreach ( array( 'by_source', 'by_medium', 'by_campaign', 'by_type' ) as $key ) {
			uasort(
				$stats[ $key ],
				function ( $a, $b ) {
					return $b['revenue'] <=> $a['revenue'];
				}
			);
		}

		return $stats;
	}

	/**
	 * Get source type label from traffic data.
	 *
	 * @param array $traffic Traffic data.
	 * @return string Source type label.
	 */
	private function get_source_type_label( $traffic ) {
		// If we have UTM medium, use it.
		if ( ! empty( $traffic['utm_medium'] ) ) {
			$medium = strtolower( $traffic['utm_medium'] );

			if ( in_array( $medium, array( 'cpc', 'ppc', 'paid', 'paidsearch' ), true ) ) {
				return 'Paid Search';
			}
			if ( in_array( $medium, array( 'social', 'social-paid', 'paid-social' ), true ) ) {
				return 'Social Media';
			}
			if ( 'email' === $medium ) {
				return 'Email';
			}
			if ( 'referral' === $medium ) {
				return 'Referral';
			}
			if ( 'organic' === $medium ) {
				return 'Organic Search';
			}

			return ucfirst( $medium );
		}

		// Fall back to auto-detected source type.
		if ( ! empty( $traffic['source_type'] ) ) {
			$type = $traffic['source_type'];

			if ( 'organic_search' === $type ) {
				return 'Organic Search';
			}
			if ( strpos( $type, 'social_' ) === 0 ) {
				return 'Social Media';
			}
			if ( 'email' === $type ) {
				return 'Email';
			}

			return 'Referral';
		}

		return 'Direct';
	}

	/**
	 * Get top traffic sources.
	 *
	 * @param int $days  Number of days.
	 * @param int $limit Number of sources.
	 * @return array Top sources.
	 */
	public function get_top_sources( $days = 30, $limit = 10 ) {
		$stats   = $this->get_traffic_stats( $days );
		$sources = array();

		foreach ( $stats['by_source'] as $source => $data ) {
			$sources[] = array(
				'source'  => $source,
				'orders'  => $data['orders'],
				'revenue' => $data['revenue'],
			);
		}

		return array_slice( $sources, 0, $limit );
	}

	/**
	 * Get campaign performance.
	 *
	 * @param int $days Number of days.
	 * @return array Campaign stats.
	 */
	public function get_campaign_performance( $days = 30 ) {
		$stats     = $this->get_traffic_stats( $days );
		$campaigns = array();

		foreach ( $stats['by_campaign'] as $campaign => $data ) {
			$campaigns[] = array(
				'campaign' => $campaign,
				'orders'   => $data['orders'],
				'revenue'  => $data['revenue'],
				'aov'      => $data['orders'] > 0 ? $data['revenue'] / $data['orders'] : 0,
			);
		}

		return $campaigns;
	}

	/**
	 * Get channel breakdown.
	 *
	 * @param int $days Number of days.
	 * @return array Channel stats.
	 */
	public function get_channel_breakdown( $days = 30 ) {
		$stats    = $this->get_traffic_stats( $days );
		$channels = array();

		foreach ( $stats['by_type'] as $type => $data ) {
			$percentage = $stats['total_tracked'] > 0
				? round( ( $data['orders'] / $stats['total_tracked'] ) * 100, 1 )
				: 0;
			$channels[] = array(
				'channel'    => $type,
				'orders'     => $data['orders'],
				'revenue'    => $data['revenue'],
				'percentage' => $percentage,
			);
		}

		return $channels;
	}
}
