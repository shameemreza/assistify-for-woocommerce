<?php
/**
 * Anthropic Provider Class.
 *
 * Implements the AI Provider interface for Anthropic Claude API.
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
 * Anthropic Provider Class.
 *
 * @since 1.0.0
 */
class AI_Provider_Anthropic extends AI_Provider_Abstract {

	/**
	 * Provider ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $id = 'anthropic';

	/**
	 * Provider name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'Anthropic';

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_base_url = 'https://api.anthropic.com/v1';

	/**
	 * API version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_version = '2023-06-01';

	/**
	 * Available models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $available_models = array(
		// Claude 4.5 Series (Latest).
		'claude-opus-4-5-20250514'   => array(
			'name'           => 'Claude Opus 4.5',
			'context_length' => 200000,
			'description'    => 'Most advanced Claude model with superior reasoning.',
		),
		'claude-sonnet-4-5-20250514' => array(
			'name'           => 'Claude Sonnet 4.5',
			'context_length' => 200000,
			'description'    => 'Excellent balance of intelligence and speed.',
		),
		'claude-haiku-4-5-20250514'  => array(
			'name'           => 'Claude Haiku 4.5',
			'context_length' => 200000,
			'description'    => 'Fast and cost-effective for simple tasks.',
		),
		// Claude 4 Series.
		'claude-opus-4-1-20250514'   => array(
			'name'           => 'Claude Opus 4.1',
			'context_length' => 200000,
			'description'    => 'Enhanced Opus with extended thinking.',
		),
		'claude-opus-4-20250514'     => array(
			'name'           => 'Claude Opus 4',
			'context_length' => 200000,
			'description'    => 'Most powerful Claude 4 model.',
		),
		'claude-sonnet-4-20250514'   => array(
			'name'           => 'Claude Sonnet 4',
			'context_length' => 200000,
			'description'    => 'Excellent for complex tasks.',
		),
		// Claude 3.5 Series.
		'claude-3-5-sonnet-20241022' => array(
			'name'           => 'Claude 3.5 Sonnet',
			'context_length' => 200000,
			'description'    => 'Fast and intelligent, great for most tasks.',
		),
		'claude-3-5-haiku-20241022'  => array(
			'name'           => 'Claude 3.5 Haiku',
			'context_length' => 200000,
			'description'    => 'Fastest model, cost-effective option.',
		),
		// Claude 3 Series.
		'claude-3-haiku-20240307'    => array(
			'name'           => 'Claude 3 Haiku',
			'context_length' => 200000,
			'description'    => 'Fast and compact Claude 3 model.',
		),
	);

	/**
	 * Get the default model.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model() {
		return 'claude-sonnet-4-5-20250514';
	}

	/**
	 * Get request headers for Anthropic API.
	 *
	 * @since 1.0.0
	 * @return array Request headers.
	 */
	protected function get_request_headers() {
		return array(
			'Content-Type'      => 'application/json',
			'x-api-key'         => $this->api_key,
			'anthropic-version' => $this->api_version,
		);
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
				__( 'Anthropic provider is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );

		// Anthropic uses a different message format.
		// System prompt should be separate from messages.
		$system_prompt = '';
		$api_messages  = array();

		foreach ( $messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$system_prompt = $message['content'];
			} else {
				$api_messages[] = array(
					'role'    => $message['role'],
					'content' => $message['content'],
				);
			}
		}

		// Add system prompt from options if provided.
		if ( isset( $options['system_prompt'] ) && ! empty( $options['system_prompt'] ) ) {
			$system_prompt = $options['system_prompt'];
		}

		$body = array(
			'model'      => isset( $options['model'] ) ? $options['model'] : $this->model,
			'messages'   => $api_messages,
			'max_tokens' => (int) $options['max_tokens'],
		);

		if ( ! empty( $system_prompt ) ) {
			$body['system'] = $system_prompt;
		}

		// Temperature is optional for Claude.
		if ( isset( $options['temperature'] ) ) {
			$body['temperature'] = (float) $options['temperature'];
		}

		$response = $this->make_request( 'messages', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract the response content.
		if ( ! isset( $response['content'][0]['text'] ) ) {
			return new \WP_Error(
				'assistify_invalid_response',
				__( 'Invalid response from Anthropic API.', 'assistify-for-woocommerce' )
			);
		}

		// Log usage.
		if ( isset( $response['usage'] ) ) {
			$usage = array(
				'prompt_tokens'     => $response['usage']['input_tokens'] ?? 0,
				'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
				'total_tokens'      => ( $response['usage']['input_tokens'] ?? 0 ) + ( $response['usage']['output_tokens'] ?? 0 ),
			);
			$this->log_usage( $usage );
		}

		return array(
			'content' => $response['content'][0]['text'],
			'usage'   => array(
				'prompt_tokens'     => $response['usage']['input_tokens'] ?? 0,
				'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
				'total_tokens'      => ( $response['usage']['input_tokens'] ?? 0 ) + ( $response['usage']['output_tokens'] ?? 0 ),
			),
			'model'   => $response['model'] ?? $body['model'],
		);
	}

	/**
	 * Chat with function/tool calling support.
	 *
	 * @since 1.0.0
	 * @param array $messages Conversation messages.
	 * @param array $tools    Available tools in OpenAI format (will be converted).
	 * @param array $options  Optional parameters.
	 * @return array|\WP_Error Response with tool_calls or content.
	 */
	public function chat_with_tools( array $messages, array $tools, array $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'assistify_not_configured',
				__( 'Anthropic provider is not configured. Please add your API key.', 'assistify-for-woocommerce' )
			);
		}

