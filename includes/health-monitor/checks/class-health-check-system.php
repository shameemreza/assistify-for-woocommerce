<?php
/**
 * Health Check - System
 *
 * Monitors server health, database, and WordPress environment.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * System Health Check Class
 *
 * @since 1.0.0
 */
class Health_Check_System {

	/**
	 * Initialize the check.
	 *
	 * @return void
	 */
	public static function init() {
		$monitor = \Assistify\Health_Monitor\Health_Monitor::get_instance();

		$monitor->register_check(
			'php_environment',
			array(
				'label'    => 'PHP Environment',
				'category' => 'performance',
				'callback' => array( __CLASS__, 'check_php_environment' ),
				'priority' => 5,
			)
		);

		$monitor->register_check(
			'database_health',
			array(
				'label'    => 'Database Health',
				'category' => 'performance',
				'callback' => array( __CLASS__, 'check_database_health' ),
				'priority' => 6,
			)
		);

		$monitor->register_check(
			'cron_health',
			array(
				'label'    => 'Cron Jobs',
				'category' => 'performance',
				'callback' => array( __CLASS__, 'check_cron_health' ),
				'priority' => 7,
			)
		);

		$monitor->register_check(
			'disk_space',
			array(
				'label'    => 'Disk Space',
				'category' => 'performance',
				'callback' => array( __CLASS__, 'check_disk_space' ),
				'priority' => 8,
			)
		);
	}

	/**
	 * Check PHP environment.
	 *
	 * @return array Check results.
	 */
	public static function check_php_environment() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check PHP version.
		$php_version     = PHP_VERSION;
		$min_php         = '7.4';
		$recommended_php = '8.1';

		if ( version_compare( $php_version, $min_php, '<' ) ) {
			$result['issues'][] = array(
				'severity' => 'critical',
				'title'    => 'PHP Version Too Old',
				'message'  => sprintf( 'PHP %s is installed. Minimum required: %s.', $php_version, $min_php ),
				'fix'      => 'Contact your hosting provider to upgrade PHP.',
			);
		} elseif ( version_compare( $php_version, $recommended_php, '<' ) ) {
			$result['issues'][] = array(
				'severity' => 'info',
				'title'    => 'PHP Version Update Available',
				'message'  => sprintf( 'PHP %s is installed. PHP %s+ is recommended.', $php_version, $recommended_php ),
				'fix'      => 'Consider upgrading PHP for better performance.',
			);
		}

		// Check memory limit.
		$memory_limit       = self::get_memory_limit_bytes();
		$min_memory         = 128 * 1024 * 1024; // 128MB.
		$recommended_memory = 256 * 1024 * 1024; // 256MB.

		if ( $memory_limit > 0 && $memory_limit < $min_memory ) {
			$result['issues'][] = array(
				'severity' => 'warning',
				'title'    => 'Low PHP Memory Limit',
				'message'  => sprintf( 'Memory limit is %s. Minimum recommended: 128MB.', size_format( $memory_limit ) ),
				'fix'      => 'Increase memory_limit in php.ini or contact hosting provider.',
			);
		}

		// Check max execution time.
		$max_execution = (int) ini_get( 'max_execution_time' );
		if ( $max_execution > 0 && $max_execution < 30 ) {
			$result['issues'][] = array(
				'severity' => 'warning',
				'title'    => 'Low Max Execution Time',
				'message'  => sprintf( 'Max execution time is %ds. Some operations may timeout.', $max_execution ),
				'fix'      => 'Increase max_execution_time to at least 60 seconds.',
			);
		}

		// Check upload size.
		$upload_max = wp_max_upload_size();
		$min_upload = 8 * 1024 * 1024; // 8MB.

		if ( $upload_max < $min_upload ) {
			$result['issues'][] = array(
				'severity' => 'info',
				'title'    => 'Low Upload Limit',
				'message'  => sprintf( 'Max upload size is %s.', size_format( $upload_max ) ),
				'fix'      => 'Increase upload_max_filesize in php.ini for larger product images.',
			);
		}

		return $result;
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int Memory limit in bytes.
	 */
	private static function get_memory_limit_bytes() {
		$memory_limit = ini_get( 'memory_limit' );

		if ( '-1' === $memory_limit ) {
			return -1; // Unlimited.
		}

		$unit  = strtolower( substr( $memory_limit, -1 ) );
		$value = (int) $memory_limit;

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}

