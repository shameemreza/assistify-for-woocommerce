<?php
/**
 * Google Image Provider Class.
 *
 * Implements image generation using Google's Imagen 4.0 API.
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
 * Google Image Provider Class.
 *
 * @since 1.0.0
 */
class Image_Provider_Google extends Image_Provider_Abstract {

	/**
	 * Provider ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $id = 'google';

	/**
	 * Provider name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'Google Imagen';

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_base_url = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Available models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $available_models = array(
		// Imagen 4.0 Series (Latest).
		'imagen-4.0-generate-001'       => array(
			'name'        => 'Imagen 4.0',
			'description' => 'Standard quality image generation.',
			'sizes'       => array( '1024x1024', '1024x1536', '1536x1024' ),
			'cost'        => 0.03,
		),
		'imagen-4.0-ultra-generate-001' => array(
			'name'        => 'Imagen 4.0 Ultra',
			'description' => 'Highest quality image generation.',
			'sizes'       => array( '1024x1024', '1024x1536', '1536x1024' ),
			'cost'        => 0.06,
		),
		'imagen-4.0-fast-generate-001'  => array(
			'name'        => 'Imagen 4.0 Fast',
			'description' => 'Fast image generation for quick previews.',
			'sizes'       => array( '1024x1024' ),
			'cost'        => 0.015,
		),
		// Imagen 3.0 Series.
		'imagen-3.0-generate-002'       => array(
			'name'        => 'Imagen 3.0',
			'description' => 'Previous generation, reliable results.',
			'sizes'       => array( '1024x1024', '1024x1536', '1536x1024' ),
			'cost'        => 0.02,
		),
	);

	/**
	 * Supported capabilities.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $capabilities = array(
		'generate'          => true,
		'edit'              => true,
		'variations'        => false,
		'remove_background' => false,
		'upscale'           => true,
	);

	/**
	 * Get the default model.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model() {
		return 'imagen-4.0-generate-001';
	}

	/**
	 * Validate the API key.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_api_key() {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error(
				'assistify_no_api_key',
				__( 'No API key provided.', 'assistify-for-woocommerce' )
			);
		}

		// Test with a simple models request.
		$response = wp_remote_get(
			$this->api_base_url . '/models?key=' . $this->api_key,
			array( 'timeout' => 30 )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid Google API key.', 'assistify-for-woocommerce' )
			);
		}

		return true;
	}

	/**
	 * Generate an image from a text prompt.
	 *
	 * @since 1.0.0
	 * @param string $prompt  Text description of the image to generate.
	 * @param array  $options Optional. Generation options.
	 * @return array|\WP_Error Array with image data, or WP_Error on failure.
	 */
	public function generate( $prompt, array $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Google Imagen is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );
		$model   = isset( $options['model'] ) ? $options['model'] : $this->model;

		// Parse size into aspect ratio.
		$aspect_ratio = $this->size_to_aspect_ratio( isset( $options['size'] ) ? $options['size'] : '1024x1024' );

		// Build request body.
		$body = array(
			'instances'  => array(
				array(
					'prompt' => $this->sanitize_prompt( $prompt ),
				),
			),
			'parameters' => array(
				'sampleCount' => isset( $options['n'] ) ? min( absint( $options['n'] ), 4 ) : 1,
			),
		);

		// Add aspect ratio if not square.
		if ( '1:1' !== $aspect_ratio ) {
			$body['parameters']['aspectRatio'] = $aspect_ratio;
		}

		// Add safety settings.
		$body['parameters']['safetySetting'] = 'block_some';

		// Add person generation setting.
		if ( isset( $options['include_people'] ) && $options['include_people'] ) {
			$body['parameters']['personGeneration'] = 'allow_adult';
		} else {
			$body['parameters']['personGeneration'] = 'dont_allow';
		}

		// Build the endpoint URL.
		$endpoint = "models/{$model}:predict?key=" . $this->api_key;

		// Log request for debugging.
		Assistify_Logger::log_api_request( 'Google Imagen', $endpoint, $body );

		// Set longer timeout for image generation (Imagen can take up to 60 seconds).
		$timeout = 120;

		$response = wp_remote_post(
			$this->api_base_url . '/' . $endpoint,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Assistify_Logger::error( $response->get_error_message(), 'google-image' );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Log response status.
		Assistify_Logger::log_api_response( 'Google Imagen', $response_code, $response_code >= 200 && $response_code < 300 );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_msg = $this->extract_error_message( $response_body, $response_code );
			Assistify_Logger::error( 'API error: ' . $error_msg, 'google-image' );
			return new \WP_Error(
				'api_error',
				$error_msg
			);
		}

		$this->log_usage( 'generations', $options );

