<?php
/**
 * Health Check - Updates
 *
 * Monitors WordPress, plugin, and theme updates.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Updates Health Check Class
 *
 * @since 1.0.0
 */
class Health_Check_Updates {

	/**
	 * Initialize the check.
	 *
	 * @return void
	 */
	public static function init() {
		$monitor = \Assistify\Health_Monitor\Health_Monitor::get_instance();

		$monitor->register_check(
			'wordpress_updates',
			array(
				'label'    => 'WordPress Updates',
				'category' => 'updates',
				'callback' => array( __CLASS__, 'check_wordpress_updates' ),
				'priority' => 1,
			)
		);

		$monitor->register_check(
			'plugin_updates',
			array(
				'label'    => 'Plugin Updates',
				'category' => 'updates',
				'callback' => array( __CLASS__, 'check_plugin_updates' ),
				'priority' => 2,
			)
		);

		$monitor->register_check(
			'theme_updates',
			array(
				'label'    => 'Theme Updates',
				'category' => 'updates',
				'callback' => array( __CLASS__, 'check_theme_updates' ),
				'priority' => 3,
			)
		);
	}

	/**
	 * Check for WordPress core updates.
	 *
	 * @return array Check results.
	 */
	public static function check_wordpress_updates() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Get core updates from existing transient.
		$core_updates = get_core_updates();

		if ( ! is_array( $core_updates ) || empty( $core_updates ) ) {
			return $result;
		}

		$update = $core_updates[0];

		if ( 'upgrade' === $update->response ) {
			$current_version = get_bloginfo( 'version' );
			$new_version     = $update->version;

			// Check if security update.
			$is_security = isset( $update->security ) && $update->security;

			// Check version difference for severity.
			$current_parts = explode( '.', $current_version );
			$new_parts     = explode( '.', $new_version );

			$is_major = isset( $current_parts[0], $new_parts[0] ) && $current_parts[0] !== $new_parts[0];
			$is_minor = isset( $current_parts[1], $new_parts[1] ) && $current_parts[1] !== $new_parts[1];

			$severity = 'info';
			if ( $is_security ) {
				$severity = 'critical';
			} elseif ( $is_major ) {
				$severity = 'warning';
			}

			$result['issues'][] = array(
				'severity'  => $severity,
				'title'     => $is_security ? 'WordPress Security Update Available' : 'WordPress Update Available',
				'message'   => sprintf(
					'WordPress %s is available (current: %s).%s',
					$new_version,
					$current_version,
					$is_security ? ' This is a security release.' : ''
				),
				'fix'       => $is_security
					? 'Apply this security update as soon as possible.'
					: 'Review the changelog and update when convenient.',
				'issue_key' => 'update_wordpress_' . sanitize_key( $new_version ),
				'data'      => array(
					'current'     => $current_version,
					'new'         => $new_version,
					'is_security' => $is_security,
					'update_url'  => admin_url( 'update-core.php' ),
				),
			);
		}

