<?php
/**
 * OpenAI Provider Class.
 *
 * Implements the AI Provider interface for OpenAI API.
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
 * OpenAI Provider Class.
 *
 * @since 1.0.0
 */
class AI_Provider_OpenAI extends AI_Provider_Abstract {

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
		// GPT-5 Series (Latest).
		'gpt-5.1'     => array(
			'name'           => 'GPT-5.1',
			'context_length' => 1048576,
			'description'    => 'Latest and most capable GPT model with 1M context.',
		),
		'gpt-5'       => array(
			'name'           => 'GPT-5',
			'context_length' => 256000,
			'description'    => 'Most powerful general-purpose model.',
		),
		'gpt-5-pro'   => array(
			'name'           => 'GPT-5 Pro',
			'context_length' => 256000,
			'description'    => 'Professional tier with extended capabilities.',
		),
		'gpt-5-mini'  => array(
			'name'           => 'GPT-5 Mini',
			'context_length' => 256000,
			'description'    => 'Cost-effective GPT-5 variant.',
		),
		'gpt-5-nano'  => array(
			'name'           => 'GPT-5 Nano',
			'context_length' => 128000,
			'description'    => 'Fastest GPT-5 variant for simple tasks.',
		),
		// GPT-4 Series.
		'gpt-4.1'     => array(
			'name'           => 'GPT-4.1',
			'context_length' => 1048576,
			'description'    => 'GPT-4.1 with 1M context window.',
		),
		'gpt-4o'      => array(
			'name'           => 'GPT-4o',
			'context_length' => 128000,
			'description'    => 'Multimodal model for complex tasks.',
		),
		'gpt-4o-mini' => array(
			'name'           => 'GPT-4o Mini',
			'context_length' => 128000,
			'description'    => 'Fast and cost-effective for most tasks.',
		),
		// Reasoning Models.
		'o3-mini'     => array(
			'name'           => 'o3-mini',
			'context_length' => 200000,
			'description'    => 'Latest o3 reasoning model.',
		),
		'o1'          => array(
			'name'           => 'o1',
			'context_length' => 200000,
			'description'    => 'Advanced reasoning for complex problems.',
		),
		'o1-mini'     => array(
			'name'           => 'o1-mini',
			'context_length' => 128000,
			'description'    => 'Faster reasoning model.',
		),
		'o1-pro'      => array(
			'name'           => 'o1-pro',
			'context_length' => 200000,
			'description'    => 'Most powerful reasoning model.',
		),
	);

	/**
	 * Get the default model.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model() {
		return 'gpt-4o';
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
				__( 'OpenAI provider is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );

		$body = array(
			'model'       => isset( $options['model'] ) ? $options['model'] : $this->model,
			'messages'    => $messages,
			'temperature' => (float) $options['temperature'],
			'max_tokens'  => (int) $options['max_tokens'],
		);

		// Add optional parameters.
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

		// Extract the response content.
		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'assistify_invalid_response',
				__( 'Invalid response from OpenAI API.', 'assistify-for-woocommerce' )
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
		return 8192;
	}

	/**
	 * Count tokens using tiktoken approximation.
	 *
	 * @since 1.0.0
	 * @param string $text Text to count tokens for.
	 * @return int Estimated token count.
	 */
	public function count_tokens( $text ) {
		// OpenAI uses roughly 4 characters per token for English.
		// This is a rough estimation; for accurate counting, use tiktoken library.
		return (int) ceil( strlen( $text ) / 4 );
	}
}
