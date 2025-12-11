<?php
/**
 * Uninstall Assistify for WooCommerce.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if we should remove data on uninstall.
$assistify_remove_data = get_option( 'assistify_remove_data_on_uninstall', 'no' );

if ( 'yes' !== $assistify_remove_data ) {
	return;
}

global $wpdb;

// Delete options - direct query is necessary for uninstall cleanup.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'assistify_%'" );

// Delete user meta - direct query is necessary for uninstall cleanup.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'assistify_%'" );

// Drop custom tables.
$assistify_tables = array(
	$wpdb->prefix . 'afw_sessions',
	$wpdb->prefix . 'afw_messages',
	$wpdb->prefix . 'afw_actions',
	$wpdb->prefix . 'afw_knowledge',
	$wpdb->prefix . 'afw_health_snapshots',
);

foreach ( $assistify_tables as $assistify_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$assistify_table}" );
}

// Clear any cached data that may have been set.
wp_cache_flush();
