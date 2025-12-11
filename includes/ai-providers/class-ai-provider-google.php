<?php
/**
 * Google Gemini Provider Class.
 *
 * Implements the AI Provider interface for Google Gemini API.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\AI_Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini Provider Class.
 *
 * @since 1.0.0
 */
class AI_Provider_Google extends AI_Provider_Abstract {

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
	protected $name = 'Google Gemini';

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
		'gemini-2.5-pro'            => array(
			'name'           => 'Gemini 2.5 Pro',
			'context_length' => 1048576,
			'description'    => 'Latest Pro model with enhanced reasoning.',
		),
		'gemini-2.5-flash'          => array(
			'name'           => 'Gemini 2.5 Flash',
			'context_length' => 1048576,
			'description'    => 'Fast model with adaptive thinking.',
		),
		'gemini-2.0-flash'          => array(
			'name'           => 'Gemini 2.0 Flash',
			'context_length' => 1048576,
			'description'    => 'Fast model with 1M context.',
		),
		'gemini-2.0-flash-lite'     => array(
			'name'           => 'Gemini 2.0 Flash-Lite',
			'context_length' => 1048576,
			'description'    => 'Cost-efficient for high-volume tasks.',
		),
		'gemini-2.0-flash-thinking' => array(
			'name'           => 'Gemini 2.0 Flash Thinking',
			'context_length' => 1048576,
			'description'    => 'Enhanced reasoning capabilities.',
		),
		'gemini-1.5-pro'            => array(
			'name'           => 'Gemini 1.5 Pro',
			'context_length' => 2097152,
			'description'    => 'Most capable with 2M context window.',
		),
		'gemini-1.5-flash'          => array(
			'name'           => 'Gemini 1.5 Flash',
			'context_length' => 1048576,
			'description'    => 'Fast and efficient with 1M context.',
		),
		'gemini-1.5-flash-8b'       => array(
			'name'           => 'Gemini 1.5 Flash-8B',
			'context_length' => 1048576,
			'description'    => 'Lightweight, high-volume tasks.',
		),
	);

	/**
	 * Get the default model.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model() {
		return 'gemini-2.0-flash';
	}

	/**
	 * Get request headers for Google API.
	 *
	 * @since 1.0.0
	 * @return array Request headers.
	 */
	protected function get_request_headers() {
		return array(
			'Content-Type' => 'application/json',
		);
	}

	/**
	 * Make an API request to Google.
	 *
	 * Google uses API key in URL, not in headers.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $body     Request body.
	 * @param string $method   HTTP method.
	 * @return array|\WP_Error Response array or WP_Error.
	 */
	protected function make_request( $endpoint, $body = array(), $method = 'POST' ) {
		$url = trailingslashit( $this->api_base_url ) . ltrim( $endpoint, '/' );
		$url = add_query_arg( 'key', $this->api_key, $url );

		$args = array(
			'method'  => $method,
			'timeout' => $this->default_options['timeout'],
			'headers' => $this->get_request_headers(),
		);

		if ( ! empty( $body ) && 'POST' === $method ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = isset( $decoded_body['error']['message'] )
				? $decoded_body['error']['message']
				: sprintf(
					/* translators: %d: HTTP response code. */
					__( 'API request failed with status code %d.', 'assistify-for-woocommerce' ),
					$response_code
				);

			return new \WP_Error(
				'assistify_api_error',
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
		$response = $this->chat(
			array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			array( 'max_tokens' => 10 )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Send a chat completion request.
	 *
	 * @since 1.0.0
	 * @param array $messages Array of message objects with 'role' and 'content'.
	 * @param array $options  Optional. Additional options.
	 * @return array|\WP_Error Response array or WP_Error on failure.
	 */
	public function chat( array $messages, array $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'assistify_not_configured',
				__( 'Google Gemini provider is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );
		$model   = isset( $options['model'] ) ? $options['model'] : $this->model;

		// Convert messages to Gemini format.
		$contents           = array();
		$system_instruction = '';

		foreach ( $messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$system_instruction = $message['content'];
				continue;
			}

			$role       = 'user' === $message['role'] ? 'user' : 'model';
			$contents[] = array(
				'role'  => $role,
				'parts' => array(
					array( 'text' => $message['content'] ),
				),
			);
		}

		// Add system prompt from options if provided.
		if ( isset( $options['system_prompt'] ) && ! empty( $options['system_prompt'] ) ) {
			$system_instruction = $options['system_prompt'];
		}

		$body = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'maxOutputTokens' => (int) $options['max_tokens'],
				'temperature'     => (float) $options['temperature'],
			),
		);

		if ( ! empty( $system_instruction ) ) {
			$body['systemInstruction'] = array(
				'parts' => array(
					array( 'text' => $system_instruction ),
				),
			);
		}

		$endpoint = "models/{$model}:generateContent";
		$response = $this->make_request( $endpoint, $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract the response content.
		if ( ! isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new \WP_Error(
				'assistify_invalid_response',
				__( 'Invalid response from Google Gemini API.', 'assistify-for-woocommerce' )
			);
		}

		// Log usage.
		$usage = array(
			'prompt_tokens'     => $response['usageMetadata']['promptTokenCount'] ?? 0,
			'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
			'total_tokens'      => $response['usageMetadata']['totalTokenCount'] ?? 0,
		);
		$this->log_usage( $usage );

		return array(
			'content' => $response['candidates'][0]['content']['parts'][0]['text'],
			'usage'   => $usage,
			'model'   => $model,
		);
	}

	/**
	 * Get the maximum context length for a model.
	 *
	 * @since 1.0.0
	 * @param string $model Optional. Model ID.
	 * @return int Maximum context length.
	 */
	public function get_max_context_length( $model = '' ) {
		if ( empty( $model ) ) {
			$model = $this->model;
		}

		if ( isset( $this->available_models[ $model ]['context_length'] ) ) {
			return $this->available_models[ $model ]['context_length'];
		}

		// Default fallback for Gemini models.
		return 1048576;
	}
}