		return $result;
	}

	/**
	 * Check for plugin updates.
	 *
	 * @return array Check results.
	 */
	public static function check_plugin_updates() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Get plugin updates from existing transient.
		$update_plugins = get_site_transient( 'update_plugins' );

		if ( ! isset( $update_plugins->response ) || empty( $update_plugins->response ) ) {
			return $result;
		}

		$all_plugins = get_plugins();
		$updates     = $update_plugins->response;

		$security_updates    = array();
		$regular_updates     = array();
		$woocommerce_updates = array();

		foreach ( $updates as $plugin_file => $plugin_data ) {
			$plugin_info = $all_plugins[ $plugin_file ] ?? null;
			if ( ! $plugin_info ) {
				continue;
			}

			$update_info = array(
				'file'            => $plugin_file,
				'name'            => $plugin_info['Name'],
				'current_version' => $plugin_info['Version'],
				'new_version'     => $plugin_data->new_version,
			);

			// Check if WooCommerce related.
			$is_wc_related = strpos( strtolower( $plugin_info['Name'] ), 'woocommerce' ) !== false
				|| strpos( $plugin_file, 'woocommerce' ) !== false;

			if ( $is_wc_related ) {
				$woocommerce_updates[] = $update_info;
			}

			// Check for security flag in plugin update data.
			if ( isset( $plugin_data->requires_plugins ) || isset( $plugin_data->slug ) ) {
				// Use slug to check for known security updates (simplified).
				$regular_updates[] = $update_info;
			} else {
				$regular_updates[] = $update_info;
			}
		}

		$total_updates = count( $updates );

		if ( $total_updates > 0 ) {
			$severity = 'info';
			$title    = 'Plugin Updates Available';
			$message  = sprintf( '%d plugin update(s) available.', $total_updates );

			if ( ! empty( $woocommerce_updates ) ) {
				$severity = 'warning';
				$message .= sprintf( ' %d are WooCommerce related.', count( $woocommerce_updates ) );
			}

			$result['issues'][] = array(
				'severity'  => $severity,
				'title'     => $title,
				'message'   => $message,
				'fix'       => 'Review and apply plugin updates. Always backup before updating.',
				'issue_key' => 'update_plugins_' . $total_updates,
				'data'      => array(
					'total'               => $total_updates,
					'woocommerce_updates' => $woocommerce_updates,
					'updates'             => array_slice( $regular_updates, 0, 10 ),
					'update_url'          => admin_url( 'update-core.php' ),
				),
			);
		}

		return $result;
	}

	/**
	 * Check for theme updates.
	 *
	 * @return array Check results.
	 */
	public static function check_theme_updates() {
		$result = array(
			'status' => 'good',
			'issues' => array(),
		);

		// Get theme updates from existing transient.
		$update_themes = get_site_transient( 'update_themes' );

		if ( ! isset( $update_themes->response ) || empty( $update_themes->response ) ) {
			return $result;
		}

		$all_themes        = wp_get_themes();
		$active_theme      = wp_get_theme();
		$active_stylesheet = $active_theme->get_stylesheet();
		$updates           = $update_themes->response;

		$theme_updates     = array();
		$active_has_update = false;

		foreach ( $updates as $theme_slug => $theme_data ) {
			$theme = $all_themes[ $theme_slug ] ?? null;
			if ( ! $theme ) {
				continue;
			}

			$is_active = $theme_slug === $active_stylesheet;
			if ( $is_active ) {
				$active_has_update = true;
			}

			$theme_updates[] = array(
				'slug'            => $theme_slug,
				'name'            => $theme->get( 'Name' ),
				'current_version' => $theme->get( 'Version' ),
				'new_version'     => $theme_data['new_version'],
				'is_active'       => $is_active,
			);
		}

		if ( ! empty( $theme_updates ) ) {
			$severity = 'info';
			$message  = sprintf( '%d theme update(s) available.', count( $theme_updates ) );

			if ( $active_has_update ) {
				$severity = 'warning';
				$message .= ' Active theme has an update.';
			}

			$result['issues'][] = array(
				'severity'  => $severity,
				'title'     => 'Theme Updates Available',
				'message'   => $message,
				'fix'       => $active_has_update
					? 'Update your active theme. Test on staging first if possible.'
					: 'Review and update themes when convenient.',
				'issue_key' => 'update_themes_' . count( $theme_updates ),
				'data'      => array(
					'total'             => count( $theme_updates ),
					'active_has_update' => $active_has_update,
					'themes'            => $theme_updates,
					'update_url'        => admin_url( 'update-core.php' ),
				),
			);
		}

		return $result;
	}

	/**
	 * Get update summary for reporting.
	 *
	 * @return array Update summary.
	 */
	public static function get_update_summary() {
		$core_updates   = get_core_updates();
		$plugin_updates = get_site_transient( 'update_plugins' );
		$theme_updates  = get_site_transient( 'update_themes' );

		return array(
			'wordpress'    => isset( $core_updates[0] ) && 'upgrade' === $core_updates[0]->response,
			'plugins'      => isset( $plugin_updates->response ) ? count( $plugin_updates->response ) : 0,
			'themes'       => isset( $theme_updates->response ) ? count( $theme_updates->response ) : 0,
			'total'        => ( isset( $core_updates[0] ) && 'upgrade' === $core_updates[0]->response ? 1 : 0 )
				+ ( isset( $plugin_updates->response ) ? count( $plugin_updates->response ) : 0 )
				+ ( isset( $theme_updates->response ) ? count( $theme_updates->response ) : 0 ),
			'last_checked' => get_option( '_site_transient_timeout_update_plugins', 0 ),
		);
	}
}

// Initialize the check.
Health_Check_Updates::init();
