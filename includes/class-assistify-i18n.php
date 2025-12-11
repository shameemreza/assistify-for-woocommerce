<?php
/**
 * Define the internationalization functionality.
 *
 * Since WordPress 4.6, translations are automatically loaded from WordPress.org
 * for plugins hosted there. This class is kept for compatibility and potential
 * custom translation loading scenarios.
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
 * Internationalization class.
 *
 * Note: Since WordPress 4.6, translations for plugins hosted on WordPress.org
 * are automatically loaded. Manual loading is no longer required.
 *
 * @since 1.0.0
 */
class Assistify_I18n {

	/**
	 * Initialize internationalization.
	 *
	 * WordPress automatically loads translations for plugins hosted on WordPress.org
	 * since version 4.6. This method is kept for potential custom translation paths
	 * or local development scenarios.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain() {
		// Translations are automatically loaded by WordPress for plugins hosted on WordPress.org.
		// This hook is intentionally empty but kept for future custom translation needs.
	}
}
