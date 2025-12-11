<?php
/**
 * XAI (Grok) Provider Class.
 *
 * Implements the AI Provider interface for xAI Grok API.
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
 * XAI (Grok) Provider Class.
 *
 * @since 1.0.0
 */
class AI_Provider_XAI extends AI_Provider_Abstract {

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
	protected $name = 'xAI (Grok)';

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
		'grok-3'           => array(
			'name'           => 'Grok 3',
			'context_length' => 131072,
			'description'    => 'Latest and most capable Grok model.',
		),
		'grok-3-fast'      => array(
			'name'           => 'Grok 3 Fast',
			'context_length' => 131072,
			'description'    => 'Faster variant of Grok 3.',
		),
		'grok-3-mini'      => array(
			'name'           => 'Grok 3 Mini',
			'context_length' => 131072,
			'description'    => 'Lightweight Grok 3 variant.',
		),
		'grok-3-mini-fast' => array(
			'name'           => 'Grok 3 Mini Fast',
			'context_length' => 131072,
			'description'    => 'Fastest Grok 3 variant.',
		),
		'grok-2'           => array(
			'name'           => 'Grok 2',
			'context_length' => 131072,
			'description'    => 'Balanced performance and speed.',
		),
		'grok-2-vision'    => array(
			'name'           => 'Grok 2 Vision',
			'context_length' => 32768,
			'description'    => 'Multimodal model with image understanding.',
		),
		'grok-2-mini'      => array(
			'name'           => 'Grok 2 Mini',
			'context_length' => 131072,
			'description'    => 'Smaller, faster Grok 2.',
		),
	);

	/**
	 * Get the default model.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model() {
		return 'grok-3-fast';
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
	 * XAI uses OpenAI-compatible API format.
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
				__( 'xAI provider is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );

		$body = array(
			'model'       => isset( $options['model'] ) ? $options['model'] : $this->model,
			'messages'    => $messages,
			'temperature' => (float) $options['temperature'],
			'max_tokens'  => (int) $options['max_tokens'],
		);

		// Add optional system prompt.
		if ( isset( $options['system_prompt'] ) ) {
			array_unshift(
				$body['messages'],
				array(
					'role'    => 'system',
					'content' => $options['system_prompt'],
				)
			);
		}

		$response = $this->make_request( 'chat/completions', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract the response content (OpenAI-compatible format).
		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'assistify_invalid_response',
				__( 'Invalid response from xAI API.', 'assistify-for-woocommerce' )
			);
		}

		// Log usage.
		if ( isset( $response['usage'] ) ) {
			$this->log_usage( $response['usage'] );
		}

		return array(
			'content' => $response['choices'][0]['message']['content'],
			'usage'   => isset( $response['usage'] ) ? $response['usage'] : array(),
			'model'   => isset( $response['model'] ) ? $response['model'] : $body['model'],
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

		// Default fallback.
		return 131072;
	}
}
