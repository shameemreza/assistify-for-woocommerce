<?php
/**
 * Health Check - Security
 *
 * Monitors SSL, file permissions, and security issues.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Security Health Check Class
 *
 * @since 1.0.0
 */
class Health_Check_Security {

	/**
	 * Initialize the check.
	 *
	 * @return void
	 */
	public static function init() {
		$monitor = \Assistify\Health_Monitor\Health_Monitor::get_instance();

		$monitor->register_check(
			'ssl_certificate',
			array(
				'label'    => 'SSL Certificate',
				'category' => 'security',
				'callback' => array( __CLASS__, 'check_ssl_certificate' ),
				'priority' => 1,
			)
		);

		$monitor->register_check(
			'file_permissions',
			array(
				'label'    => 'File Permissions',
				'category' => 'security',
				'callback' => array( __CLASS__, 'check_file_permissions' ),
				'priority' => 2,
			)
		);

		$monitor->register_check(
			'admin_security',
			array(
				'label'    => 'Admin Security',
				'category' => 'security',
				'callback' => array( __CLASS__, 'check_admin_security' ),
				'priority' => 3,
			)
		);
	}

	/**
	 * Check SSL certificate.
	 *
	 * @return array Check results.
	 */
	public static function check_ssl_certificate() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check if site is using HTTPS.
		$site_url = get_site_url();
		$is_https = strpos( $site_url, 'https://' ) === 0;

		if ( ! $is_https ) {
			$result['issues'][] = array(
				'severity' => 'critical',
				'title'    => 'Site Not Using HTTPS',
				'message'  => 'Your site is not using HTTPS. Customer data is not encrypted.',
				'fix'      => 'Install an SSL certificate and update site URL to use HTTPS.',
			);
			return $result;
		}

