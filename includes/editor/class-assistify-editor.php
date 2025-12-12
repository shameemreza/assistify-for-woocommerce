<?php
/**
 * Editor Integration Class.
 *
 * Handles AI content generation integration with WordPress editors.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Editor;

use Assistify_For_WooCommerce\Assistify_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assistify Editor Class.
 *
 * @since 1.0.0
 */
class Assistify_Editor {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Assistify_Editor|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return Assistify_Editor Instance.
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
		if ( ! is_admin() ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Check if image generation is supported based on selected image model.
	 *
	 * @since 1.0.0
	 * @return string 'yes' if supported, 'no' otherwise.
	 */
	private function is_image_generation_supported() {
		$image_model = get_option( 'assistify_image_model', '' );

		// No image model selected.
		if ( empty( $image_model ) ) {
			return 'no';
		}

		// Check if it's a valid image model.
		$supported_prefixes = array( 'gpt-image', 'dall-e', 'imagen', 'grok-2-image' );

		foreach ( $supported_prefixes as $prefix ) {
			if ( strpos( $image_model, $prefix ) === 0 ) {
				return 'yes';
			}
		}

		return 'no';
	}

	/**
	 * Check if current image provider supports variations.
	 *
	 * Currently only OpenAI supports variations via their API.
	 *
	 * @since 1.0.0
	 * @return bool True if variations are supported.
	 */
	private function supports_image_variations() {
		$image_model = get_option( 'assistify_image_model', '' );

		// OpenAI gpt-image models support variations via edit endpoint.
		if ( strpos( $image_model, 'gpt-image' ) === 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current image provider supports reference image editing.
	 *
	 * @since 1.0.0
	 * @return bool True if reference image editing is supported.
	 */
	private function supports_reference_image() {
		$image_model = get_option( 'assistify_image_model', '' );

		// OpenAI supports image editing with reference.
		if ( strpos( $image_model, 'gpt-image' ) === 0 ) {
			return true;
		}

		// Google Imagen supports image editing.
		if ( strpos( $image_model, 'imagen' ) === 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		// Block Editor assets (posts/pages only).
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		// Sidebar meta box for all post types (posts, pages, products).
		add_action( 'add_meta_boxes', array( $this, 'add_sidebar_meta_box' ) );

		// Enqueue scripts for classic editor.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_assistify_generate_content', array( $this, 'ajax_generate_content' ) );
		add_action( 'wp_ajax_assistify_generate_image', array( $this, 'ajax_generate_image' ) );
		add_action( 'wp_ajax_assistify_set_product_image', array( $this, 'ajax_set_product_image' ) );
		add_action( 'wp_ajax_assistify_add_to_gallery', array( $this, 'ajax_add_to_gallery' ) );
	}

	/**
	 * Enqueue Block Editor assets (posts/pages only).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		$api_key = get_option( 'assistify_api_key', '' );
		if ( empty( $api_key ) ) {
			return;
		}

		// Get current post type.
		$post_type = get_post_type();
		if ( ! $post_type ) {
			global $typenow;
			$post_type = $typenow;
		}

		// Only for posts and pages (not products - WooCommerce uses classic editor).
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		// Register and enqueue the sidebar script.
		wp_enqueue_script(
			'assistify-block-editor-sidebar',
			ASSISTIFY_PLUGIN_URL . 'assets/js/editor/sidebar-panel.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-i18n',
				'wp-block-editor',
				'wp-blocks',
				'wp-compose',
			),
			ASSISTIFY_VERSION,
			true
		);

		wp_enqueue_style(
			'assistify-editor',
			ASSISTIFY_PLUGIN_URL . 'assets/css/editor.css',
			array(),
			ASSISTIFY_VERSION
		);

		$post_type_label = 'page' === $post_type ? __( 'page', 'assistify-for-woocommerce' ) : __( 'post', 'assistify-for-woocommerce' );

		wp_localize_script(
			'assistify-block-editor-sidebar',
			'assistifyEditor',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'assistify_editor_nonce' ),
				'postType'      => $post_type,
				'postTypeLabel' => $post_type_label,
				'settings'      => array(
					'defaultTone'   => get_option( 'assistify_content_default_tone', 'professional' ),
					'defaultLength' => get_option( 'assistify_content_default_length', 600 ),
				),
				'strings'       => array(
					'panelTitle'         => esc_html__( 'Assistify AI', 'assistify-for-woocommerce' ),
					/* translators: %s: post type label */
					'generateContentFor' => sprintf( esc_html__( 'Generate AI content for this %s.', 'assistify-for-woocommerce' ), $post_type_label ),
					'generateTitle'      => esc_html__( 'Generate Title', 'assistify-for-woocommerce' ),
					'generateContent'    => esc_html__( 'Generate Content', 'assistify-for-woocommerce' ),
					'generateExcerpt'    => esc_html__( 'Generate Excerpt', 'assistify-for-woocommerce' ),
					'generateSeoMeta'    => esc_html__( 'Generate SEO Meta', 'assistify-for-woocommerce' ),
					'generateImage'      => esc_html__( 'Generate Image', 'assistify-for-woocommerce' ),
					'instructions'       => esc_html__( 'Instructions (optional)', 'assistify-for-woocommerce' ),
					'instructionsPlc'    => esc_html__( 'Describe what you want...', 'assistify-for-woocommerce' ),
					'generate'           => esc_html__( 'Generate', 'assistify-for-woocommerce' ),
					'generating'         => esc_html__( 'Generating...', 'assistify-for-woocommerce' ),
					'apply'              => esc_html__( 'Apply', 'assistify-for-woocommerce' ),
					'regenerate'         => esc_html__( 'Regenerate', 'assistify-for-woocommerce' ),
					'copy'               => esc_html__( 'Copy', 'assistify-for-woocommerce' ),
					'copied'             => esc_html__( 'Copied!', 'assistify-for-woocommerce' ),
					'cancel'             => esc_html__( 'Cancel', 'assistify-for-woocommerce' ),
					'error'              => esc_html__( 'Error generating content. Please try again.', 'assistify-for-woocommerce' ),
					'noContentWarning'   => esc_html__( 'No existing content. Please provide instructions.', 'assistify-for-woocommerce' ),
					'configureIn'        => esc_html__( 'Configure in Settings', 'assistify-for-woocommerce' ),
					'settingsUrl'        => admin_url( 'admin.php?page=wc-settings&tab=assistify' ),
					'fromContent'        => esc_html__( 'Generate from content', 'assistify-for-woocommerce' ),
					'customPrompt'       => esc_html__( 'Write custom prompt', 'assistify-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Enqueue scripts for editor (all post types).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_editor_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'post', 'page', 'product' ), true ) ) {
			return;
		}

		$api_key = get_option( 'assistify_api_key', '' );
		if ( empty( $api_key ) ) {
			return;
		}

		// For posts/pages with Block Editor, we use React sidebar - don't load classic JS.
		$use_classic = true;
		if ( in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $screen->post_type ) ) {
				$use_classic = false;
			}
		}

		// Enqueue styles.
		wp_enqueue_style(
			'assistify-editor',
			ASSISTIFY_PLUGIN_URL . 'assets/css/editor.css',
			array(),
			ASSISTIFY_VERSION
		);

		// Enqueue classic editor script only when needed.
		if ( $use_classic ) {
			global $post;

			$default_tone   = get_option( 'assistify_content_default_tone', 'professional' );
			$default_length = get_option( 'assistify_content_default_length', 600 );
			$is_product     = 'product' === $screen->post_type;

			wp_enqueue_script(
				'assistify-classic-editor',
				ASSISTIFY_PLUGIN_URL . 'assets/js/editor/classic-editor.js',
				array( 'jquery' ),
				ASSISTIFY_VERSION,
				true
			);

			// Pass data to JavaScript.
			wp_localize_script(
				'assistify-classic-editor',
				'assistifyEditor',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'assistify_editor_nonce' ),
					'postId'         => $post ? $post->ID : 0,
					'postType'       => $screen->post_type,
					'tone'           => $default_tone,
					'length'         => $default_length,
					'typeLabels'     => array(
						'title'             => __( 'Title', 'assistify-for-woocommerce' ),
						'description'       => $is_product ? __( 'Description', 'assistify-for-woocommerce' ) : __( 'Content', 'assistify-for-woocommerce' ),
						'short_description' => $is_product ? __( 'Short Description', 'assistify-for-woocommerce' ) : __( 'Excerpt', 'assistify-for-woocommerce' ),
						'tags'              => __( 'Tags', 'assistify-for-woocommerce' ),
						'meta_description'  => __( 'SEO Meta', 'assistify-for-woocommerce' ),
					),
					'strings'        => array(
						'instructionsOptional'  => __( 'Instructions (optional)', 'assistify-for-woocommerce' ),
						'error'                 => __( 'Error generating content.', 'assistify-for-woocommerce' ),
						'option'                => __( 'Option', 'assistify-for-woocommerce' ),
						'copy'                  => __( 'Copy', 'assistify-for-woocommerce' ),
						'copied'                => __( 'Copied!', 'assistify-for-woocommerce' ),
						'use'                   => __( 'Use', 'assistify-for-woocommerce' ),
						'generateWithAssistify' => __( 'Generate with AI', 'assistify-for-woocommerce' ),
						'generateFeatured'      => __( 'Generate Featured Image', 'assistify-for-woocommerce' ),
						'generateGallery'       => __( 'Generate Gallery Images', 'assistify-for-woocommerce' ),
						'generating'            => __( 'Generating...', 'assistify-for-woocommerce' ),
						'imageError'            => __( 'Error generating image.', 'assistify-for-woocommerce' ),
					),
					'thumbnailNonce' => wp_create_nonce( 'set_post_thumbnail-' . ( $post ? $post->ID : 0 ) ),
					'imageSettings'  => array(
						'enabled'                => $this->is_image_generation_supported(),
						'provider'               => get_option( 'assistify_ai_provider', 'openai' ),
						'model'                  => get_option( 'assistify_image_model', 'gpt-image-1' ),
						'size'                   => get_option( 'assistify_image_size', '1024x1024' ),
						'quality'                => get_option( 'assistify_image_quality', 'auto' ),
						'style'                  => get_option( 'assistify_image_style', 'natural' ),
						'removeBgEnabled'        => ! empty( get_option( 'assistify_removebg_api_key', '' ) ),
						'removeBgSize'           => get_option( 'assistify_removebg_size', 'auto' ),
						'supportsVariations'     => $this->supports_image_variations(),
						'supportsReferenceImage' => $this->supports_reference_image(),
					),
				)
			);
		}
	}

	/**
	 * Add sidebar meta box for all post types.
	 *
	 * Shows in Screen Options and can be toggled on/off.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_sidebar_meta_box() {
		$api_key = get_option( 'assistify_api_key', '' );
		if ( empty( $api_key ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// For posts/pages with Block Editor enabled, we use the React sidebar instead.
		if ( in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $screen->post_type ) ) {
				return;
			}
		}

		// Add sidebar meta box for posts, pages, and products.
		$post_types = array( 'post', 'page', 'product' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'assistify-ai-sidebar',
				__( 'Assistify AI', 'assistify-for-woocommerce' ),
				array( $this, 'render_sidebar_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render sidebar meta box.
	 *
	 * Works for posts, pages, and products.
	 * Always shows instructions option for better UX.
	 * Note: JavaScript is in external file (classic-editor.js).
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_sidebar_meta_box( $post ) {
		wp_nonce_field( 'assistify_editor_nonce', 'assistify_editor_nonce_field' );

		$is_product = 'product' === $post->post_type;

		// Determine labels based on post type.
		if ( $is_product ) {
			$type_label    = __( 'product', 'assistify-for-woocommerce' );
			$content_label = __( 'Generate Description', 'assistify-for-woocommerce' );
			$excerpt_label = __( 'Generate Short Description', 'assistify-for-woocommerce' );
			$show_tags     = true;
		} elseif ( 'page' === $post->post_type ) {
			$type_label    = __( 'page', 'assistify-for-woocommerce' );
			$content_label = __( 'Generate Content', 'assistify-for-woocommerce' );
			$excerpt_label = __( 'Generate Excerpt', 'assistify-for-woocommerce' );
			$show_tags     = false;
		} else {
			$type_label    = __( 'post', 'assistify-for-woocommerce' );
			$content_label = __( 'Generate Content', 'assistify-for-woocommerce' );
			$excerpt_label = __( 'Generate Excerpt', 'assistify-for-woocommerce' );
			$show_tags     = false;
		}
		?>
		<div class="assistify-sidebar-panel">
			<p class="description">
				<?php
				/* translators: %s: post type label */
				printf( esc_html__( 'Generate AI content for this %s.', 'assistify-for-woocommerce' ), esc_html( $type_label ) );
				?>
			</p>

			<p><button type="button" class="button assistify-generate-btn" data-type="title"><?php esc_html_e( 'Generate Title', 'assistify-for-woocommerce' ); ?></button></p>
			<p><button type="button" class="button assistify-generate-btn" data-type="description"><?php echo esc_html( $content_label ); ?></button></p>
			<p><button type="button" class="button assistify-generate-btn" data-type="short_description"><?php echo esc_html( $excerpt_label ); ?></button></p>
			
			<?php if ( $show_tags ) : ?>
			<p><button type="button" class="button assistify-generate-btn" data-type="tags"><?php esc_html_e( 'Generate Tags', 'assistify-for-woocommerce' ); ?></button></p>
			<?php endif; ?>
			
			<p><button type="button" class="button assistify-generate-btn" data-type="meta_description"><?php esc_html_e( 'Generate SEO Meta', 'assistify-for-woocommerce' ); ?></button></p>

			<!-- Instructions input - Always shown when generating -->
			<div id="assistify-instructions">
				<p><strong id="assistify-instructions-label"><?php esc_html_e( 'Instructions (optional)', 'assistify-for-woocommerce' ); ?></strong></p>
				<p class="description"><?php esc_html_e( 'Add context or specific requirements. Leave empty to generate based on existing content.', 'assistify-for-woocommerce' ); ?></p>
				<textarea id="assistify-prompt" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'e.g., Focus on luxury appeal, mention eco-friendly materials...', 'assistify-for-woocommerce' ); ?>"></textarea>
				<p>
					<button type="button" class="button button-primary" id="assistify-submit"><?php esc_html_e( 'Generate', 'assistify-for-woocommerce' ); ?></button>
					<button type="button" class="button" id="assistify-cancel"><?php esc_html_e( 'Cancel', 'assistify-for-woocommerce' ); ?></button>
				</p>
			</div>

			<!-- Preview - Full content visible, scrollable -->
			<div id="assistify-preview">
				<p><strong><?php esc_html_e( 'Choose an option', 'assistify-for-woocommerce' ); ?></strong></p>
				<div id="assistify-options"></div>
				<p>
					<button type="button" class="button" id="assistify-regenerate"><?php esc_html_e( 'Regenerate', 'assistify-for-woocommerce' ); ?></button>
					<button type="button" class="button" id="assistify-close"><?php esc_html_e( 'Close', 'assistify-for-woocommerce' ); ?></button>
				</p>
			</div>

			<!-- Loading -->
			<div id="assistify-loading">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Generating...', 'assistify-for-woocommerce' ); ?>
			</div>

			<p class="description">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=assistify' ) ); ?>"><?php esc_html_e( 'Settings', 'assistify-for-woocommerce' ); ?></a>
			</p>
		</div>
		<?php
	}



	/**
	 * AJAX handler for content generation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_generate_content() {
		check_ajax_referer( 'assistify_editor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		$type             = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$post_id          = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$tone             = isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : 'professional';
		$length           = isset( $_POST['length'] ) ? sanitize_text_field( wp_unslash( $_POST['length'] ) ) : 'medium';
		$custom_prompt    = isset( $_POST['custom_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_prompt'] ) ) : '';
		$generate_options = isset( $_POST['generate_options'] ) ? absint( $_POST['generate_options'] ) : 0;

		if ( empty( $type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'assistify-for-woocommerce' ) ) );
		}

		// Get post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'assistify-for-woocommerce' ) ) );
		}

		$is_product = 'product' === $post->post_type;

		// Check if we need to generate multiple options.
		$num_options = $generate_options ? 3 : 1;

		$result = $this->generate_content( $type, $post, $tone, $length, $custom_prompt, $num_options );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Generate content based on type.
	 *
	 * @param string   $type          Content type.
	 * @param \WP_Post $post          Post object.
	 * @param string   $tone          Writing tone.
	 * @param string   $length        Content length.
	 * @param string   $custom_prompt Custom instructions.
	 * @param int      $num_options   Number of options to generate.
	 * @return array|\WP_Error
	 */
	private function generate_content( $type, $post, $tone, $length, $custom_prompt, $num_options = 1 ) {
		$is_product = 'product' === $post->post_type;

		// Build context.
		$context = $this->build_context( $post );

		// Build prompt based on type.
		$prompt = $this->build_prompt( $type, $post, $context, $custom_prompt, $tone, $length, $num_options );

		// Generate with AI.
		$result = $this->call_ai( $prompt, $tone );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Parse options if multiple were requested.
		$options = array();
		if ( $num_options > 1 ) {
			// Try to parse numbered options.
			$lines = preg_split( '/\d+\.\s+/', $result );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( ! empty( $line ) ) {
					$options[] = $line;
				}
			}
			// If parsing failed, just use the whole result.
			if ( count( $options ) < 2 ) {
				$options = array( $result );
			}
		} else {
			$options = array( $result );
		}

		return array(
			'success'   => true,
			'type'      => $type,
			'generated' => $options[0],
			'options'   => $options,
		);
	}

	/**
	 * Build context from post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Context string.
	 */
	private function build_context( $post ) {
		$context = '';

		if ( ! empty( $post->post_title ) ) {
			$context .= 'Title: ' . $post->post_title . "\n";
		}

		if ( ! empty( $post->post_content ) ) {
			$context .= 'Content: ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 200 ) . "\n";
		}

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				if ( $product->get_price() ) {
					$context .= 'Price: ' . wc_price( $product->get_price() ) . "\n";
				}
				if ( $product->get_sku() ) {
					$context .= 'SKU: ' . $product->get_sku() . "\n";
				}
				$categories = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
				if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
					$context .= 'Categories: ' . implode( ', ', $categories ) . "\n";
				}
				if ( $product->get_short_description() ) {
					$context .= 'Short Description: ' . wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 50 ) . "\n";
				}
			}
		}

		return $context;
	}

	/**
	 * Build prompt for AI.
	 *
	 * Generates human-like, SEO-friendly content without robotic language.
	 *
	 * @param string   $type          Content type.
	 * @param \WP_Post $post          Post object.
	 * @param string   $context       Context string.
	 * @param string   $custom_prompt Custom instructions.
	 * @param string   $tone          Writing tone.
	 * @param string   $length        Content length.
	 * @param int      $num_options   Number of options.
	 * @return string Prompt.
	 */
	private function build_prompt( $type, $post, $context, $custom_prompt, $tone, $length, $num_options ) {
		$is_product   = 'product' === $post->post_type;
		$type_name    = $is_product ? 'product' : $post->post_type;
		$options_text = $num_options > 1 ? "Generate exactly {$num_options} different options, clearly numbered as 1., 2., 3. on separate lines." : '';

		// Common quality guidelines.
		$quality_rules = 'IMPORTANT RULES:
- Write naturally like a human, not robotic
- Do NOT use emojis
- Do NOT use em dashes (—)
- Do NOT start with generic phrases like "Introducing", "Welcome to", "Discover"
- Use simple, clear language
- Make it SEO-friendly but readable';

		switch ( $type ) {
			case 'title':
				if ( $is_product ) {
					// Product titles should be descriptive product names.
					$prompt = "Generate a clear, descriptive product name/title. It should be:
- A proper product name (not a marketing tagline)
- Descriptive of what the product is
- Under 60 characters
- Include key product attributes (material, type, use case if relevant)
Example good titles: 'Organic Cotton T-Shirt', 'Wireless Bluetooth Earbuds Pro', 'Handmade Leather Wallet'
Example bad titles: 'Transform Your Style Today!', 'The Ultimate Solution'
{$options_text}";
				} else {
					// Post/page titles should be engaging but not clickbait.
					$prompt = "Generate an engaging {$type_name} title that:
- Is clear and descriptive
- Under 60 characters
- SEO-friendly but natural
- Not clickbait or overly sensational
{$options_text}";
				}
				break;

			case 'description':
				// Use numeric word count from settings (default 600).
				$word_count = is_numeric( $length ) ? absint( $length ) : 600;

				if ( $is_product ) {
					$prompt = "Write a product description (approximately {$word_count} words):
- Start directly with product benefits or features (NO heading)
- Focus on what the product does and why it's valuable
- Use short paragraphs (2-3 sentences each)
- Include key features naturally in the text
- End with a subtle call-to-action if appropriate
{$options_text}";
				} else {
					$prompt = "Write {$type_name} content (approximately {$word_count} words):
- Start directly with the main content (NO heading at the beginning)
- Use short, readable paragraphs
- Include relevant information naturally
- Make it informative and engaging
- Structure with clear flow of ideas
{$options_text}";
				}
				break;

			case 'short_description':
			case 'excerpt':
				$label  = $is_product ? 'short description' : 'excerpt';
				$prompt = "Write a compelling {$label} (2-3 sentences, max 160 characters):
- Summarize the main value/point
- Make it scannable and clear
- Encourage readers to learn more
{$options_text}";
				break;

			case 'tags':
				$prompt = 'Generate 5-8 relevant product tags:
- Use lowercase words
- Single words or short phrases
- Relevant to the product category and features
- Return as comma-separated list only';
				break;

			case 'meta_description':
				$prompt = "Write an SEO meta description (under 160 characters):
- Summarize the main value proposition
- Include a subtle call-to-action
- Make it compelling for search results
{$options_text}";
				break;

			default:
				$prompt = "Generate appropriate content for this {$type_name}.";
		}

		// Add quality rules.
		$prompt .= "\n\n" . $quality_rules;

		// Add custom instructions if provided.
		if ( ! empty( $custom_prompt ) ) {
			$prompt = 'User instructions: ' . $custom_prompt . "\n\n" . $prompt;
		}

		// Add context if available.
		if ( ! empty( $context ) ) {
			$prompt .= "\n\nContent context:\n" . $context;
		}

		return $prompt;
	}

	/**
	 * Call AI provider.
	 *
	 * @param string $prompt Prompt to send.
	 * @param string $tone   Writing tone.
	 * @return string|\WP_Error Generated content or error.
	 */
	private function call_ai( $prompt, $tone ) {
		$api_key  = get_option( 'assistify_api_key', '' );
		$provider = get_option( 'assistify_ai_provider', 'openai' );
		$model    = get_option( 'assistify_ai_model', '' );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'AI API key is not configured. Please configure it in WooCommerce > Settings > Assistify.', 'assistify-for-woocommerce' ) );
		}

		// Check if AI Provider Factory class exists.
		if ( ! class_exists( '\Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory' ) ) {
			return new \WP_Error( 'class_not_found', __( 'AI Provider Factory not found. Please check plugin installation.', 'assistify-for-woocommerce' ) );
		}

		$tone_instructions = array(
			'professional' => 'Use a professional, trustworthy tone.',
			'casual'       => 'Use a friendly, conversational tone.',
			'luxury'       => 'Use an elegant, sophisticated tone.',
			'playful'      => 'Use a fun, energetic tone.',
		);

		$tone_instruction = isset( $tone_instructions[ $tone ] ) ? $tone_instructions[ $tone ] : $tone_instructions['professional'];

		try {
			$ai_provider = \Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory::create( $provider, $api_key );

			if ( ! $ai_provider ) {
				return new \WP_Error( 'provider_error', __( 'Failed to create AI provider. Please check your settings.', 'assistify-for-woocommerce' ) );
			}

			$system_prompt = 'You are an expert content writer specializing in e-commerce products and blog content. ' . $tone_instruction . '

Writing style requirements:
- Write naturally like a human copywriter, never robotic
- Use simple, clear sentences
- NO emojis whatsoever
- NO em dashes (—), use commas or periods instead
- NO overused marketing phrases like "game-changer", "revolutionary", "cutting-edge"
- NO starting paragraphs with "In today\'s world" or similar clichés
- Focus on benefits and value, not hype
- Be concise and scannable

Return ONLY the requested content. No explanations, no additional commentary.';

			$messages = array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			);

			$options = array(
				'temperature' => 0.7,
				'max_tokens'  => 1500,
			);

			if ( ! empty( $model ) ) {
				$options['model'] = $model;
			}

			$response = $ai_provider->chat( $messages, $options );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// Extract content from response array.
			$content = '';
			if ( is_array( $response ) && isset( $response['content'] ) ) {
				$content = $response['content'];
			} elseif ( is_string( $response ) ) {
				$content = $response;
			}

			if ( empty( $content ) ) {
				return new \WP_Error( 'empty_response', __( 'AI returned an empty response. Please try again.', 'assistify-for-woocommerce' ) );
			}

			return trim( $content );

		} catch ( \Exception $e ) {
			return new \WP_Error( 'ai_error', $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for image generation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_generate_image() {
		check_ajax_referer( 'assistify_editor_nonce', 'nonce' );

		// Increase PHP execution time for image generation (gpt-image-1 can take 1-2 minutes).
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged
		@set_time_limit( 300 );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		// Check if image generation is supported.
		if ( 'yes' !== $this->is_image_generation_supported() ) {
			$provider = get_option( 'assistify_ai_provider', 'openai' );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: provider name */
						__( 'Your selected AI provider (%s) does not support image generation. Please select OpenAI, Google, or xAI, and choose an image model in settings.', 'assistify-for-woocommerce' ),
						ucfirst( $provider )
					),
				)
			);
		}

		$action    = isset( $_POST['image_action'] ) ? sanitize_text_field( wp_unslash( $_POST['image_action'] ) ) : 'generate';
		$prompt    = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';

		// Get options from settings or request.
		$options = array(
			'size'           => isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : get_option( 'assistify_image_size', '1024x1024' ),
			'quality'        => isset( $_POST['quality'] ) ? sanitize_text_field( wp_unslash( $_POST['quality'] ) ) : get_option( 'assistify_image_quality', 'auto' ),
			'style'          => isset( $_POST['style'] ) ? sanitize_text_field( wp_unslash( $_POST['style'] ) ) : get_option( 'assistify_image_style', 'natural' ),
			'model'          => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : get_option( 'assistify_image_model', '' ),
			'n'              => isset( $_POST['count'] ) ? min( absint( $_POST['count'] ), 4 ) : 1,
			'post_id'        => $post_id,
			'set_featured'   => isset( $_POST['set_featured'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['set_featured'] ) ),
			'add_to_gallery' => isset( $_POST['add_to_gallery'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['add_to_gallery'] ) ),
		);

		// Generate image based on action.
		switch ( $action ) {
			case 'generate':
			case 'text_to_image':
				$result = $this->generate_image_from_prompt( $prompt, $options );
				break;

			case 'featured_image':
				$result = $this->generate_featured_image( $post_id, $prompt, $options );
				break;

			case 'product_image':
				$result = $this->generate_product_image( $post_id, $prompt, $options );
				break;

			case 'gallery':
				$options['n'] = isset( $_POST['count'] ) ? min( absint( $_POST['count'] ), 4 ) : 4;
				$result       = $this->generate_gallery_images( $post_id, $prompt, $options );
				break;

			case 'variations':
				$count  = isset( $_POST['count'] ) ? min( absint( $_POST['count'] ), 4 ) : 3;
				$result = $this->generate_image_variations( $image_url, $count, $options );
				break;

			case 'edit':
			case 'enhance':
				$result = $this->edit_image( $image_url, $prompt, $options );
				break;

			case 'remove_background':
				$bg_options = array(
					'size'       => isset( $_POST['bg_size'] ) ? sanitize_text_field( wp_unslash( $_POST['bg_size'] ) ) : get_option( 'assistify_removebg_size', 'auto' ),
					'type'       => isset( $_POST['bg_type'] ) ? sanitize_text_field( wp_unslash( $_POST['bg_type'] ) ) : 'auto',
					'bg_color'   => isset( $_POST['bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bg_color'] ) ) : '',
					'add_shadow' => isset( $_POST['add_shadow'] ) && 'true' === $_POST['add_shadow'],
					'crop'       => isset( $_POST['crop'] ) && 'true' === $_POST['crop'],
					'post_id'    => $post_id,
				);
				$result     = $this->remove_image_background( $image_url, $bg_options );
				break;

			default:
				$result = new \WP_Error( 'invalid_action', __( 'Invalid image action.', 'assistify-for-woocommerce' ) );
		}

		if ( is_wp_error( $result ) ) {
			// Log error for debugging.
			Assistify_Logger::error(
				'Image generation failed: ' . $result->get_error_message(),
				'image-generation',
				$result->get_error_data() ? $result->get_error_data() : array()
			);

			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for setting product image.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_set_product_image() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		$post_id       = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $post_id || ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		// Check if post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		// Set the thumbnail using WordPress function.
		$result = set_post_thumbnail( $post_id, $attachment_id );

		// For WooCommerce products, also update the meta directly to ensure it works.
		if ( 'product' === $post->post_type ) {
			update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
		}

		// Get thumbnail URL for response.
		$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		// Send success response (even if set_post_thumbnail returns false, meta is set).
		wp_send_json_success(
			array(
				'message'       => __( 'Product image set successfully.', 'assistify-for-woocommerce' ),
				'attachment_id' => $attachment_id,
				'thumbnail_url' => $thumbnail_url ? $thumbnail_url : '',
			)
		);
	}

	/**
	 * AJAX handler for adding image to product gallery.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_add_to_gallery() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'assistify_editor_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		$post_id       = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $post_id || ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		// Check if this is a product.
		$post = get_post( $post_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'This action is only available for products.', 'assistify-for-woocommerce' ) ) );
			return;
		}

		// Get existing gallery using WooCommerce or post meta.
		$product     = wc_get_product( $post_id );
		$gallery_ids = array();

		if ( $product ) {
			$gallery_ids = $product->get_gallery_image_ids();
		} else {
			// Fallback to meta.
			$gallery_meta = get_post_meta( $post_id, '_product_image_gallery', true );
			if ( ! empty( $gallery_meta ) ) {
				$gallery_ids = array_map( 'absint', explode( ',', $gallery_meta ) );
			}
		}

		// Avoid duplicates.
		if ( in_array( $attachment_id, $gallery_ids, true ) ) {
			wp_send_json_success(
				array(
					'message'       => __( 'Image already in gallery.', 'assistify-for-woocommerce' ),
					'attachment_id' => $attachment_id,
					'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					'gallery'       => $gallery_ids,
				)
			);
			return;
		}

		// Add to gallery.
		$gallery_ids[] = $attachment_id;

		if ( $product ) {
			$product->set_gallery_image_ids( $gallery_ids );
			$product->save();
		} else {
			// Fallback to direct meta update.
			update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
		}

		wp_send_json_success(
			array(
				'message'       => __( 'Image added to gallery.', 'assistify-for-woocommerce' ),
				'attachment_id' => $attachment_id,
				'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
				'gallery'       => $gallery_ids,
			)
		);
	}

	/**
	 * Generate image from text prompt.
	 *
	 * @since 1.0.0
	 * @param string $prompt  Text prompt.
	 * @param array  $options Generation options.
	 * @return array|\WP_Error Result array or WP_Error.
	 */
	private function generate_image_from_prompt( $prompt, $options = array() ) {
		if ( empty( $prompt ) ) {
			return new \WP_Error( 'empty_prompt', __( 'Please provide a description for the image.', 'assistify-for-woocommerce' ) );
		}

		// Get image provider.
		$provider = \Assistify_For_WooCommerce\Image_Providers\Image_Provider_Factory::get_configured_provider();

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		if ( ! $provider->is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Image provider is not configured. Please add your API key in settings.', 'assistify-for-woocommerce' )
			);
		}

		// Get number of images to generate.
		$num_images = isset( $options['n'] ) ? min( absint( $options['n'] ), 4 ) : 1;
		$model      = \Assistify_For_WooCommerce\Image_Providers\Image_Provider_Factory::get_configured_model();

		// Ensure model is set in options.
		if ( empty( $options['model'] ) ) {
			$options['model'] = $model;
		}

		// For gpt-image-1 models, we need to make separate requests for each image.
		$is_gpt_image = strpos( $model, 'gpt-image' ) === 0;
		$all_images   = array();

		if ( $is_gpt_image && $num_images > 1 ) {
			// Make multiple requests for gpt-image-1.
			$single_options      = $options;
			$single_options['n'] = 1;

			for ( $i = 0; $i < $num_images; $i++ ) {
				$result = $provider->generate( $prompt, $single_options );

				if ( is_wp_error( $result ) ) {
					// If at least one image succeeded, continue with what we have.
					if ( ! empty( $all_images ) ) {
						break;
					}
					return $result;
				}

				if ( ! empty( $result['images'] ) ) {
					$all_images = array_merge( $all_images, $result['images'] );
				}
			}
		} else {
			// Single request for other providers or single image.
			$result = $provider->generate( $prompt, $options );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$all_images = $result['images'];
		}

		// Build a better title for the image based on post title.
		$image_title = 'ai-generated';
		if ( ! empty( $options['post_id'] ) ) {
			$post = get_post( $options['post_id'] );
			if ( $post && ! empty( $post->post_title ) ) {
				$image_title = $post->post_title;
			}
		}

		// Fallback to a shortened version of the prompt.
		if ( 'ai-generated' === $image_title && '' !== $prompt ) {
			// Take first 50 chars of prompt.
			$image_title = substr( wp_strip_all_tags( $prompt ), 0, 50 );
		}

		// Upload images to media library.
		$uploaded_images = array();
		foreach ( $all_images as $index => $image ) {
			$uploaded = $this->upload_image_to_media_library( $image, $image_title, $index );
			if ( ! is_wp_error( $uploaded ) ) {
				$uploaded_images[] = $uploaded;
			} else {
				Assistify_Logger::error( 'Upload failed for image ' . $index . ': ' . $uploaded->get_error_message(), 'image-upload' );
			}
		}

		if ( empty( $uploaded_images ) ) {
			return new \WP_Error( 'upload_failed', __( 'Failed to upload generated images.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success' => true,
			'images'  => $uploaded_images,
			'model'   => $model,
		);
	}

	/**
	 * Generate featured image for a post/product.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Post ID.
	 * @param string $prompt  Optional custom prompt.
	 * @param array  $options Generation options.
	 * @return array|\WP_Error Result array or WP_Error.
	 */
	private function generate_featured_image( $post_id, $prompt = '', $options = array() ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'invalid_post', __( 'Post not found.', 'assistify-for-woocommerce' ) );
		}

		// Build prompt from post content if not provided.
		if ( empty( $prompt ) ) {
			$prompt = $this->build_image_prompt_from_post( $post );
		}

		// Generate single image.
		$options['n'] = 1;
		$result       = $this->generate_image_from_prompt( $prompt, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Set as featured image if requested.
		$set_as_featured = ! empty( $options['set_featured'] );
		if ( $set_as_featured && ! empty( $result['images'] ) ) {
			$attachment_id = $result['images'][0]['id'];
			set_post_thumbnail( $post_id, $attachment_id );
			$result['set_as_featured'] = true;
		}

		return $result;
	}

	/**
	 * Generate product image.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Product ID.
	 * @param string $prompt  Optional custom prompt.
	 * @param array  $options Generation options.
	 * @return array|\WP_Error Result array or WP_Error.
	 */
	private function generate_product_image( $post_id, $prompt = '', $options = array() ) {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return new \WP_Error( 'invalid_product', __( 'Product not found.', 'assistify-for-woocommerce' ) );
		}

		// Build prompt from product if not provided.
		if ( empty( $prompt ) ) {
			$prompt = $this->build_product_image_prompt( $product );
		}

		return $this->generate_featured_image( $post_id, $prompt, $options );
	}

	/**
	 * Generate gallery images.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Post ID.
	 * @param string $prompt  Base prompt.
	 * @param array  $options Generation options.
	 * @return array|\WP_Error Result array or WP_Error.
	 */
	private function generate_gallery_images( $post_id, $prompt = '', $options = array() ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'invalid_post', __( 'Post not found.', 'assistify-for-woocommerce' ) );
		}

		// Build prompt if not provided.
		if ( empty( $prompt ) ) {
			$prompt = $this->build_image_prompt_from_post( $post );
		}

		// Generate multiple images.
		$result = $this->generate_image_from_prompt( $prompt, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Add to product gallery if it's a product.
		$add_to_gallery = ! empty( $options['add_to_gallery'] );

		if ( $add_to_gallery && 'product' === $post->post_type && ! empty( $result['images'] ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$gallery_ids = $product->get_gallery_image_ids();
				foreach ( $result['images'] as $image ) {
					$gallery_ids[] = $image['id'];
				}
				$product->set_gallery_image_ids( $gallery_ids );
				$product->save();
				$result['added_to_gallery'] = true;
			}
		}

		return $result;
	}

	/**
	 * Generate image variations.
	 *
	 * @since 1.0.0
	 * @param string $image_url Source image URL.
	 * @param int    $count     Number of variations.
	 * @param array  $options   Generation options.
	 * @return array|\WP_Error Result array or WP_Error.
	 */
	private function generate_image_variations( $image_url, $count = 3, $options = array() ) {
		if ( empty( $image_url ) ) {
			return new \WP_Error( 'no_image', __( 'Please provide a source image.', 'assistify-for-woocommerce' ) );
		}

		$provider = \Assistify_For_WooCommerce\Image_Providers\Image_Provider_Factory::get_configured_provider();

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		if ( ! $provider->supports( 'variations' ) ) {
			return new \WP_Error(
				'not_supported',
				sprintf(
					/* translators: %s: Provider name. */
					__( '%s does not support image variations. Try OpenAI.', 'assistify-for-woocommerce' ),
					$provider->get_name()
				)
			);
		}

		// Download image to temp file.
		$temp_file = $this->download_to_temp_file( $image_url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$result = $provider->create_variations( $temp_file, $count, $options );

		// Clean up temp file.
		wp_delete_file( $temp_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Upload variations to media library.
		$uploaded_images = array();
		foreach ( $result['images'] as $index => $image ) {
			$uploaded = $this->upload_image_to_media_library( $image, 'variation', $index );
			if ( ! is_wp_error( $uploaded ) ) {
				$uploaded_images[] = $uploaded;
			}
		}

		return array(
			'success' => true,
			'images'  => $uploaded_images,
			'model'   => $result['model'],
		);
	}

	/**
	 * Edit/enhance an existing image.
	 *
	 * @since 1.0.0
	 * @param string $image_url Source image URL.
	 * @param string $prompt    Edit instructions.
	 * @param array  $options   Generation options.
	 * @return array|\WP_Error Result array or WP_Error.
	 */
	private function edit_image( $image_url, $prompt, $options = array() ) {
		if ( empty( $image_url ) ) {
			return new \WP_Error( 'no_image', __( 'Please provide a source image.', 'assistify-for-woocommerce' ) );
		}

		if ( empty( $prompt ) ) {
			return new \WP_Error( 'empty_prompt', __( 'Please provide edit instructions.', 'assistify-for-woocommerce' ) );
		}

		$provider = \Assistify_For_WooCommerce\Image_Providers\Image_Provider_Factory::get_configured_provider();

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		if ( ! $provider->supports( 'edit' ) ) {
			return new \WP_Error(
				'not_supported',
				sprintf(
					/* translators: %s: Provider name. */
					__( '%s does not support image editing. Try OpenAI or Google Imagen.', 'assistify-for-woocommerce' ),
					$provider->get_name()
				)
			);
		}

		// Download image to temp file.
		$temp_file = $this->download_to_temp_file( $image_url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$result = $provider->edit( $temp_file, $prompt, $options );

		// Clean up temp file.
		wp_delete_file( $temp_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Upload to media library.
		$uploaded_images = array();
		foreach ( $result['images'] as $index => $image ) {
			$uploaded = $this->upload_image_to_media_library( $image, 'edited-' . substr( sanitize_title( $prompt ), 0, 20 ), $index );
			if ( ! is_wp_error( $uploaded ) ) {
				$uploaded_images[] = $uploaded;
			}
		}

		return array(
			'success' => true,
			'images'  => $uploaded_images,
			'model'   => $result['model'],
		);
	}

	/**
	 * Remove background from an image using Remove.bg API.
	 *
	 * @since 1.0.0
	 * @param string $image_url Source image URL.
	 * @param array  $options   Background removal options.
	 * @return array|\WP_Error Result array or WP_Error.
	 */
	private function remove_image_background( $image_url, $options = array() ) {
		if ( empty( $image_url ) ) {
			return new \WP_Error( 'no_image', __( 'Please provide an image to process.', 'assistify-for-woocommerce' ) );
		}

		// Initialize remove.bg provider.
		$provider = new \Assistify_For_WooCommerce\Image_Providers\RemoveBG_Provider();

		if ( ! $provider->is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Background removal is not configured. Please add your Remove.bg API key in settings.', 'assistify-for-woocommerce' )
			);
		}

		// Remove background.
		$result = $provider->remove_background( $image_url, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Build a better filename based on original image or post title.
		$base_name = '';

		// Try to get name from original image.
		$original_attachment_id = attachment_url_to_postid( $image_url );
		if ( $original_attachment_id ) {
			$original_title = get_the_title( $original_attachment_id );
			if ( ! empty( $original_title ) ) {
				$base_name = $original_title;
			}
		}

		// If still empty, try post_id from options.
		if ( empty( $base_name ) && ! empty( $options['post_id'] ) ) {
			$post = get_post( $options['post_id'] );
			if ( $post && ! empty( $post->post_title ) ) {
				$base_name = $post->post_title;
			}
		}

		// Fallback to generic name.
		if ( empty( $base_name ) ) {
			$base_name = 'product-image';
		}

		// Clean and format filename.
		$safe_name = sanitize_file_name( $base_name );
		$safe_name = preg_replace( '/[^a-z0-9\-]/', '-', strtolower( $safe_name ) );
		$safe_name = preg_replace( '/-+/', '-', $safe_name );
		$safe_name = trim( $safe_name, '-' );
		if ( strlen( $safe_name ) > 50 ) {
			$safe_name = substr( $safe_name, 0, 50 );
		}

		$mime_type = isset( $result['mime_type'] ) ? $result['mime_type'] : 'image/png';
		$extension = 'png';
		if ( strpos( $mime_type, 'jpeg' ) !== false || strpos( $mime_type, 'jpg' ) !== false ) {
			$extension = 'jpg';
		} elseif ( strpos( $mime_type, 'webp' ) !== false ) {
			$extension = 'webp';
		}

		$filename = $safe_name . '-no-bg-' . time() . '.' . $extension;
		$upload   = wp_upload_bits( $filename, null, $result['image_data'] );

		if ( ! empty( $upload['error'] ) ) {
			Assistify_Logger::error( 'Background removal upload error: ' . $upload['error'], 'removebg' );
			return new \WP_Error( 'upload_error', $upload['error'] );
		}

		if ( empty( $upload['file'] ) ) {
			Assistify_Logger::error( 'Background removal upload error: No file created', 'removebg' );
			return new \WP_Error( 'upload_error', __( 'Upload failed - no file created.', 'assistify-for-woocommerce' ) );
		}

		Assistify_Logger::log_image_operation( 'background-removal', 'success', array( 'file' => $upload['file'] ) );

		// Create attachment.
		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_text_field( $base_name . ' - No Background' ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Attach to parent post if provided.
		$parent_post_id = ! empty( $options['post_id'] ) ? absint( $options['post_id'] ) : 0;
		$attachment_id  = wp_insert_attachment( $attachment, $upload['file'], $parent_post_id );

		if ( is_wp_error( $attachment_id ) ) {
			Assistify_Logger::error( 'Attachment creation error: ' . $attachment_id->get_error_message(), 'removebg' );
			return $attachment_id;
		}

		// Generate attachment metadata.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Add metadata.
		$alt_text = $base_name . ' - transparent background';
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		update_post_meta( $attachment_id, '_assistify_background_removed', 'yes' );
		if ( $original_attachment_id ) {
			update_post_meta( $attachment_id, '_assistify_original_image_id', $original_attachment_id );
		}

		// Get the actual URLs.
		$attachment_url = wp_get_attachment_url( $attachment_id );
		$thumbnail_url  = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		$full_url       = wp_get_attachment_image_url( $attachment_id, 'full' );

		Assistify_Logger::debug( 'Background removed - Attachment ID: ' . $attachment_id, 'removebg', array( 'url' => $attachment_url ) );

		return array(
			'success'         => true,
			'images'          => array(
				array(
					'id'        => $attachment_id,
					'url'       => $attachment_url,
					'thumbnail' => $thumbnail_url ? $thumbnail_url : $attachment_url,
					'full'      => $full_url ? $full_url : $attachment_url,
				),
			),
			'credits_charged' => isset( $result['credits_charged'] ) ? $result['credits_charged'] : null,
		);
	}

	/**
	 * Build image prompt from post content.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return string Generated prompt.
	 */
	private function build_image_prompt_from_post( $post ) {
		$prompt_parts = array();

		// Add title.
		if ( ! empty( $post->post_title ) ) {
			$prompt_parts[] = 'Subject: ' . $post->post_title;
		}

		// Add excerpt or short content.
		$content = ! empty( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
		if ( ! empty( $content ) ) {
			$clean_content  = wp_trim_words( wp_strip_all_tags( $content ), 50 );
			$prompt_parts[] = 'Context: ' . $clean_content;
		}

		// Add product-specific details.
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$categories = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
				if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
					$prompt_parts[] = 'Category: ' . implode( ', ', array_slice( $categories, 0, 3 ) );
				}
			}
		}

		$prompt = implode( '. ', $prompt_parts );

		// Add style guidance.
		$style = get_option( 'assistify_image_style', 'natural' );
		if ( 'natural' === $style ) {
			$prompt .= '. Professional product photography style, high quality, clean background.';
		} else {
			$prompt .= '. Vivid, eye-catching, artistic product image.';
		}

		return $prompt;
	}

	/**
	 * Build image prompt specifically for products.
	 *
	 * @since 1.0.0
	 * @param \WC_Product $product Product object.
	 * @return string Generated prompt.
	 */
	private function build_product_image_prompt( $product ) {
		$prompt_parts = array(
			'Professional e-commerce product photo of: ' . $product->get_name(),
		);

		// Add category context.
		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$prompt_parts[] = 'Product type: ' . implode( ', ', array_slice( $categories, 0, 2 ) );
		}

		// Add description context.
		$description = $product->get_short_description();
		if ( ! empty( $description ) ) {
			$prompt_parts[] = 'Features: ' . wp_trim_words( wp_strip_all_tags( $description ), 30 );
		}

		// Add styling.
		$prompt_parts[] = 'Style: Professional studio lighting, clean white or gradient background, high resolution, e-commerce ready';

		return implode( '. ', $prompt_parts );
	}

	/**
	 * Upload image to WordPress media library.
	 *
	 * @since 1.0.0
	 * @param array  $image Image data with 'url' or 'data' key.
	 * @param string $title Base title for the image.
	 * @param int    $index Image index for numbering.
	 * @return array|\WP_Error Uploaded image data or WP_Error.
	 */
	private function upload_image_to_media_library( $image, $title = 'ai-generated', $index = 0 ) {
		// Get image data.
		$image_data = null;

		// Log image structure for debugging.
		Assistify_Logger::debug( 'Upload image keys: ' . implode( ', ', array_keys( $image ) ), 'image-upload' );

		if ( isset( $image['data'] ) && ! empty( $image['data'] ) ) {
			$image_data = $image['data'];
			Assistify_Logger::debug( 'Using image data (length: ' . strlen( $image_data ) . ')', 'image-upload' );
		} elseif ( isset( $image['url'] ) && ! empty( $image['url'] ) ) {
			Assistify_Logger::debug( 'Downloading from URL', 'image-upload' );
			$response = wp_remote_get(
				$image['url'],
				array( 'timeout' => 60 )
			);

			if ( is_wp_error( $response ) ) {
				Assistify_Logger::error( 'Download failed: ' . $response->get_error_message(), 'image-upload' );
				return $response;
			}

			$image_data = wp_remote_retrieve_body( $response );
			Assistify_Logger::debug( 'Downloaded (length: ' . strlen( $image_data ) . ')', 'image-upload' );
		}

		if ( empty( $image_data ) ) {
			Assistify_Logger::error( 'No image data available', 'image-upload' );
			return new \WP_Error( 'no_data', __( 'No image data available.', 'assistify-for-woocommerce' ) );
		}

		// Validate image data is actually binary image data (not base64 still).
		// Check if data starts with typical image signatures or is base64.
		$first_char = substr( $image_data, 0, 1 );
		if ( ctype_alnum( $first_char ) && strlen( $image_data ) > 100 ) {
			// Might still be base64 encoded - check for common base64 patterns.
			$test_decode = substr( $image_data, 0, 100 );
			if ( preg_match( '/^[A-Za-z0-9+\/=]+$/', $test_decode ) ) {
				Assistify_Logger::debug( 'Data appears to be base64, attempting decode', 'image-upload' );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				$decoded = base64_decode( $image_data, true );
				if ( false !== $decoded && strlen( $decoded ) > 0 ) {
					$image_data = $decoded;
					Assistify_Logger::debug( 'Decoded base64 (new length: ' . strlen( $image_data ) . ')', 'image-upload' );
				}
			}
		}

		// Detect mime type using first bytes (most reliable).
		$mime_type   = 'image/png';
		$extension   = 'png';
		$first_bytes = substr( $image_data, 0, 8 );

		if ( substr( $first_bytes, 0, 4 ) === "\x89PNG" || substr( $first_bytes, 0, 8 ) === "\x89PNG\r\n\x1a\n" ) {
			$mime_type = 'image/png';
			$extension = 'png';
			Assistify_Logger::debug( 'Detected PNG from signature', 'image-upload' );
		} elseif ( substr( $first_bytes, 0, 2 ) === "\xFF\xD8" ) {
			$mime_type = 'image/jpeg';
			$extension = 'jpg';
			Assistify_Logger::debug( 'Detected JPEG from signature', 'image-upload' );
		} elseif ( substr( $first_bytes, 0, 4 ) === 'RIFF' && substr( $image_data, 8, 4 ) === 'WEBP' ) {
			$mime_type = 'image/webp';
			$extension = 'webp';
			Assistify_Logger::debug( 'Detected WebP from signature', 'image-upload' );
		} elseif ( function_exists( 'finfo_open' ) ) {
			// Try finfo as fallback.
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$detected = finfo_buffer( $finfo, $image_data );
				finfo_close( $finfo );
				if ( $detected && 0 === strpos( $detected, 'image/' ) ) {
					$mime_type = $detected;
					if ( false !== strpos( $detected, 'jpeg' ) ) {
						$extension = 'jpg';
					} elseif ( false !== strpos( $detected, 'webp' ) ) {
						$extension = 'webp';
					}
					Assistify_Logger::debug( 'Detected via finfo: ' . $mime_type, 'image-upload' );
				}
			}
		}

		// Create SEO-friendly filename based on title.
		$safe_title = sanitize_file_name( $title );
		$safe_title = preg_replace( '/[^a-z0-9\-]/', '-', strtolower( $safe_title ) );
		$safe_title = preg_replace( '/-+/', '-', $safe_title );
		$safe_title = trim( $safe_title, '-' );

		// Limit title length and add unique suffix.
		if ( strlen( $safe_title ) > 50 ) {
			$safe_title = substr( $safe_title, 0, 50 );
		}
		if ( empty( $safe_title ) ) {
			$safe_title = 'ai-image';
		}

		$filename = $safe_title . '-' . ( $index + 1 ) . '-' . time() . '.' . $extension;

		Assistify_Logger::debug( 'Uploading: ' . $filename . ' (' . $mime_type . ', ' . strlen( $image_data ) . ' bytes)', 'image-upload' );

		// Upload to WordPress.
		$upload = wp_upload_bits( $filename, null, $image_data );

		if ( ! empty( $upload['error'] ) ) {
			Assistify_Logger::error( 'Upload error: ' . $upload['error'], 'image-upload' );
			return new \WP_Error( 'upload_error', $upload['error'] );
		}

		if ( empty( $upload['file'] ) ) {
			Assistify_Logger::error( 'Upload error: No file path returned', 'image-upload' );
			return new \WP_Error( 'upload_error', __( 'Upload failed - no file created.', 'assistify-for-woocommerce' ) );
		}

		Assistify_Logger::log_image_operation( 'upload', 'success', array( 'file' => basename( $upload['file'] ) ) );

		// Create attachment.
		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_text_field( $title . ' ' . ( $index + 1 ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate attachment metadata.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Add alt text.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );

		// Mark as AI-generated.
		update_post_meta( $attachment_id, '_assistify_ai_generated', 'yes' );
		if ( isset( $image['revised_prompt'] ) ) {
			update_post_meta( $attachment_id, '_assistify_ai_prompt', sanitize_textarea_field( $image['revised_prompt'] ) );
		}

		return array(
			'id'        => $attachment_id,
			'url'       => wp_get_attachment_url( $attachment_id ),
			'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			'full'      => wp_get_attachment_image_url( $attachment_id, 'full' ),
		);
	}

	/**
	 * Download image to temporary file.
	 *
	 * @since 1.0.0
	 * @param string $url Image URL.
	 * @return string|\WP_Error Path to temp file or WP_Error.
	 */
	private function download_to_temp_file( $url ) {
		// If it's a local attachment URL, get the file path directly.
		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				return $file_path;
			}
		}

		// Download from URL.
		$response = wp_remote_get(
			$url,
			array( 'timeout' => 60 )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$image_data = wp_remote_retrieve_body( $response );

		if ( empty( $image_data ) ) {
			return new \WP_Error( 'download_failed', __( 'Failed to download image.', 'assistify-for-woocommerce' ) );
		}

		// Create temp file.
		$temp_file = wp_tempnam( 'assistify_img_' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $temp_file, $image_data );

		if ( false === $result ) {
			return new \WP_Error( 'write_failed', __( 'Failed to save temporary file.', 'assistify-for-woocommerce' ) );
		}

		return $temp_file;
	}
}
