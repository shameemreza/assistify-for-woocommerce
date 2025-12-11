<?php
/**
 * Abstract AI Provider Class.
 *
 * Base class for all AI provider implementations.
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
 * Abstract AI Provider Class.
 *
 * @since 1.0.0
 */
abstract class AI_Provider_Abstract implements AI_Provider_Interface {

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
	 * Default options for API requests.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $default_options = array(
		'temperature' => 0.7,
		'max_tokens'  => 2048,
		'timeout'     => 60,
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
	 * @since 1.0.0
	 * @return string The decrypted API key or empty string.
	 */
	protected function get_stored_api_key() {
		$encrypted_key = get_option( 'assistify_api_key', '' );

		if ( empty( $encrypted_key ) ) {
			return '';
		}

		// For now, return as-is. In production, implement proper decryption.
		// TODO: Implement encryption/decryption using wp_salt().
		return $encrypted_key;
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
	 * Count tokens in text (rough estimation).
	 *
	 * This is a rough estimation. Each provider may have different tokenization.
	 * Override in specific provider classes for accurate counting.
	 *
	 * @since 1.0.0
	 * @param string $text Text to count tokens for.
	 * @return int Estimated token count.
	 */
	public function count_tokens( $text ) {
		// Rough estimation: ~4 characters per token for English text.
		return (int) ceil( strlen( $text ) / 4 );
	}

	/**
	 * Make an API request.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $body     Request body.
	 * @param string $method   HTTP method. Default 'POST'.
	 * @return array|\WP_Error Response array or WP_Error on failure.
	 */
	protected function make_request( $endpoint, $body = array(), $method = 'POST' ) {
		$url = trailingslashit( $this->api_base_url ) . ltrim( $endpoint, '/' );

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
	 * Get request headers for API calls.
	 *
	 * Override in provider classes to customize headers.
	 *
	 * @since 1.0.0
	 * @return array Request headers.
	 */
	protected function get_request_headers() {
		return array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
		);
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
	 * Log API usage for tracking.
	 *
	 * @since 1.0.0
	 * @param array $usage Usage data from API response.
	 * @return void
	 */
	protected function log_usage( $usage ) {
		// Store usage data for cost tracking.
		$total_usage = get_option( 'assistify_api_usage', array() );

		$provider_id = $this->get_id();
		$today       = gmdate( 'Y-m-d' );

		if ( ! isset( $total_usage[ $provider_id ] ) ) {
			$total_usage[ $provider_id ] = array();
		}

		if ( ! isset( $total_usage[ $provider_id ][ $today ] ) ) {
			$total_usage[ $provider_id ][ $today ] = array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
				'requests'          => 0,
			);
		}

		$total_usage[ $provider_id ][ $today ]['prompt_tokens']     += isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
		$total_usage[ $provider_id ][ $today ]['completion_tokens'] += isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0;
		$total_usage[ $provider_id ][ $today ]['total_tokens']      += isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : 0;
		$total_usage[ $provider_id ][ $today ]['requests']          += 1;

		update_option( 'assistify_api_usage', $total_usage );
	}
}
