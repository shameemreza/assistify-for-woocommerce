<?php
/**
 * Abstract Image Provider Class.
 *
 * Base class for all image generation provider implementations.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Image_Providers;

use Assistify_For_WooCommerce\Assistify_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Image Provider Class.
 *
 * @since 1.0.0
 */
abstract class Image_Provider_Abstract implements Image_Provider_Interface {

	/**
	 * Provider ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $id = '';

	/**
	 * Provider name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = '';

	/**
	 * API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_key = '';

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_base_url = '';

	/**
	 * Current model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $model = '';

	/**
	 * Available models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $available_models = array();

	/**
	 * Supported capabilities.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $capabilities = array(
		'generate'          => true,
		'edit'              => false,
		'variations'        => false,
		'remove_background' => false,
		'upscale'           => false,
	);

	/**
	 * Default options for image generation.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $default_options = array(
		'size'    => '1024x1024',
		'quality' => 'standard',
		'style'   => 'natural',
		'format'  => 'url',
		'timeout' => 120,
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $api_key Optional. API key to use.
	 */
	public function __construct( $api_key = '' ) {
		if ( ! empty( $api_key ) ) {
			$this->api_key = $api_key;
		} else {
			$this->api_key = $this->get_stored_api_key();
		}

		$this->model = $this->get_default_model();
	}

	/**
	 * Get the provider ID.
	 *
	 * @since 1.0.0
	 * @return string Provider unique identifier.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the provider name.
	 *
	 * @since 1.0.0
	 * @return string Provider display name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Check if the provider is configured.
	 *
	 * @since 1.0.0
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Get the stored API key from options.
	 *
	 * Uses the same API key as the text provider for the same service.
	 *
	 * @since 1.0.0
	 * @return string The decrypted API key or empty string.
	 */
	protected function get_stored_api_key() {
		// Use the AI Provider Factory to get the API key (same key for text and image).
		if ( class_exists( '\Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory' ) ) {
			return \Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory::get_api_key_for_provider( $this->id );
		}

		return '';
	}

	/**
	 * Set the model to use.
	 *
	 * @since 1.0.0
	 * @param string $model Model ID.
	 * @return void
	 */
	public function set_model( $model ) {
		$this->model = $model;
	}

	/**
	 * Get the current model.
	 *
	 * @since 1.0.0
	 * @return string Current model ID.
	 */
	public function get_model() {
		return $this->model;
	}

	/**
	 * Get available models.
	 *
	 * @since 1.0.0
	 * @return array Array of available models.
	 */
	public function get_available_models() {
		return $this->available_models;
	}

	/**
	 * Check if a capability is supported.
	 *
	 * @since 1.0.0
	 * @param string $capability Capability to check.
	 * @return bool True if supported.
	 */
	public function supports( $capability ) {
		return isset( $this->capabilities[ $capability ] ) && $this->capabilities[ $capability ];
	}

	/**
	 * Make an API request.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $body     Request body.
	 * @param string $method   HTTP method. Default 'POST'.
	 * @param bool   $is_multipart Whether the request is multipart/form-data.
	 * @return array|\WP_Error Response array or WP_Error on failure.
	 */
	protected function make_request( $endpoint, $body = array(), $method = 'POST', $is_multipart = false ) {
		$url = trailingslashit( $this->api_base_url ) . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => $method,
			'timeout' => $this->default_options['timeout'],
			'headers' => $this->get_request_headers( $is_multipart ),
		);

		if ( ! empty( $body ) && 'POST' === $method ) {
			if ( $is_multipart ) {
				$args['body'] = $body;
			} else {
				$args['body'] = wp_json_encode( $body );
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Assistify_Logger::error( 'API request failed: ' . $response->get_error_message(), 'image-api' );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		// Log response for debugging.
		if ( $response_code >= 200 && $response_code < 300 ) {
			Assistify_Logger::log_api_response( $this->name, $response_code, true );
		} else {
			Assistify_Logger::log_api_response( $this->name, $response_code, false, $response_body );
		}

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = $this->extract_error_message( $decoded_body, $response_code );

			return new \WP_Error(
				'assistify_image_api_error',
				$error_message,
				array(
					'status_code' => $response_code,
					'response'    => $decoded_body,
				)
			);
		}

		return $decoded_body;
	}

