<?php
/**
 * Health Check - Orders
 *
 * Monitors failed orders, payment issues, and order anomalies.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Order Health Check Class
 *
 * @since 1.0.0
 */
class Health_Check_Orders {

	/**
	 * Initialize the check.
	 *
	 * @return void
	 */
	public static function init() {
		$monitor = \Assistify\Health_Monitor\Health_Monitor::get_instance();

		$monitor->register_check(
			'failed_orders',
			array(
				'label'    => 'Failed Orders',
				'category' => 'critical',
				'callback' => array( __CLASS__, 'check_failed_orders' ),
				'priority' => 1,
			)
		);

		$monitor->register_check(
			'payment_success_rate',
			array(
				'label'    => 'Payment Success Rate',
				'category' => 'critical',
				'callback' => array( __CLASS__, 'check_payment_success_rate' ),
				'priority' => 2,
			)
		);

		$monitor->register_check(
			'order_anomalies',
			array(
				'label'    => 'Order Anomalies',
				'category' => 'business',
				'callback' => array( __CLASS__, 'check_order_anomalies' ),
				'priority' => 10,
			)
		);
	}

	/**
	 * Check for recent failed orders.
	 *
	 * @return array Check results.
	 */
	public static function check_failed_orders() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Get failed orders in last 24 hours.
		$failed_orders = wc_get_orders(
			array(
				'status'       => 'failed',
				'date_created' => '>' . strtotime( '-24 hours' ),
				'limit'        => 50,
			)
		);

		$failure_count = count( $failed_orders );

		if ( 0 === $failure_count ) {
			return $result;
		}

		// Analyze failures by gateway.
		$by_gateway      = array();
		$by_reason       = array();
		$failure_details = array();

		foreach ( $failed_orders as $order ) {
			$gateway_title = $order->get_payment_method_title();
			$gateway       = ! empty( $gateway_title ) ? $gateway_title : 'Unknown';

			if ( ! isset( $by_gateway[ $gateway ] ) ) {
				$by_gateway[ $gateway ] = 0;
			}
			++$by_gateway[ $gateway ];

			// Try to find failure reason from order notes.
			$reason = self::get_failure_reason( $order );
			if ( ! isset( $by_reason[ $reason ] ) ) {
				$by_reason[ $reason ] = 0;
			}
			++$by_reason[ $reason ];

			$failure_details[] = array(
				'order_id' => $order->get_id(),
				'gateway'  => $gateway,
				'total'    => $order->get_total(),
				'currency' => $order->get_currency(),
				'reason'   => $reason,
				'date'     => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			);
		}

		// Determine severity based on failure count.
		$severity = 'warning';
		if ( $failure_count >= 5 ) {
			$severity = 'critical';
		}

		// Find most common gateway with failures.
		arsort( $by_gateway );
		$top_gateway       = key( $by_gateway );
		$top_gateway_count = current( $by_gateway );

		// Build fix suggestion.
		$fix = self::get_fix_suggestion( $by_gateway, $by_reason );

		$result['issues'][] = array(
			'severity' => $severity,
			'title'    => 'Failed Orders Detected',
			'message'  => sprintf(
				'%d failed order(s) in the last 24 hours. Most failures: %s (%d).',
				$failure_count,
				$top_gateway,
				$top_gateway_count
			),
			'fix'      => $fix,
			'data'     => array(
				'total_failures' => $failure_count,
				'by_gateway'     => $by_gateway,
				'by_reason'      => $by_reason,
				'recent_orders'  => array_slice( $failure_details, 0, 10 ),
			),
		);

