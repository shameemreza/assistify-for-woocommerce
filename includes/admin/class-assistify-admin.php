<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 *
 * @since 1.0.0
 */
class Assistify_Admin {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor.
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		// Only load on our plugin pages or WooCommerce pages.
		if ( ! $this->should_load_assets( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'assistify-admin',
			ASSISTIFY_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ASSISTIFY_VERSION,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our plugin pages or WooCommerce pages.
		if ( ! $this->should_load_assets( $hook ) ) {
			return;
		}

		wp_enqueue_script(
			'assistify-admin',
			ASSISTIFY_PLUGIN_URL . 'assets/js/admin/admin.js',
			array( 'jquery', 'selectWoo' ),
			ASSISTIFY_VERSION,
			true
		);

		wp_localize_script(
			'assistify-admin',
			'assistifyAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'assistify_admin_nonce' ),
				'strings'          => array(
					'error'       => esc_html__( 'Sorry, something went wrong. Please try again.', 'assistify-for-woocommerce' ),
					'loading'     => esc_html__( 'Loading...', 'assistify-for-woocommerce' ),
					'placeholder' => esc_html__( 'Ask Ayana anything...', 'assistify-for-woocommerce' ),
					'openChat'    => esc_html__( 'Chat with Ayana', 'assistify-for-woocommerce' ),
				),
				'settings'         => array(
					'chatEnabled'   => get_option( 'assistify_admin_chat_enabled', 'yes' ),
					'position'      => get_option( 'assistify_chat_position', 'bottom-right' ),
					'apiConfigured' => $this->is_api_key_configured(),
				),
				'modelsByProvider' => $this->get_models_by_provider(),
				'defaultModels'    => $this->get_default_models(),
			)
		);
	}

	/**
	 * Check if API key is configured and valid format.
	 *
	 * @since 1.0.0
	 * @return bool True if API key is configured.
	 */
	private function is_api_key_configured() {
		$api_key = get_option( 'assistify_api_key', '' );
		return ! empty( $api_key ) && strlen( $api_key ) > 20;
	}

