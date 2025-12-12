<?php
/**
 * Health Monitor - Admin Page
 *
 * Dedicated store health dashboard page.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor;

defined( 'ABSPATH' ) || exit;

/**
 * Health Page Class
 *
 * Renders the dedicated Store Health admin page.
 *
 * @since 1.0.0
 */
class Health_Page {

	/**
	 * Singleton instance.
	 *
	 * @var Health_Page|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Health_Page
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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_assistify_health_action', array( $this, 'handle_health_action' ) );
		add_action( 'wp_ajax_assistify_get_ai_recommendations', array( $this, 'get_ai_recommendations' ) );
		add_action( 'wp_ajax_assistify_resolve_issue', array( $this, 'resolve_issue' ) );
	}

	/**
	 * Handle AJAX request to mark an issue as resolved.
	 *
	 * @return void
	 */
	public function resolve_issue() {
		check_ajax_referer( 'assistify_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		$issue_key = isset( $_POST['issue_key'] ) ? sanitize_text_field( wp_unslash( $_POST['issue_key'] ) ) : '';

		if ( empty( $issue_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid issue key.', 'assistify-for-woocommerce' ) ) );
		}

		// Get current resolved issues.
		$resolved = get_option( 'assistify_resolved_issues', array() );

		// Add this issue to resolved list.
		$resolved[ $issue_key ] = array(
			'time'       => time(),
			'user'       => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login,
		);

		// Save.
		update_option( 'assistify_resolved_issues', $resolved );

		// Clear health cache so next refresh shows updated issues.
		Health_Monitor::get_instance()->clear_cache();

		wp_send_json_success(
			array(
				'message' => __( 'Issue marked as resolved.', 'assistify-for-woocommerce' ),
			)
		);
	}

	/**
	 * Add menu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Store Health', 'assistify-for-woocommerce' ),
			__( 'Store Health', 'assistify-for-woocommerce' ),
			'manage_woocommerce',
			'assistify-health',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts for the health page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_assistify-health' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'assistify-health-page',
			ASSISTIFY_PLUGIN_URL . 'assets/css/admin/health-page.css',
			array(),
			ASSISTIFY_VERSION
		);

		wp_enqueue_script(
			'assistify-health-page',
			ASSISTIFY_PLUGIN_URL . 'assets/js/admin/health-page.js',
			array( 'jquery' ),
			ASSISTIFY_VERSION,
			true
		);

		wp_localize_script(
			'assistify-health-page',
			'assistifyHealth',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'assistify_health_nonce' ),
				'strings' => array(
					'running'    => __( 'Running...', 'assistify-for-woocommerce' ),
					'success'    => __( 'Done!', 'assistify-for-woocommerce' ),
					'error'      => __( 'Failed', 'assistify-for-woocommerce' ),
					'analyzing'  => __( 'Assistify is analyzing your store...', 'assistify-for-woocommerce' ),
					'refreshing' => __( 'Refreshing health data...', 'assistify-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Render the health page.
	 *
	 * @return void
	 */
	public function render_page() {
		// Check if monitoring is enabled.
		if ( 'yes' !== get_option( 'assistify_enable_health_monitoring', 'yes' ) ) {
			$this->render_disabled_notice();
			return;
		}

		$monitor = Health_Monitor::get_instance();

		// Always run fresh checks on health page (no cache).
		$monitor->clear_cache();
		$results = $monitor->run_checks( true );

		include __DIR__ . '/views/health-page.php';
	}

	/**
	 * Render disabled notice.
	 *
	 * @return void
	 */
	private function render_disabled_notice() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Store Health', 'assistify-for-woocommerce' ); ?></h1>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Health monitoring is currently disabled.', 'assistify-for-woocommerce' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=assistify' ) ); ?>">
						<?php esc_html_e( 'Enable it in settings', 'assistify-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle health action AJAX requests.
	 *
	 * @return void
	 */
	public function handle_health_action() {
		check_ajax_referer( 'assistify_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		$action = isset( $_POST['health_action'] ) ? sanitize_text_field( wp_unslash( $_POST['health_action'] ) ) : '';

		$result = $this->execute_health_action( $action );

		if ( $result['success'] ) {
			// Clear health cache after action.
			$monitor = Health_Monitor::get_instance();
			$monitor->clear_cache();

			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Execute a health action.
	 *
	 * @param string $action Action identifier.
	 * @return array Result with success status and message.
	 */
	private function execute_health_action( $action ) {
		switch ( $action ) {
			case 'clear_transients':
				return $this->action_clear_transients();

			case 'optimize_autoload':
				return $this->action_optimize_autoload();

			case 'clear_wc_sessions':
				return $this->action_clear_wc_sessions();

			case 'clear_wc_logs':
				return $this->action_clear_wc_logs();

			case 'regenerate_thumbnails_check':
				return $this->action_check_thumbnails();

			case 'refresh_health':
				$monitor = Health_Monitor::get_instance();
				$monitor->clear_cache();
				$results = $monitor->run_checks( true );
				return array(
					'success' => true,
					'message' => __( 'Health data refreshed.', 'assistify-for-woocommerce' ),
					'score'   => $results['score'],
				);

			default:
				return array(
					'success' => false,
					'message' => __( 'Unknown action.', 'assistify-for-woocommerce' ),
				);
		}
	}

	/**
	 * Action: Clear expired transients.
	 *
	 * @return array Result.
	 */
	private function action_clear_transients() {
		global $wpdb;

		// Delete expired transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE a, b FROM {$wpdb->options} a
				INNER JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 20))
				WHERE a.option_name LIKE %s
				AND a.option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of transients deleted */
				__( 'Cleared %d expired transient(s).', 'assistify-for-woocommerce' ),
				$deleted / 2
			),
		);
	}

	/**
	 * Action: Optimize autoloaded options.
	 *
	 * @return array Result.
	 */
	private function action_optimize_autoload() {
		global $wpdb;

		// Find large autoloaded options that shouldn't be autoloaded.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$large_options = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) as size 
			FROM {$wpdb->options} 
			WHERE autoload = 'yes' 
			AND LENGTH(option_value) > 10000
			AND option_name NOT LIKE '%_transient_%'
			ORDER BY size DESC
			LIMIT 20"
		);

		$optimized = 0;

		// Known options that can be safely set to not autoload.
		$safe_to_disable = array(
			'_site_transient_',
			'rewrite_rules',
			'cron',
		);

		foreach ( $large_options as $option ) {
			foreach ( $safe_to_disable as $prefix ) {
				if ( strpos( $option->option_name, $prefix ) === 0 ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->update(
						$wpdb->options,
						array( 'autoload' => 'no' ),
						array( 'option_name' => $option->option_name )
					);
					++$optimized;
					break;
				}
			}
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of options optimized */
				__( 'Optimized %d autoloaded option(s).', 'assistify-for-woocommerce' ),
				$optimized
			),
			'data'    => array(
				'large_options_found' => count( $large_options ),
				'optimized'           => $optimized,
			),
		);
	}

