<?php
/**
 * Assistify Logger Class
 *
 * Handles debug logging with WooCommerce integration.
 * Logs are saved to WooCommerce > Status > Logs when enabled.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class for Assistify.
 *
 * @since 1.0.0
 */
class Assistify_Logger {

	/**
	 * Log source identifier.
	 *
	 * @var string
	 */
	const LOG_SOURCE = 'assistify';

	/**
	 * WooCommerce logger instance.
	 *
	 * @var \WC_Logger|null
	 */
	private static $logger = null;

	/**
	 * Whether logging is enabled.
	 *
	 * @var bool|null
	 */
	private static $enabled = null;

	/**
	 * Check if debug logging is enabled.
	 *
	 * @return bool True if logging is enabled.
	 */
	public static function is_enabled() {
		if ( null === self::$enabled ) {
			self::$enabled = 'yes' === get_option( 'assistify_debug_logging', 'no' );
		}
		return self::$enabled;
	}

	/**
	 * Get the WooCommerce logger instance.
	 *
	 * @return \WC_Logger|null Logger instance or null if WooCommerce is not available.
	 */
	private static function get_logger() {
		if ( null === self::$logger && function_exists( 'wc_get_logger' ) ) {
			self::$logger = wc_get_logger();
		}
		return self::$logger;
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message  The message to log.
	 * @param string $context  Optional. Context identifier (e.g., 'image', 'content').
	 * @param array  $data     Optional. Additional data to include in the log.
	 */
	public static function debug( $message, $context = '', $data = array() ) {
		self::log( 'debug', $message, $context, $data );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message  The message to log.
	 * @param string $context  Optional. Context identifier.
	 * @param array  $data     Optional. Additional data to include in the log.
	 */
	public static function info( $message, $context = '', $data = array() ) {
		self::log( 'info', $message, $context, $data );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message  The message to log.
	 * @param string $context  Optional. Context identifier.
	 * @param array  $data     Optional. Additional data to include in the log.
	 */
	public static function warning( $message, $context = '', $data = array() ) {
		self::log( 'warning', $message, $context, $data );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message  The message to log.
	 * @param string $context  Optional. Context identifier.
	 * @param array  $data     Optional. Additional data to include in the log.
	 */
	public static function error( $message, $context = '', $data = array() ) {
		self::log( 'error', $message, $context, $data );
	}

	/**
	 * Log a critical message.
	 *
	 * @param string $message  The message to log.
	 * @param string $context  Optional. Context identifier.
	 * @param array  $data     Optional. Additional data to include in the log.
	 */
	public static function critical( $message, $context = '', $data = array() ) {
		self::log( 'critical', $message, $context, $data );
	}

	/**
	 * Log a message with the specified level.
	 *
	 * @param string $level    Log level (debug, info, warning, error, critical).
	 * @param string $message  The message to log.
	 * @param string $context  Optional. Context identifier.
	 * @param array  $data     Optional. Additional data to include in the log.
	 */
	private static function log( $level, $message, $context = '', $data = array() ) {
		// Skip if logging is disabled (except for errors and critical).
		if ( ! self::is_enabled() && ! in_array( $level, array( 'error', 'critical' ), true ) ) {
			return;
		}

		$logger = self::get_logger();

		// Format the message.
		$formatted_message = self::format_message( $message, $context, $data );

		// Log to WooCommerce if available.
		if ( $logger ) {
			$logger->log( $level, $formatted_message, array( 'source' => self::LOG_SOURCE ) );
		}
	}

	/**
	 * Format a log message.
	 *
	 * @param string $message The message to format.
	 * @param string $context Context identifier.
	 * @param array  $data    Additional data.
	 * @return string Formatted message.
	 */
	private static function format_message( $message, $context = '', $data = array() ) {
		$parts = array();

		// Add context prefix.
		if ( ! empty( $context ) ) {
			$parts[] = '[' . strtoupper( $context ) . ']';
		}

		// Add message.
		$parts[] = $message;

		// Add data if present.
		if ( ! empty( $data ) ) {
			$parts[] = '| Data: ' . wp_json_encode( $data );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Log an API request.
	 *
	 * @param string $provider    Provider name (e.g., 'OpenAI', 'RemoveBG').
	 * @param string $endpoint    API endpoint.
	 * @param array  $request     Request data (will be sanitized).
	 */
	public static function log_api_request( $provider, $endpoint, $request = array() ) {
		// Sanitize sensitive data.
		$sanitized = self::sanitize_log_data( $request );

		self::debug(
			sprintf( '%s API Request to %s', $provider, $endpoint ),
			'api',
			$sanitized
		);
	}

	/**
	 * Log an API response.
	 *
	 * @param string $provider      Provider name.
	 * @param int    $response_code HTTP response code.
	 * @param bool   $success       Whether the request was successful.
	 * @param string $error_message Optional error message.
	 */
	public static function log_api_response( $provider, $response_code, $success = true, $error_message = '' ) {
		$level = $success ? 'debug' : 'error';

		$message = sprintf(
			'%s API Response: %d %s',
			$provider,
			$response_code,
			$success ? 'OK' : 'ERROR'
		);

		if ( ! empty( $error_message ) ) {
			$message .= ' - ' . $error_message;
		}

		self::log( $level, $message, 'api' );
	}

	/**
	 * Log an image operation.
	 *
	 * @param string $operation Operation name (e.g., 'generate', 'upload', 'remove-background').
	 * @param string $status    Status (e.g., 'started', 'success', 'failed').
	 * @param array  $data      Additional data.
	 */
	public static function log_image_operation( $operation, $status, $data = array() ) {
		$level = 'failed' === $status ? 'error' : 'debug';

		$message = sprintf( 'Image %s: %s', $operation, ucfirst( $status ) );

		self::log( $level, $message, 'image', $data );
	}

	/**
	 * Sanitize log data to remove sensitive information.
	 *
	 * @param array $data Data to sanitize.
	 * @return array Sanitized data.
	 */
	private static function sanitize_log_data( $data ) {
		$sensitive_keys = array(
			'api_key',
			'apiKey',
			'key',
			'secret',
			'password',
			'token',
			'authorization',
			'X-Api-Key',
		);

		if ( ! is_array( $data ) ) {
			return $data;
		}

		foreach ( $data as $key => $value ) {
			// Check if key is sensitive.
			foreach ( $sensitive_keys as $sensitive ) {
				if ( stripos( $key, $sensitive ) !== false ) {
					$data[ $key ] = '[REDACTED]';
					break;
				}
			}

			// Recursively sanitize nested arrays.
			if ( is_array( $value ) ) {
				$data[ $key ] = self::sanitize_log_data( $value );
			}

			// Truncate very long strings (like base64 image data).
			if ( is_string( $value ) && strlen( $value ) > 500 ) {
				$data[ $key ] = substr( $value, 0, 100 ) . '...[truncated, ' . strlen( $value ) . ' chars]';
			}
		}

		return $data;
	}

	/**
	 * Clear the log file.
	 *
	 * @return bool True on success.
	 */
	public static function clear_log() {
		$logger = self::get_logger();

		if ( $logger && method_exists( $logger, 'clear' ) ) {
			$logger->clear( self::LOG_SOURCE );
			return true;
		}

		return false;
	}

	/**
	 * Reset the enabled cache (useful after option changes).
	 */
	public static function reset_cache() {
		self::$enabled = null;
	}
}
