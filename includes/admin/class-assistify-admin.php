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
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'assistify_admin_nonce' ),
				'strings'               => array(
					'error'       => esc_html__( 'Sorry, something went wrong. Please try again.', 'assistify-for-woocommerce' ),
					'loading'     => esc_html__( 'Loading...', 'assistify-for-woocommerce' ),
					'placeholder' => esc_html__( 'Ask Ayana anything...', 'assistify-for-woocommerce' ),
					'openChat'    => esc_html__( 'Chat with Ayana', 'assistify-for-woocommerce' ),
				),
				'settings'              => array(
					'chatEnabled'   => get_option( 'assistify_admin_chat_enabled', 'yes' ),
					'position'      => get_option( 'assistify_chat_position', 'bottom-right' ),
					'apiConfigured' => $this->is_api_key_configured(),
				),
				'modelsByProvider'      => $this->get_models_by_provider(),
				'defaultModels'         => $this->get_default_models(),
				'imageModelsByProvider' => $this->get_image_models_by_provider(),
				'defaultImageModels'    => $this->get_default_image_models(),
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
				'gpt-5.1'     => 'GPT-5.1',
				'gpt-5'       => 'GPT-5',
				'gpt-5-pro'   => 'GPT-5 Pro',
				'gpt-5-mini'  => 'GPT-5 Mini',
				'gpt-5-nano'  => 'GPT-5 Nano',
				'gpt-4.1'     => 'GPT-4.1',
				'gpt-4o'      => 'GPT-4o',
				'gpt-4o-mini' => 'GPT-4o Mini',
				'o3-mini'     => 'o3-mini',
				'o1'          => 'o1',
				'o1-mini'     => 'o1-mini',
				'o1-pro'      => 'o1-pro',
			),
			'anthropic' => array(
				'claude-opus-4-5-20250514'   => 'Claude Opus 4.5',
				'claude-sonnet-4-5-20250514' => 'Claude Sonnet 4.5',
				'claude-haiku-4-5-20250514'  => 'Claude Haiku 4.5',
				'claude-opus-4-1-20250514'   => 'Claude Opus 4.1',
				'claude-opus-4-20250514'     => 'Claude Opus 4',
				'claude-sonnet-4-20250514'   => 'Claude Sonnet 4',
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
			),
			'google'    => array(
				'gemini-3-pro-preview'  => 'Gemini 3 Pro',
				'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
				'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
				'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
				'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
				'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite',
				'gemini-1.5-pro'        => 'Gemini 1.5 Pro',
				'gemini-1.5-flash'      => 'Gemini 1.5 Flash',
			),
			'xai'       => array(
				'grok-4-0709'                 => 'Grok 4',
				'grok-4-fast-reasoning'       => 'Grok 4 Fast (Reasoning)',
				'grok-4-fast-non-reasoning'   => 'Grok 4 Fast',
				'grok-4-1-fast-reasoning'     => 'Grok 4.1 Fast (Reasoning)',
				'grok-4-1-fast-non-reasoning' => 'Grok 4.1 Fast',
				'grok-code-fast-1'            => 'Grok Code Fast',
				'grok-3'                      => 'Grok 3',
				'grok-3-mini'                 => 'Grok 3 Mini',
				'grok-2-vision-1212'          => 'Grok 2 Vision',
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
			'anthropic' => 'claude-sonnet-4-5-20250514',
			'google'    => 'gemini-2.5-flash',
			'xai'       => 'grok-4-fast-non-reasoning',
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
				'desc'  => esc_html__( 'Configure your AI provider and API settings. The same API key works for both text and image generation.', 'assistify-for-woocommerce' ),
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
				'title'    => esc_html__( 'API Key', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Enter your API key from your selected provider.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_api_key',
				'type'     => 'password',
				'default'  => '',
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Text Model', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Select the AI model for text generation (chat, content).', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_ai_model',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select assistify-model-select',
				'default'  => 'gpt-4o-mini',
				'options'  => $this->get_all_models(),
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Image Model', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Select the AI model for image generation.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_image_model',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select assistify-image-model-select',
				'default'  => 'gpt-image-1',
				'options'  => $this->get_all_image_models(),
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
				'default'  => '#6861F2',
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

			// Content Generation Settings.
			array(
				'title' => esc_html__( 'Content Generation Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure defaults for AI-powered content generation.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_content_settings',
			),
			array(
				'title'    => esc_html__( 'Default Tone', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Default writing style for generated content.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_content_default_tone',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'default'  => 'professional',
				'options'  => array(
					'professional' => esc_html__( 'Professional - Trustworthy and feature-focused', 'assistify-for-woocommerce' ),
					'casual'       => esc_html__( 'Casual - Friendly and conversational', 'assistify-for-woocommerce' ),
					'luxury'       => esc_html__( 'Luxury - Elegant and sophisticated', 'assistify-for-woocommerce' ),
					'playful'      => esc_html__( 'Playful - Fun and energetic', 'assistify-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'             => esc_html__( 'Content Length (Words)', 'assistify-for-woocommerce' ),
				'desc'              => esc_html__( 'Target word count for generated content (product descriptions, post content, page content).', 'assistify-for-woocommerce' ),
				'id'                => 'assistify_content_default_length',
				'type'              => 'number',
				'default'           => '600',
				'placeholder'       => '600',
				'custom_attributes' => array(
					'min'  => '50',
					'max'  => '5000',
					'step' => '50',
				),
				'desc_tip'          => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_content_settings',
			),

			// Image Generation Settings (Output Options).
			array(
				'title' => esc_html__( 'Image Generation Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure default image output settings. Image generation is automatically enabled when an Image Model is selected above. Note: Anthropic and DeepSeek do not support image generation.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_image_settings',
			),
			array(
				'title'    => esc_html__( 'Default Size', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Default image dimensions for generation.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_image_size',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'default'  => '1024x1024',
				'options'  => array(
					'1024x1024' => esc_html__( '1024×1024 (Square)', 'assistify-for-woocommerce' ),
					'1024x1536' => esc_html__( '1024×1536 (Portrait)', 'assistify-for-woocommerce' ),
					'1536x1024' => esc_html__( '1536×1024 (Landscape)', 'assistify-for-woocommerce' ),
					'auto'      => esc_html__( 'Auto (Let AI decide)', 'assistify-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Default Quality', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Image quality level. Higher quality costs more.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_image_quality',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'default'  => 'auto',
				'options'  => array(
					'auto'     => esc_html__( 'Auto (Recommended)', 'assistify-for-woocommerce' ),
					'standard' => esc_html__( 'Standard', 'assistify-for-woocommerce' ),
					'hd'       => esc_html__( 'HD (Higher cost)', 'assistify-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Default Style', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Image style for generation.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_image_style',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'default'  => 'natural',
				'options'  => array(
					'natural' => esc_html__( 'Natural - Realistic photos', 'assistify-for-woocommerce' ),
					'vivid'   => esc_html__( 'Vivid - More dramatic and artistic', 'assistify-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_image_settings',
			),

			// Background Removal (Remove.bg) Settings.
			array(
				'title' => esc_html__( 'Background Removal', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => sprintf(
					/* translators: %s: Link to remove.bg API page */
					esc_html__( 'Remove image backgrounds using Remove.bg API. Get your API key at %s (50 free API calls/month).', 'assistify-for-woocommerce' ),
					'<a href="https://www.remove.bg/api#api-key" target="_blank" rel="noopener">remove.bg</a>'
				),
				'id'    => 'assistify_removebg_settings',
			),
			array(
				'title'    => esc_html__( 'Remove.bg API Key', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Enter your Remove.bg API key for background removal feature.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_removebg_api_key',
				'type'     => 'password',
				'default'  => '',
				'desc_tip' => true,
			),
			array(
				'title'    => esc_html__( 'Output Size', 'assistify-for-woocommerce' ),
				'desc'     => esc_html__( 'Default size for background-removed images.', 'assistify-for-woocommerce' ),
				'id'       => 'assistify_removebg_size',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'default'  => 'auto',
				'options'  => array(
					'auto'    => esc_html__( 'Auto (Match original)', 'assistify-for-woocommerce' ),
					'preview' => esc_html__( 'Preview (Up to 500x500)', 'assistify-for-woocommerce' ),
					'small'   => esc_html__( 'Small (Up to 625x400)', 'assistify-for-woocommerce' ),
					'regular' => esc_html__( 'Regular (Up to 1500x1000)', 'assistify-for-woocommerce' ),
					'hd'      => esc_html__( 'HD (Up to 2500x2500)', 'assistify-for-woocommerce' ),
					'4k'      => esc_html__( '4K (Up to 4096x4096)', 'assistify-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_removebg_settings',
			),

			// Health Monitoring Settings.
			array(
				'title' => esc_html__( 'Health Monitoring', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure store health monitoring and email alerts. Alerts are sent to the admin email when issues are detected.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_health_settings',
			),
			array(
				'title'   => esc_html__( 'Enable Health Monitoring', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Monitor store health and display status in admin dashboard.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_enable_health_monitoring',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => esc_html__( 'Critical Issue Alerts', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Send email when critical issues are detected (PHP errors, security issues).', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_alert_critical',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => esc_html__( 'Payment Failure Alerts', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Send email when multiple payment failures occur within an hour.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_alert_payment',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => esc_html__( 'Low Stock Alerts', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Send email when products are running low in stock.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_alert_stock',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'       => esc_html__( 'Alert Email', 'assistify-for-woocommerce' ),
				'desc'        => esc_html__( 'Email address for health alerts. Leave empty to use WooCommerce admin email.', 'assistify-for-woocommerce' ),
				'id'          => 'assistify_alert_email',
				'type'        => 'email',
				'default'     => '',
				'placeholder' => get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
				'desc_tip'    => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_health_settings',
			),

			// Advanced Settings.
			array(
				'title' => esc_html__( 'Advanced Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Developer and debugging options.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_advanced_settings',
			),
			array(
				'title'   => esc_html__( 'Enable Debug Logging', 'assistify-for-woocommerce' ),
				'desc'    => sprintf(
					/* translators: %s: Link to WooCommerce logs */
					esc_html__( 'Log API requests and errors for troubleshooting. View logs in %s.', 'assistify-for-woocommerce' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">' . esc_html__( 'WooCommerce > Status > Logs', 'assistify-for-woocommerce' ) . '</a>'
				),
				'id'      => 'assistify_debug_logging',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'assistify_advanced_settings',
			),

			// Privacy & AI Compliance Settings.
			array(
				'title' => esc_html__( 'Privacy & AI Compliance', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure GDPR, AI transparency, and data handling settings. Customer consent is required before chat use. Sensitive data (credit cards, passwords) is automatically redacted before sending to AI providers.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_privacy_settings',
			),
			array(
				'title'       => esc_html__( 'Consent Notice Text', 'assistify-for-woocommerce' ),
				'desc'        => esc_html__( 'Customize the consent text shown to customers. Leave empty for default.', 'assistify-for-woocommerce' ),
				'id'          => 'assistify_consent_text',
				'type'        => 'textarea',
				'default'     => '',
				'css'         => 'width:100%; height:60px;',
				'placeholder' => esc_attr__( 'This chat is powered by AI to help you find products, answer questions, and assist with your orders. By continuing, you agree that your messages will be processed to provide responses.', 'assistify-for-woocommerce' ),
				'desc_tip'    => true,
			),
			array(
				'title'   => esc_html__( 'Show Privacy Link', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Display a privacy policy link in the consent notice.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_show_privacy_link',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => esc_html__( 'Show AI Attribution', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Display AI provider attribution in chat footer for transparency compliance.', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_show_ai_attribution',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => esc_html__( 'Label AI-Generated Content', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Add "AI-generated" label to content created by AI (product descriptions, etc.).', 'assistify-for-woocommerce' ),
				'id'      => 'assistify_label_ai_content',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'             => esc_html__( 'Audit Log Retention (Days)', 'assistify-for-woocommerce' ),
				'desc'              => esc_html__( 'How long to keep AI activity logs. Set to 0 to disable logging.', 'assistify-for-woocommerce' ),
				'id'                => 'assistify_audit_log_retention',
				'type'              => 'number',
				'default'           => '90',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '365',
					'step' => '30',
				),
				'desc_tip'          => true,
			),
			array(
				'title'   => esc_html__( 'Remove Data on Uninstall', 'assistify-for-woocommerce' ),
				'desc'    => esc_html__( 'Remove all plugin data (settings, logs, sessions) when uninstalling.', 'assistify-for-woocommerce' ),
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
	 * Get all available image models as flat array for WooCommerce settings.
	 *
	 * @since 1.0.0
	 * @return array Image models array (flat key => value).
	 */
	private function get_all_image_models() {
		$all_models = array();

		foreach ( $this->get_image_models_by_provider() as $models ) {
			$all_models = array_merge( $all_models, $models );
		}

		return $all_models;
	}

	/**
	 * Get image models organized by provider.
	 *
	 * @since 1.0.0
	 * @return array Image models by provider.
	 */
	private function get_image_models_by_provider() {
		return array(
			'openai'    => array(
				'gpt-image-1'      => 'GPT Image 1 (Recommended)',
				'gpt-image-1-mini' => 'GPT Image 1 Mini (Faster)',
				'dall-e-3'         => 'DALL-E 3 (Stable)',
			),
			'anthropic' => array(),
			'google'    => array(
				'imagen-4.0-generate-001'       => 'Imagen 4.0',
				'imagen-4.0-ultra-generate-001' => 'Imagen 4.0 Ultra',
				'imagen-4.0-fast-generate-001'  => 'Imagen 4.0 Fast',
				'imagen-3.0-generate-002'       => 'Imagen 3.0',
			),
			'xai'       => array(
				'grok-2-image-1212' => 'Grok 2 Image',
			),
			'deepseek'  => array(),
		);
	}

	/**
	 * Get default image model for each provider.
	 *
	 * @since 1.0.0
	 * @return array Default image models.
	 */
	private function get_default_image_models() {
		return array(
			'openai'    => 'gpt-image-1',
			'anthropic' => '',
			'google'    => 'imagen-4.0-generate-001',
			'xai'       => 'grok-2-image-1212',
			'deepseek'  => '',
		);
	}

	/**
	 * Handle AJAX request for admin chat.
	 *
	 * Uses agentic approach with function/tool calling for actions.
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

		// Log admin chat request if debug enabled.
		\Assistify_For_WooCommerce\Assistify_Logger::debug(
			'Admin chat request (agentic)',
			'admin-chat',
			array( 'message' => substr( $message, 0, 100 ) )
		);

		// Use agentic approach with function calling.
		$response = $this->process_agentic_chat( $provider, $message, $session_id );

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
				'message'       => $response['content'],
				'usage'         => isset( $response['usage'] ) ? $response['usage'] : array(),
				'actions_taken' => isset( $response['actions_taken'] ) ? $response['actions_taken'] : array(),
			)
		);
	}

	/**
	 * Process chat using agentic approach with function calling.
	 *
	 * This method implements a tool-calling loop where:
	 * 1. Send message to AI with available tools
	 * 2. If AI calls tools, execute them and send results back
	 * 3. Continue until AI returns a final text response
	 *
	 * @since 1.0.0
	 * @param object $provider   The AI provider instance.
	 * @param string $message    User message.
	 * @param string $session_id Session ID for context.
	 * @return array|\WP_Error Response with content or error.
	 */
	private function process_agentic_chat( $provider, $message, $session_id ) {
		// Get admin tools.
		$admin_tools = Admin_Tools::instance();
		$tools       = $admin_tools->get_tools_for_openai();

		// Build system prompt for agentic mode.
		$system_prompt = $this->get_agentic_system_prompt();

		// Get chat history.
		$history = $this->get_chat_history_from_session( $session_id );

		// Build initial messages.
		$messages   = $history;
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Get selected model.
		$model = get_option( 'assistify_ai_model', '' );

		$options = array(
			'system_prompt' => $system_prompt,
			'model'         => ! empty( $model ) ? $model : null,
			'max_tokens'    => 2048,
			'temperature'   => 0.7,
		);

		// Track actions taken.
		$actions_taken  = array();
		$max_iterations = 5; // Prevent infinite loops.
		$iteration      = 0;

		// Check if provider supports tool calling.
		if ( ! method_exists( $provider, 'chat_with_tools' ) ) {
			// Fallback to regular chat for providers without tool support.
			\Assistify_For_WooCommerce\Assistify_Logger::debug(
				'Provider does not support tools, using regular chat',
				'admin-chat'
			);

			$response = $provider->chat( $messages, $options );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return array(
				'content'       => $response['content'],
				'usage'         => $response['usage'] ?? array(),
				'actions_taken' => array(),
			);
		}

		// Tool calling loop.
		while ( $iteration < $max_iterations ) {
			++$iteration;

			\Assistify_For_WooCommerce\Assistify_Logger::debug(
				sprintf( 'Agentic chat iteration %d', $iteration ),
				'admin-chat'
			);

			// Call AI with tools.
			$response = $provider->chat_with_tools( $messages, $tools, $options );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// Check response type.
			if ( 'content' === $response['type'] ) {
				// AI returned a final text response.
				return array(
					'content'       => $response['content'],
					'usage'         => $response['usage'] ?? array(),
					'actions_taken' => $actions_taken,
				);
			}

			if ( 'tool_calls' === $response['type'] && ! empty( $response['tool_calls'] ) ) {
				// AI wants to call tools.
				// Add assistant message with tool calls to conversation.
				$messages[] = $response['message'];

				// Process each tool call.
				foreach ( $response['tool_calls'] as $tool_call ) {
					$tool_name = $tool_call['function']['name'] ?? '';
					$tool_args = array();

					if ( isset( $tool_call['function']['arguments'] ) ) {
						$tool_args = json_decode( $tool_call['function']['arguments'], true );
						if ( ! is_array( $tool_args ) ) {
							$tool_args = array();
						}
					}

					\Assistify_For_WooCommerce\Assistify_Logger::info(
						sprintf( 'Executing tool: %s', $tool_name ),
						'admin-chat',
						array( 'args' => $tool_args )
					);

					// Execute the tool.
					$tool_result = $admin_tools->execute( $tool_name, $tool_args );

					if ( is_wp_error( $tool_result ) ) {
						$tool_result = array(
							'success' => false,
							'error'   => $tool_result->get_error_message(),
						);
					}

					// Track action taken.
					$actions_taken[] = array(
						'tool'   => $tool_name,
						'args'   => $tool_args,
						'result' => $tool_result,
					);

					// Add tool result to conversation.
					$messages[] = array(
						'role'         => 'tool',
						'tool_call_id' => $tool_call['id'],
						'content'      => wp_json_encode( $tool_result ),
					);
				}

				// Continue the loop to get AI's response to tool results.
				continue;
			}

			// Unexpected response type - break loop.
			break;
		}

		// If we exit the loop without a content response, return error.
		return new \WP_Error(
			'assistify_agent_error',
			__( 'Agent could not complete the request. Please try again.', 'assistify-for-woocommerce' )
		);
	}

	/**
	 * Get the system prompt for agentic mode.
	 *
	 * @since 1.0.0
	 * @return string System prompt.
	 */
	private function get_agentic_system_prompt() {
		$store_name      = get_bloginfo( 'name' );
		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol() )
			: '$';
		$admin_name      = wp_get_current_user()->display_name;

		$prompt = sprintf(
			'You are Ayana, an AI assistant for the "%s" WooCommerce store. You help %s manage their store.

## YOUR CAPABILITIES
You have access to tools that let you ACTUALLY perform actions, not just describe them:

### Actions You Can Take:
- **Create coupons** with any discount type, amount, and conditions
- **Enable/disable payment gateways** (PayPal, Stripe, Cash on Delivery, etc.)
- **Update WooCommerce settings** (guest checkout, coupons, reviews, etc.)
- **Update order status** and add order notes
- **Update product stock** and prices

### Information You Can Retrieve:
- Order details and recent orders
- Product details and search products
- Coupon list and details
- Payment gateway status
- Store settings
- Sales summaries

## HOW TO RESPOND

1. **For action requests**: USE THE TOOLS to actually perform the action, then confirm what you did.
   Example: "Create a 10%% coupon" → Call create_coupon tool → "Done! Created coupon SAVE10 for 10%% off."

2. **For information requests**: USE THE TOOLS to get real data, then present it clearly.
   Example: "How many payment methods?" → Call get_payment_gateways → "You have 3 enabled: PayPal, Stripe, COD."

3. **Be concise**: After performing an action, confirm briefly. No lengthy explanations.

4. **Use markdown**: Bold for emphasis, backticks for codes like `COUPON123`.

## IMPORTANT RULES
- NEVER say "I cannot do that" if you have a tool for it - USE THE TOOL
- NEVER give step-by-step instructions for things you can do directly
- ALWAYS use tools to get real data instead of making assumptions
- After tool execution, report what actually happened based on the result

## STORE INFO
- Currency: %s
- Admin: %s',
			$store_name,
			$admin_name,
			$currency_symbol,
			$admin_name
		);

		return $prompt;
	}

	/**
	 * Build the confirmation message for a pending action.
	 *
	 * @since 1.0.0
	 * @param array $pending Pending action data.
	 * @return string Formatted confirmation message.
	 */
	private function build_action_confirmation_message( $pending ) {
		$message  = "## Action Confirmation Required\n\n";
		$message .= "I understand you want to perform the following action:\n\n";
		$message .= '**' . esc_html( $pending['preview'] ) . "**\n\n";

		if ( ! empty( $pending['is_destructive'] ) ) {
			$message .= "**Warning: This is a destructive action and cannot be undone.**\n\n";
		}

		$message .= "Please confirm or cancel this action using the buttons below.\n";

		return $message;
	}

	/**
	 * Handle action confirmation AJAX request.
	 *
	 * @since 1.0.0
	 */
	public function handle_confirm_action() {
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

		// Get confirmation token.
		$token = isset( $_POST['confirmation_token'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation_token'] ) ) : '';

		if ( empty( $token ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid confirmation token.', 'assistify-for-woocommerce' ) )
			);
		}

		// Execute the confirmed action.
		$classifier = new \Assistify_For_WooCommerce\Intent_Classifier();
		$result     = $classifier->execute_confirmed_action( $token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() )
			);
		}

		// Get session ID for logging.
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		// Build success message.
		$success_message  = "## Action Completed\n\n";
		$success_message .= esc_html( $result['message'] ) . "\n\n";

		if ( ! empty( $result['result'] ) && is_array( $result['result'] ) ) {
			// Add relevant details from the result.
			if ( isset( $result['result']['id'] ) ) {
				$success_message .= '**ID:** ' . $result['result']['id'] . "\n";
			}
			if ( isset( $result['result']['status'] ) ) {
				$success_message .= '**Status:** ' . ucfirst( $result['result']['status'] ) . "\n";
			}
			if ( isset( $result['result']['edit_url'] ) ) {
				$success_message .= "\n[View/Edit →](" . esc_url( $result['result']['edit_url'] ) . ")\n";
			}
		}

		// Save to database session.
		if ( ! empty( $session_id ) ) {
			$this->save_message_to_db( $session_id, 'assistant', $success_message );
		}

		wp_send_json_success(
			array(
				'message' => $success_message,
				'result'  => $result['result'],
			)
		);
	}

	/**
	 * Handle action cancellation AJAX request.
	 *
	 * @since 1.0.0
	 */
	public function handle_cancel_action() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) )
			);
		}

		// Get confirmation token and delete the pending action.
		$token = isset( $_POST['confirmation_token'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation_token'] ) ) : '';

		if ( ! empty( $token ) ) {
			delete_transient( 'assistify_pending_action_' . $token );
		}

		// Get session ID for logging.
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		$cancel_message = __( 'Action cancelled. Is there anything else I can help you with?', 'assistify-for-woocommerce' );

		// Save to database session.
		if ( ! empty( $session_id ) ) {
			$this->save_message_to_db( $session_id, 'assistant', $cancel_message );
		}

		wp_send_json_success(
			array( 'message' => $cancel_message )
		);
	}

	/**
	 * Fetch relevant store data based on user message intent.
	 *
	 * Uses a hybrid approach:
	 * 1. Always provide baseline context (recent activity, summary)
	 * 2. Use Intent Classifier for specific queries
	 *
	 * This ensures the AI always has context (like customer chat) while
	 * still allowing specific data fetching for detailed queries.
	 *
	 * @since 1.0.0
	 * @param string $message User message.
	 * @return array Store data relevant to the query.
	 */
	/**
	 * Fetch relevant store data for admin chat.
	 *
	 * Simplified approach: Always inject comprehensive context like customer chat.
	 * No intent classification - let AI respond naturally based on provided data.
	 *
	 * @since 1.0.0
	 * @param string $message The user message.
	 * @return array Store data context.
	 */
	private function fetch_relevant_store_data( $message ) {
		$results = array();

		// Get comprehensive baseline context.
		$baseline = $this->get_baseline_admin_context();
		if ( ! empty( $baseline ) ) {
			$results['store_overview'] = $baseline;
		}

		// For specific queries, add additional context based on simple keyword detection.
		$additional = $this->get_additional_context_for_query( $message );
		if ( ! empty( $additional ) ) {
			$results = array_merge( $results, $additional );
		}

		return $results;
	}

	/**
	 * Get additional context based on user query keywords.
	 *
	 * Simple keyword detection (not intent classification) to enrich context
	 * when user asks about specific things.
	 *
	 * @since 1.0.0
	 * @param string $message The user message.
	 * @return array Additional context data.
	 */
	private function get_additional_context_for_query( $message ) {
		$abilities = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();
		$results   = array();
		$message   = strtolower( $message );

		// If asking about specific order number.
		if ( preg_match( '/order\s*#?\s*(\d+)/i', $message, $matches ) ) {
			$order_data = $abilities->execute( 'afw/orders/get', array( 'order_id' => (int) $matches[1] ) );
			if ( ! is_wp_error( $order_data ) ) {
				$results['specific_order'] = $order_data;
			}
		}

		// If asking about products.
		if ( preg_match( '/product|item|inventory|stock/i', $message ) ) {
			// Get recent products.
			$products = $abilities->execute( 'afw/products/list', array( 'limit' => 10 ) );
			if ( ! is_wp_error( $products ) ) {
				$results['products'] = $products;
			}

			// Check for low stock.
			if ( preg_match( '/low\s*stock|out\s*of\s*stock/i', $message ) ) {
				$low_stock = $abilities->execute( 'afw/products/low-stock', array( 'threshold' => 5 ) );
				if ( ! is_wp_error( $low_stock ) ) {
					$results['low_stock_products'] = $low_stock;
				}
			}
		}

		// If asking about coupons.
		if ( preg_match( '/coupon|discount|promo/i', $message ) ) {
			$coupons = $abilities->execute( 'afw/coupons/list', array( 'limit' => 10 ) );
			if ( ! is_wp_error( $coupons ) ) {
				$results['coupons'] = $coupons;
			}
		}

		// If asking about customers.
		if ( preg_match( '/customer|buyer|client/i', $message ) ) {
			$customers = $abilities->execute( 'afw/customers/recent', array( 'limit' => 10 ) );
			if ( ! is_wp_error( $customers ) ) {
				$results['recent_customers'] = $customers;
			}
		}

		// If asking about sales/revenue/analytics.
		if ( preg_match( '/sales|revenue|earning|analytics|report/i', $message ) ) {
			$sales = $abilities->execute( 'afw/analytics/sales', array( 'days' => 30 ) );
			if ( ! is_wp_error( $sales ) ) {
				$results['sales_analytics'] = $sales;
			}
		}

		// If asking about settings, payment, or shipping.
		if ( preg_match( '/setting|payment|gateway|checkout|shipping|tax/i', $message ) ) {
			// Full store settings.
			$store_settings = $abilities->execute( 'afw/store/settings', array() );
			if ( ! is_wp_error( $store_settings ) ) {
				$results['store_settings'] = $store_settings;
			}

			// Full payment gateways list.
			$gateways = $abilities->execute( 'afw/store/payment-gateways', array() );
			if ( ! is_wp_error( $gateways ) ) {
				$results['all_payment_gateways'] = $gateways;
			}

			// Shipping zones details.
			if ( preg_match( '/shipping/i', $message ) ) {
				$shipping = $abilities->execute( 'afw/store/shipping-zones', array() );
				if ( ! is_wp_error( $shipping ) ) {
					$results['shipping_zones'] = $shipping;
				}
			}

			// Tax settings.
			if ( preg_match( '/tax/i', $message ) ) {
				$tax_rates = $abilities->execute( 'afw/store/tax-rates', array() );
				if ( ! is_wp_error( $tax_rates ) ) {
					$results['tax_rates'] = $tax_rates;
				}
			}
		}

		return $results;
	}

	/**
	 * Get baseline admin context that is ALWAYS injected.
	 *
	 * Similar to how customer chat always has order/cart context,
	 * admin chat always has recent activity context.
	 *
	 * @since 1.0.0
	 * @return array Baseline context data.
	 */
	private function get_baseline_admin_context() {
		$abilities = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();
		$context   = array();

		// Today's summary (orders, revenue, customers).
		$summary = $abilities->execute( 'afw/analytics/daily-summary', array( 'days' => 1 ) );
		if ( ! is_wp_error( $summary ) && ! empty( $summary ) ) {
			$context['today'] = array(
				'orders'        => $summary['total_orders'] ?? 0,
				'revenue'       => $summary['total_sales_formatted'] ?? '$0.00',
				'new_customers' => $summary['new_customers'] ?? 0,
			);
		}

		// Recent orders (last 5).
		$recent_orders_result = $abilities->execute( 'afw/orders/list', array( 'limit' => 5 ) );
		if ( ! is_wp_error( $recent_orders_result ) && ! empty( $recent_orders_result['orders'] ) ) {
			$context['recent_orders'] = array();
			foreach ( $recent_orders_result['orders'] as $order ) {
				$order_id                   = $order['id'] ?? 0;
				$context['recent_orders'][] = array(
					'id'       => $order_id,
					'number'   => $order['number'] ?? $order_id,
					'status'   => $order['status_label'] ?? $order['status'] ?? 'unknown',
					'total'    => $order['total'] ?? 'N/A',
					'customer' => $order['customer'] ?? 'Guest',
					'date'     => $order['date_created'] ?? '',
					'edit_url' => $order_id ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '',
				);
			}
		}

		// Pending actions count.
		$processing = $abilities->execute( 'afw/orders/processing-count', array() );
		if ( ! is_wp_error( $processing ) && ! empty( $processing ) ) {
			$context['pending_actions'] = array(
				'to_ship'          => $processing['processing'] ?? 0,
				'awaiting_payment' => $processing['pending'] ?? 0,
				'on_hold'          => $processing['on-hold'] ?? 0,
			);
		}

		// Low stock products (top 5).
		$low_stock = $abilities->execute(
			'afw/products/low-stock',
			array(
				'threshold' => 5,
				'limit'     => 5,
			)
		);
		if ( ! is_wp_error( $low_stock ) && ! empty( $low_stock ) ) {
			$context['low_stock_alert'] = count( $low_stock ) . ' products low on stock';
		}

		// Active coupons count.
		$coupons = $abilities->execute(
			'afw/coupons/list',
			array(
				'status' => 'publish',
				'limit'  => 100,
			)
		);
		if ( ! is_wp_error( $coupons ) ) {
			$context['active_coupons'] = count( $coupons );
		}

		// Store currency.
		$context['currency'] = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

		// Payment gateways (enabled ones).
		$payment_gateways = $abilities->execute( 'afw/store/payment-gateways', array() );
		if ( ! is_wp_error( $payment_gateways ) && ! empty( $payment_gateways ) ) {
			$enabled_gateways      = array();
			$enabled_gateways_list = array();
			foreach ( $payment_gateways as $gateway ) {
				if ( ! empty( $gateway['enabled'] ) ) {
					$enabled_gateways_list[] = array(
						'id'    => $gateway['id'] ?? '',
						'title' => $gateway['title'] ?? $gateway['id'],
					);
				}
			}
			$context['payment_gateways'] = array(
				'enabled_count' => count( $enabled_gateways_list ),
				'enabled_list'  => $enabled_gateways_list,
				'settings_url'  => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
			);
		}

		// Shipping zones count.
		$shipping_zones = $abilities->execute( 'afw/store/shipping-zones', array() );
		if ( ! is_wp_error( $shipping_zones ) && ! empty( $shipping_zones ) ) {
			$context['shipping'] = array(
				'zones_count'  => count( $shipping_zones ),
				'settings_url' => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
			);
		}

		return $context;
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

		// Decode any HTML entities in the data for clean AI processing.
		$store_data = $this->decode_html_entities_recursive( $store_data );

		$data_context = "\n\n## 📊 LIVE STORE DATA\n\n";

		// Check if we have baseline overview.
		$has_overview = isset( $store_data['store_overview'] ) && ! empty( $store_data['store_overview'] );

		if ( $has_overview ) {
			$overview      = $store_data['store_overview'];
			$data_context .= "### 🏪 Store Overview (Real-time):\n";

			if ( isset( $overview['today'] ) ) {
				$data_context .= "**Today's Activity:**\n";
				$data_context .= '- Orders: ' . ( $overview['today']['orders'] ?? 0 ) . "\n";
				$data_context .= '- Revenue: ' . ( $overview['today']['revenue'] ?? '$0.00' ) . "\n";
				$data_context .= '- New Customers: ' . ( $overview['today']['new_customers'] ?? 0 ) . "\n\n";
			}

			if ( isset( $overview['pending_actions'] ) ) {
				$data_context .= "**Pending Actions:**\n";
				$data_context .= '- Orders to Ship: ' . ( $overview['pending_actions']['to_ship'] ?? 0 ) . "\n";
				$data_context .= '- Awaiting Payment: ' . ( $overview['pending_actions']['awaiting_payment'] ?? 0 ) . "\n";
				$data_context .= '- On Hold: ' . ( $overview['pending_actions']['on_hold'] ?? 0 ) . "\n\n";
			}

			if ( isset( $overview['low_stock_alert'] ) ) {
				$data_context .= '**Low Stock Alert:** ' . $overview['low_stock_alert'] . "\n\n";
			}

			if ( isset( $overview['recent_orders'] ) && ! empty( $overview['recent_orders'] ) ) {
				$data_context .= "**Recent Orders:**\n";
				foreach ( $overview['recent_orders'] as $order ) {
					$data_context .= sprintf(
						"- [Order #%s](%s): %s - %s (%s)\n",
						$order['number'],
						$order['edit_url'] ?? '#',
						$order['status'],
						$order['total'],
						$order['customer']
					);
				}
				$data_context .= "\n";
			}

			$data_context .= '**Active Coupons:** ' . ( $overview['active_coupons'] ?? 0 ) . "\n";
			$data_context .= '**Currency:** ' . ( $overview['currency'] ?? '$' ) . "\n";

			// Payment gateways.
			if ( isset( $overview['payment_gateways'] ) && ! empty( $overview['payment_gateways']['enabled_list'] ) ) {
				$gateway_names = array_map(
					function ( $gw ) {
						return $gw['title'];
					},
					$overview['payment_gateways']['enabled_list']
				);
				$data_context .= '**Payment Methods Enabled:** ' . count( $gateway_names ) . ' - ' . implode( ', ', $gateway_names ) . "\n";
				$data_context .= '[Payment Settings](' . ( $overview['payment_gateways']['settings_url'] ?? '#' ) . ")\n";
			}

			// Shipping zones.
			if ( isset( $overview['shipping'] ) ) {
				$data_context .= '**Shipping Zones:** ' . ( $overview['shipping']['zones_count'] ?? 0 ) . ' configured\n';
				$data_context .= '[Shipping Settings](' . ( $overview['shipping']['settings_url'] ?? '#' ) . ")\n";
			}

			$data_context .= "\n";

			// Remove overview from store_data to avoid duplication.
			unset( $store_data['store_overview'] );
		}

		// Add any additional specific data (from intent matching).
		if ( ! empty( $store_data ) ) {
			$data_context .= "### 📋 Specific Query Results:\n";
			foreach ( $store_data as $key => $value ) {
				$data_context .= "\n#### " . ucwords( str_replace( '_', ' ', $key ) ) . ":\n";
				$data_context .= "```json\n" . wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n```\n";
			}
		}

		$data_context .= "\n---\n\n";
		$data_context .= "## ABSOLUTE RULES - MUST FOLLOW:\n\n";
		$data_context .= "1. **USE ONLY DATA ABOVE** - Never invent data. If not shown, say \"I don't see that in my current data.\"\n";
		$data_context .= "2. **NEVER SAY \"LOG IN\"** - Admin is ALREADY logged in. Just give the link.\n";
		$data_context .= "3. **NO NUMBERED STEPS** - Don't write \"Step 1, Step 2...\" Just give link + one sentence.\n";
		$data_context .= "4. **BE BRIEF** - Maximum 2-3 sentences for action requests.\n\n";
		$data_context .= "## Quick Links for Actions:\n";
		$data_context .= '- Checkout/Guest Settings: ' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . "\n";
		$data_context .= '- Payment Methods: ' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . "\n";
		$data_context .= '- Shipping Settings: ' . admin_url( 'admin.php?page=wc-settings&tab=shipping' ) . "\n";
		$data_context .= '- General Settings: ' . admin_url( 'admin.php?page=wc-settings' ) . "\n";
		$data_context .= '- Create Coupon: ' . admin_url( 'post-new.php?post_type=shop_coupon' ) . "\n";
		$data_context .= '- All Orders: ' . admin_url( 'edit.php?post_type=shop_order' ) . "\n";
		$data_context .= '- Add Product: ' . admin_url( 'post-new.php?post_type=product' ) . "\n";

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

## CRITICAL CONTEXT - READ FIRST:
- %1$s is ALREADY LOGGED IN to WordPress Admin Dashboard
- They are chatting with you FROM INSIDE the admin panel
- NEVER say "Log in to WordPress" or "Go to your dashboard" - they are already there!
- Just provide direct links and brief instructions

## Your Capabilities:
- **View Data**: Orders, products, customers, analytics, coupons, settings - use the LIVE DATA provided
- **Provide Links**: Give direct admin URLs for any action
- **Generate Content**: Product titles, descriptions, meta descriptions, tags

## Store Info:
- Currency: %3$s (%4$s)
- Platform: WooCommerce

## Response Style - BE CONCISE:
1. Use the LIVE STORE DATA provided - never say "I cannot access" if data is given
2. For actions: Give ONE direct link + brief instruction (1-2 sentences max)
3. NEVER list numbered steps like "Step 1, Step 2..." - just give the link
4. Format: **bold** for emphasis, bullets for lists
5. Keep responses SHORT and actionable

## Example Good Response:
User: "Disable guest checkout"
Good: "Go to [Checkout Settings](link) and uncheck "Allow guest checkout", then save."
Bad: "Step 1: Log in... Step 2: Navigate to... Step 3: Click on..."

## Coupon Display:
- **Code**: `COUPONCODE` (in backticks)
- Show discount, expiry, usage

## Content Generation:
- **Product Title**: SEO-optimized, under 60 characters
- **Description**: HTML with features/benefits
- **Short Description**: 2-3 sentences
- **Meta Description**: 150-160 characters for SEO

## Tone:
Direct, helpful, efficient. No fluff.',
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