		// Check SSL certificate expiry.
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );
		$ssl_info = self::get_ssl_certificate_info( $domain );

		if ( $ssl_info && isset( $ssl_info['valid_to'] ) ) {
			$days_until_expiry = floor( ( $ssl_info['valid_to'] - time() ) / DAY_IN_SECONDS );

			if ( $days_until_expiry <= 0 ) {
				$result['issues'][] = array(
					'severity' => 'critical',
					'title'    => 'SSL Certificate Expired',
					'message'  => 'Your SSL certificate has expired. Customers will see security warnings.',
					'fix'      => 'Renew your SSL certificate immediately.',
					'data'     => array( 'expired_on' => gmdate( 'Y-m-d', $ssl_info['valid_to'] ) ),
				);
			} elseif ( $days_until_expiry <= 14 ) {
				$result['issues'][] = array(
					'severity' => 'warning',
					'title'    => 'SSL Certificate Expiring Soon',
					'message'  => sprintf( 'Your SSL certificate expires in %d days.', $days_until_expiry ),
					'fix'      => 'Renew your SSL certificate before it expires.',
					'data'     => array(
						'expires_on' => gmdate( 'Y-m-d', $ssl_info['valid_to'] ),
						'days_left'  => $days_until_expiry,
					),
				);
			} elseif ( $days_until_expiry <= 30 ) {
				$result['issues'][] = array(
					'severity' => 'info',
					'title'    => 'SSL Certificate Renewal Reminder',
					'message'  => sprintf( 'Your SSL certificate expires in %d days.', $days_until_expiry ),
					'fix'      => 'Plan to renew your SSL certificate.',
					'data'     => array( 'days_left' => $days_until_expiry ),
				);
			}
		}

		return $result;
	}

	/**
	 * Get SSL certificate information.
	 *
	 * @param string $domain Domain name.
	 * @return array|false Certificate info or false on failure.
	 */
	private static function get_ssl_certificate_info( $domain ) {
		// Check cached result.
		$cached = get_transient( 'assistify_ssl_info_' . md5( $domain ) );
		if ( false !== $cached ) {
			return $cached;
		}

		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => false,
					'verify_peer_name'  => false,
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- SSL check may fail on some hosts.
		$stream = @stream_socket_client(
			'ssl://' . $domain . ':443',
			$errno,
			$errstr,
			10,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $stream ) {
			return false;
		}

		$params = stream_context_get_params( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $stream );

		if ( ! isset( $params['options']['ssl']['peer_certificate'] ) ) {
			return false;
		}

		$cert_info = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );

		if ( ! $cert_info ) {
			return false;
		}

		$info = array(
			'valid_from' => $cert_info['validFrom_time_t'] ?? 0,
			'valid_to'   => $cert_info['validTo_time_t'] ?? 0,
			'issuer'     => $cert_info['issuer']['O'] ?? 'Unknown',
		);

		// Cache for 12 hours.
		set_transient( 'assistify_ssl_info_' . md5( $domain ), $info, 12 * HOUR_IN_SECONDS );

		return $info;
	}

	/**
	 * Check file permissions.
	 *
	 * @return array Check results.
	 */
	public static function check_file_permissions() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check wp-config.php permissions.
		$wp_config = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config ) ) {
			$perms = fileperms( $wp_config ) & 0777;

			if ( $perms > 0644 ) {
				$result['issues'][] = array(
					'severity' => 'warning',
					'title'    => 'wp-config.php Permissions Too Open',
					'message'  => sprintf( 'wp-config.php has permissions %o. Should be 644 or less.', $perms ),
					'fix'      => 'Change permissions to 644: chmod 644 wp-config.php',
				);
			}
		}

		// Check .htaccess permissions.
		$htaccess = ABSPATH . '.htaccess';
		if ( file_exists( $htaccess ) ) {
			$perms = fileperms( $htaccess ) & 0777;

			if ( $perms > 0644 ) {
				$result['issues'][] = array(
					'severity' => 'info',
					'title'    => '.htaccess Permissions',
					'message'  => sprintf( '.htaccess has permissions %o. Consider setting to 644.', $perms ),
					'fix'      => 'Change permissions to 644: chmod 644 .htaccess',
				);
			}
		}

		// Check uploads directory is writable.
		$upload_dir = wp_upload_dir();
		if ( ! wp_is_writable( $upload_dir['basedir'] ) ) {
			$result['issues'][] = array(
				'severity' => 'warning',
				'title'    => 'Uploads Directory Not Writable',
				'message'  => 'The uploads directory is not writable. File uploads will fail.',
				'fix'      => 'Set uploads directory permissions to 755.',
			);
		}

		return $result;
	}

	/**
	 * Check admin security settings.
	 *
	 * @return array Check results.
	 */
	public static function check_admin_security() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Check if admin user exists with ID 1.
		$admin_user = get_user_by( 'id', 1 );
		if ( $admin_user && 'admin' === $admin_user->user_login ) {
			$result['issues'][] = array(
				'severity' => 'info',
				'title'    => 'Default Admin Username',
				'message'  => 'Using "admin" as username is a common attack target.',
				'fix'      => 'Consider creating a new admin user with a different username.',
			);
		}

		// Check if debug is enabled on production.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$result['issues'][] = array(
				'severity'  => 'warning',
				'title'     => 'Debug Mode Enabled',
				'message'   => 'WP_DEBUG is enabled. This should be disabled on production sites.',
				'fix'       => 'Edit wp-config.php and set: define( \'WP_DEBUG\', false );',
				'issue_key' => 'security_wp_debug',
			);

			if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
				$result['issues'][] = array(
					'severity'  => 'critical',
					'title'     => 'Debug Display Enabled',
					'message'   => 'WP_DEBUG_DISPLAY is enabled. PHP errors are visible to visitors.',
					'fix'       => 'Edit wp-config.php and set: define( \'WP_DEBUG_DISPLAY\', false );',
					'issue_key' => 'security_wp_debug_display',
				);
			}

			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				$result['issues'][] = array(
					'severity'  => 'info',
					'title'     => 'Debug Log Enabled',
					'message'   => 'WP_DEBUG_LOG is writing errors to debug.log file.',
					'fix'       => 'This is fine for debugging. Ensure debug.log is not publicly accessible via .htaccess or nginx config.',
					'issue_key' => 'security_wp_debug_log',
				);
			}
		}

		// Check for .git directory exposure.
		// Use get_home_path() for proper path detection in various WordPress setups.
		$home_path = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		$git_dir   = trailingslashit( $home_path ) . '.git';
		if ( is_dir( $git_dir ) ) {
			// Check if it's accessible.
			$response = wp_remote_head(
				site_url( '/.git/config' ),
				array(
					'timeout'   => 5,
					'sslverify' => false,
				)
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$result['issues'][] = array(
					'severity' => 'critical',
					'title'    => '.git Directory Exposed',
					'message'  => 'Your .git directory is publicly accessible. Source code may be leaked.',
					'fix'      => 'Block access to .git in your .htaccess or nginx config.',
				);
			}
		}

		return $result;
	}
}

// Initialize the check.
Health_Check_Security::init();