		return $this->parse_image_response( $response_body, $options );
	}

	/**
	 * Edit an existing image based on a prompt.
	 *
	 * Uses Imagen's inpainting/outpainting capabilities.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param string $prompt     Edit instructions.
	 * @param array  $options    Optional. Edit options.
	 * @return array|\WP_Error Array with image data, or WP_Error on failure.
	 */
	public function edit( $image_path, $prompt, array $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Google Imagen is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		// Get image data.
		$image_data = $this->get_file_contents( $image_path );
		if ( is_wp_error( $image_data ) ) {
			return $image_data;
		}

		$options = $this->merge_options( $options );
		$model   = isset( $options['model'] ) ? $options['model'] : $this->model;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$base64_image = base64_encode( $image_data );

		// Build request body for image editing.
		$body = array(
			'instances'  => array(
				array(
					'prompt' => $this->sanitize_prompt( $prompt ),
					'image'  => array(
						'bytesBase64Encoded' => $base64_image,
					),
				),
			),
			'parameters' => array(
				'sampleCount'   => 1,
				'safetySetting' => 'block_some',
			),
		);

		// For editing, we might use a different endpoint.
		$edit_model = str_replace( 'generate', 'edit', $model );
		$endpoint   = "models/{$edit_model}:predict?key=" . $this->api_key;

		// Log request for debugging.
		Assistify_Logger::log_api_request( 'Google Imagen', $endpoint, array( 'operation' => 'edit' ) );

		$response = wp_remote_post(
			$this->api_base_url . '/' . $endpoint,
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Assistify_Logger::error( 'Edit failed: ' . $response->get_error_message(), 'google-image' );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		Assistify_Logger::log_api_response( 'Google Imagen', $response_code, $response_code >= 200 && $response_code < 300 );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_msg = $this->extract_error_message( $response_body, $response_code );
			Assistify_Logger::error( 'API error: ' . $error_msg, 'google-image' );
			return new \WP_Error(
				'api_error',
				$error_msg
			);
		}

		$this->log_usage( 'edits', $options );

		return $this->parse_image_response( $response_body, $options );
	}

	/**
	 * Upscale an image to higher resolution.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param int    $scale      Scale factor (2x, 4x).
	 * @param array  $options    Optional. Upscale options.
	 * @return array|\WP_Error Array with upscaled image data, or WP_Error on failure.
	 */
	public function upscale( $image_path, $scale = 2, array $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Google Imagen is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$image_data = $this->get_file_contents( $image_path );
		if ( is_wp_error( $image_data ) ) {
			return $image_data;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$base64_image = base64_encode( $image_data );

		// Imagen uses upscaleFactor parameter.
		$upscale_factor = min( max( 2, $scale ), 4 );

		$body = array(
			'instances'  => array(
				array(
					'image' => array(
						'bytesBase64Encoded' => $base64_image,
					),
				),
			),
			'parameters' => array(
				'upscaleFactor' => $upscale_factor,
			),
		);

		$endpoint = 'models/imagegeneration@002:predict?key=' . $this->api_key;

		// Log request for debugging.
		Assistify_Logger::log_api_request(
			'Google Imagen',
			$endpoint,
			array(
				'operation' => 'upscale',
				'factor'    => $upscale_factor,
			)
		);

		$response = wp_remote_post(
			$this->api_base_url . '/' . $endpoint,
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Assistify_Logger::error( 'Upscale failed: ' . $response->get_error_message(), 'google-image' );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		Assistify_Logger::log_api_response( 'Google Imagen', $response_code, $response_code >= 200 && $response_code < 300 );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_msg = $this->extract_error_message( $response_body, $response_code );
			Assistify_Logger::error( 'API error: ' . $error_msg, 'google-image' );
			return new \WP_Error(
				'api_error',
				$error_msg
			);
		}

		$this->log_usage( 'upscale', $options );

		return $this->parse_image_response( $response_body, $options );
	}

	/**
	 * Parse image response from Google Imagen.
	 *
	 * @since 1.0.0
	 * @param array $response API response.
	 * @param array $options  Request options.
	 * @return array Parsed response with images.
	 */
	private function parse_image_response( $response, $options ) {
		$images = array();

		// Google Imagen returns predictions array.
		$predictions = isset( $response['predictions'] ) ? $response['predictions'] : array();

		Assistify_Logger::debug( 'Response keys: ' . implode( ', ', array_keys( $response ) ), 'google-image' );

		if ( empty( $predictions ) ) {
			// Check alternative response format.
			if ( isset( $response['images'] ) ) {
				$predictions = $response['images'];
			} else {
				Assistify_Logger::error( 'Invalid response format - no predictions or images', 'google-image', array( 'response' => $response ) );
				return new \WP_Error(
					'invalid_response',
					__( 'Invalid response from Google Imagen API.', 'assistify-for-woocommerce' )
				);
			}
		}

		Assistify_Logger::debug( 'Processing ' . count( $predictions ) . ' prediction(s)', 'google-image' );

		foreach ( $predictions as $item ) {
			$image = array();

			// Handle base64 encoded image.
			if ( isset( $item['bytesBase64Encoded'] ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				$image['data'] = base64_decode( $item['bytesBase64Encoded'] );
				Assistify_Logger::debug( 'Decoded image data (length: ' . strlen( $image['data'] ) . ')', 'google-image' );
			}

			// Handle image URI (GCS path).
			if ( isset( $item['gcsUri'] ) ) {
				$image['gcs_uri'] = $item['gcsUri'];
			}

			// Handle mime type.
			if ( isset( $item['mimeType'] ) ) {
				$image['mime_type'] = $item['mimeType'];
			} else {
				$image['mime_type'] = 'image/png';
			}

			// Only add image if it has actual image data or URI.
			if ( isset( $image['data'] ) || isset( $image['gcs_uri'] ) ) {
				$images[] = $image;
			}
		}

		if ( empty( $images ) ) {
			Assistify_Logger::warning( 'No images in parsed response', 'google-image' );
			return new \WP_Error(
				'no_images',
				__( 'No images were generated.', 'assistify-for-woocommerce' )
			);
		}

		Assistify_Logger::log_image_operation( 'generate', 'success', array( 'count' => count( $images ) ) );

		return array(
			'success' => true,
			'images'  => $images,
			'model'   => isset( $options['model'] ) ? $options['model'] : $this->model,
		);
	}

	/**
	 * Convert size to aspect ratio.
	 *
	 * @since 1.0.0
	 * @param string $size Size in WIDTHxHEIGHT format.
	 * @return string Aspect ratio.
	 */
	private function size_to_aspect_ratio( $size ) {
		$ratios = array(
			'1024x1024' => '1:1',
			'1024x1536' => '2:3',
			'1536x1024' => '3:2',
			'1024x1792' => '9:16',
			'1792x1024' => '16:9',
		);

		return isset( $ratios[ $size ] ) ? $ratios[ $size ] : '1:1';
	}

	/**
	 * Sanitize prompt for image generation.
	 *
	 * @since 1.0.0
	 * @param string $prompt Raw prompt.
	 * @return string Sanitized prompt.
	 */
	private function sanitize_prompt( $prompt ) {
		$prompt = wp_strip_all_tags( $prompt );
		$prompt = preg_replace( '/\s+/', ' ', $prompt );
		$prompt = trim( $prompt );

		// Google Imagen supports up to 480 tokens (~2000 characters).
		if ( strlen( $prompt ) > 2000 ) {
			$prompt = substr( $prompt, 0, 1997 ) . '...';
		}

		return $prompt;
	}

	/**
	 * Get available sizes for this provider.
	 *
	 * @since 1.0.0
	 * @param string $model Optional. Model ID.
	 * @return array Array of available sizes.
	 */
	public function get_available_sizes( $model = '' ) {
		if ( empty( $model ) ) {
			$model = $this->model;
		}

		if ( isset( $this->available_models[ $model ]['sizes'] ) ) {
			return $this->available_models[ $model ]['sizes'];
		}

		return array( '1024x1024' );
	}

	/**
	 * Get estimated cost for an operation.
	 *
	 * @since 1.0.0
	 * @param string $operation Operation type.
	 * @param array  $options   Operation options.
	 * @return float Estimated cost in USD.
	 */
	public function get_estimated_cost( $operation, array $options = array() ) {
		$model = isset( $options['model'] ) ? $options['model'] : $this->model;

		if ( isset( $this->available_models[ $model ]['cost'] ) ) {
			$base_cost = $this->available_models[ $model ]['cost'];
		} else {
			$base_cost = 0.03;
		}

		// Upscaling is cheaper.
		if ( 'upscale' === $operation ) {
			$base_cost *= 0.5;
		}

		// Multiple images.
		$count      = isset( $options['n'] ) ? absint( $options['n'] ) : 1;
		$base_cost *= $count;

		return round( $base_cost, 4 );
	}

	/**
	 * Extract error message from Google API response.
	 *
	 * @since 1.0.0
	 * @param array $response    Decoded response body.
	 * @param int   $status_code HTTP status code.
	 * @return string Error message.
	 */
	protected function extract_error_message( $response, $status_code ) {
		if ( isset( $response['error']['message'] ) ) {
			return $response['error']['message'];
		}

		if ( isset( $response['error']['status'] ) ) {
			return $response['error']['status'];
		}

		return parent::extract_error_message( $response, $status_code );
	}
}
