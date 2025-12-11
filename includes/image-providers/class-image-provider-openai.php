<?php
/**
 * OpenAI Image Provider Class.
 *
 * Implements image generation using OpenAI's gpt-image-1 (replaces DALL-E).
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
 * OpenAI Image Provider Class.
 *
 * @since 1.0.0
 */
class Image_Provider_OpenAI extends Image_Provider_Abstract {

	/**
	 * Provider ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $id = 'openai';

	/**
	 * Provider name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'OpenAI';

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_base_url = 'https://api.openai.com/v1';

	/**
	 * Available models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $available_models = array(
		'gpt-image-1'      => array(
			'name'        => 'GPT Image 1',
			'description' => 'Latest image generation model, highest quality (may take 1-2 min).',
			'sizes'       => array( '1024x1024', '1024x1536', '1536x1024', 'auto' ),
			'quality'     => array( 'auto', 'low', 'medium', 'high' ),
			'cost'        => 0.04,
		),
		'gpt-image-1-mini' => array(
			'name'        => 'GPT Image 1 Mini',
			'description' => 'Fast and cost-effective image generation.',
			'sizes'       => array( '1024x1024', '1024x1536', '1536x1024', 'auto' ),
			'quality'     => array( 'auto', 'low', 'medium', 'high' ),
			'cost'        => 0.02,
		),
		'dall-e-3'         => array(
			'name'        => 'DALL-E 3',
			'description' => 'Previous generation, reliable image generation.',
			'sizes'       => array( '1024x1024', '1024x1792', '1792x1024' ),
			'quality'     => array( 'standard', 'hd' ),
			'cost'        => 0.04,
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
		'variations'        => true,
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
		return 'gpt-image-1';
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

		// Make a simple request to validate the key.
		$response = $this->make_request( 'models', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
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
				__( 'OpenAI is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );
		$model   = isset( $options['model'] ) ? $options['model'] : $this->model;

		// Build request body based on model.
		$body = array(
			'model'  => $model,
			'prompt' => $this->sanitize_prompt( $prompt ),
		);

		// Model-specific parameters.
		$is_gpt_image = strpos( $model, 'gpt-image' ) === 0;
		$is_dalle     = strpos( $model, 'dall-e' ) === 0;

		// gpt-image-1 doesn't support 'n' parameter for multiple images in one request.
		// DALL-E 3 only supports n=1, DALL-E 2 supports up to 10.
		if ( ! $is_gpt_image && 'dall-e-3' !== $model ) {
			$body['n'] = isset( $options['n'] ) ? min( absint( $options['n'] ), 4 ) : 1;
		}

		// Add size if not auto.
		if ( isset( $options['size'] ) && 'auto' !== $options['size'] ) {
			$body['size'] = $options['size'];
		}

		// Quality parameter handling.
		if ( $is_gpt_image && isset( $options['quality'] ) && 'auto' !== $options['quality'] ) {
			// Map standard/hd to low/medium/high for gpt-image-1.
			$quality_map     = array(
				'standard' => 'medium',
				'hd'       => 'high',
				'low'      => 'low',
				'medium'   => 'medium',
				'high'     => 'high',
			);
			$body['quality'] = isset( $quality_map[ $options['quality'] ] ) ? $quality_map[ $options['quality'] ] : 'medium';
		} elseif ( $is_dalle && isset( $options['quality'] ) ) {
			// DALL-E uses 'standard' and 'hd'.
			$body['quality'] = in_array( $options['quality'], array( 'standard', 'hd' ), true ) ? $options['quality'] : 'standard';
		}

		// Add response_format for DALL-E models (gpt-image-1 returns b64 by default).
		if ( $is_dalle ) {
			$body['response_format'] = 'b64_json';
		}

		// Log request for debugging.
		Assistify_Logger::log_api_request( 'OpenAI', 'images/generations', $body );

		// Set longer timeout for image generation.
		$this->default_options['timeout'] = 180;

		$response = $this->make_request( 'images/generations', $body );

		// Log response status.
		if ( is_wp_error( $response ) ) {
			Assistify_Logger::error( $response->get_error_message(), 'openai-image' );
		} else {
			Assistify_Logger::debug( 'Image response received successfully', 'openai-image' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Log usage.
		$this->log_usage( 'generations', $options );

		// Parse response.
		return $this->parse_image_response( $response, $options );
	}

	/**
	 * Edit an existing image based on a prompt.
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
				__( 'OpenAI is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		// Validate image.
		$validation = $this->validate_image(
			$image_path,
			array(
				'max_size'     => 4 * 1024 * 1024,
				'allowed_mime' => array( 'image/png' ),
			)
		);

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$options = $this->merge_options( $options );
		$model   = isset( $options['model'] ) ? $options['model'] : $this->model;

		// Prepare multipart form data.
		$boundary   = wp_generate_password( 24, false );
		$body       = '';
		$image_data = $this->get_file_contents( $image_path );

		if ( is_wp_error( $image_data ) ) {
			return $image_data;
		}

		// Build multipart body.
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
		$body .= $model . "\r\n";

		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"image\"; filename=\"image.png\"\r\n";
		$body .= "Content-Type: image/png\r\n\r\n";
		$body .= $image_data . "\r\n";

		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
		$body .= $this->sanitize_prompt( $prompt ) . "\r\n";

		if ( isset( $options['size'] ) && 'auto' !== $options['size'] ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"size\"\r\n\r\n";
			$body .= $options['size'] . "\r\n";
		}

		$body .= "--{$boundary}--\r\n";

		// Make request with multipart headers.
		$response = wp_remote_post(
			$this->api_base_url . '/images/edits',
			array(
				'timeout' => $this->default_options['timeout'],
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			return new \WP_Error(
				'api_error',
				$this->extract_error_message( $response_body, $response_code )
			);
		}

		$this->log_usage( 'edits', $options );

		return $this->parse_image_response( $response_body, $options );
	}

	/**
	 * Create variations of an existing image.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param int    $count      Number of variations to generate.
	 * @param array  $options    Optional. Variation options.
	 * @return array|\WP_Error Array of image data, or WP_Error on failure.
	 */
	public function create_variations( $image_path, $count = 1, array $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'OpenAI is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		// Validate image.
		$validation = $this->validate_image(
			$image_path,
			array(
				'max_size'     => 4 * 1024 * 1024,
				'allowed_mime' => array( 'image/png' ),
			)
		);

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$options    = $this->merge_options( $options );
		$count      = min( max( 1, absint( $count ) ), 4 );
		$model      = isset( $options['model'] ) ? $options['model'] : $this->model;
		$image_data = $this->get_file_contents( $image_path );

		if ( is_wp_error( $image_data ) ) {
			return $image_data;
		}

		// Build multipart body.
		$boundary = wp_generate_password( 24, false );
		$body     = '';

		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
		$body .= $model . "\r\n";

		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"image\"; filename=\"image.png\"\r\n";
		$body .= "Content-Type: image/png\r\n\r\n";
		$body .= $image_data . "\r\n";

		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"n\"\r\n\r\n";
		$body .= $count . "\r\n";

		if ( isset( $options['size'] ) && 'auto' !== $options['size'] ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"size\"\r\n\r\n";
			$body .= $options['size'] . "\r\n";
		}

		$body .= "--{$boundary}--\r\n";

		$response = wp_remote_post(
			$this->api_base_url . '/images/variations',
			array(
				'timeout' => $this->default_options['timeout'],
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			return new \WP_Error(
				'api_error',
				$this->extract_error_message( $response_body, $response_code )
			);
		}

		$this->log_usage( 'variations', $options );

		return $this->parse_image_response( $response_body, $options );
	}

	/**
	 * Parse image response from OpenAI.
	 *
	 * Handles both legacy DALL-E format and new gpt-image-1 format.
	 *
	 * @since 1.0.0
	 * @param array $response API response.
	 * @param array $options  Request options.
	 * @return array|\WP_Error Parsed response with images or WP_Error.
	 */
	private function parse_image_response( $response, $options ) {
		$images = array();

		// Log response structure for debugging.
		Assistify_Logger::debug( 'Response keys: ' . implode( ', ', array_keys( $response ) ), 'openai-image' );

		// Handle gpt-image-1 response format (may have 'data' array or direct fields).
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			foreach ( $response['data'] as $item ) {
				$image = array(
					'revised_prompt' => isset( $item['revised_prompt'] ) ? $item['revised_prompt'] : '',
				);

				// Handle URL response.
				if ( isset( $item['url'] ) ) {
					$image['url'] = $item['url'];
					Assistify_Logger::debug( 'Found URL in response', 'openai-image' );
				}

				// Handle base64 response.
				if ( isset( $item['b64_json'] ) ) {
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					$image['data'] = base64_decode( $item['b64_json'] );
					Assistify_Logger::debug( 'Decoded b64_json (length: ' . strlen( $image['data'] ) . ')', 'openai-image' );
				}

				// Check if image has data or URL.
				if ( ! empty( $image['data'] ) || ! empty( $image['url'] ) ) {
					$images[] = $image;
				} else {
					Assistify_Logger::warning( 'Image item has no data or URL', 'openai-image' );
				}
			}
		} elseif ( isset( $response['url'] ) ) {
			// Direct URL response.
			$images[] = array(
				'url'            => $response['url'],
				'revised_prompt' => isset( $response['revised_prompt'] ) ? $response['revised_prompt'] : '',
			);
		} elseif ( isset( $response['b64_json'] ) ) {
			// Direct base64 response.
			$images[] = array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				'data'           => base64_decode( $response['b64_json'] ),
				'revised_prompt' => isset( $response['revised_prompt'] ) ? $response['revised_prompt'] : '',
			);
		} else {
			// Log the response for debugging.
			Assistify_Logger::error( 'Invalid response format', 'openai-image', array( 'response' => $response ) );

			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from OpenAI Image API. Please check your API key and try again.', 'assistify-for-woocommerce' )
			);
		}

		if ( empty( $images ) ) {
			return new \WP_Error(
				'no_images',
				__( 'No images were returned from the API.', 'assistify-for-woocommerce' )
			);
		}

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
		// Remove potentially problematic content.
		$prompt = wp_strip_all_tags( $prompt );
		$prompt = preg_replace( '/\s+/', ' ', $prompt );
		$prompt = trim( $prompt );

		// Limit length (OpenAI allows up to 4000 characters).
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
			$base_cost = 0.04;
		}

		// HD quality costs more.
		if ( isset( $options['quality'] ) && in_array( $options['quality'], array( 'hd', 'high' ), true ) ) {
			$base_cost *= 1.5;
		}

		// Larger sizes cost more.
		if ( isset( $options['size'] ) && in_array( $options['size'], array( '1792x1024', '1024x1792', '1536x1024', '1024x1536' ), true ) ) {
			$base_cost *= 1.25;
		}

		// Multiple images.
		$count      = isset( $options['n'] ) ? absint( $options['n'] ) : 1;
		$base_cost *= $count;

		return round( $base_cost, 4 );
	}
}
