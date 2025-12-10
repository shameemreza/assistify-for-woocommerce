<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class.
 *
 * @since 1.0.0
 */
class Assistify_Frontend {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor.
	}

	/**
	 * Check if the chat widget should be displayed.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function should_display_chat() {
		// Check if customer chat is enabled.
		$customer_chat_enabled = get_option( 'assistify_customer_chat_enabled', 'no' );

		if ( 'yes' !== $customer_chat_enabled ) {
			return false;
		}

		// Check if guest chat is allowed for non-logged-in users.
		if ( ! is_user_logged_in() ) {
			$guest_chat_enabled = get_option( 'assistify_guest_chat_enabled', 'no' );
			if ( 'yes' !== $guest_chat_enabled ) {
				return false;
			}
		}

		// Allow filtering of chat display.
		return apply_filters( 'assistify_display_chat_widget', true );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! $this->should_display_chat() ) {
			return;
		}

		wp_enqueue_style(
			'assistify-frontend',
			ASSISTIFY_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ASSISTIFY_VERSION,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_display_chat() ) {
			return;
		}

		wp_enqueue_script(
			'assistify-frontend',
			ASSISTIFY_PLUGIN_URL . 'assets/js/frontend/frontend.js',
			array( 'jquery' ),
			ASSISTIFY_VERSION,
			true
		);

		wp_localize_script(
			'assistify-frontend',
			'assistifyFrontend',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'assistify_frontend_nonce' ),
				'isLoggedIn' => is_user_logged_in(),
				'strings'    => array(
					'error'        => esc_html__( 'An error occurred. Please try again.', 'assistify-for-woocommerce' ),
					'loading'      => esc_html__( 'Loading...', 'assistify-for-woocommerce' ),
					'placeholder'  => esc_html__( 'Type your message...', 'assistify-for-woocommerce' ),
					'send'         => esc_html__( 'Send', 'assistify-for-woocommerce' ),
					'welcome'      => esc_html__( 'Hi! How can I help you today?', 'assistify-for-woocommerce' ),
					'chatTitle'    => esc_html__( 'Chat with us', 'assistify-for-woocommerce' ),
					'consentTitle' => esc_html__( 'Privacy Notice', 'assistify-for-woocommerce' ),
					'consentText'  => esc_html__( 'This chat is powered by AI. By continuing, you agree that your messages will be processed to provide responses.', 'assistify-for-woocommerce' ),
					'consentAgree' => esc_html__( 'I Agree', 'assistify-for-woocommerce' ),
					'consentDecline' => esc_html__( 'No Thanks', 'assistify-for-woocommerce' ),
				),
				'settings'   => array(
					'position'     => get_option( 'assistify_chat_position', 'bottom-right' ),
					'primaryColor' => get_option( 'assistify_primary_color', '#7F54B3' ),
				),
			)
		);
	}

	/**
	 * Render the chat widget in the footer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_chat_widget() {
		if ( ! $this->should_display_chat() ) {
			return;
		}

		$position = get_option( 'assistify_chat_position', 'bottom-right' );
		?>
		<div id="assistify-chat-widget" class="assistify-chat-widget assistify-position-<?php echo esc_attr( $position ); ?>" aria-label="<?php esc_attr_e( 'Chat widget', 'assistify-for-woocommerce' ); ?>">
			<!-- Chat toggle button -->
			<button 
				type="button" 
				id="assistify-chat-toggle" 
				class="assistify-chat-toggle"
				aria-expanded="false"
				aria-controls="assistify-chat-container"
				aria-label="<?php esc_attr_e( 'Open chat', 'assistify-for-woocommerce' ); ?>"
			>
				<span class="assistify-chat-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
						<path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.36 5.07L2 22l4.93-1.36C8.42 21.5 10.15 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.57 0-3.05-.43-4.32-1.18l-.31-.18-3.22.89.89-3.22-.18-.31C4.43 15.05 4 13.57 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8z"/>
					</svg>
				</span>
				<span class="assistify-close-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
						<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
					</svg>
				</span>
			</button>

			<!-- Chat container -->
			<div id="assistify-chat-container" class="assistify-chat-container" hidden>
				<div class="assistify-chat-header">
					<h3 class="assistify-chat-title"><?php esc_html_e( 'Chat with us', 'assistify-for-woocommerce' ); ?></h3>
					<button 
						type="button" 
						class="assistify-chat-minimize" 
						aria-label="<?php esc_attr_e( 'Minimize chat', 'assistify-for-woocommerce' ); ?>"
					>
						<span aria-hidden="true">−</span>
					</button>
				</div>

				<div class="assistify-chat-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Chat messages', 'assistify-for-woocommerce' ); ?>">
					<!-- Messages will be inserted here via JavaScript -->
				</div>

				<form class="assistify-chat-form" id="assistify-chat-form">
					<label for="assistify-chat-input" class="screen-reader-text">
						<?php esc_html_e( 'Type your message', 'assistify-for-woocommerce' ); ?>
					</label>
					<input 
						type="text" 
						id="assistify-chat-input" 
						class="assistify-chat-input" 
						placeholder="<?php esc_attr_e( 'Type your message...', 'assistify-for-woocommerce' ); ?>"
						autocomplete="off"
					/>
					<button 
						type="submit" 
						class="assistify-chat-send"
						aria-label="<?php esc_attr_e( 'Send message', 'assistify-for-woocommerce' ); ?>"
					>
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
							<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
						</svg>
					</button>
				</form>
			</div>
		</div>
		<?php
	}
}

