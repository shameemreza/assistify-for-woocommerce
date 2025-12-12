<?php
/**
 * Health Monitor - Core Orchestrator
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor;

defined( 'ABSPATH' ) || exit;

/**
 * Health Monitor Class
 *
 * Central orchestrator for all health checks and monitoring.
 *
 * @since 1.0.0
 */
class Health_Monitor {

	/**
	 * Singleton instance.
	 *
	 * @var Health_Monitor|null
	 */
	private static $instance = null;

	/**
	 * Registered health checks.
	 *
	 * @var array
	 */
	private $checks = array();

	/**
	 * Health check results cache.
	 *
	 * @var array|null
	 */
	private $cached_results = null;

	/**
	 * Cache expiry time in seconds.
	 *
	 * @var int
	 */
	private $cache_expiry = 300; // 5 minutes.

	/**
	 * Health categories with weights for scoring.
	 *
	 * @var array
	 */
	private $categories = array(
		'critical'    => array(
			'weight' => 40,
			'label'  => 'Critical Issues',
		),
		'performance' => array(
			'weight' => 20,
			'label'  => 'Performance',
		),
		'security'    => array(
			'weight' => 15,
			'label'  => 'Security',
		),
		'inventory'   => array(
			'weight' => 10,
			'label'  => 'Inventory',
		),
		'business'    => array(
			'weight' => 10,
			'label'  => 'Business Health',
		),
		'updates'     => array(
			'weight' => 5,
			'label'  => 'Updates',
		),
	);

	/**
	 * Get singleton instance.
	 *
	 * @return Health_Monitor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			// Set instance BEFORE constructor runs to avoid re-entry issues.
			self::$instance = new self();
			self::$instance->initialize();
		}
		return self::$instance;
	}

	/**
	 * Constructor - keep minimal to avoid re-entry issues.
	 */
	private function __construct() {
		// Do nothing here - initialization happens in initialize().
	}

	/**
	 * Initialize the monitor after singleton is set.
	 *
	 * @return void
	 */
	private function initialize() {
		$this->load_checks();
		$this->init_hooks();
	}

	/**
	 * Load health check classes.
	 *
	 * @return void
	 */
	private function load_checks() {
		$checks_dir = __DIR__ . '/checks/';

		// Load check classes.
		$check_files = array(
			'class-health-check-errors.php',
			'class-health-check-orders.php',
			'class-health-check-system.php',
			'class-health-check-security.php',
			'class-health-check-inventory.php',
			'class-health-check-updates.php',
		);

		foreach ( $check_files as $file ) {
			$file_path = $checks_dir . $file;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Schedule health check cron.
		add_action( 'init', array( $this, 'schedule_health_check' ) );
		add_action( 'assistify_health_check_cron', array( $this, 'run_scheduled_check' ) );

		// Hook into WooCommerce order events for real-time monitoring.
		add_action( 'woocommerce_order_status_failed', array( $this, 'track_failed_order' ), 10, 2 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'track_payment_status' ), 10, 3 );

		// Admin dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_styles' ) );

		// Clear cache when relevant options change.
		add_action( 'update_option', array( $this, 'maybe_clear_cache' ), 10, 1 );
	}

	/**
	 * Enqueue dashboard widget styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_dashboard_styles( $hook ) {
		// Only load on dashboard.
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Check if monitoring is enabled.
		if ( 'yes' !== get_option( 'assistify_enable_health_monitoring', 'yes' ) ) {
			return;
		}

		wp_enqueue_style(
			'assistify-dashboard-widget',
			ASSISTIFY_PLUGIN_URL . 'assets/css/admin/dashboard-widget.css',
			array(),
			ASSISTIFY_VERSION
		);
	}

	/**
	 * Register a health check.
	 *
	 * @param string $id       Unique check identifier.
	 * @param array  $callback Check callback and metadata.
	 * @return void
	 */
	public function register_check( $id, $callback ) {
		$this->checks[ $id ] = wp_parse_args(
			$callback,
			array(
				'label'    => $id,
				'category' => 'critical',
				'callback' => null,
				'priority' => 10,
			)
		);
	}

