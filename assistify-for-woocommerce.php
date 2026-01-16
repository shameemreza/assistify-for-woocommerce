<?php
/**
 * Plugin Name:       Assistify for WooCommerce
 * Plugin URI:        https://github.com/shameemreza/assistify-for-woocommerce
 * Description:       A unified, native WooCommerce AI Assistant that provides dual-interface chat for both store owners and customers with deep WooCommerce integration.
 * Version:           1.1.0
 * Author:            Shameem Reza
 * Author URI:        https://shameem.blog
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       assistify-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0.0
 * WC tested up to:   10.4.3
 *
 * @package Assistify_For_WooCommerce
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'ASSISTIFY_VERSION', '1.1.0' );

/**
 * Plugin base file.
 */
define( 'ASSISTIFY_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 */
define( 'ASSISTIFY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'ASSISTIFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'ASSISTIFY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 */
define( 'ASSISTIFY_MIN_PHP_VERSION', '8.0' );

/**
 * Minimum WordPress version required.
 */
define( 'ASSISTIFY_MIN_WP_VERSION', '6.4' );

/**
 * Minimum WooCommerce version required.
 */
define( 'ASSISTIFY_MIN_WC_VERSION', '8.0.0' );

/**
 * Check if the minimum requirements are met.
 *
 * @since 1.0.0
 * @return bool True if requirements are met, false otherwise.
 */
function assistify_requirements_met() {
	// Check PHP version.
	if ( version_compare( PHP_VERSION, ASSISTIFY_MIN_PHP_VERSION, '<' ) ) {
		return false;
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), ASSISTIFY_MIN_WP_VERSION, '<' ) ) {
		return false;
	}

	return true;
}

/**
 * Display admin notice for minimum requirements.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_requirements_notice() {
	$message = '';

	if ( version_compare( PHP_VERSION, ASSISTIFY_MIN_PHP_VERSION, '<' ) ) {
		$message = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version. */
			esc_html__( 'Assistify for WooCommerce requires PHP version %1$s or higher. Your current version is %2$s.', 'assistify-for-woocommerce' ),
			ASSISTIFY_MIN_PHP_VERSION,
			PHP_VERSION
		);
	} elseif ( version_compare( get_bloginfo( 'version' ), ASSISTIFY_MIN_WP_VERSION, '<' ) ) {
		$message = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version. */
			esc_html__( 'Assistify for WooCommerce requires WordPress version %1$s or higher. Your current version is %2$s.', 'assistify-for-woocommerce' ),
			ASSISTIFY_MIN_WP_VERSION,
			get_bloginfo( 'version' )
		);
	}

	if ( ! empty( $message ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}

/**
 * Check if WooCommerce is active.
 *
 * @since 1.0.0
 * @return bool True if WooCommerce is active, false otherwise.
 */
function assistify_is_woocommerce_active() {
	// Check if WooCommerce class exists.
	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}

	// Check active plugins.
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}

	return in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
}

/**
 * Display admin notice when WooCommerce is not active.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Plugin name, 2: WooCommerce. */
				esc_html__( '%1$s requires %2$s to be installed and active.', 'assistify-for-woocommerce' ),
				'<strong>' . esc_html__( 'Assistify for WooCommerce', 'assistify-for-woocommerce' ) . '</strong>',
				'<strong>' . esc_html__( 'WooCommerce', 'assistify-for-woocommerce' ) . '</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Check WooCommerce version compatibility.
 *
 * @since 1.0.0
 * @return bool True if WooCommerce version is compatible, false otherwise.
 */
function assistify_is_woocommerce_version_compatible() {
	if ( ! defined( 'WC_VERSION' ) ) {
		return false;
	}

	return version_compare( WC_VERSION, ASSISTIFY_MIN_WC_VERSION, '>=' );
}

/**
 * Display admin notice for WooCommerce version incompatibility.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_woocommerce_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version. */
				esc_html__( 'Assistify for WooCommerce requires WooCommerce version %1$s or higher. Your current version is %2$s.', 'assistify-for-woocommerce' ),
				esc_html( ASSISTIFY_MIN_WC_VERSION ),
				esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '0.0.0' )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_init() {
	// Check minimum requirements.
	if ( ! assistify_requirements_met() ) {
		add_action( 'admin_notices', 'assistify_requirements_notice' );
		return;
	}

	// Check if WooCommerce is active.
	if ( ! assistify_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'assistify_woocommerce_notice' );
		return;
	}

	// Check WooCommerce version.
	if ( ! assistify_is_woocommerce_version_compatible() ) {
		add_action( 'admin_notices', 'assistify_woocommerce_version_notice' );
		return;
	}

	// Load the main plugin class.
	require_once ASSISTIFY_PLUGIN_DIR . 'includes/class-assistify.php';

	// Initialize the plugin.
	Assistify_For_WooCommerce\Assistify::instance();

	// Run migrations on admin init.
	add_action( 'admin_init', 'assistify_run_migrations' );
}
add_action( 'plugins_loaded', 'assistify_init' );

/**
 * Run database migrations and option updates.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_run_migrations() {
	$migration_version = get_option( 'assistify_migration_version', '0' );

	// Migration 1.0.1: Update brand color and set content defaults.
	if ( version_compare( $migration_version, '1.0.1', '<' ) ) {
		// Update primary color from old WooCommerce purple to Assistify indigo.
		$current_color = get_option( 'assistify_primary_color' );
		if ( empty( $current_color ) || '#7F54B3' === strtoupper( $current_color ) || '#7f54b3' === strtolower( $current_color ) ) {
			update_option( 'assistify_primary_color', '#6861F2' );
		}

		// Set default content length if not set or empty.
		$content_length = get_option( 'assistify_content_default_length' );
		if ( false === $content_length || '' === $content_length ) {
			update_option( 'assistify_content_default_length', '600' );
		}

		// Set default content tone if not set.
		$content_tone = get_option( 'assistify_content_default_tone' );
		if ( false === $content_tone || '' === $content_tone ) {
			update_option( 'assistify_content_default_tone', 'professional' );
		}

		update_option( 'assistify_migration_version', '1.0.1' );
	}
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_activate() {
	// Check requirements before activation.
	if ( ! assistify_requirements_met() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			sprintf(
				/* translators: 1: Required PHP version, 2: Required WordPress version. */
				esc_html__( 'Assistify for WooCommerce requires PHP %1$s and WordPress %2$s or higher.', 'assistify-for-woocommerce' ),
				esc_html( ASSISTIFY_MIN_PHP_VERSION ),
				esc_html( ASSISTIFY_MIN_WP_VERSION )
			),
			esc_html__( 'Plugin Activation Error', 'assistify-for-woocommerce' ),
			array( 'back_link' => true )
		);
	}

	// Run activator.
	require_once ASSISTIFY_PLUGIN_DIR . 'includes/class-assistify-activator.php';
	Assistify_For_WooCommerce\Assistify_Activator::activate();
}
register_activation_hook( __FILE__, 'assistify_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_deactivate() {
	require_once ASSISTIFY_PLUGIN_DIR . 'includes/class-assistify-deactivator.php';
	Assistify_For_WooCommerce\Assistify_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'assistify_deactivate' );

/**
 * Declare HPOS compatibility.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'assistify_declare_hpos_compatibility' );

/**
 * Declare Cart/Checkout Blocks compatibility.
 *
 * @since 1.0.0
 * @return void
 */
function assistify_declare_blocks_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'assistify_declare_blocks_compatibility' );