		return $result;
	}

	/**
	 * Get failure reason from order notes.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string Failure reason category.
	 */
	private static function get_failure_reason( $order ) {
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'limit'    => 10,
			)
		);

		$reason_patterns = array(
			'declined'     => array( 'declined', 'card declined', 'do not honor' ),
			'insufficient' => array( 'insufficient', 'not enough', 'low balance' ),
			'expired'      => array( 'expired', 'expiry', 'expiration' ),
			'invalid'      => array( 'invalid', 'incorrect', 'wrong' ),
			'fraud'        => array( 'fraud', 'suspicious', 'blocked' ),
			'timeout'      => array( 'timeout', 'timed out', 'connection' ),
			'auth'         => array( 'authentication', '3d secure', 'verify' ),
			'limit'        => array( 'limit', 'exceeded', 'maximum' ),
		);

		foreach ( $notes as $note ) {
			$content = strtolower( $note->content );

			foreach ( $reason_patterns as $reason => $patterns ) {
				foreach ( $patterns as $pattern ) {
					if ( strpos( $content, $pattern ) !== false ) {
						return ucfirst( $reason );
					}
				}
			}
		}

		return 'Unknown';
	}

	/**
	 * Get fix suggestion based on failure analysis.
	 *
	 * @param array $by_gateway Failures by gateway.
	 * @param array $by_reason  Failures by reason.
	 * @return string Fix suggestion.
	 */
	private static function get_fix_suggestion( $by_gateway, $by_reason ) {
		// Most common reason.
		arsort( $by_reason );
		$top_reason = key( $by_reason );

		$suggestions = array(
			'Declined'     => 'Customers\' cards are being declined. This is usually a bank issue, not your store.',
			'Insufficient' => 'Customers have insufficient funds. Consider offering alternative payment methods.',
			'Expired'      => 'Expired card errors detected. Ensure your checkout validates card expiry.',
			'Invalid'      => 'Invalid payment details. Review your checkout form for proper validation.',
			'Fraud'        => 'Fraud protection is blocking transactions. Review gateway fraud settings.',
			'Timeout'      => 'Payment requests timing out. Check server performance and gateway status.',
			'Auth'         => '3D Secure authentication failing. Check your gateway\'s authentication settings.',
			'Limit'        => 'Transaction limits exceeded. Review gateway limits and settings.',
			'Unknown'      => 'Check payment gateway settings and review order notes for specific errors.',
		);

		$fix = $suggestions[ $top_reason ] ?? $suggestions['Unknown'];

		// Add gateway-specific advice if concentrated.
		$total_failures  = array_sum( $by_gateway );
		$top_gateway_pct = ( current( $by_gateway ) / $total_failures ) * 100;

		if ( $top_gateway_pct >= 80 ) {
			$fix .= sprintf( ' Most failures are from %s - verify API credentials.', key( $by_gateway ) );
		}

		return $fix;
	}

	/**
	 * Check payment success rate.
	 *
	 * @return array Check results.
	 */
	public static function check_payment_success_rate() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		$monitor = \Assistify\Health_Monitor\Health_Monitor::get_instance();
		$stats   = $monitor->get_payment_stats();

		// Also calculate from recent orders if no stats.
		if ( 0 === $stats['total'] ) {
			$stats = self::calculate_payment_stats();
		}

		if ( 0 === $stats['total'] ) {
			return $result;
		}

		$success_rate = $stats['success_rate'];

		// Alert if success rate drops below 90%.
		if ( $success_rate < 90 && $stats['total'] >= 10 ) {
			$severity = 'warning';
			if ( $success_rate < 80 ) {
				$severity = 'critical';
			}

			$result['issues'][] = array(
				'severity' => $severity,
				'title'    => 'Low Payment Success Rate',
				'message'  => sprintf(
					'Payment success rate is %.1f%% (%d of %d orders).',
					$success_rate,
					$stats['success'],
					$stats['total']
				),
				'fix'      => 'Review payment gateway configuration and check for API issues.',
				'data'     => $stats,
			);
		}

		return $result;
	}

	/**
	 * Calculate payment stats from recent orders.
	 *
	 * @return array Payment statistics.
	 */
	private static function calculate_payment_stats() {
		$args = array(
			'date_created' => '>' . strtotime( '-7 days' ),
			'limit'        => 500,
			'status'       => array( 'completed', 'processing', 'on-hold', 'failed', 'cancelled' ),
		);

		$orders = wc_get_orders( $args );

		$success = 0;
		$failed  = 0;

		foreach ( $orders as $order ) {
			$status = $order->get_status();
			if ( in_array( $status, array( 'completed', 'processing', 'on-hold' ), true ) ) {
				++$success;
			} elseif ( 'failed' === $status ) {
				++$failed;
			}
		}

		$total        = $success + $failed;
		$success_rate = $total > 0 ? round( ( $success / $total ) * 100, 1 ) : 100;

		return array(
			'success_rate' => $success_rate,
			'total'        => $total,
			'success'      => $success,
			'failed'       => $failed,
		);
	}

	/**
	 * Check for order anomalies (unusual patterns).
	 *
	 * @return array Check results.
	 */
	public static function check_order_anomalies() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Compare today's orders vs same day last week.
		$today_count     = self::get_order_count_for_period( 'today' );
		$last_week_count = self::get_order_count_for_period( 'last_week_same_day' );

		// Only check if there's enough data.
		if ( $last_week_count < 5 ) {
			return $result;
		}

		// Calculate change percentage.
		$change_pct = 0;
		if ( $last_week_count > 0 ) {
			$change_pct = ( ( $today_count - $last_week_count ) / $last_week_count ) * 100;
		}

		// Alert if significant drop (more than 50%).
		if ( $change_pct <= -50 ) {
			$result['issues'][] = array(
				'severity' => 'warning',
				'title'    => 'Significant Drop in Orders',
				'message'  => sprintf(
					'Orders today (%d) are down %.0f%% compared to same day last week (%d).',
					$today_count,
					abs( $change_pct ),
					$last_week_count
				),
				'fix'      => 'Check if there are any site issues, marketing changes, or external factors.',
				'data'     => array(
					'today'      => $today_count,
					'last_week'  => $last_week_count,
					'change_pct' => $change_pct,
				),
			);
		}

		// Check for unusually high failed order rate today.
		$today_failed = self::get_order_count_for_period( 'today', array( 'failed' ) );
		$failed_rate  = $today_count > 0 ? ( $today_failed / ( $today_count + $today_failed ) ) * 100 : 0;

		if ( $failed_rate > 20 && ( $today_count + $today_failed ) >= 5 ) {
			$result['issues'][] = array(
				'severity' => 'warning',
				'title'    => 'High Failed Order Rate Today',
				'message'  => sprintf(
					'%.0f%% of orders today have failed (%d out of %d).',
					$failed_rate,
					$today_failed,
					$today_count + $today_failed
				),
				'fix'      => 'Check payment gateway status and review recent failed orders.',
				'data'     => array(
					'failed_count' => $today_failed,
					'total_count'  => $today_count + $today_failed,
					'failed_rate'  => $failed_rate,
				),
			);
		}

		return $result;
	}

	/**
	 * Get order count for a specific period.
	 *
	 * @param string $period  Period identifier.
	 * @param array  $status  Order statuses to count.
	 * @return int Order count.
	 */
	private static function get_order_count_for_period( $period, $status = array( 'completed', 'processing', 'on-hold' ) ) {
		$args = array(
			'status' => $status,
			'limit'  => -1,
			'return' => 'ids',
		);

		switch ( $period ) {
			case 'today':
				$args['date_created'] = '>=' . strtotime( 'today midnight' );
				break;

			case 'last_week_same_day':
				$day_start            = strtotime( '-7 days midnight' );
				$day_end              = strtotime( '-7 days 23:59:59' );
				$args['date_created'] = $day_start . '...' . $day_end;
				break;
		}

		$orders = wc_get_orders( $args );
		return count( $orders );
	}

	/**
	 * Get failed order details for reporting.
	 *
	 * @param int $limit Number of orders to return.
	 * @return array Failed order details.
	 */
	public static function get_failed_order_details( $limit = 10 ) {
		$orders = wc_get_orders(
			array(
				'status'  => 'failed',
				'limit'   => $limit,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$details = array();

		foreach ( $orders as $order ) {
			$notes = wc_get_order_notes(
				array(
					'order_id' => $order->get_id(),
					'limit'    => 5,
				)
			);

			$failure_notes = array();
			foreach ( $notes as $note ) {
				$content = strtolower( $note->content );
				if ( strpos( $content, 'fail' ) !== false ||
					strpos( $content, 'error' ) !== false ||
					strpos( $content, 'declined' ) !== false ) {
					$failure_notes[] = $note->content;
				}
			}

			$details[] = array(
				'order_id'       => $order->get_id(),
				'order_url'      => $order->get_edit_order_url(),
				'date'           => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
				'total'          => $order->get_total(),
				'currency'       => $order->get_currency(),
				'payment_method' => $order->get_payment_method_title(),
				'customer_email' => $order->get_billing_email(),
				'customer_ip'    => $order->get_customer_ip_address(),
				'failure_reason' => self::get_failure_reason( $order ),
				'notes'          => $failure_notes,
			);
		}

		return $details;
	}
}

// Initialize the check.
Health_Check_Orders::init();
