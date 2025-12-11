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
				'postType'      => $screen->post_type,
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
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'assistify_editor_nonce' ),
					'postId'     => $post ? $post->ID : 0,
					'postType'   => $screen->post_type,
					'tone'       => $default_tone,
					'length'     => $default_length,
					'typeLabels' => array(
						'title'             => __( 'Title', 'assistify-for-woocommerce' ),
						'description'       => $is_product ? __( 'Description', 'assistify-for-woocommerce' ) : __( 'Content', 'assistify-for-woocommerce' ),
						'short_description' => $is_product ? __( 'Short Description', 'assistify-for-woocommerce' ) : __( 'Excerpt', 'assistify-for-woocommerce' ),
						'tags'              => __( 'Tags', 'assistify-for-woocommerce' ),
						'meta_description'  => __( 'SEO Meta', 'assistify-for-woocommerce' ),
					),
					'strings'    => array(
						'instructionsOptional'  => __( 'Instructions (optional)', 'assistify-for-woocommerce' ),
						'error'                 => __( 'Error generating content.', 'assistify-for-woocommerce' ),
						'option'                => __( 'Option', 'assistify-for-woocommerce' ),
						'copy'                  => __( 'Copy', 'assistify-for-woocommerce' ),
						'copied'                => __( 'Copied!', 'assistify-for-woocommerce' ),
						'use'                   => __( 'Use', 'assistify-for-woocommerce' ),
						'generateWithAssistify' => __( 'Generate with Assistify', 'assistify-for-woocommerce' ),
						'imageComingSoon'       => __( 'Image generation coming soon!', 'assistify-for-woocommerce' ),
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
				/* translators: %s: post type (product/post/page) */
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

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'assistify-for-woocommerce' ) ) );
		}

		// Image generation will be implemented in next phase.
		wp_send_json_error(
			array(
				'message' => __( 'Image generation will be available soon!', 'assistify-for-woocommerce' ),
			)
		);
	}
}