	/**
	 * Extract error message from API response.
	 *
	 * @since 1.0.0
	 * @param array $response    Decoded response body.
	 * @param int   $status_code HTTP status code.
	 * @return string Error message.
	 */
	protected function extract_error_message( $response, $status_code ) {
		// Try common error response formats.
		if ( isset( $response['error']['message'] ) ) {
			return $response['error']['message'];
		}

		if ( isset( $response['message'] ) ) {
			return $response['message'];
		}

		if ( isset( $response['error'] ) && is_string( $response['error'] ) ) {
			return $response['error'];
		}

		return sprintf(
			/* translators: %d: HTTP response code. */
			__( 'Image API request failed with status code %d.', 'assistify-for-woocommerce' ),
			$status_code
		);
	}

	/**
	 * Get request headers for API calls.
	 *
	 * Override in provider classes to customize headers.
	 *
	 * @since 1.0.0
	 * @param bool $is_multipart Whether the request is multipart.
	 * @return array Request headers.
	 */
	protected function get_request_headers( $is_multipart = false ) {
		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
		);

		if ( ! $is_multipart ) {
			$headers['Content-Type'] = 'application/json';
		}

		return $headers;
	}

	/**
	 * Merge options with defaults.
	 *
	 * @since 1.0.0
	 * @param array $options Options to merge.
	 * @return array Merged options.
	 */
	protected function merge_options( $options ) {
		return wp_parse_args( $options, $this->default_options );
	}

	/**
	 * Log image generation usage for tracking.
	 *
	 * @since 1.0.0
	 * @param string $operation Operation type.
	 * @param array  $options   Operation options.
	 * @return void
	 */
	protected function log_usage( $operation, $options = array() ) {
		$total_usage = get_option( 'assistify_image_usage', array() );

		$provider_id = $this->get_id();
		$today       = gmdate( 'Y-m-d' );

		if ( ! isset( $total_usage[ $provider_id ] ) ) {
			$total_usage[ $provider_id ] = array();
		}

		if ( ! isset( $total_usage[ $provider_id ][ $today ] ) ) {
			$total_usage[ $provider_id ][ $today ] = array(
				'generations' => 0,
				'edits'       => 0,
				'variations'  => 0,
				'other'       => 0,
			);
		}

		$key = isset( $total_usage[ $provider_id ][ $today ][ $operation ] ) ? $operation : 'other';
		++$total_usage[ $provider_id ][ $today ][ $key ];

		update_option( 'assistify_image_usage', $total_usage );
	}

	/**
	 * Download image from URL and return binary data.
	 *
	 * @since 1.0.0
	 * @param string $url Image URL.
	 * @return string|\WP_Error Binary image data or WP_Error.
	 */
	protected function download_image( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'download_failed',
				__( 'Failed to download generated image.', 'assistify-for-woocommerce' )
			);
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Get file contents for upload.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the image file.
	 * @return string|\WP_Error File contents or WP_Error.
	 */
	protected function get_file_contents( $image_path ) {
		// Handle URL.
		if ( filter_var( $image_path, FILTER_VALIDATE_URL ) ) {
			return $this->download_image( $image_path );
		}

		// Handle local path.
		if ( ! file_exists( $image_path ) ) {
			return new \WP_Error(
				'file_not_found',
				__( 'Image file not found.', 'assistify-for-woocommerce' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $image_path );

		if ( false === $contents ) {
			return new \WP_Error(
				'file_read_error',
				__( 'Could not read image file.', 'assistify-for-woocommerce' )
			);
		}

		return $contents;
	}

	/**
	 * Validate image dimensions for API requirements.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to image.
	 * @param array  $requirements Size requirements.
	 * @return bool|\WP_Error True if valid, WP_Error if not.
	 */
	protected function validate_image( $image_path, $requirements = array() ) {
		$requirements = wp_parse_args(
			$requirements,
			array(
				'max_size'     => 4 * 1024 * 1024, // 4MB default.
				'min_width'    => 64,
				'min_height'   => 64,
				'max_width'    => 4096,
				'max_height'   => 4096,
				'allowed_mime' => array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ),
			)
		);

		// Check if file exists.
		if ( ! file_exists( $image_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Image file not found.', 'assistify-for-woocommerce' ) );
		}

		// Check file size.
		$file_size = filesize( $image_path );
		if ( $file_size > $requirements['max_size'] ) {
			return new \WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: Maximum file size in MB. */
					__( 'Image file is too large. Maximum size is %s MB.', 'assistify-for-woocommerce' ),
					round( $requirements['max_size'] / ( 1024 * 1024 ), 1 )
				)
			);
		}

		// Check MIME type.
		$mime_type = wp_check_filetype( $image_path )['type'];
		if ( ! in_array( $mime_type, $requirements['allowed_mime'], true ) ) {
			return new \WP_Error(
				'invalid_mime_type',
				__( 'Invalid image format. Please use PNG, JPEG, GIF, or WebP.', 'assistify-for-woocommerce' )
			);
		}

		// Check dimensions.
		$image_size = wp_getimagesize( $image_path );
		if ( false === $image_size ) {
			return new \WP_Error( 'invalid_image', __( 'Could not read image dimensions.', 'assistify-for-woocommerce' ) );
		}

		list( $width, $height ) = $image_size;

		if ( $width < $requirements['min_width'] || $height < $requirements['min_height'] ) {
			return new \WP_Error(
				'image_too_small',
				sprintf(
					/* translators: 1: Minimum width, 2: Minimum height. */
					__( 'Image is too small. Minimum dimensions are %1$dx%2$d pixels.', 'assistify-for-woocommerce' ),
					$requirements['min_width'],
					$requirements['min_height']
				)
			);
		}

		if ( $width > $requirements['max_width'] || $height > $requirements['max_height'] ) {
			return new \WP_Error(
				'image_too_large',
				sprintf(
					/* translators: 1: Maximum width, 2: Maximum height. */
					__( 'Image dimensions are too large. Maximum dimensions are %1$dx%2$d pixels.', 'assistify-for-woocommerce' ),
					$requirements['max_width'],
					$requirements['max_height']
				)
			);
		}

		return true;
	}

	/**
	 * Default implementation for edit - returns not supported error.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param string $prompt     Edit instructions.
	 * @param array  $options    Optional. Edit options.
	 * @return \WP_Error Not supported error.
	 */
	public function edit( $image_path, $prompt, array $options = array() ) {
		return new \WP_Error(
			'not_supported',
			sprintf(
				/* translators: %s: Provider name. */
				__( 'Image editing is not supported by %s.', 'assistify-for-woocommerce' ),
				$this->get_name()
			)
		);
	}

	/**
	 * Default implementation for variations - returns not supported error.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param int    $count      Number of variations.
	 * @param array  $options    Optional. Variation options.
	 * @return \WP_Error Not supported error.
	 */
	public function create_variations( $image_path, $count = 1, array $options = array() ) {
		return new \WP_Error(
			'not_supported',
			sprintf(
				/* translators: %s: Provider name. */
				__( 'Image variations are not supported by %s.', 'assistify-for-woocommerce' ),
				$this->get_name()
			)
		);
	}

	/**
	 * Default implementation for background removal - returns not supported error.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param array  $options    Optional. Options.
	 * @return \WP_Error Not supported error.
	 */
	public function remove_background( $image_path, array $options = array() ) {
		return new \WP_Error(
			'not_supported',
			sprintf(
				/* translators: %s: Provider name. */
				__( 'Background removal is not supported by %s.', 'assistify-for-woocommerce' ),
				$this->get_name()
			)
		);
	}

	/**
	 * Default implementation for upscale - returns not supported error.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param int    $scale      Scale factor.
	 * @param array  $options    Optional. Options.
	 * @return \WP_Error Not supported error.
	 */
	public function upscale( $image_path, $scale = 2, array $options = array() ) {
		return new \WP_Error(
			'not_supported',
			sprintf(
				/* translators: %s: Provider name. */
				__( 'Image upscaling is not supported by %s.', 'assistify-for-woocommerce' ),
				$this->get_name()
			)
		);
	}

	/**
	 * Get available sizes for this provider.
	 *
	 * @since 1.0.0
	 * @param string $model Optional. Model ID.
	 * @return array Array of available sizes.
	 */
	public function get_available_sizes( $model = '' ) {
		return array( '1024x1024' );
	}

	/**
	 * Get estimated cost for an operation.
	 *
	 * Override in provider classes for accurate pricing.
	 *
	 * @since 1.0.0
	 * @param string $operation Operation type.
	 * @param array  $options   Operation options.
	 * @return float Estimated cost in USD.
	 */
	public function get_estimated_cost( $operation, array $options = array() ) {
		// Default estimate - providers should override.
		return 0.04;
	}
}