	/**
	 * Run all health checks.
	 *
	 * @param bool $force_refresh Force fresh check, bypass cache.
	 * @return array Health check results.
	 */
	public function run_checks( $force_refresh = false ) {
		// Check cache.
		if ( ! $force_refresh && null !== $this->cached_results ) {
			return $this->cached_results;
		}

		// Check transient cache.
		if ( ! $force_refresh ) {
			$cached = get_transient( 'assistify_health_results' );
			if ( false !== $cached ) {
				$this->cached_results = $cached;
				return $cached;
			}
		}

		$results = array(
			'timestamp'  => current_time( 'mysql' ),
			'score'      => 100,
			'categories' => array(),
			'issues'     => array(
				'critical' => array(),
				'warning'  => array(),
				'info'     => array(),
			),
			'summary'    => array(),
		);

		// Initialize category scores.
		foreach ( $this->categories as $cat_id => $cat_data ) {
			$results['categories'][ $cat_id ] = array(
				'label'  => $cat_data['label'],
				'score'  => 100,
				'issues' => array(),
			);
		}

		// Run registered checks.
		foreach ( $this->checks as $check_id => $check ) {
			if ( ! is_callable( $check['callback'] ) ) {
				continue;
			}

			try {
				$check_result = call_user_func( $check['callback'] );

				if ( ! empty( $check_result['issues'] ) ) {
					foreach ( $check_result['issues'] as $issue ) {
						$severity = $issue['severity'] ?? 'warning';
						$category = $check['category'];

						$issue_data = array(
							'check_id'  => $check_id,
							'title'     => $issue['title'] ?? 'Unknown Issue',
							'message'   => $issue['message'] ?? '',
							'severity'  => $severity,
							'category'  => $category,
							'fix'       => $issue['fix'] ?? '',
							'data'      => $issue['data'] ?? array(),
							'issue_key' => $issue['issue_key'] ?? '',
							'timestamp' => current_time( 'mysql' ),
						);

						$results['issues'][ $severity ][]               = $issue_data;
						$results['categories'][ $category ]['issues'][] = $issue_data;

						// Reduce category score based on severity.
						$score_penalty                               = $this->get_severity_penalty( $severity );
						$results['categories'][ $category ]['score'] = max(
							0,
							$results['categories'][ $category ]['score'] - $score_penalty
						);
					}
				}
			} catch ( \Exception $e ) {
				\Assistify_For_WooCommerce\Assistify_Logger::error( 'Health check failed: ' . $check_id . ' - ' . $e->getMessage() );
			}
		}

		// Calculate overall score.
		$results['score'] = $this->calculate_overall_score( $results['categories'] );

		// Generate summary.
		$results['summary'] = $this->generate_summary( $results );

		// Cache results.
		$this->cached_results = $results;
		set_transient( 'assistify_health_results', $results, $this->cache_expiry );

		// Check if alerts need to be sent.
		$this->maybe_send_alerts( $results );

		return $results;
	}

	/**
	 * Get severity penalty for score calculation.
	 *
	 * @param string $severity Issue severity level.
	 * @return int Score penalty.
	 */
	private function get_severity_penalty( $severity ) {
		$penalties = array(
			'critical' => 30,
			'warning'  => 15,
			'info'     => 5,
		);

		return $penalties[ $severity ] ?? 10;
	}

	/**
	 * Calculate overall health score.
	 *
	 * @param array $categories Category scores.
	 * @return int Overall score (0-100).
	 */
	private function calculate_overall_score( $categories ) {
		$total_weight   = 0;
		$weighted_score = 0;

		foreach ( $this->categories as $cat_id => $cat_data ) {
			if ( isset( $categories[ $cat_id ] ) ) {
				$weighted_score += $categories[ $cat_id ]['score'] * $cat_data['weight'];
				$total_weight   += $cat_data['weight'];
			}
		}

		if ( 0 === $total_weight ) {
			return 100;
		}

		return (int) round( $weighted_score / $total_weight );
	}