	/**
	 * Check if assets should be loaded.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page.
	 * @return bool
	 */
	private function should_load_assets( $hook ) {
		// Always load on our settings page for model filtering.
		if ( 'woocommerce_page_wc-settings' === $hook ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking tab parameter.
			if ( isset( $_GET['tab'] ) && 'assistify' === sanitize_key( $_GET['tab'] ) ) {
				return true;
			}
		}

		// Load if admin chat is enabled.
		if ( 'yes' === get_option( 'assistify_admin_chat_enabled', 'yes' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=assistify' ) ) . '">' . esc_html__( 'Settings', 'assistify-for-woocommerce' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Add plugin row meta links.
	 *
	 * Adds additional links to the plugin meta row (next to Version, Author, etc.).
	 *
	 * @since 1.0.0
	 * @param array  $links Plugin row meta links.
	 * @param string $file  Plugin base file.
	 * @return array Modified plugin row meta links.
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( ASSISTIFY_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$row_meta = array(
			'docs'    => '<a href="' . esc_url( 'https://github.com/shameemreza/assistify-for-woocommerce#readme' ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'View documentation', 'assistify-for-woocommerce' ) . '">' . esc_html__( 'Docs', 'assistify-for-woocommerce' ) . '</a>',
			'support' => '<a href="' . esc_url( 'https://github.com/shameemreza/assistify-for-woocommerce/issues' ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'Get support', 'assistify-for-woocommerce' ) . '">' . esc_html__( 'Get Support', 'assistify-for-woocommerce' ) . '</a>',
		);

		return array_merge( $links, $row_meta );
	}

	/**
	 * Add WooCommerce settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['assistify'] = esc_html__( 'Assistify', 'assistify-for-woocommerce' );
		return $tabs;
	}

	/**
	 * Output the settings tab content.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_tab_content() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Get models organized by provider for JavaScript.
	 *
	 * @since 1.0.0
	 * @return array Models by provider.
	 */
	private function get_models_by_provider() {
		return array(
			'openai'    => array(
				'gpt-4o'        => 'GPT-4o',
				'gpt-4o-mini'   => 'GPT-4o Mini',
				'gpt-4.1'       => 'GPT-4.1',
				'gpt-4.1-mini'  => 'GPT-4.1 Mini',
				'gpt-4.1-nano'  => 'GPT-4.1 Nano',
				'o1'            => 'o1',
				'o1-mini'       => 'o1-mini',
				'o1-pro'        => 'o1-pro',
				'o3-mini'       => 'o3-mini',
				'gpt-4-turbo'   => 'GPT-4 Turbo',
				'gpt-4'         => 'GPT-4',
				'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
			),
			'anthropic' => array(
				'claude-sonnet-4-20250514'   => 'Claude Sonnet 4',
				'claude-opus-4-20250514'     => 'Claude Opus 4',
				'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet',
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
				'claude-3-opus-20240229'     => 'Claude 3 Opus',
				'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
			),
			'google'    => array(
				'gemini-2.5-pro'            => 'Gemini 2.5 Pro',
				'gemini-2.5-flash'          => 'Gemini 2.5 Flash',
				'gemini-2.0-flash'          => 'Gemini 2.0 Flash',
				'gemini-2.0-flash-lite'     => 'Gemini 2.0 Flash-Lite',
				'gemini-2.0-flash-thinking' => 'Gemini 2.0 Flash Thinking',
				'gemini-1.5-pro'            => 'Gemini 1.5 Pro',
				'gemini-1.5-flash'          => 'Gemini 1.5 Flash',
				'gemini-1.5-flash-8b'       => 'Gemini 1.5 Flash-8B',
			),
			'xai'       => array(
				'grok-3'           => 'Grok 3',
				'grok-3-fast'      => 'Grok 3 Fast',
				'grok-3-mini'      => 'Grok 3 Mini',
				'grok-3-mini-fast' => 'Grok 3 Mini Fast',
				'grok-2'           => 'Grok 2',
				'grok-2-vision'    => 'Grok 2 Vision',
				'grok-2-mini'      => 'Grok 2 Mini',
			),
			'deepseek'  => array(
				'deepseek-chat'     => 'DeepSeek-V3',
				'deepseek-reasoner' => 'DeepSeek-R1',
				'deepseek-coder'    => 'DeepSeek Coder',
			),
		);
	}

	/**
	 * Get default model for each provider.
	 *
	 * @since 1.0.0
	 * @return array Default models.
	 */
	private function get_default_models() {
		return array(
			'openai'    => 'gpt-4o-mini',
			'anthropic' => 'claude-3-5-sonnet-20241022',
			'google'    => 'gemini-2.0-flash',
			'xai'       => 'grok-3-fast',
			'deepseek'  => 'deepseek-chat',
		);
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings() {
		// Validate API key before saving.
		$this->validate_api_key_on_save();

		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * Validate API key when saving settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function validate_api_key_on_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WooCommerce settings.
		$api_key = isset( $_POST['assistify_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['assistify_api_key'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WooCommerce settings.
		$provider = isset( $_POST['assistify_ai_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['assistify_ai_provider'] ) ) : 'openai';

		// Skip validation if API key is empty (user clearing it).
		if ( empty( $api_key ) ) {
			return;
		}

		// Validate API key format based on provider.
		$validation_result = $this->validate_api_key_format( $api_key, $provider );

		if ( is_wp_error( $validation_result ) ) {
			\WC_Admin_Settings::add_error( $validation_result->get_error_message() );
		}
	}

	/**
	 * Validate API key format.
	 *
	 * @since 1.0.0
	 * @param string $api_key  The API key to validate.
	 * @param string $provider The AI provider.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_api_key_format( $api_key, $provider ) {
		$api_key = trim( $api_key );

		switch ( $provider ) {
			case 'openai':
				// OpenAI keys start with 'sk-' and are typically 51+ characters.
				if ( ! preg_match( '/^sk-[a-zA-Z0-9_-]{40,}$/', $api_key ) ) {
					return new \WP_Error(
						'invalid_api_key',
						__( 'Invalid OpenAI API key format. Keys should start with "sk-" followed by at least 40 characters.', 'assistify-for-woocommerce' )
					);
				}
				break;

			case 'anthropic':
				// Anthropic keys start with 'sk-ant-' and are typically 90+ characters.
				if ( ! preg_match( '/^sk-ant-[a-zA-Z0-9_-]{80,}$/', $api_key ) ) {
					return new \WP_Error(
						'invalid_api_key',
						__( 'Invalid Anthropic API key format. Keys should start with "sk-ant-" followed by at least 80 characters.', 'assistify-for-woocommerce' )
					);
				}
				break;

			case 'google':
				// Google API keys are typically 39 characters.
				if ( strlen( $api_key ) < 30 || strlen( $api_key ) > 50 ) {
					return new \WP_Error(
						'invalid_api_key',
						__( 'Invalid Google API key format. Keys are typically 39 characters long.', 'assistify-for-woocommerce' )
					);
				}
				break;

			case 'xai':
				// xAI keys start with 'xai-' and are typically 80+ characters.
				if ( ! preg_match( '/^xai-[a-zA-Z0-9_-]{70,}$/', $api_key ) ) {
					return new \WP_Error(
						'invalid_api_key',
						__( 'Invalid xAI API key format. Keys should start with "xai-" followed by at least 70 characters.', 'assistify-for-woocommerce' )
					);
				}
				break;

			case 'deepseek':
				// DeepSeek keys start with 'sk-' and are typically 32+ characters.
				if ( ! preg_match( '/^sk-[a-zA-Z0-9]{28,}$/', $api_key ) ) {
					return new \WP_Error(
						'invalid_api_key',
						__( 'Invalid DeepSeek API key format. Keys should start with "sk-" followed by at least 28 characters.', 'assistify-for-woocommerce' )
					);
				}
				break;

			default:
				// Unknown provider - basic validation.
				if ( strlen( $api_key ) < 20 ) {
					return new \WP_Error(
						'invalid_api_key',
						__( 'API key appears too short. Please check your API key.', 'assistify-for-woocommerce' )
					);
				}
		}

		return true;
	}

	/**
	 * Test API key connectivity.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_test_api_key() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page.', 'assistify-for-woocommerce' ) )
			);
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'assistify-for-woocommerce' ) )
			);
		}

		// Get the AI provider.
		$provider = \Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory::get_configured_provider();

		if ( is_wp_error( $provider ) ) {
			wp_send_json_error(
				array( 'message' => $provider->get_error_message() )
			);
		}

		// Try a simple test message.
		$test_messages = array(
			array(
				'role'    => 'user',
				'content' => 'Say "Hello" in one word.',
			),
		);

		$response = $provider->chat(
			$test_messages,
			array(
				'max_tokens' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array( 'message' => $response->get_error_message() )
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'API connection successful! Your AI provider is configured correctly.', 'assistify-for-woocommerce' ),
			)
		);
	}

	/**
	 * Get settings array.
	 *
	 * @since 1.0.0
	 * @return array Settings array.
	 */
	private function get_settings() {
		$settings = array(
			array(
				'title' => esc_html__( 'AI Provider Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure your AI provider and API settings.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_provider_settings',
			),
			array(
				'title'    => esc_html__( 'AI Provider', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Select your preferred AI provider.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_ai_provider',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select assistify-provider-select',
				'default'  => 'openai',
				'options'  => array(
					'openai'    => esc_html__( 'OpenAI', 'assistify-for-woocommerce' ),
					'anthropic' => esc_html__( 'Anthropic', 'assistify-for-woocommerce' ),
					'google'    => esc_html__( 'Google', 'assistify-for-woocommerce' ),
					'xai'       => esc_html__( 'xAI', 'assistify-for-woocommerce' ),
					'deepseek'  => esc_html__( 'DeepSeek', 'assistify-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Model', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Select the AI model to use.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_ai_model',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select assistify-model-select',
				'default'  => 'gpt-4o-mini',
				'options'  => $this->get_all_models(),
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'API Key', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Enter your API key from your selected provider.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_api_key',
				'type'     => 'password',
				'default'  => '',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_provider_settings',
			),

			// Admin Chat Settings.
			array(
				'title' => esc_html__( 'Admin Chat Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure the admin chat assistant.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_admin_chat_settings',
			),
			array(
				'title'   => esc_html__( 'Enable Admin Chat', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Enable the AI assistant in the WordPress admin area.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_admin_chat_enabled',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_admin_chat_settings',
			),

			// Customer Chat Settings.
			array(
				'title' => esc_html__( 'Customer Chat Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure the customer-facing chat widget.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_customer_chat_settings',
			),
			array(
				'title'   => esc_html__( 'Enable Customer Chat', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Enable the AI chat widget for customers on the frontend.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_customer_chat_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Allow Guest Chat', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Allow non-logged-in visitors to use the chat.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_guest_chat_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'    => esc_html__( 'Chat Position', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Choose where the chat widget appears on the screen.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_chat_position',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'default'  => 'bottom-right',
				'options'  => array(
					'bottom-right' => esc_html__( 'Bottom Right', 'assistify-for-woocommerce' ),
					'bottom-left'  => esc_html__( 'Bottom Left', 'assistify-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Assistant Name', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Name shown to customers as the AI assistant.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_assistant_name',
				'type'     => 'text',
				'default'  => 'Ayana',
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Primary Color', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Main color for the chat widget.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_primary_color',
				'type'     => 'color',
				'default'  => '#7F54B3',
				'desc_tip' => true,
			),
			array(
				'title'   => esc_html__( 'Enable Notification Sound', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Play a sound when AI responds.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_sound_enabled',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'    => esc_html__( 'Custom Sound URL', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Optional: URL to a custom notification sound (MP3/WAV).', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_custom_sound_url',
				'type'     => 'url',
				'default'  => '',
				'desc_tip' => true,
			),
			array(
				'title'   => esc_html__( 'Auto-Open Chat', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Automatically open chat after user is idle (once per day).', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_auto_open_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'             => esc_html__( 'Auto-Open Delay', 'assistify-for-woocommerce' ),
				'desc'              => esc_html__( 'Seconds of idle time before auto-opening chat.', 'assistify-for-woocommerce' ),
				'id'                => 'assistify_auto_open_delay',
				'type'              => 'number',
				'default'           => '90',
				'custom_attributes' => array(
					'min'  => '30',
					'max'  => '300',
					'step' => '10',
				),
				'desc_tip'          => true,
			),
			array(
				'title'             => esc_html__( 'Product Context Limit', 'assistify-for-woocommerce' ),
				'desc'              => esc_html__( 'Maximum products to include in AI context for recommendations.', 'assistify-for-woocommerce' ),
				'id'                => 'assistify_product_context_limit',
				'type'              => 'number',
				'default'           => '100',
				'custom_attributes' => array(
					'min'  => '10',
					'max'  => '500',
					'step' => '10',
				),
				'desc_tip'          => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_customer_chat_settings',
			),

			// Privacy Settings.
			array(
				'title' => esc_html__( 'Privacy Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure privacy and data handling options. Privacy policy text is automatically added to Settings > Privacy.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_privacy_settings',
			),
			array(
				'title'   => esc_html__( 'Show Privacy Link', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Display a privacy policy link in the customer chat consent modal.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_show_privacy_link',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => esc_html__( 'Remove Data on Uninstall', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Remove all plugin data when uninstalling the plugin.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_remove_data_on_uninstall',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_privacy_settings',
			),
		);

		return apply_filters( 'assistify_settings', $settings );
	}

	/**
	 * Get all available models as flat array for WooCommerce settings.
	 * JavaScript will filter these based on selected provider.
	 *
	 * @since 1.0.0
	 * @return array Models array (flat key => value).
	 */
	private function get_all_models() {
		// Flatten all models from all providers.
		$all_models = array();

		foreach ( $this->get_models_by_provider() as $models ) {
			$all_models = array_merge( $all_models, $models );
		}

		return $all_models;
	}

	/**
	 * Handle AJAX request for admin chat.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_admin_chat() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page.', 'assistify-for-woocommerce' ) )
			);
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to use this feature.', 'assistify-for-woocommerce' ) )
			);
		}

		// Get and sanitize message.
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $message ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter a message.', 'assistify-for-woocommerce' ) )
			);
		}

		// Check if API key is configured.
		$api_key = get_option( 'assistify_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please configure your AI provider API key in the settings.', 'assistify-for-woocommerce' ) )
			);
		}

		// Get the AI provider.
		$provider = \Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory::get_configured_provider();

		if ( is_wp_error( $provider ) ) {
			wp_send_json_error(
				array( 'message' => $provider->get_error_message() )
			);
		}

		// Get session ID from client.
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		// Detect intent and fetch relevant store data.
		$store_data = $this->fetch_relevant_store_data( $message );

		// Build system prompt with store context and data.
		$system_prompt = $this->get_admin_system_prompt_with_data( $store_data );

		// Get chat history from session (optional - for context).
		$history = $this->get_chat_history_from_session( $session_id );

		// Build messages array.
		$messages   = $history;
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Get selected model.
		$model = get_option( 'assistify_ai_model', '' );

		// Send to AI provider.
		$response = $provider->chat(
			$messages,
			array(
				'system_prompt' => $system_prompt,
				'model'         => ! empty( $model ) ? $model : null,
				'max_tokens'    => 2048,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array( 'message' => $response->get_error_message() )
			);
		}

		// Save to database session.
		if ( ! empty( $session_id ) ) {
			$this->save_message_to_db( $session_id, 'user', $message );
			$this->save_message_to_db( $session_id, 'assistant', $response['content'] );
		}

		// Also store in transient for context continuity.
		$this->save_chat_to_session( $message, $response['content'] );

		wp_send_json_success(
			array(
				'message' => $response['content'],
				'usage'   => isset( $response['usage'] ) ? $response['usage'] : array(),
			)
		);
	}

	/**
	 * Fetch relevant store data based on user message intent.
	 *
	 * Uses the Intent Classifier to intelligently route queries to abilities.
	 *
	 * @since 1.0.0
	 * @param string $message User message.
	 * @return array Store data relevant to the query.
	 */
	private function fetch_relevant_store_data( $message ) {
		// Use the Intent Classifier for smart ability routing.
		$classifier = new \Assistify_For_WooCommerce\Intent_Classifier();

		// Execute matching abilities and get results.
		$results = $classifier->execute_matching_abilities( $message );

		// If no matches, return empty array (general conversation).
		if ( empty( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Check if message matches any keywords.
	 *
	 * @since 1.0.0
	 * @param string $message  Message to check.
	 * @param array  $keywords Keywords to match.
	 * @return bool True if matches.
	 */
	private function message_matches( $message, $keywords ) {
		foreach ( $keywords as $keyword ) {
			if ( strpos( $message, $keyword ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get system prompt with injected store data.
	 *
	 * @since 1.0.0
	 * @param array $store_data Fetched store data.
	 * @return string System prompt with data.
	 */
	private function get_admin_system_prompt_with_data( $store_data ) {
		$base_prompt = $this->get_admin_system_prompt();

		if ( empty( $store_data ) ) {
			return $base_prompt;
		}

		// Decode any HTML entities in the data for clean AI processing.
		$store_data = $this->decode_html_entities_recursive( $store_data );

		$data_context = "\n\n## LIVE STORE DATA (Use this to answer the user's question):\n";

		foreach ( $store_data as $key => $value ) {
			$data_context .= "\n### " . ucwords( str_replace( '_', ' ', $key ) ) . ":\n";
			$data_context .= "```json\n" . wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n```\n";
		}

		$data_context .= "\n**IMPORTANT**: Use the above real store data to answer the user's question. Format the response nicely with markdown (use **bold**, lists, tables where appropriate). Do NOT say you cannot access data - you have the data above.";

		return $base_prompt . $data_context;
	}

	/**
	 * Recursively decode HTML entities in data.
	 *
	 * @since 1.0.0
	 * @param mixed $data Data to decode.
	 * @return mixed Decoded data.
	 */
	private function decode_html_entities_recursive( $data ) {
		if ( is_string( $data ) ) {
			return html_entity_decode( $data, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->decode_html_entities_recursive( $value );
			}
		}

		return $data;
	}

	/**
	 * Get the system prompt for admin chat.
	 *
	 * @since 1.0.0
	 * @return string System prompt.
	 */
	private function get_admin_system_prompt() {
		$store_name      = get_bloginfo( 'name' );
		$currency        = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '$';
		$admin_name      = wp_get_current_user()->display_name;

		$prompt = sprintf(
			/* translators: %1$s: Admin name, %2$s: Store name, %3$s: Currency code, %4$s: Currency symbol. */
			__(
				'You are Ayana, the AI assistant for "%2$s" WooCommerce store. You help %1$s manage their business.

## Your Capabilities:
- **Orders**: View details, list by status, search orders, track fulfillment
- **Products**: Check inventory, low stock alerts, product details, sales performance
- **Customers**: Look up info, purchase history, lifetime value
- **Analytics**: Sales reports, revenue, top products, geographic sales, AOV
- **Coupons**: List coupons, check usage stats, view available codes

## Store Info:
- Currency: %3$s (%4$s)
- Platform: WooCommerce

## Response Guidelines:
1. **Always use the LIVE STORE DATA provided** - never say you cannot access data if data is given
2. Format with **markdown**: bold for emphasis, bullet points for lists
3. For product/order lists, use clean bullet points or simple formatting - avoid complex tables
4. Be concise and direct
5. Include key numbers and metrics
6. Use currency symbol (%4$s) for prices

## CRITICAL - Coupon Display Rules:
When showing coupons, ALWAYS display the coupon code prominently like this:
- **Code**: `COUPONCODE` (in backticks for easy copying)
- Then show discount amount, expiry, usage stats

## Tone:
Friendly, helpful, and efficient. Keep responses focused.',
				'assistify-for-woocommerce'
			),
			$admin_name,
			$store_name,
			$currency,
			$currency_symbol
		);

		/**
		 * Filter the admin system prompt.
		 *
		 * @since 1.0.0
		 * @param string $prompt The system prompt.
		 */
		return apply_filters( 'assistify_admin_system_prompt', $prompt );
	}

	/**
	 * Get chat history from user session.
	 *
	 * @since 1.0.0
	 * @param string $session_id Optional session ID to load from database.
	 * @return array Chat history messages.
	 */
	private function get_chat_history_from_session( $session_id = '' ) {
		// If session ID provided, try to load from database first.
		if ( ! empty( $session_id ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT role, content FROM {$wpdb->prefix}afw_messages WHERE session_id = %s ORDER BY created_at ASC LIMIT 20",
					$session_id
				),
				ARRAY_A
			);

			if ( ! empty( $messages ) ) {
				// Limit to last 10 messages for token management.
				return array_slice( $messages, -10 );
			}
		}

		// Fallback to transient.
		$user_id     = get_current_user_id();
		$session_key = 'assistify_chat_history_' . $user_id;
		$history     = get_transient( $session_key );

		if ( ! is_array( $history ) ) {
			return array();
		}

		// Limit history to last 10 messages to manage token usage.
		return array_slice( $history, -10 );
	}

	/**
	 * Save chat messages to user session.
	 *
	 * @since 1.0.0
	 * @param string $user_message      User's message.
	 * @param string $assistant_message AI response.
	 * @return void
	 */
	private function save_chat_to_session( $user_message, $assistant_message ) {
		$user_id     = get_current_user_id();
		$session_key = 'assistify_chat_history_' . $user_id;
		$history     = get_transient( $session_key );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Add new messages.
		$history[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);
		$history[] = array(
			'role'    => 'assistant',
			'content' => $assistant_message,
		);

		// Keep only last 20 messages.
		$history = array_slice( $history, -20 );

		// Store for 1 hour.
		set_transient( $session_key, $history, HOUR_IN_SECONDS );
	}

	/**
	 * Handle AJAX request to get user's chat sessions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_get_sessions() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		// Get user's sessions with message count and preview (only sessions with messages).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT s.session_id as id, s.last_activity, 
				(SELECT COUNT(*) FROM ' . $wpdb->prefix . 'afw_messages WHERE session_id = s.session_id) as message_count,
				(SELECT content FROM ' . $wpdb->prefix . 'afw_messages WHERE session_id = s.session_id AND role = %s ORDER BY created_at DESC LIMIT 1) as preview
				FROM ' . $wpdb->prefix . 'afw_sessions s 
				WHERE s.user_id = %d AND s.context = %s
				AND EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'afw_messages WHERE session_id = s.session_id)
				ORDER BY s.last_activity DESC
				LIMIT 20',
				'user',
				$user_id,
				'admin'
			),
			ARRAY_A
		);

		wp_send_json_success( array( 'sessions' => $sessions ? $sessions : array() ) );
	}

	/**
	 * Handle AJAX request to get messages for a session.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_get_session_messages() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		if ( empty( $session_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Session ID required.', 'assistify-for-woocommerce' ) ) );
		}

		global $wpdb;

		// Verify session belongs to current user.
		$user_id = get_current_user_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}afw_sessions WHERE session_id = %s AND user_id = %d",
				$session_id,
				$user_id
			)
		);

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'assistify-for-woocommerce' ) ) );
		}

		// Get messages.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, created_at FROM {$wpdb->prefix}afw_messages WHERE session_id = %s ORDER BY created_at ASC",
				$session_id
			),
			ARRAY_A
		);

		wp_send_json_success( array( 'messages' => $messages ? $messages : array() ) );
	}

	/**
	 * Handle AJAX request to create a new session.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_create_session() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		if ( empty( $session_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Session ID required.', 'assistify-for-woocommerce' ) ) );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		// Check if session already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $wpdb->prefix . 'afw_sessions WHERE session_id = %s',
				$session_id
			)
		);

		if ( $existing ) {
			wp_send_json_success(
				array(
					'session_id' => $session_id,
					'status'     => 'exists',
				)
			);
			return;
		}

		// Create new session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'afw_sessions',
			array(
				'session_id'    => $session_id,
				'user_id'       => $user_id,
				'context'       => 'admin',
				'started_at'    => current_time( 'mysql' ),
				'last_activity' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);

		if ( $result ) {
			wp_send_json_success(
				array(
					'session_id' => $session_id,
					'status'     => 'created',
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to create session.', 'assistify-for-woocommerce' ) ) );
		}
	}

	/**
	 * Handle AJAX request to delete a session.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_delete_session() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		if ( empty( $session_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Session ID required.', 'assistify-for-woocommerce' ) ) );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		// Verify session belongs to current user before deleting.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $wpdb->prefix . 'afw_sessions WHERE session_id = %s AND user_id = %d',
				$session_id,
				$user_id
			)
		);

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'assistify-for-woocommerce' ) ) );
		}

		// Delete messages first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'afw_messages',
			array( 'session_id' => $session_id ),
			array( '%s' )
		);

		// Delete session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'afw_sessions',
			array( 'session_id' => $session_id ),
			array( '%s' )
		);

		wp_send_json_success( array( 'deleted' => $session_id ) );
	}

	/**
	 * Handle AJAX request to clear all sessions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_clear_all_sessions() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		// Get all user's session IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT session_id FROM ' . $wpdb->prefix . 'afw_sessions WHERE user_id = %d AND context = %s',
				$user_id,
				'admin'
			)
		);

		if ( ! empty( $session_ids ) ) {
			// Delete messages for each session.
			foreach ( $session_ids as $sid ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete(
					$wpdb->prefix . 'afw_messages',
					array( 'session_id' => $sid ),
					array( '%s' )
				);
			}

			// Delete all sessions.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$wpdb->prefix . 'afw_sessions',
				array(
					'user_id' => $user_id,
					'context' => 'admin',
				),
				array( '%d', '%s' )
			);
		}

		wp_send_json_success( array( 'cleared' => count( $session_ids ) ) );
	}

	/**
	 * Save message to database session.
	 *
	 * @since 1.0.0
	 * @param string $session_id Session ID.
	 * @param string $role       Message role (user/assistant).
	 * @param string $content    Message content.
	 * @return bool Success status.
	 */
	private function save_message_to_db( $session_id, $role, $content ) {
		global $wpdb;
		$user_id = get_current_user_id();

		// Ensure session exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $wpdb->prefix . 'afw_sessions WHERE session_id = %s',
				$session_id
			)
		);

		if ( ! $session_exists ) {
			// Create session if it doesn't exist.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'afw_sessions',
				array(
					'session_id'    => $session_id,
					'user_id'       => $user_id,
					'context'       => 'admin',
					'started_at'    => current_time( 'mysql' ),
					'last_activity' => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s' )
			);
		} else {
			// Update last activity.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'afw_sessions',
				array( 'last_activity' => current_time( 'mysql' ) ),
				array( 'session_id' => $session_id ),
				array( '%s' ),
				array( '%s' )
			);
		}

		// Insert message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'afw_messages',
			array(
				'session_id' => $session_id,
				'role'       => $role,
				'content'    => $content,
				'context'    => 'admin',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		// Occasionally clean up old sessions (1 in 50 chance to avoid performance impact).
		if ( wp_rand( 1, 50 ) === 1 ) {
			$this->cleanup_old_sessions( $user_id );
		}

		return false !== $result;
	}

	/**
	 * Clean up old sessions to maintain performance.
	 * Keeps only the last 50 sessions per user, deletes older ones.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function cleanup_old_sessions( $user_id ) {
		global $wpdb;

		$max_sessions = 50;

		// Get the timestamp of the 50th most recent session.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cutoff_time = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT last_activity FROM ' . $wpdb->prefix . 'afw_sessions 
				WHERE user_id = %d AND context = %s 
				ORDER BY last_activity DESC 
				LIMIT 1 OFFSET %d',
				$user_id,
				'admin',
				$max_sessions - 1
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// If no cutoff (less than max sessions), nothing to clean.
		if ( empty( $cutoff_time ) ) {
			return;
		}

		// Get session IDs to delete (older than cutoff).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions_to_delete = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT session_id FROM ' . $wpdb->prefix . 'afw_sessions 
				WHERE user_id = %d AND context = %s AND last_activity < %s',
				$user_id,
				'admin',
				$cutoff_time
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $sessions_to_delete ) ) {
			return;
		}

		// Delete messages and sessions one by one to avoid complex IN clauses.
		foreach ( $sessions_to_delete as $session_id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			// Delete messages for this session.
			$wpdb->delete(
				$wpdb->prefix . 'afw_messages',
				array( 'session_id' => $session_id ),
				array( '%s' )
			);

			// Delete the session.
			$wpdb->delete(
				$wpdb->prefix . 'afw_sessions',
				array( 'session_id' => $session_id ),
				array( '%s' )
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}
