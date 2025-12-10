<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks,
 * and public-facing site hooks.
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
 * Main Assistify class.
 *
 * @since 1.0.0
 */
final class Assistify {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var Assistify|null
	 */
	private static $instance = null;

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since 1.0.0
	 * @var Assistify_Loader
	 */
	protected $loader;

	/**
	 * Main Assistify Instance.
	 *
	 * Ensures only one instance of Assistify is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @return Assistify Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->init_abilities();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_rest_api();
		$this->run();
	}

	/**
	 * Prevent cloning.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'assistify-for-woocommerce' ), '1.0.0' );
	}

	/**
	 * Prevent unserializing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is forbidden.', 'assistify-for-woocommerce' ), '1.0.0' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_dependencies() {
		// The class responsible for orchestrating the actions and filters.
		require_once ASSISTIFY_PLUGIN_DIR . 'includes/class-assistify-loader.php';

		// The class responsible for defining internationalization functionality.
		require_once ASSISTIFY_PLUGIN_DIR . 'includes/class-assistify-i18n.php';

		// Load AI Provider classes.
		$this->load_ai_providers();

		// Load admin classes.
		if ( is_admin() ) {
			require_once ASSISTIFY_PLUGIN_DIR . 'includes/admin/class-assistify-admin.php';
		}

		// Load frontend classes.
		if ( ! is_admin() || wp_doing_ajax() ) {
			require_once ASSISTIFY_PLUGIN_DIR . 'includes/frontend/class-assistify-frontend.php';
		}

		$this->loader = new Assistify_Loader();
	}

	/**
	 * Load AI Provider classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_ai_providers() {
		$ai_providers_dir = ASSISTIFY_PLUGIN_DIR . 'includes/ai-providers/';

		// Load interface first.
		require_once $ai_providers_dir . 'interface-ai-provider.php';

		// Load abstract class.
		require_once $ai_providers_dir . 'class-ai-provider-abstract.php';

		// Load provider implementations.
		require_once $ai_providers_dir . 'class-ai-provider-openai.php';
		require_once $ai_providers_dir . 'class-ai-provider-anthropic.php';
		require_once $ai_providers_dir . 'class-ai-provider-google.php';
		require_once $ai_providers_dir . 'class-ai-provider-xai.php';
		require_once $ai_providers_dir . 'class-ai-provider-deepseek.php';

		// Load factory class.
		require_once $ai_providers_dir . 'class-ai-provider-factory.php';
	}

	/**
	 * Load REST API classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_rest_api() {
		$rest_api_dir = ASSISTIFY_PLUGIN_DIR . 'includes/rest-api/';

		require_once $rest_api_dir . 'class-rest-api-controller.php';
		require_once $rest_api_dir . 'class-rest-chat-controller.php';
		require_once $rest_api_dir . 'class-rest-api.php';
	}

	/**
	 * Load Abilities classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_abilities() {
		require_once ASSISTIFY_PLUGIN_DIR . 'includes/abilities/class-abilities-registry.php';
	}

	/**
	 * Initialize the Abilities Registry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_abilities() {
		$this->load_abilities();

		// Initialize the abilities registry singleton.
		Abilities\Abilities_Registry::instance();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_locale() {
		$plugin_i18n = new Assistify_i18n();
		$this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		$plugin_admin = new Admin\Assistify_Admin();

		// Enqueue admin styles and scripts.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Add admin menu items.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

		// Add settings link on plugins page.
		$this->loader->add_filter( 'plugin_action_links_' . ASSISTIFY_PLUGIN_BASENAME, $plugin_admin, 'add_plugin_action_links' );

		// Add row meta links (Docs, Support) on plugins page.
		$this->loader->add_filter( 'plugin_row_meta', $plugin_admin, 'add_plugin_row_meta', 10, 2 );

		// Add WooCommerce settings tab.
		$this->loader->add_filter( 'woocommerce_settings_tabs_array', $plugin_admin, 'add_settings_tab', 50 );
		$this->loader->add_action( 'woocommerce_settings_tabs_assistify', $plugin_admin, 'settings_tab_content' );
		$this->loader->add_action( 'woocommerce_update_options_assistify', $plugin_admin, 'save_settings' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function define_public_hooks() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$plugin_frontend = new Frontend\Assistify_Frontend();

		// Enqueue frontend styles and scripts.
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_frontend, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_frontend, 'enqueue_scripts' );

		// Add chat widget to footer.
		$this->loader->add_action( 'wp_footer', $plugin_frontend, 'render_chat_widget' );
	}

	/**
	 * Initialize REST API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function define_rest_api() {
		// Load REST API classes.
		$this->load_rest_api();

		// Register REST API routes.
		$this->loader->add_action( 'rest_api_init', $this, 'register_rest_routes' );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_rest_routes() {
		$rest_api = new REST_API\REST_API();
		$rest_api->register_routes();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks.
	 *
	 * @since 1.0.0
	 * @return Assistify_Loader Orchestrates the hooks.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since 1.0.0
	 * @return string The version number.
	 */
	public function get_version() {
		return ASSISTIFY_VERSION;
	}
}

