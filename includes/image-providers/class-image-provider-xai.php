<?php
/**
 * XAI Image Provider Class.
 *
 * Implements image generation using xAI's Grok Image API.
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
 * XAI Image Provider Class.
 *
 * @since 1.0.0
 */
class Image_Provider_XAI extends Image_Provider_Abstract {

	/**
	 * Provider ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $id = 'xai';

	/**
	 * Provider name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'xAI Grok';

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_base_url = 'https://api.x.ai/v1';

	/**
	 * Available models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $available_models = array(
		'grok-2-image-1212' => array(
			'name'        => 'Grok 2 Image',
			'description' => 'xAI Grok image generation model.',
			'sizes'       => array( '1024x1024', '1024x1536', '1536x1024' ),
			'cost'        => 0.07,
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
		'edit'              => false,
		'variations'        => false,
		'remove_background' => false,
		'upscale'           => false,
	);

	/**
	 * Get the default model.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model() {
		return 'grok-2-image-1212';
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
			$this->api_base_url . '/models',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid xAI API key.', 'assistify-for-woocommerce' )
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
				__( 'xAI Grok is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );
		$model   = isset( $options['model'] ) ? $options['model'] : $this->model;

		// Build request body.
		$body = array(
			'model'  => $model,
			'prompt' => $this->sanitize_prompt( $prompt ),
			'n'      => isset( $options['n'] ) ? min( absint( $options['n'] ), 4 ) : 1,
		);

		// Add size.
		if ( isset( $options['size'] ) && 'auto' !== $options['size'] ) {
			$body['size'] = $options['size'];
		}

		// Set response format - use b64_json for more reliable data transfer.
		$body['response_format'] = 'b64_json';

		// Log request for debugging.
		Assistify_Logger::log_api_request( 'xAI Grok', '/images/generations', $body );

		// Set longer timeout for image generation (xAI can take up to 60 seconds).
		$timeout = 120;

		$response = wp_remote_post(
			$this->api_base_url . '/images/generations',
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Assistify_Logger::error( $response->get_error_message(), 'xai-image' );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Log response status.
		Assistify_Logger::log_api_response( 'xAI Grok', $response_code, $response_code >= 200 && $response_code < 300 );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_msg = $this->extract_error_message( $response_body, $response_code );
			Assistify_Logger::error( 'API error: ' . $error_msg, 'xai-image' );
			return new \WP_Error(
				'api_error',
				$error_msg
			);
		}

		$this->log_usage( 'generations', $options );

		return $this->parse_image_response( $response_body, $options );
	}

	/**
	 * Parse image response from xAI.
	 *
	 * @since 1.0.0
	 * @param array $response API response.
	 * @param array $options  Request options.
	 * @return array Parsed response with images.
	 */
	private function parse_image_response( $response, $options ) {
		$images = array();

		Assistify_Logger::debug( 'Response keys: ' . implode( ', ', array_keys( $response ) ), 'xai-image' );

		if ( ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
			Assistify_Logger::error( 'Invalid response format - no data array', 'xai-image', array( 'response' => $response ) );
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from xAI Image API.', 'assistify-for-woocommerce' )
			);
		}

		Assistify_Logger::debug( 'Processing ' . count( $response['data'] ) . ' image(s)', 'xai-image' );

		foreach ( $response['data'] as $item ) {
			$image = array();

			if ( isset( $item['url'] ) ) {
				$image['url'] = $item['url'];
				Assistify_Logger::debug( 'Found URL in response', 'xai-image' );
			}

			if ( isset( $item['b64_json'] ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				$image['data'] = base64_decode( $item['b64_json'] );
				Assistify_Logger::debug( 'Decoded b64_json (length: ' . strlen( $image['data'] ) . ')', 'xai-image' );
			}

			if ( isset( $item['revised_prompt'] ) ) {
				$image['revised_prompt'] = $item['revised_prompt'];
			}

			if ( ! empty( $image ) ) {
				$images[] = $image;
			}
		}

		if ( empty( $images ) ) {
			Assistify_Logger::warning( 'No images in parsed response', 'xai-image' );
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

		// Limit length.
		if ( strlen( $prompt ) > 4000 ) {
			$prompt = substr( $prompt, 0, 3997 ) . '...';
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
			$base_cost = 0.07;
		}

		// Multiple images.
		$count      = isset( $options['n'] ) ? absint( $options['n'] ) : 1;
		$base_cost *= $count;

		return round( $base_cost, 4 );
	}
}
