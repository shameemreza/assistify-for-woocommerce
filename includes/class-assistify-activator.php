<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
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
 * Activator class.
 *
 * @since 1.0.0
 */
class Assistify_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		// Create database tables.
		self::create_tables();

		// Set default options.
		self::set_default_options();

		// Set activation flag for redirect.
		set_transient( 'assistify_activation_redirect', true, 30 );

		// Store plugin version.
		update_option( 'assistify_version', ASSISTIFY_VERSION );

		// Clear any cached data.
		wp_cache_flush();
	}

	/**
	 * Create database tables.
	 *
	 * This method is public so it can be called during upgrades.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// Sessions table.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}afw_sessions (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			session_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			context VARCHAR(20) NOT NULL DEFAULT 'customer',
			metadata LONGTEXT,
			started_at DATETIME NOT NULL,
			last_activity DATETIME NOT NULL,
			INDEX idx_session_id (session_id),
			INDEX idx_user (user_id),
			INDEX idx_context (context)
		) {$charset_collate};";

		// Messages table.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}afw_messages (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			session_id VARCHAR(36) NOT NULL,
			role VARCHAR(20) NOT NULL,
			content LONGTEXT NOT NULL,
			context VARCHAR(20) NOT NULL DEFAULT 'customer',
			tokens_used INT UNSIGNED DEFAULT 0,
			abilities_called LONGTEXT,
			created_at DATETIME NOT NULL,
			INDEX idx_session (session_id),
			INDEX idx_created (created_at)
		) {$charset_collate};";

		// Actions audit log table.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}afw_actions (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			session_id BIGINT UNSIGNED DEFAULT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			ability VARCHAR(100) NOT NULL,
			input_params LONGTEXT,
			result LONGTEXT,
			status ENUM('success', 'failed', 'pending', 'cancelled') NOT NULL DEFAULT 'pending',
			executed_at DATETIME NOT NULL,
			INDEX idx_user (user_id),
			INDEX idx_ability (ability),
			INDEX idx_status (status),
			INDEX idx_executed (executed_at)
		) {$charset_collate};";

		// Knowledge base table.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}afw_knowledge (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			type ENUM('faq', 'policy', 'product_info', 'custom') NOT NULL DEFAULT 'custom',
			title VARCHAR(255) NOT NULL,
			content LONGTEXT NOT NULL,
			embedding LONGBLOB,
			metadata LONGTEXT,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			INDEX idx_type (type),
			INDEX idx_active (is_active)
		) {$charset_collate};";

		// Health snapshots table.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}afw_health_snapshots (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			overall_score INT UNSIGNED NOT NULL,
			category_scores LONGTEXT NOT NULL,
			issues LONGTEXT,
			recommendations LONGTEXT,
			created_at DATETIME NOT NULL,
			INDEX idx_created (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Set default options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			'assistify_ai_provider'              => 'openai',
			'assistify_admin_chat_enabled'       => 'yes',
			'assistify_customer_chat_enabled'    => 'no',
			'assistify_guest_chat_enabled'       => 'no',
			'assistify_remove_data_on_uninstall' => 'no',
			'assistify_rate_limit_admin'         => 100,
			'assistify_rate_limit_customer'      => 20,
			'assistify_rate_limit_guest'         => 5,
			'assistify_chat_position'            => 'bottom-right',
			'assistify_primary_color'            => '#6861F2',
			'assistify_content_default_length'   => 600,
			'assistify_content_default_tone'     => 'professional',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}
}