	/**
	 * Generate human-readable summary.
	 *
	 * @param array $results Health check results.
	 * @return array Summary data.
	 */
	private function generate_summary( $results ) {
		$critical_count = count( $results['issues']['critical'] );
		$warning_count  = count( $results['issues']['warning'] );
		$info_count     = count( $results['issues']['info'] );

		$status  = 'healthy';
		$message = 'Your store is running smoothly.';

		if ( $critical_count > 0 ) {
			$status  = 'critical';
			$message = sprintf(
				'%d critical issue(s) require immediate attention.',
				$critical_count
			);
		} elseif ( $warning_count > 0 ) {
			$status  = 'warning';
			$message = sprintf(
				'%d warning(s) found. Review recommended.',
				$warning_count
			);
		} elseif ( $info_count > 0 ) {
			$status  = 'info';
			$message = sprintf(
				'Store is healthy. %d minor suggestion(s) available.',
				$info_count
			);
		}

		return array(
			'status'         => $status,
			'message'        => $message,
			'critical_count' => $critical_count,
			'warning_count'  => $warning_count,
			'info_count'     => $info_count,
			'total_issues'   => $critical_count + $warning_count + $info_count,
		);
	}

	/**
	 * Check if alerts should be sent and send them.
	 *
	 * @param array $results Health check results.
	 * @return void
	 */
	private function maybe_send_alerts( $results ) {
		// Only alert on critical issues.
		if ( empty( $results['issues']['critical'] ) ) {
			return;
		}

		// Check if we've already sent an alert recently.
		$last_alert = get_transient( 'assistify_last_health_alert' );
		if ( false !== $last_alert ) {
			return; // Don't spam alerts.
		}

		// Send alert.
		$alerts = Health_Alerts::get_instance();
		$alerts->send_health_alert( $results );

		// Set cooldown (1 hour minimum between alerts).
		set_transient( 'assistify_last_health_alert', time(), HOUR_IN_SECONDS );
	}

	/**
	 * Schedule health check cron job.
	 *
	 * @return void
	 */
	public function schedule_health_check() {
		if ( ! wp_next_scheduled( 'assistify_health_check_cron' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'assistify_health_check_cron' );
		}
	}

	/**
	 * Run scheduled health check.
	 *
	 * @return void
	 */
	public function run_scheduled_check() {
		// Check if monitoring is enabled.
		if ( 'yes' !== get_option( 'assistify_enable_health_monitoring', 'yes' ) ) {
			return;
		}

		$this->run_checks( true );
	}

	/**
	 * Track failed order for monitoring.
	 *
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Order object.
	 * @return void
	 */
	public function track_failed_order( $order_id, $order = null ) {
		if ( null === $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		// Get failure data.
		$failure_data = array(
			'order_id'       => $order_id,
			'timestamp'      => current_time( 'mysql' ),
			'payment_method' => $order->get_payment_method(),
			'gateway_title'  => $order->get_payment_method_title(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'customer_email' => $order->get_billing_email(),
			'customer_ip'    => $order->get_customer_ip_address(),
			'order_notes'    => $this->get_order_failure_notes( $order ),
		);

		// Store in transient for recent failures tracking.
		$recent_failures = get_transient( 'assistify_recent_order_failures' );
		if ( ! is_array( $recent_failures ) ) {
			$recent_failures = array();
		}

		// Keep only last 50 failures.
		$recent_failures[] = $failure_data;
		$recent_failures   = array_slice( $recent_failures, -50 );

		set_transient( 'assistify_recent_order_failures', $recent_failures, DAY_IN_SECONDS );

		// Log the failure.
		\Assistify_For_WooCommerce\Assistify_Logger::warning(
			sprintf(
				'Order #%d failed. Gateway: %s, Total: %s %s',
				$order_id,
				$failure_data['gateway_title'],
				$failure_data['total'],
				$failure_data['currency']
			)
		);

		// Check if we need to send immediate alert (multiple failures).
		$this->check_failure_spike( $recent_failures );
	}

	/**
	 * Get order notes related to payment failure.
	 *
	 * @param \WC_Order $order Order object.
	 * @return array Failure-related notes.
	 */
	private function get_order_failure_notes( $order ) {
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'limit'    => 5,
				'orderby'  => 'date_created',
				'order'    => 'DESC',
			)
		);

		$failure_notes = array();
		foreach ( $notes as $note ) {
			// Look for payment-related notes.
			$content = strtolower( $note->content );
			if ( strpos( $content, 'fail' ) !== false ||
				strpos( $content, 'error' ) !== false ||
				strpos( $content, 'declined' ) !== false ||
				strpos( $content, 'payment' ) !== false ) {
				$failure_notes[] = array(
					'content' => $note->content,
					'date'    => $note->date_created->date( 'Y-m-d H:i:s' ),
				);
			}
		}

		return $failure_notes;
	}

