<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
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
 * Deactivator class.
 *
 * @since 1.0.0
 */
class Assistify_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate() {
		// Clear scheduled events.
		self::clear_scheduled_events();

		// Clear transients.
		self::clear_transients();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Clear scheduled events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function clear_scheduled_events() {
		$scheduled_events = array(
			'assistify_health_check',
			'assistify_cleanup_sessions',
			'assistify_cleanup_logs',
		);

		foreach ( $scheduled_events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}
	}

	/**
	 * Clear transients.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete all transients with our prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_assistify_%' 
			OR option_name LIKE '_transient_timeout_assistify_%'"
		);
	}
}
