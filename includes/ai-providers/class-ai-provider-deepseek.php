<?php
/**
 * DeepSeek Provider Class.
 *
 * Implements the AI Provider interface for DeepSeek API.
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
 * DeepSeek Provider Class.
 *
 * @since 1.0.0
 */
class AI_Provider_DeepSeek extends AI_Provider_Abstract {

	/**
	 * Provider ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $id = 'deepseek';

	/**
	 * Provider name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'DeepSeek';

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_base_url = 'https://api.deepseek.com/v1';

	/**
	 * Available models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $available_models = array(
		'deepseek-chat'     => array(
			'name'           => 'DeepSeek-V3',
			'context_length' => 64000,
			'description'    => 'Latest V3 general-purpose chat model.',
		),
		'deepseek-reasoner' => array(
			'name'           => 'DeepSeek-R1',
			'context_length' => 64000,
			'description'    => 'Advanced reasoning model (R1).',
		),
		'deepseek-coder'    => array(
			'name'           => 'DeepSeek Coder',
			'context_length' => 64000,
			'description'    => 'Specialized for code generation.',
		),
	);

	/**
	 * Get the default model.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model() {
		return 'deepseek-chat';
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
	 * DeepSeek uses OpenAI-compatible API format.
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
				__( 'DeepSeek provider is not configured. Please add your API key.', 'assistify-for-woocommerce' )
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
				__( 'Invalid response from DeepSeek API.', 'assistify-for-woocommerce' )
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
		return 64000;
	}
}