	/**
	 * Check database health.
	 *
	 * @return array Check results.
	 */
	public static function check_database_health() {
		global $wpdb;

		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check for autoloaded options size.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$autoload_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) 
			FROM {$wpdb->options} 
			WHERE autoload = 'yes'"
		);

		if ( $autoload_size > 1000000 ) { // 1MB.
			$result['issues'][] = array(
				'severity' => 'warning',
				'title'    => 'Large Autoloaded Options',
				'message'  => sprintf( 'Autoloaded options are %s. This can slow down page loads.', size_format( $autoload_size ) ),
				'fix'      => 'Review autoloaded options and clean up unused transients.',
				'data'     => array( 'size' => $autoload_size ),
			);
		}

		// Check for orphaned postmeta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orphaned_meta = $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$wpdb->postmeta} pm 
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
			WHERE p.ID IS NULL"
		);

		if ( $orphaned_meta > 1000 ) {
			$result['issues'][] = array(
				'severity' => 'info',
				'title'    => 'Orphaned Post Meta',
				'message'  => sprintf( '%s orphaned postmeta rows found.', number_format( $orphaned_meta ) ),
				'fix'      => 'Consider running database cleanup to remove orphaned data.',
				'data'     => array( 'count' => $orphaned_meta ),
			);
		}

		// Check for expired transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$expired_transients = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		if ( $expired_transients > 100 ) {
			$result['issues'][] = array(
				'severity' => 'info',
				'title'    => 'Expired Transients',
				'message'  => sprintf( '%d expired transients in database.', $expired_transients ),
				'fix'      => 'Expired transients will be cleaned automatically, but you can manually clear them.',
				'data'     => array( 'count' => $expired_transients ),
			);
		}

		// Check database connection.
		$can_connect = $wpdb->check_connection( false );
		if ( ! $can_connect ) {
			$result['issues'][] = array(
				'severity' => 'critical',
				'title'    => 'Database Connection Issues',
				'message'  => 'Unable to establish a stable database connection.',
				'fix'      => 'Contact your hosting provider immediately.',
			);
		}

		return $result;
	}

	/**
	 * Check WordPress cron health.
	 *
	 * @return array Check results.
	 */
	public static function check_cron_health() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check if cron is disabled.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			// Check if we have a server cron.
			$last_cron = get_option( 'assistify_last_cron_run', 0 );
			$cron_age  = time() - $last_cron;

			if ( $last_cron > 0 && $cron_age > 3600 ) {
				$result['issues'][] = array(
					'severity' => 'warning',
					'title'    => 'WP-Cron May Not Be Running',
					'message'  => 'WP-Cron is disabled and last run was over an hour ago.',
					'fix'      => 'Verify your server cron is configured correctly.',
				);
			}
		}

		// Check for overdue scheduled events.
		$crons            = _get_cron_array();
		$overdue_count    = 0;
		$critical_overdue = array();

		if ( ! empty( $crons ) ) {
			$now = time();
			foreach ( $crons as $timestamp => $cron ) {
				if ( $timestamp < $now - 3600 ) { // More than 1 hour overdue.
					$overdue_count += count( $cron );

					// Track critical WooCommerce crons.
					foreach ( $cron as $hook => $data ) {
						if ( strpos( $hook, 'woocommerce' ) !== false || strpos( $hook, 'wc_' ) !== false ) {
							$critical_overdue[] = $hook;
						}
					}
				}
			}
		}

		if ( ! empty( $critical_overdue ) ) {
			$result['issues'][] = array(
				'severity' => 'warning',
				'title'    => 'Overdue WooCommerce Tasks',
				'message'  => sprintf( '%d WooCommerce cron task(s) are overdue.', count( $critical_overdue ) ),
				'fix'      => 'Check if WP-Cron is running properly. Visit the site or set up a server cron.',
				'data'     => array( 'tasks' => array_slice( array_unique( $critical_overdue ), 0, 5 ) ),
			);
		} elseif ( $overdue_count > 10 ) {
			$result['issues'][] = array(
				'severity' => 'info',
				'title'    => 'Scheduled Tasks Backlog',
				'message'  => sprintf( '%d scheduled task(s) are overdue.', $overdue_count ),
				'fix'      => 'This is usually temporary. If persistent, check cron configuration.',
			);
		}

		// Track our own cron execution.
		update_option( 'assistify_last_cron_run', time(), false );

		return $result;
	}

	/**
	 * Check disk space.
	 *
	 * @return array Check results.
	 */
	public static function check_disk_space() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check uploads directory.
		$upload_dir  = wp_upload_dir();
		$upload_path = $upload_dir['basedir'];

		if ( function_exists( 'disk_free_space' ) && is_dir( $upload_path ) ) {
			$free_space = @disk_free_space( $upload_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false !== $free_space ) {
				$free_mb = $free_space / ( 1024 * 1024 );

				if ( $free_mb < 100 ) {
					$result['issues'][] = array(
						'severity' => 'critical',
						'title'    => 'Low Disk Space',
						'message'  => sprintf( 'Only %s free disk space remaining.', size_format( $free_space ) ),
						'fix'      => 'Clean up old files or upgrade your hosting plan.',
						'data'     => array( 'free_bytes' => $free_space ),
					);
				} elseif ( $free_mb < 500 ) {
					$result['issues'][] = array(
						'severity' => 'warning',
						'title'    => 'Disk Space Running Low',
						'message'  => sprintf( '%s free disk space remaining.', size_format( $free_space ) ),
						'fix'      => 'Consider cleaning up unused media files.',
						'data'     => array( 'free_bytes' => $free_space ),
					);
				}
			}
		}

		// Check WooCommerce log directory size.
		if ( defined( 'WC_LOG_DIR' ) && is_dir( WC_LOG_DIR ) ) {
			$log_size = self::get_directory_size( WC_LOG_DIR );

			if ( $log_size > 100 * 1024 * 1024 ) { // 100MB.
				$result['issues'][] = array(
					'severity' => 'info',
					'title'    => 'Large WooCommerce Logs',
					'message'  => sprintf( 'WooCommerce logs are using %s of disk space.', size_format( $log_size ) ),
					'fix'      => 'Consider clearing old log files from WooCommerce > Status > Logs.',
					'data'     => array( 'size' => $log_size ),
				);
			}
		}

		return $result;
	}

	/**
	 * Get directory size.
	 *
	 * @param string $path Directory path.
	 * @return int Size in bytes.
	 */
	private static function get_directory_size( $path ) {
		$size = 0;

		if ( ! is_dir( $path ) ) {
			return $size;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			$size += $file->getSize();
		}

		return $size;
	}
}

// Initialize the check.
Health_Check_System::init();