	/**
	 * Check for payment failure spike and alert if needed.
	 *
	 * @param array $recent_failures Recent failure data.
	 * @return void
	 */
	private function check_failure_spike( $recent_failures ) {
		// Count failures in last hour.
		$one_hour_ago = strtotime( '-1 hour' );
		$recent_count = 0;

		foreach ( $recent_failures as $failure ) {
			$failure_time = strtotime( $failure['timestamp'] );
			if ( $failure_time >= $one_hour_ago ) {
				++$recent_count;
			}
		}

		// Alert if more than 3 failures in an hour.
		if ( $recent_count >= 3 ) {
			$last_spike_alert = get_transient( 'assistify_payment_spike_alert' );
			if ( false === $last_spike_alert ) {
				$alerts = Health_Alerts::get_instance();
				$alerts->send_payment_failure_alert( $recent_failures, $recent_count );
				set_transient( 'assistify_payment_spike_alert', time(), HOUR_IN_SECONDS );
			}
		}
	}

	/**
	 * Track payment status for monitoring.
	 *
	 * @param string    $status   Payment status.
	 * @param int       $order_id Order ID (unused, required by filter).
	 * @param \WC_Order $order    Order object (unused, required by filter).
	 * @return string Payment status.
	 */
	public function track_payment_status( $status, $order_id, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Track successful payments for success rate calculation.
		$payment_stats = get_transient( 'assistify_payment_stats' );
		if ( ! is_array( $payment_stats ) ) {
			$payment_stats = array(
				'success'      => 0,
				'failed'       => 0,
				'period_start' => current_time( 'mysql' ),
			);
		}

		if ( 'failed' === $status ) {
			++$payment_stats['failed'];
		} else {
			++$payment_stats['success'];
		}

		set_transient( 'assistify_payment_stats', $payment_stats, DAY_IN_SECONDS );

		return $status;
	}

	/**
	 * Add dashboard widget.
	 *
	 * @return void
	 */
	public function add_dashboard_widget() {
		// Check if monitoring is enabled.
		if ( 'yes' !== get_option( 'assistify_enable_health_monitoring', 'yes' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'assistify_health_widget',
			__( 'Store Health', 'assistify-for-woocommerce' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$results = $this->run_checks();
		include __DIR__ . '/views/dashboard-widget.php';
	}

	/**
	 * Clear cache when relevant settings change.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public function maybe_clear_cache( $option ) {
		$relevant_options = array(
			'woocommerce_',
			'assistify_',
		);

		foreach ( $relevant_options as $prefix ) {
			if ( 0 === strpos( $option, $prefix ) ) {
				$this->clear_cache();
				break;
			}
		}
	}

	/**
	 * Clear health check cache.
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->cached_results = null;
		delete_transient( 'assistify_health_results' );
	}

	/**
	 * Get health score status label.
	 *
	 * @param int $score Health score.
	 * @return string Status label.
	 */
	public function get_score_status( $score ) {
		if ( $score >= 90 ) {
			return 'excellent';
		} elseif ( $score >= 70 ) {
			return 'good';
		} elseif ( $score >= 50 ) {
			return 'fair';
		} else {
			return 'poor';
		}
	}

	/**
	 * Get recent failed orders.
	 *
	 * @param int $limit Number of failures to return.
	 * @return array Recent failed orders.
	 */
	public function get_recent_failures( $limit = 10 ) {
		$failures = get_transient( 'assistify_recent_order_failures' );
		if ( ! is_array( $failures ) ) {
			return array();
		}

		return array_slice( array_reverse( $failures ), 0, $limit );
	}

	/**
	 * Get payment success rate.
	 *
	 * @return array Payment statistics.
	 */
	public function get_payment_stats() {
		$stats = get_transient( 'assistify_payment_stats' );
		if ( ! is_array( $stats ) ) {
			return array(
				'success_rate' => 100,
				'total'        => 0,
				'success'      => 0,
				'failed'       => 0,
			);
		}

		$total        = (int) $stats['success'] + (int) $stats['failed'];
		$success_rate = $total > 0 ? round( ( (int) $stats['success'] / $total ) * 100, 1 ) : 100;

		return array(
			'success_rate' => $success_rate,
			'total'        => $total,
			'success'      => $stats['success'],
			'failed'       => $stats['failed'],
			'period_start' => $stats['period_start'] ?? '',
		);
	}
}
