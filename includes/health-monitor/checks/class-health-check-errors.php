<?php
/**
 * Health Check - Errors
 *
 * Intelligent monitoring of PHP errors and WooCommerce logs with
 * specific plugin identification, direct log links, and resolution tracking.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Error Health Check Class
 *
 * @since 1.0.0
 */
class Health_Check_Errors {

	/**
	 * Known plugin paths for identification.
	 *
	 * @var array
	 */
	private static $plugin_paths = array();

	/**
	 * Initialize the check.
	 *
	 * @return void
	 */
	public static function init() {
		$monitor = \Assistify\Health_Monitor\Health_Monitor::get_instance();
		$monitor->register_check(
			'php_errors',
			array(
				'label'    => 'PHP Errors',
				'category' => 'critical',
				'callback' => array( __CLASS__, 'check_php_errors' ),
				'priority' => 1,
			)
		);

		$monitor->register_check(
			'wc_logs',
			array(
				'label'    => 'WooCommerce Logs',
				'category' => 'critical',
				'callback' => array( __CLASS__, 'check_wc_logs' ),
				'priority' => 2,
			)
		);
	}

	/**
	 * Check PHP error log for recent errors.
	 *
	 * @return array Check results.
	 */
	public static function check_php_errors() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check if debug log exists.
		$debug_log = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $debug_log ) ) {
			return $result;
		}

		// Check if file was modified in last 7 days.
		if ( filemtime( $debug_log ) < strtotime( '-7 days' ) ) {
			return $result;
		}

		// Read and parse errors.
		$errors = self::read_log_tail( $debug_log, 200 );
		if ( empty( $errors ) ) {
			return $result;
		}

		$parsed_errors = self::parse_php_errors( $errors );
		$recent_errors = self::filter_recent_errors( $parsed_errors, 24 );

		if ( empty( $recent_errors ) ) {
			return $result;
		}

		// Group errors by source (plugin/theme).
		$grouped_errors = self::group_errors_by_source( $recent_errors );

		// Get resolved issues.
		$resolved = get_option( 'assistify_resolved_issues', array() );

		foreach ( $grouped_errors as $source => $source_data ) {
			// Check if this issue was marked as resolved.
			$issue_key = 'php_error_' . sanitize_key( $source );
			if ( isset( $resolved[ $issue_key ] ) ) {
				// Check if there are NEW errors after resolution.
				$resolved_time = $resolved[ $issue_key ]['time'] ?? 0;
				$has_new       = false;
				foreach ( $source_data['errors'] as $error ) {
					$error_time = strtotime( $error['timestamp'] ?? '' );
					if ( $error_time && $error_time > $resolved_time ) {
						$has_new = true;
						break;
					}
				}
				if ( ! $has_new ) {
					continue;
				}
			}

			$severity = 'warning';
			if ( $source_data['fatal_count'] > 0 ) {
				$severity = 'critical';
			}

			// Build specific message.
			$message = self::build_error_message( $source, $source_data );

			// Build specific fix suggestion.
			$fix = self::build_error_fix( $source, $source_data );

			$result['issues'][] = array(
				'severity'  => $severity,
				'title'     => self::get_error_title( $source, $source_data ),
				'message'   => $message,
				'fix'       => $fix,
				'check_id'  => 'php_errors',
				'issue_key' => $issue_key,
				'data'      => array(
					'source'      => $source,
					'total'       => $source_data['total'],
					'fatal_count' => $source_data['fatal_count'],
					'errors'      => array_slice( $source_data['errors'], 0, 5 ),
					'log_file'    => 'debug.log',
				),
			);
		}

		return $result;
	}

	/**
	 * Check WooCommerce logs for errors.
	 *
	 * @return array Check results.
	 */
	public static function check_wc_logs() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// WooCommerce log directory.
		if ( ! defined( 'WC_LOG_DIR' ) ) {
			return $result;
		}

		$log_dir = WC_LOG_DIR;
		if ( ! is_dir( $log_dir ) ) {
			return $result;
		}

		// Get log files from last 7 days.
		$log_files = glob( $log_dir . '*.log' );
		if ( empty( $log_files ) ) {
			return $result;
		}

		$recent_files = array_filter(
			$log_files,
			function ( $file ) {
				return filemtime( $file ) > strtotime( '-7 days' );
			}
		);

		if ( empty( $recent_files ) ) {
			return $result;
		}

		// Get resolved issues.
		$resolved = get_option( 'assistify_resolved_issues', array() );

		// Analyze each log file.
		foreach ( $recent_files as $log_file ) {
			$file_name   = basename( $log_file, '.log' );
			$file_id     = self::get_wc_log_file_id( $log_file );
			$log_content = self::read_log_tail( $log_file, 300 );

			if ( empty( $log_content ) ) {
				continue;
			}

			// Detect log source (plugin/feature name).
			$log_source = self::identify_log_source( $file_name );

			// Parse for specific error types.
			$analysis = self::analyze_wc_log( $log_content, $log_source );

			if ( empty( $analysis['issues'] ) ) {
				continue;
			}

			foreach ( $analysis['issues'] as $issue ) {
				$issue_key = 'wc_log_' . sanitize_key( $file_name . '_' . $issue['type'] );

				// Check if resolved.
				if ( isset( $resolved[ $issue_key ] ) ) {
					$resolved_time = $resolved[ $issue_key ]['time'] ?? 0;
					$file_mtime    = filemtime( $log_file );
					// Only skip if file hasn't been modified since resolution.
					if ( $file_mtime <= $resolved_time ) {
						continue;
					}
				}

				$result['issues'][] = array(
					'severity'  => $issue['severity'],
					'title'     => $issue['title'],
					'message'   => $issue['message'],
					'fix'       => $issue['fix'],
					'check_id'  => 'wc_logs',
					'issue_key' => $issue_key,
					'data'      => array(
						'log_file'   => $file_name,
						'file_id'    => $file_id,
						'log_source' => $log_source,
						'type'       => $issue['type'],
						'count'      => $issue['count'],
						'samples'    => $issue['samples'] ?? array(),
						'log_url'    => admin_url( 'admin.php?page=wc-status&tab=logs&view=single_file&file_id=' . rawurlencode( $file_id ) ),
					),
				);
			}
		}

		return $result;
	}

	/**
	 * Group errors by source (plugin/theme).
	 *
	 * @param array $errors Parsed errors.
	 * @return array Grouped errors.
	 */
	private static function group_errors_by_source( $errors ) {
		$grouped = array();

		foreach ( $errors as $error ) {
			$file    = $error['file'] ?? '';
			$message = $error['message'] ?? '';
			$source  = self::identify_error_source( $file, $message );

			if ( ! isset( $grouped[ $source ] ) ) {
				$grouped[ $source ] = array(
					'total'       => 0,
					'fatal_count' => 0,
					'warn_count'  => 0,
					'errors'      => array(),
					'files'       => array(),
				);
			}

			++$grouped[ $source ]['total'];

			$type = strtolower( $error['type'] ?? '' );
			if ( strpos( $type, 'fatal' ) !== false ) {
				++$grouped[ $source ]['fatal_count'];
			} elseif ( strpos( $type, 'warning' ) !== false ) {
				++$grouped[ $source ]['warn_count'];
			}

			$grouped[ $source ]['errors'][] = $error;

			if ( ! empty( $file ) && ! in_array( $file, $grouped[ $source ]['files'], true ) ) {
				$grouped[ $source ]['files'][] = $file;
			}
		}

		// Sort by severity (fatal first, then by count).
		uasort(
			$grouped,
			function ( $a, $b ) {
				if ( $a['fatal_count'] !== $b['fatal_count'] ) {
					return $b['fatal_count'] - $a['fatal_count'];
				}
				return $b['total'] - $a['total'];
			}
		);

		return $grouped;
	}

	/**
	 * Identify error source from file path and error message.
	 *
	 * @param string $file    File path.
	 * @param string $message Error message (optional).
	 * @return string Source name.
	 */
	private static function identify_error_source( $file, $message = '' ) {
		// First try to identify from file path.
		if ( ! empty( $file ) ) {
			// Check for plugin.
			if ( preg_match( '/[\/\\\\]plugins[\/\\\\]([^\/\\\\]+)/', $file, $matches ) ) {
				$plugin_slug = $matches[1];
				return self::get_plugin_name( $plugin_slug );
			}

			// Check for theme.
			if ( preg_match( '/[\/\\\\]themes[\/\\\\]([^\/\\\\]+)/', $file, $matches ) ) {
				$theme_slug = $matches[1];
				$theme      = wp_get_theme( $theme_slug );
				if ( $theme->exists() ) {
					return $theme->get( 'Name' ) . ' Theme';
				}
				return ucfirst( $theme_slug ) . ' Theme';
			}

			// Check for WordPress core.
			if ( preg_match( '/[\/\\\\]wp-(?:includes|admin)[\/\\\\]/', $file ) ) {
				return 'WordPress Core';
			}

			// Check for WooCommerce.
			if ( stripos( $file, 'woocommerce' ) !== false ) {
				return 'WooCommerce';
			}
		}

		// Try to identify from error message (namespace or class name).
		if ( ! empty( $message ) ) {
			// Check for Assistify namespace.
			if ( stripos( $message, 'Assistify_For_WooCommerce' ) !== false || stripos( $message, 'Assistify\\' ) !== false ) {
				return 'Assistify for WooCommerce';
			}

			// Check for WooCommerce namespace.
			if ( preg_match( '/\bWC_|\bWooCommerce\b|Automattic\\\\WooCommerce/i', $message ) ) {
				return 'WooCommerce';
			}

			// Check for common plugin namespaces in error message.
			$namespace_patterns = array(
				'Starter Templates' => '/Starter.?Templates|starter-templates/i',
				'Elementor'         => '/Elementor\\\\|\\\\Elementor/i',
				'Jetpack'           => '/Jetpack\\\\|Automattic\\\\Jetpack/i',
				'Yoast SEO'         => '/Yoast\\\\|WPSEO/i',
				'Contact Form 7'    => '/WPCF7/i',
				'WPForms'           => '/WPForms\\\\|wpforms/i',
				'Gravity Forms'     => '/GF_|GFAPI|GFCommon/i',
				'ACF'               => '/ACF\\\\|acf_/i',
				'All in One SEO'    => '/AIOSEO\\\\|aioseo/i',
			);

			foreach ( $namespace_patterns as $name => $pattern ) {
				if ( preg_match( $pattern, $message ) ) {
					return $name;
				}
			}

			// Generic PHP namespace detection.
			if ( preg_match( '/([A-Z][a-z]+(?:_[A-Z][a-z]+)+)\\\\/', $message, $matches ) ) {
				// Convert namespace to readable name.
				return str_replace( '_', ' ', $matches[1] );
			}
		}

		return 'Unknown Source';
	}

	/**
	 * Get plugin name from slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Plugin name.
	 */
	private static function get_plugin_name( $slug ) {
		if ( empty( self::$plugin_paths ) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = get_plugins();
			foreach ( $all_plugins as $path => $data ) {
				$parts                           = explode( '/', $path );
				self::$plugin_paths[ $parts[0] ] = $data['Name'];
			}
		}

		if ( isset( self::$plugin_paths[ $slug ] ) ) {
			return self::$plugin_paths[ $slug ];
		}

		// Fallback: Format slug as name.
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Identify WooCommerce log source from filename.
	 *
	 * @param string $file_name Log filename.
	 * @return array Source info.
	 */
	private static function identify_log_source( $file_name ) {
		// Extract base name (remove date suffix).
		$parts     = explode( '-', $file_name );
		$base_name = $parts[0];

		// Known WooCommerce log sources.
		$known_sources = array(
			'woocommerce'  => array(
				'name' => 'WooCommerce Core',
				'type' => 'core',
			),
			'fatal-errors' => array(
				'name' => 'Fatal Errors',
				'type' => 'error',
			),
			'stripe'       => array(
				'name'    => 'Stripe Payment Gateway',
				'type'    => 'payment',
				'plugin'  => 'woocommerce-gateway-stripe',
				'support' => 'https://woocommerce.com/products/stripe/',
			),
			'paypal'       => array(
				'name'    => 'PayPal Gateway',
				'type'    => 'payment',
				'support' => 'https://woocommerce.com/products/woocommerce-paypal-payments/',
			),
			'ppcp'         => array(
				'name'    => 'PayPal Commerce Platform',
				'type'    => 'payment',
				'support' => 'https://woocommerce.com/products/woocommerce-paypal-payments/',
			),
			'square'       => array(
				'name'    => 'Square Payment Gateway',
				'type'    => 'payment',
				'plugin'  => 'woocommerce-square',
				'support' => 'https://woocommerce.com/products/square/',
			),
			'shipping'     => array(
				'name' => 'Shipping',
				'type' => 'shipping',
			),
			'email'        => array(
				'name' => 'Email',
				'type' => 'email',
			),
			'rest-api'     => array(
				'name' => 'REST API',
				'type' => 'api',
			),
			'webhooks'     => array(
				'name' => 'Webhooks',
				'type' => 'webhook',
			),
			'assistify'    => array(
				'name'    => 'Assistify',
				'type'    => 'assistify',
				'support' => 'https://developer.developer/assistify-support',
			),
		);

		// Check for match.
		foreach ( $known_sources as $key => $source ) {
			if ( strpos( strtolower( $base_name ), $key ) !== false ) {
				$source['key'] = $key;
				return $source;
			}
		}

		// Unknown source.
		return array(
			'name' => ucwords( str_replace( array( '-', '_' ), ' ', $base_name ) ),
			'type' => 'unknown',
			'key'  => $base_name,
		);
	}

	/**
	 * Analyze WooCommerce log content.
	 *
	 * @param string $content    Log content.
	 * @param array  $log_source Log source info.
	 * @return array Analysis results.
	 */
	private static function analyze_wc_log( $content, $log_source ) {
		$issues = array();

		// Count lines for analysis.
		$lines       = explode( "\n", $content );
		$line_count  = count( $lines );
		$error_lines = array();
		$warn_lines  = array();

		foreach ( $lines as $line ) {
			$line_lower = strtolower( $line );

			if ( strpos( $line_lower, 'error' ) !== false || strpos( $line_lower, 'fatal' ) !== false || strpos( $line_lower, 'exception' ) !== false ) {
				$error_lines[] = trim( $line );
			} elseif ( strpos( $line_lower, 'warning' ) !== false || strpos( $line_lower, 'failed' ) !== false ) {
				$warn_lines[] = trim( $line );
			}
		}

		// Analyze based on log type.
		switch ( $log_source['type'] ) {
			case 'payment':
				$issues = array_merge( $issues, self::analyze_payment_log( $content, $error_lines, $log_source ) );
				break;

			case 'email':
				$issues = array_merge( $issues, self::analyze_email_log( $content, $error_lines, $log_source ) );
				break;

			case 'api':
			case 'webhook':
				$issues = array_merge( $issues, self::analyze_api_log( $content, $error_lines, $log_source ) );
				break;

			case 'error':
				$issues = array_merge( $issues, self::analyze_fatal_log( $content, $error_lines, $log_source ) );
				break;

			default:
				// Generic error detection.
				if ( count( $error_lines ) > 0 ) {
					$issues[] = array(
						'type'     => 'generic_error',
						'severity' => count( $error_lines ) >= 10 ? 'critical' : 'warning',
						'title'    => sprintf( '%s Errors', $log_source['name'] ),
						'message'  => sprintf(
							'%d error(s) detected in %s logs.',
							count( $error_lines ),
							$log_source['name']
						),
						'fix'      => self::get_generic_fix( $log_source ),
						'count'    => count( $error_lines ),
						'samples'  => array_slice( $error_lines, 0, 3 ),
					);
				}
		}

		return array( 'issues' => $issues );
	}

	/**
	 * Analyze payment gateway log.
	 *
	 * @param string $content    Log content.
	 * @param array  $errors     Error lines.
	 * @param array  $log_source Log source.
	 * @return array Issues.
	 */
	private static function analyze_payment_log( $content, $errors, $log_source ) {
		$issues = array();

		if ( empty( $errors ) ) {
			return $issues;
		}

		// Detect specific payment issues.
		$auth_errors    = 0;
		$decline_errors = 0;
		$timeout_errors = 0;
		$config_errors  = 0;
		$sample_errors  = array();

		foreach ( $errors as $error ) {
			$error_lower = strtolower( $error );

			if ( strpos( $error_lower, 'authentication' ) !== false || strpos( $error_lower, 'api key' ) !== false || strpos( $error_lower, 'unauthorized' ) !== false ) {
				++$auth_errors;
			} elseif ( strpos( $error_lower, 'declined' ) !== false || strpos( $error_lower, 'insufficient' ) !== false ) {
				++$decline_errors;
			} elseif ( strpos( $error_lower, 'timeout' ) !== false || strpos( $error_lower, 'timed out' ) !== false ) {
				++$timeout_errors;
			} elseif ( strpos( $error_lower, 'configuration' ) !== false || strpos( $error_lower, 'invalid' ) !== false ) {
				++$config_errors;
			}

			if ( count( $sample_errors ) < 3 ) {
				$sample_errors[] = substr( $error, 0, 200 );
			}
		}

		// Build specific issues.
		if ( $auth_errors > 0 ) {
			$gateway_name = $log_source['name'];
			$settings_url = self::get_payment_settings_url( $log_source );

			$issues[] = array(
				'type'     => 'payment_auth',
				'severity' => 'critical',
				'title'    => sprintf( '%s Authentication Errors', $gateway_name ),
				'message'  => sprintf(
					'%d authentication error(s) detected. Your API credentials may be invalid or expired.',
					$auth_errors
				),
				'fix'      => sprintf(
					'1. Go to WooCommerce > Settings > Payments > %s. 2. Verify your API keys are correct. 3. Ensure your account is active with the payment provider.',
					$gateway_name
				),
				'count'    => $auth_errors,
				'samples'  => $sample_errors,
				'data'     => array( 'settings_url' => $settings_url ),
			);
		}

		if ( $timeout_errors > 3 ) {
			$issues[] = array(
				'type'     => 'payment_timeout',
				'severity' => 'warning',
				'title'    => sprintf( '%s Connection Timeouts', $log_source['name'] ),
				'message'  => sprintf(
					'%d timeout error(s) detected. This may cause failed checkouts.',
					$timeout_errors
				),
				'fix'      => 'Check your server\'s ability to connect to ' . $log_source['name'] . '. Contact your hosting provider if timeouts persist.',
				'count'    => $timeout_errors,
				'samples'  => $sample_errors,
			);
		}

		if ( $config_errors > 0 ) {
			$issues[] = array(
				'type'     => 'payment_config',
				'severity' => 'warning',
				'title'    => sprintf( '%s Configuration Issues', $log_source['name'] ),
				'message'  => sprintf(
					'%d configuration error(s) detected. Gateway settings may need review.',
					$config_errors
				),
				'fix'      => 'Review your ' . $log_source['name'] . ' settings in WooCommerce > Settings > Payments.',
				'count'    => $config_errors,
				'samples'  => $sample_errors,
			);
		}

		return $issues;
	}

	/**
	 * Analyze email log.
	 *
	 * @param string $content    Log content.
	 * @param array  $errors     Error lines.
	 * @param array  $log_source Log source (reserved for future use).
	 * @return array Issues.
	 */
	private static function analyze_email_log( $content, $errors, $log_source ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$issues = array();

		if ( empty( $errors ) ) {
			return $issues;
		}

		$delivery_errors = 0;
		$smtp_errors     = 0;
		$sample_errors   = array();

		foreach ( $errors as $error ) {
			$error_lower = strtolower( $error );

			if ( strpos( $error_lower, 'smtp' ) !== false || strpos( $error_lower, 'mail' ) !== false ) {
				++$smtp_errors;
			} else {
				++$delivery_errors;
			}

			if ( count( $sample_errors ) < 3 ) {
				$sample_errors[] = substr( $error, 0, 200 );
			}
		}

		if ( $smtp_errors > 0 || $delivery_errors > 0 ) {
			$issues[] = array(
				'type'     => 'email_delivery',
				'severity' => 'warning',
				'title'    => 'Email Delivery Errors',
				'message'  => sprintf(
					'%d email error(s) detected. Order confirmation and notification emails may not be sent.',
					$smtp_errors + $delivery_errors
				),
				'fix'      => 'Consider installing an SMTP plugin (like WP Mail SMTP) to improve email deliverability. Check WooCommerce > Settings > Emails.',
				'count'    => $smtp_errors + $delivery_errors,
				'samples'  => $sample_errors,
			);
		}

		return $issues;
	}

	/**
	 * Analyze API/webhook log.
	 *
	 * @param string $content    Log content.
	 * @param array  $errors     Error lines.
	 * @param array  $log_source Log source.
	 * @return array Issues.
	 */
	private static function analyze_api_log( $content, $errors, $log_source ) {
		$issues = array();

		if ( empty( $errors ) ) {
			return $issues;
		}

		$issues[] = array(
			'type'     => 'api_errors',
			'severity' => count( $errors ) >= 10 ? 'critical' : 'warning',
			'title'    => sprintf( '%s Errors', $log_source['name'] ),
			'message'  => sprintf(
				'%d error(s) in %s. Third-party integrations may be affected.',
				count( $errors ),
				$log_source['name']
			),
			'fix'      => 'Check API credentials and webhook URLs in WooCommerce > Settings > Advanced.',
			'count'    => count( $errors ),
			'samples'  => array_slice( $errors, 0, 3 ),
		);

		return $issues;
	}

	/**
	 * Analyze fatal errors log.
	 *
	 * @param string $content    Log content.
	 * @param array  $errors     Error lines.
	 * @param array  $log_source Log source (reserved for future use).
	 * @return array Issues.
	 */
	private static function analyze_fatal_log( $content, $errors, $log_source ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$issues = array();

		if ( empty( $errors ) ) {
			return $issues;
		}

		// Extract plugin/file info from fatal errors.
		$sources = array();
		foreach ( $errors as $error ) {
			if ( preg_match( '/plugins[\/\\\\]([^\/\\\\]+)/', $error, $matches ) ) {
				$plugin_slug = $matches[1];
				if ( ! isset( $sources[ $plugin_slug ] ) ) {
					$sources[ $plugin_slug ] = 0;
				}
				++$sources[ $plugin_slug ];
			}
		}

		if ( ! empty( $sources ) ) {
			arsort( $sources );
			$top_plugin  = key( $sources );
			$plugin_name = self::get_plugin_name( $top_plugin );

			$issues[] = array(
				'type'     => 'fatal_errors',
				'severity' => 'critical',
				'title'    => 'Fatal Errors Detected',
				'message'  => sprintf(
					'%d fatal error(s) detected. Most are from: %s (%d errors).',
					count( $errors ),
					$plugin_name,
					$sources[ $top_plugin ]
				),
				'fix'      => sprintf(
					'1. Deactivate %s temporarily to confirm it\'s the cause. 2. Check for plugin updates. 3. Contact the plugin developer or try an alternative.',
					$plugin_name
				),
				'count'    => count( $errors ),
				'samples'  => array_slice( $errors, 0, 3 ),
				'data'     => array( 'sources' => $sources ),
			);
		} else {
			$issues[] = array(
				'type'     => 'fatal_errors',
				'severity' => 'critical',
				'title'    => 'Fatal Errors Detected',
				'message'  => sprintf( '%d fatal error(s) detected in logs.', count( $errors ) ),
				'fix'      => 'Review the fatal errors log for details. Consider enabling WP_DEBUG for more information.',
				'count'    => count( $errors ),
				'samples'  => array_slice( $errors, 0, 3 ),
			);
		}

		return $issues;
	}

	/**
	 * Get payment gateway settings URL.
	 *
	 * @param array $log_source Log source info.
	 * @return string Settings URL.
	 */
	private static function get_payment_settings_url( $log_source ) {
		$key = $log_source['key'] ?? '';
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $key );
	}

	/**
	 * Get generic fix suggestion.
	 *
	 * @param array $log_source Log source info.
	 * @return string Fix suggestion.
	 */
	private static function get_generic_fix( $log_source ) {
		if ( ! empty( $log_source['support'] ) ) {
			return sprintf(
				'Review the log file for details. For help, visit: %s',
				$log_source['support']
			);
		}
		return 'Review the log file for specific error details and contact the relevant plugin developer if needed.';
	}

	/**
	 * Get WooCommerce log file ID for direct linking.
	 *
	 * @param string $file_path Log file path.
	 * @return string File ID.
	 */
	private static function get_wc_log_file_id( $file_path ) {
		$file_name = basename( $file_path, '.log' );
		// WooCommerce uses the filename without .log as the file_id.
		return $file_name;
	}

	/**
	 * Build error message for PHP errors.
	 *
	 * @param string $source      Error source.
	 * @param array  $source_data Error data.
	 * @return string Message.
	 */
	private static function build_error_message( $source, $source_data ) {
		$parts = array();

		if ( $source_data['fatal_count'] > 0 ) {
			$parts[] = sprintf( '%d fatal', $source_data['fatal_count'] );
		}
		if ( $source_data['warn_count'] > 0 ) {
			$parts[] = sprintf( '%d warning(s)', $source_data['warn_count'] );
		}

		$error_summary = implode( ', ', $parts );
		if ( empty( $error_summary ) ) {
			$error_summary = sprintf( '%d error(s)', $source_data['total'] );
		}

		// Get a sample error message.
		$sample = '';
		if ( ! empty( $source_data['errors'][0]['message'] ) ) {
			$sample = ': "' . substr( $source_data['errors'][0]['message'], 0, 100 ) . '..."';
		}

		return sprintf( '%s in the last 24 hours%s', $error_summary, $sample );
	}

	/**
	 * Build fix suggestion for PHP errors.
	 *
	 * @param string $source      Error source.
	 * @param array  $source_data Error data (reserved for future use).
	 * @return string Fix suggestion.
	 */
	private static function build_error_fix( $source, $source_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( strpos( $source, 'Theme' ) !== false ) {
			return sprintf(
				'1. Check for theme updates. 2. Switch to a default theme temporarily to confirm. 3. Contact theme developer: Look for support link in Appearance > Themes.'
			);
		}

		if ( 'WordPress Core' === $source ) {
			return 'This is a WordPress core issue. Make sure WordPress is up to date. If persists, check WordPress.org support forums.';
		}

		if ( 'WooCommerce' === $source ) {
			return 'Update WooCommerce to the latest version. Check WooCommerce > Status > System Status for compatibility issues.';
		}

		// Plugin error.
		return sprintf(
			'1. Update %s to the latest version. 2. If error persists, deactivate temporarily. 3. Contact plugin support or check the plugin page for known issues.',
			$source
		);
	}

	/**
	 * Get error title.
	 *
	 * @param string $source      Error source.
	 * @param array  $source_data Error data.
	 * @return string Title.
	 */
	private static function get_error_title( $source, $source_data ) {
		if ( $source_data['fatal_count'] > 0 ) {
			return sprintf( 'Fatal Errors in %s', $source );
		}
		return sprintf( 'PHP Warnings in %s', $source );
	}

	/**
	 * Read last N lines from a log file.
	 *
	 * @param string $file  File path.
	 * @param int    $lines Number of lines.
	 * @return string Log content.
	 */
	private static function read_log_tail( $file, $lines = 100 ) {
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return '';
		}

		// Use tail command if available (faster for large files).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', explode( ',', ini_get( 'disable_functions' ) ), true ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
			$output = shell_exec( 'tail -n ' . (int) $lines . ' ' . escapeshellarg( $file ) . ' 2>/dev/null' );
			if ( null !== $output ) {
				return $output;
			}
		}

		// Fallback: Read file in PHP.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file );
		if ( false === $content ) {
			return '';
		}

		$all_lines = explode( "\n", $content );
		return implode( "\n", array_slice( $all_lines, -$lines ) );
	}

	/**
	 * Parse PHP errors from log content.
	 *
	 * @param string $content Log content.
	 * @return array Parsed errors.
	 */
	private static function parse_php_errors( $content ) {
		$errors = array();

		// Pattern for PHP errors.
		$pattern = '/\[(\d{2}-\w{3}-\d{4}\s+[\d:]+\s+\w+)\]\s+(PHP\s+[\w\s]+):\s+(.+?)(?:\s+in\s+(.+?)\s+on\s+line\s+(\d+))?$/m';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$errors[] = array(
					'timestamp' => $match[1] ?? '',
					'type'      => $match[2] ?? 'Unknown',
					'message'   => $match[3] ?? '',
					'file'      => $match[4] ?? '',
					'line'      => $match[5] ?? 0,
				);
			}
		}

		return $errors;
	}

	/**
	 * Filter errors to only include recent ones.
	 *
	 * @param array $errors Parsed errors.
	 * @param int   $hours  Hours to look back.
	 * @return array Filtered errors.
	 */
	private static function filter_recent_errors( $errors, $hours = 24 ) {
		$cutoff = strtotime( "-{$hours} hours" );

		return array_filter(
			$errors,
			function ( $error ) use ( $cutoff ) {
				$timestamp = strtotime( $error['timestamp'] ?? '' );
				return false !== $timestamp && $timestamp >= $cutoff;
			}
		);
	}
}

// Initialize the check.
Health_Check_Errors::init();