		$options = $this->merge_options( $options );

		// Convert OpenAI tool format to Anthropic format.
		$anthropic_tools = array();
		foreach ( $tools as $tool ) {
			if ( isset( $tool['function'] ) ) {
				$anthropic_tools[] = array(
					'name'         => $tool['function']['name'],
					'description'  => $tool['function']['description'],
					'input_schema' => $tool['function']['parameters'],
				);
			}
		}

		// Build messages for Anthropic.
		$system_prompt = '';
		$api_messages  = array();

		foreach ( $messages as $message ) {
			if ( 'system' === $message['role'] ) {
				$system_prompt = $message['content'];
			} elseif ( 'tool' === $message['role'] ) {
				// Convert tool result to Anthropic format.
				$api_messages[] = array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'        => 'tool_result',
							'tool_use_id' => $message['tool_call_id'],
							'content'     => $message['content'],
						),
					),
				);
			} elseif ( 'assistant' === $message['role'] && isset( $message['tool_calls'] ) ) {
				// Convert assistant tool calls to Anthropic format.
				$content = array();
				foreach ( $message['tool_calls'] as $tool_call ) {
					$content[] = array(
						'type'  => 'tool_use',
						'id'    => $tool_call['id'],
						'name'  => $tool_call['function']['name'],
						'input' => json_decode( $tool_call['function']['arguments'], true ) ?? array(),
					);
				}
				$api_messages[] = array(
					'role'    => 'assistant',
					'content' => $content,
				);
			} else {
				$api_messages[] = array(
					'role'    => $message['role'],
					'content' => $message['content'],
				);
			}
		}

		// Add system prompt from options.
		if ( isset( $options['system_prompt'] ) && ! empty( $options['system_prompt'] ) ) {
			$system_prompt = $options['system_prompt'];
		}

		$body = array(
			'model'      => isset( $options['model'] ) ? $options['model'] : $this->model,
			'messages'   => $api_messages,
			'tools'      => $anthropic_tools,
			'max_tokens' => (int) $options['max_tokens'],
		);

		if ( ! empty( $system_prompt ) ) {
			$body['system'] = $system_prompt;
		}

		if ( isset( $options['temperature'] ) ) {
			$body['temperature'] = (float) $options['temperature'];
		}

		$response = $this->make_request( 'messages', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check for tool use in response.
		$tool_calls   = array();
		$text_content = '';

		if ( isset( $response['content'] ) && is_array( $response['content'] ) ) {
			foreach ( $response['content'] as $block ) {
				if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
					// Convert to OpenAI format for compatibility.
					$tool_calls[] = array(
						'id'       => $block['id'],
						'type'     => 'function',
						'function' => array(
							'name'      => $block['name'],
							'arguments' => wp_json_encode( $block['input'] ?? array() ),
						),
					);
				} elseif ( 'text' === ( $block['type'] ?? '' ) ) {
					$text_content .= $block['text'];
				}
			}
		}

		// Build usage array.
		$usage = array(
			'prompt_tokens'     => $response['usage']['input_tokens'] ?? 0,
			'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
			'total_tokens'      => ( $response['usage']['input_tokens'] ?? 0 ) + ( $response['usage']['output_tokens'] ?? 0 ),
		);

		if ( ! empty( $tool_calls ) ) {
			// Build assistant message in OpenAI format for conversation continuity.
			$assistant_message = array(
				'role'       => 'assistant',
				'content'    => $text_content,
				'tool_calls' => $tool_calls,
			);

			return array(
				'type'       => 'tool_calls',
				'tool_calls' => $tool_calls,
				'message'    => $assistant_message,
				'usage'      => $usage,
				'model'      => $response['model'] ?? $body['model'],
			);
		}

		// Regular content response.
		return array(
			'type'    => 'content',
			'content' => $text_content,
			'usage'   => $usage,
			'model'   => $response['model'] ?? $body['model'],
		);
	}

	/**
	 * Continue a conversation after tool execution.
	 *
	 * @since 1.0.0
	 * @param array $messages Updated messages including tool results.
	 * @param array $tools    Available tools.
	 * @param array $options  Optional parameters.
	 * @return array|\WP_Error Response.
	 */
	public function continue_with_tool_results( array $messages, array $tools, array $options = array() ) {
		return $this->chat_with_tools( $messages, $tools, $options );
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

		// Default fallback for Claude models.
		return 200000;
	}
}
