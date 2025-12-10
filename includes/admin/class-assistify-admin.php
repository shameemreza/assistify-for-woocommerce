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
			array( 'jquery' ),
			ASSISTIFY_VERSION,
			true
		);

		wp_localize_script(
			'assistify-admin',
			'assistifyAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'assistify_admin_nonce' ),
				'strings'  => array(
					'error'   => esc_html__( 'An error occurred. Please try again.', 'assistify-for-woocommerce' ),
					'loading' => esc_html__( 'Loading...', 'assistify-for-woocommerce' ),
				),
				'settings' => array(
					'chatEnabled' => get_option( 'assistify_admin_chat_enabled', 'yes' ),
					'position'    => get_option( 'assistify_chat_position', 'bottom-right' ),
				),
			)
		);
	}

	/**
	 * Check if assets should be loaded.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page.
	 * @return bool
	 */
	private function should_load_assets( $hook ) {
		// Always load if admin chat is enabled.
		if ( 'yes' === get_option( 'assistify_admin_chat_enabled', 'yes' ) ) {
			return true;
		}

		// Load on WooCommerce pages.
		$wc_pages = array(
			'woocommerce_page_wc-settings',
			'woocommerce_page_wc-orders',
			'edit.php',
		);

		if ( in_array( $hook, $wc_pages, true ) ) {
			return true;
		}

		// Load on our settings page.
		if ( isset( $_GET['tab'] ) && 'assistify' === sanitize_key( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * Add admin menu items.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		// Add submenu under WooCommerce.
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Assistify', 'assistify-for-woocommerce' ),
			esc_html__( 'Assistify', 'assistify-for-woocommerce' ),
			'manage_woocommerce',
			'assistify',
			array( $this, 'render_dashboard_page' )
		);
	}

	/**
	 * Render the dashboard page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Assistify for WooCommerce', 'assistify-for-woocommerce' ); ?></h1>
			
			<div class="assistify-dashboard">
				<div class="assistify-welcome-panel">
					<h2><?php echo esc_html__( 'Welcome to Assistify', 'assistify-for-woocommerce' ); ?></h2>
					<p><?php echo esc_html__( 'Your AI-powered assistant for WooCommerce is ready.', 'assistify-for-woocommerce' ); ?></p>
					
					<div class="assistify-quick-links">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=assistify' ) ); ?>" class="button button-primary">
							<?php echo esc_html__( 'Configure Settings', 'assistify-for-woocommerce' ); ?>
						</a>
					</div>
				</div>

				<div class="assistify-status-section">
					<h3><?php echo esc_html__( 'Status', 'assistify-for-woocommerce' ); ?></h3>
					<table class="widefat striped">
						<tbody>
							<tr>
								<td><?php echo esc_html__( 'AI Provider', 'assistify-for-woocommerce' ); ?></td>
								<td>
									<?php
									$provider = get_option( 'assistify_ai_provider', 'openai' );
									echo esc_html( ucfirst( $provider ) );
									?>
								</td>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'Admin Chat', 'assistify-for-woocommerce' ); ?></td>
								<td>
									<?php
									$admin_chat = get_option( 'assistify_admin_chat_enabled', 'yes' );
									echo 'yes' === $admin_chat 
										? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' . esc_html__( 'Enabled', 'assistify-for-woocommerce' )
										: '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> ' . esc_html__( 'Disabled', 'assistify-for-woocommerce' );
									?>
								</td>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'Customer Chat', 'assistify-for-woocommerce' ); ?></td>
								<td>
									<?php
									$customer_chat = get_option( 'assistify_customer_chat_enabled', 'no' );
									echo 'yes' === $customer_chat 
										? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' . esc_html__( 'Enabled', 'assistify-for-woocommerce' )
										: '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> ' . esc_html__( 'Disabled', 'assistify-for-woocommerce' );
									?>
								</td>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'API Key', 'assistify-for-woocommerce' ); ?></td>
								<td>
									<?php
									$api_key = get_option( 'assistify_api_key', '' );
									echo ! empty( $api_key ) 
										? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' . esc_html__( 'Configured', 'assistify-for-woocommerce' )
										: '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span> ' . esc_html__( 'Not configured', 'assistify-for-woocommerce' );
									?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
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
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings() );
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
				'class'    => 'wc-enhanced-select',
				'default'  => 'openai',
				'options'  => array(
					'openai'    => esc_html__( 'OpenAI (GPT-5, GPT-5-mini)', 'assistify-for-woocommerce' ),
					'anthropic' => esc_html__( 'Anthropic (Claude 4.5)', 'assistify-for-woocommerce' ),
					'google'    => esc_html__( 'Google (Gemini 3 Pro)', 'assistify-for-woocommerce' ),
					'xai'       => esc_html__( 'xAI (Grok-4)', 'assistify-for-woocommerce' ),
					'deepseek'  => esc_html__( 'DeepSeek (V3.2)', 'assistify-for-woocommerce' ),
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
				'type' => 'sectionend',
				'id'   => 'assistify_customer_chat_settings',
			),

			// Privacy Settings.
			array(
				'title' => esc_html__( 'Privacy Settings', 'assistify-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Configure privacy and data handling options.', 'assistify-for-woocommerce' ),
				'id'    => 'assistify_privacy_settings',
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
}