	/**
	 * Action: Clear old WooCommerce sessions.
	 *
	 * @return array Result.
	 */
	private function action_clear_wc_sessions() {
		global $wpdb;

		// Clear expired sessions.
		$table = $wpdb->prefix . 'woocommerce_sessions';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( ! $table_exists ) {
			return array(
				'success' => true,
				'message' => __( 'No WooCommerce sessions table found (HPOS may be enabled).', 'assistify-for-woocommerce' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}woocommerce_sessions WHERE session_expiry < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				time()
			)
		);

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of sessions deleted */
				__( 'Cleared %d expired session(s).', 'assistify-for-woocommerce' ),
				$deleted
			),
		);
	}

	/**
	 * Action: Clear old WooCommerce logs.
	 *
	 * @return array Result.
	 */
	private function action_clear_wc_logs() {
		if ( ! defined( 'WC_LOG_DIR' ) || ! is_dir( WC_LOG_DIR ) ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce log directory not found.', 'assistify-for-woocommerce' ),
			);
		}

		$files   = glob( WC_LOG_DIR . '*.log' );
		$deleted = 0;
		$cutoff  = strtotime( '-30 days' );

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				if ( wp_delete_file( $file ) ) {
					++$deleted;
				}
			}
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of log files deleted */
				__( 'Deleted %d old log file(s).', 'assistify-for-woocommerce' ),
				$deleted
			),
		);
	}

	/**
	 * Action: Check for missing thumbnails.
	 *
	 * @return array Result.
	 */
	private function action_check_thumbnails() {
		$missing = 0;

		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 100,
			)
		);

		foreach ( $products as $product ) {
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				$file = get_attached_file( $image_id );
				if ( ! file_exists( $file ) ) {
					++$missing;
				}
			}
		}

		if ( $missing > 0 ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %d: number of missing images */
					__( 'Found %d product(s) with missing images.', 'assistify-for-woocommerce' ),
					$missing
				),
				'data'    => array( 'missing' => $missing ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'All product images are intact.', 'assistify-for-woocommerce' ),
		);
	}

	/**
	 * Ensure WordPress update transients exist.
	 *
	 * @return void
	 */
	private function refresh_update_transients() {
		// Only refresh if transients don't exist - don't delete existing data!
		if ( false === get_site_transient( 'update_plugins' ) ) {
			if ( ! function_exists( 'wp_update_plugins' ) ) {
				require_once ABSPATH . 'wp-includes/update.php';
			}
			wp_update_plugins();
		}

		if ( false === get_site_transient( 'update_themes' ) ) {
			if ( ! function_exists( 'wp_update_themes' ) ) {
				require_once ABSPATH . 'wp-includes/update.php';
			}
			wp_update_themes();
		}

		if ( false === get_site_transient( 'update_core' ) ) {
			if ( ! function_exists( 'wp_version_check' ) ) {
				require_once ABSPATH . 'wp-includes/update.php';
			}
			wp_version_check();
		}
	}

	/**
	 * Get AI-powered recommendations.
	 *
	 * @return void
	 */
	public function get_ai_recommendations() {
		check_ajax_referer( 'assistify_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		// Force refresh update checks to get latest data.
		$this->refresh_update_transients();

		// Get health results with fresh cache.
		$monitor = Health_Monitor::get_instance();
		$monitor->clear_cache();
		$results = $monitor->run_checks( true );

		// Get business metrics.
		$metrics = $this->get_business_metrics();

		// Build context for AI.
		$context = $this->build_ai_context( $results, $metrics );

		// Get AI recommendations.
		$recommendations = $this->generate_ai_recommendations( $context );

		wp_send_json_success(
			array(
				'recommendations' => $recommendations,
				'generated_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get business metrics for AI analysis.
	 *
	 * @return array Business metrics.
	 */
	private function get_business_metrics() {
		// Sales data.
		$today_sales     = $this->get_sales_for_period( 'today' );
		$yesterday_sales = $this->get_sales_for_period( 'yesterday' );
		$week_sales      = $this->get_sales_for_period( 'week' );
		$last_week_sales = $this->get_sales_for_period( 'last_week' );

		// Order data by status.
		$pending_orders = wc_get_orders(
			array(
				'status' => array( 'pending', 'on-hold' ),
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$processing_orders = wc_get_orders(
			array(
				'status' => 'processing',
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$failed_orders = wc_get_orders(
			array(
				'status'       => 'failed',
				'date_created' => '>' . strtotime( '-7 days' ),
				'limit'        => -1,
				'return'       => 'ids',
			)
		);

		// Refund rate.
		$refunds_week = wc_get_orders(
			array(
				'type'         => 'shop_order_refund',
				'date_created' => '>' . strtotime( '-7 days' ),
				'limit'        => -1,
				'return'       => 'ids',
			)
		);

		$total_orders_week = wc_get_orders(
			array(
				'date_created' => '>' . strtotime( '-7 days' ),
				'limit'        => -1,
				'return'       => 'ids',
			)
		);

		$refund_rate = count( $total_orders_week ) > 0
			? round( ( count( $refunds_week ) / count( $total_orders_week ) ) * 100, 1 )
			: 0;

		// Get plugin updates.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_updates      = get_site_transient( 'update_plugins' );
		$plugin_update_count = isset( $plugin_updates->response ) ? count( $plugin_updates->response ) : 0;
		$plugin_names        = array();
		if ( $plugin_update_count > 0 ) {
			$all_plugins = get_plugins();
			foreach ( array_slice( array_keys( $plugin_updates->response ), 0, 5 ) as $plugin_file ) {
				if ( isset( $all_plugins[ $plugin_file ] ) ) {
					$plugin_names[] = $all_plugins[ $plugin_file ]['Name'];
				}
			}
		}

		// Get theme updates.
		$theme_updates      = get_site_transient( 'update_themes' );
		$theme_update_count = isset( $theme_updates->response ) ? count( $theme_updates->response ) : 0;

		// Get WordPress update.
		$wp_update    = false;
		$wp_new_ver   = '';
		$core_updates = get_core_updates();
		if ( is_array( $core_updates ) && ! empty( $core_updates ) && 'upgrade' === $core_updates[0]->response ) {
			$wp_update  = true;
			$wp_new_ver = $core_updates[0]->version;
		}

		// Get low stock products.
		$low_stock_threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		$low_stock_products  = wc_get_products(
			array(
				'status'       => 'publish',
				'stock_status' => 'instock',
				'limit'        => 10,
				'manage_stock' => true,
				'orderby'      => 'meta_value_num',
				'order'        => 'ASC',
				'meta_key'     => '_stock', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_stock',
						'value'   => $low_stock_threshold,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$low_stock_info = array();
		foreach ( $low_stock_products as $product ) {
			$low_stock_info[] = array(
				'name'  => $product->get_name(),
				'stock' => $product->get_stock_quantity(),
			);
		}

		// Get out of stock products count.
		$out_of_stock = wc_get_products(
			array(
				'status'       => 'publish',
				'stock_status' => 'outofstock',
				'limit'        => -1,
				'return'       => 'ids',
			)
		);

		// Get top selling products this week.
		$top_products = $this->get_top_products( 5 );

		// Get customer stats.
		$new_customers = $this->get_new_customers_count( 7 );

		// Total products and orders.
		$total_products = wp_count_posts( 'product' );
		$total_orders   = wp_count_posts( 'shop_order' );

		// Get advanced analytics data.
		$analytics_data = array(
			'retention_rate' => 0,
			'aov'            => 0,
			'aov_change'     => 0,
		);

		if ( class_exists( '\Assistify\Analytics\Analytics' ) ) {
			$analytics  = \Assistify\Analytics\Analytics::get_instance();
			$retention  = $analytics->get_customer_retention();
			$aov_trends = $analytics->get_aov_trends( 2 );

			$analytics_data['retention_rate']   = $retention['retention_rate'] ?? 0;
			$analytics_data['repeat_customers'] = $retention['repeat_customers'] ?? 0;
			$analytics_data['total_customers']  = $retention['total_customers'] ?? 0;

			if ( ! empty( $aov_trends ) ) {
				$latest                       = end( $aov_trends );
				$analytics_data['aov']        = $latest['aov'] ?? 0;
				$previous                     = count( $aov_trends ) > 1 ? $aov_trends[ count( $aov_trends ) - 2 ] : $latest;
				$analytics_data['aov_change'] = $previous['aov'] > 0
					? round( ( ( $latest['aov'] - $previous['aov'] ) / $previous['aov'] ) * 100, 1 )
					: 0;
			}
		}

		return array(
			'sales'              => array(
				'today'     => $today_sales,
				'yesterday' => $yesterday_sales,
				'week'      => $week_sales,
				'last_week' => $last_week_sales,
			),
			'orders'             => array(
				'pending'    => count( $pending_orders ),
				'processing' => count( $processing_orders ),
				'failed'     => count( $failed_orders ),
				'total'      => isset( $total_orders->publish ) ? $total_orders->publish : 0,
			),
			'refund_rate'        => $refund_rate,
			'sales_trend'        => $week_sales > $last_week_sales ? 'up' : ( $week_sales < $last_week_sales ? 'down' : 'stable' ),
			'daily_comparison'   => $today_sales >= $yesterday_sales ? 'up' : 'down',
			'updates'            => array(
				'plugins'      => $plugin_update_count,
				'plugin_names' => $plugin_names,
				'themes'       => $theme_update_count,
				'wordpress'    => $wp_update,
				'wp_new_ver'   => $wp_new_ver,
			),
			'inventory'          => array(
				'low_stock'    => $low_stock_info,
				'out_of_stock' => count( $out_of_stock ),
				'total'        => isset( $total_products->publish ) ? $total_products->publish : 0,
			),
			'top_products'       => $top_products,
			'new_customers_week' => $new_customers,
			'analytics'          => $analytics_data,
		);
	}

	/**
	 * Get top selling products.
	 *
	 * @param int $limit Number of products.
	 * @return array Top products.
	 */
	private function get_top_products( $limit = 5 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.order_item_name as name, 
						SUM(oim.meta_value) as quantity,
						SUM(oim2.meta_value) as total
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_qty'
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_line_total'
				INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
				WHERE oi.order_item_type = 'line_item'
				AND p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing')
				AND p.post_date >= %s
				GROUP BY oi.order_item_name
				ORDER BY quantity DESC
				LIMIT %d",
				gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				$limit
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get new customers count.
	 *
	 * @param int $days Number of days.
	 * @return int Customer count.
	 */
	private function get_new_customers_count( $days = 7 ) {
		$args = array(
			'role'        => 'customer',
			'date_query'  => array(
				array(
					'after' => $days . ' days ago',
				),
			),
			'count_total' => true,
			'fields'      => 'ID',
		);

		$query = new \WP_User_Query( $args );
		return $query->get_total();
	}

	/**
	 * Get sales for a period.
	 *
	 * @param string $period Period identifier.
	 * @return float Total sales.
	 */
	private function get_sales_for_period( $period ) {
		$args = array(
			'status' => array( 'completed', 'processing' ),
			'limit'  => -1,
		);

		switch ( $period ) {
			case 'today':
				$args['date_created'] = '>=' . strtotime( 'today midnight' );
				break;
			case 'yesterday':
				$args['date_created'] = strtotime( 'yesterday midnight' ) . '...' . strtotime( 'today midnight' );
				break;
			case 'week':
				$args['date_created'] = '>' . strtotime( '-7 days' );
				break;
			case 'last_week':
				$args['date_created'] = strtotime( '-14 days' ) . '...' . strtotime( '-7 days' );
				break;
		}

		$orders = wc_get_orders( $args );
		$total  = 0;

		foreach ( $orders as $order ) {
			$total += (float) $order->get_total();
		}

		return $total;
	}

	/**
	 * Build context for AI analysis.
	 *
	 * @param array $health_results Health check results.
	 * @param array $metrics        Business metrics.
	 * @return string Context string.
	 */
	private function build_ai_context( $health_results, $metrics ) {
		$context = "=== STORE DATA (Use ONLY this data for recommendations) ===\n\n";

		// Updates - IMPORTANT for actionable recommendations.
		$context .= "PENDING UPDATES:\n";
		if ( $metrics['updates']['plugins'] > 0 ) {
			$context .= "- {$metrics['updates']['plugins']} plugin(s) need updates";
			if ( ! empty( $metrics['updates']['plugin_names'] ) ) {
				$context .= ': ' . implode( ', ', $metrics['updates']['plugin_names'] );
			}
			$context .= "\n";
		}
		if ( $metrics['updates']['themes'] > 0 ) {
			$context .= "- {$metrics['updates']['themes']} theme(s) need updates\n";
		}
		if ( $metrics['updates']['wordpress'] ) {
			$context .= "- WordPress update available: {$metrics['updates']['wp_new_ver']}\n";
		}
		if ( 0 === $metrics['updates']['plugins'] && 0 === $metrics['updates']['themes'] && ! $metrics['updates']['wordpress'] ) {
			$context .= "- All up to date\n";
		}

		// Orders needing attention.
		$context .= "\nORDERS NEEDING ATTENTION:\n";
		$context .= "- {$metrics['orders']['pending']} pending/on-hold (awaiting payment or action)\n";
		$context .= "- {$metrics['orders']['processing']} processing (need to be shipped)\n";
		$context .= "- {$metrics['orders']['failed']} failed in last 7 days\n";

		// Sales data.
		$context .= "\nSALES DATA:\n";
		$context .= '- Today: ' . wp_strip_all_tags( wc_price( $metrics['sales']['today'] ) ) . "\n";
		$context .= '- Yesterday: ' . wp_strip_all_tags( wc_price( $metrics['sales']['yesterday'] ) ) . "\n";
		$context .= '- This week: ' . wp_strip_all_tags( wc_price( $metrics['sales']['week'] ) ) . "\n";
		$context .= '- Last week: ' . wp_strip_all_tags( wc_price( $metrics['sales']['last_week'] ) ) . "\n";
		$context .= "- Weekly trend: {$metrics['sales_trend']}\n";
		$context .= "- Refund rate (7d): {$metrics['refund_rate']}%\n";

		// Inventory issues.
		$context .= "\nINVENTORY STATUS:\n";
		$context .= "- Total products: {$metrics['inventory']['total']}\n";
		$context .= "- Out of stock: {$metrics['inventory']['out_of_stock']}\n";
		if ( ! empty( $metrics['inventory']['low_stock'] ) ) {
			$context  .= '- Low stock products: ';
			$low_items = array();
			foreach ( array_slice( $metrics['inventory']['low_stock'], 0, 3 ) as $item ) {
				$low_items[] = "{$item['name']} ({$item['stock']} left)";
			}
			$context .= implode( ', ', $low_items ) . "\n";
		}

		// Top products.
		if ( ! empty( $metrics['top_products'] ) ) {
			$context .= "\nTOP SELLING (30 days):\n";
			foreach ( array_slice( $metrics['top_products'], 0, 3 ) as $product ) {
				$context .= "- {$product['name']}: {$product['quantity']} sold\n";
			}
		}

		// Customers and Analytics.
		$context .= "\nCUSTOMER ANALYTICS:\n";
		$context .= "- New customers this week: {$metrics['new_customers_week']}\n";
		if ( isset( $metrics['analytics'] ) ) {
			$analytics = $metrics['analytics'];
			$context  .= "- Total customers: {$analytics['total_customers']}\n";
			$context  .= "- Repeat customers: {$analytics['repeat_customers']}\n";
			$context  .= "- Customer retention rate: {$analytics['retention_rate']}%\n";
			$context  .= '- Average order value: ' . wp_strip_all_tags( wc_price( $analytics['aov'] ) ) . "\n";
			$aov_trend = $analytics['aov_change'] >= 0 ? 'up' : 'down';
			$context  .= "- AOV trend: {$aov_trend} " . abs( $analytics['aov_change'] ) . "% vs last month\n";
		}

		// Health issues.
		if ( ! empty( $health_results['issues']['critical'] ) ) {
			$context .= "\nCRITICAL ISSUES:\n";
			foreach ( $health_results['issues']['critical'] as $issue ) {
				$context .= "- {$issue['title']}: {$issue['message']}\n";
			}
		}

		if ( ! empty( $health_results['issues']['warning'] ) ) {
			$context .= "\nWARNINGS:\n";
			foreach ( array_slice( $health_results['issues']['warning'], 0, 5 ) as $issue ) {
				$context .= "- {$issue['title']}: {$issue['message']}\n";
			}
		}

		$context .= "\n=== END STORE DATA ===\n";
		$context .= "\nProvide 3-5 specific, actionable recommendations based ONLY on the data above.";
		$context .= "\nDo NOT give generic advice. Reference specific numbers and items from the data.";

		return $context;
	}

	/**
	 * Generate AI recommendations.
	 *
	 * @param string $context Context for AI.
	 * @return array Recommendations.
	 */
	private function generate_ai_recommendations( $context ) {
		// Get AI provider.
		$provider_name = get_option( 'assistify_ai_provider', 'openai' );
		$provider      = \Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory::create( $provider_name );

		if ( ! $provider ) {
			return array(
				array(
					'type'    => 'error',
					'title'   => __( 'AI Provider Not Configured', 'assistify-for-woocommerce' ),
					'message' => __( 'Configure your AI provider in settings to get personalized recommendations.', 'assistify-for-woocommerce' ),
				),
			);
		}

		$system_prompt = 'You are an expert WooCommerce store consultant. IMPORTANT RULES:
1. ONLY use the exact data provided - do not assume, guess, or make up any numbers or facts
2. Base recommendations solely on the actual metrics and issues shown
3. If data is missing, say "insufficient data" rather than guessing

Analyze the provided store health data and give 3-5 actionable recommendations.

You MUST respond in this exact JSON format:
[
  {
    "priority": "high",
    "title": "Brief title here",
    "action": "Specific action to take",
    "impact": "Expected benefit"
  }
]

Priority must be: "high", "medium", or "low"
Keep titles under 50 characters.
Keep action and impact under 150 characters each.
Only output the JSON array, nothing else.';

		$user_prompt = "Based ONLY on this actual store data, provide recommendations:\n\n{$context}\n\nRemember: Only reference data that is explicitly shown above. Do not invent statistics.";

		try {
			$response = $provider->chat(
				array(
					array(
						'role'    => 'system',
						'content' => $system_prompt,
					),
					array(
						'role'    => 'user',
						'content' => $user_prompt,
					),
				),
				array(
					'max_tokens'  => 1000,
					'temperature' => 0.3, // Lower temperature for more factual responses.
				)
			);

			if ( ! empty( $response['content'] ) ) {
				return $this->parse_ai_recommendations( $response['content'] );
			}
		} catch ( \Exception $e ) {
			\Assistify_For_WooCommerce\Assistify_Logger::error( 'AI recommendation failed: ' . $e->getMessage() );
		}

		// Return default recommendations if AI fails.
		return $this->get_fallback_recommendations( $context );
	}

	/**
	 * Parse AI recommendations from response.
	 *
	 * @param string $response AI response text.
	 * @return array Parsed recommendations.
	 */
	private function parse_ai_recommendations( $response ) {
		$recommendations = array();

		// Try to extract JSON from the response.
		$json_match = preg_match( '/\[[\s\S]*\]/', $response, $matches );

		if ( $json_match ) {
			$json_data = json_decode( $matches[0], true );

			if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_data ) ) {
				foreach ( $json_data as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$priority = isset( $item['priority'] ) ? strtolower( $item['priority'] ) : 'medium';
					if ( ! in_array( $priority, array( 'high', 'medium', 'low' ), true ) ) {
						$priority = 'medium';
					}

					$recommendations[] = array(
						'priority' => $priority,
						'title'    => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
						'action'   => isset( $item['action'] ) ? sanitize_text_field( $item['action'] ) : '',
						'impact'   => isset( $item['impact'] ) ? sanitize_text_field( $item['impact'] ) : '',
					);
				}

				return array_slice( $recommendations, 0, 5 );
			}
		}

		// Fallback: Try to parse plain text format.
		return $this->parse_plain_text_recommendations( $response );
	}

	/**
	 * Parse plain text recommendations as fallback.
	 *
	 * @param string $response Plain text response.
	 * @return array Parsed recommendations.
	 */
	private function parse_plain_text_recommendations( $response ) {
		$recommendations = array();

		// Split by double newlines or numbered items.
		$blocks = preg_split( '/\n\n+|\n(?=\d+\.)/', $response );

		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( empty( $block ) || strlen( $block ) < 20 ) {
				continue;
			}

			// Extract priority.
			$priority = 'medium';
			if ( preg_match( '/priority[:\s]*(high|critical|urgent)/i', $block ) ) {
				$priority = 'high';
			} elseif ( preg_match( '/priority[:\s]*(low|minor)/i', $block ) ) {
				$priority = 'low';
			}

			// Extract title.
			$title = '';
			if ( preg_match( '/title[:\s]*([^\n]+)/i', $block, $matches ) ) {
				$title = trim( $matches[1] );
			} elseif ( preg_match( '/^\d*\.?\s*\*?\*?([^:\n]+)/', $block, $matches ) ) {
				$title = trim( $matches[1] );
			}

			// Extract action.
			$action = '';
			if ( preg_match( '/action[:\s]*([^\n]+)/i', $block, $matches ) ) {
				$action = trim( $matches[1] );
			}

			// Extract impact.
			$impact = '';
			if ( preg_match( '/impact[:\s]*([^\n]+)/i', $block, $matches ) ) {
				$impact = trim( $matches[1] );
			}

			if ( ! empty( $title ) || ! empty( $action ) ) {
				$recommendations[] = array(
					'priority' => $priority,
					'title'    => sanitize_text_field( $title ),
					'action'   => sanitize_text_field( $action ),
					'impact'   => sanitize_text_field( $impact ),
				);
			}
		}

		return array_slice( $recommendations, 0, 5 );
	}

	/**
	 * Get fallback recommendations when AI is unavailable.
	 *
	 * @param string $context Context string (unused but kept for consistency).
	 * @return array Fallback recommendations.
	 */
	private function get_fallback_recommendations( $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array(
			array(
				'priority' => 'high',
				'title'    => __( 'Review Critical Issues', 'assistify-for-woocommerce' ),
				'message'  => __( 'Address any critical issues shown above to maintain store stability.', 'assistify-for-woocommerce' ),
			),
			array(
				'priority' => 'medium',
				'title'    => __( 'Keep Everything Updated', 'assistify-for-woocommerce' ),
				'message'  => __( 'Regularly update WordPress, WooCommerce, and all plugins for security.', 'assistify-for-woocommerce' ),
			),
			array(
				'priority' => 'medium',
				'title'    => __( 'Monitor Stock Levels', 'assistify-for-woocommerce' ),
				'message'  => __( 'Keep popular products in stock to avoid lost sales.', 'assistify-for-woocommerce' ),
			),
		);
	}
}
