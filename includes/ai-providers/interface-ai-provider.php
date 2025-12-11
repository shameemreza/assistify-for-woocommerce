<?php
/**
 * AI Provider Interface.
 *
 * Defines the contract that all AI providers must implement.
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
 * AI Provider Interface.
 *
 * @since 1.0.0
 */
interface AI_Provider_Interface {

	/**
	 * Get the provider ID.
	 *
	 * @since 1.0.0
	 * @return string Provider unique identifier.
	 */
	public function get_id();

	/**
	 * Get the provider name.
	 *
	 * @since 1.0.0
	 * @return string Provider display name.
	 */
	public function get_name();

	/**
	 * Check if the provider is configured and ready to use.
	 *
	 * @since 1.0.0
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured();

	/**
	 * Validate the API key.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_api_key();

	/**
	 * Send a chat completion request.
	 *
	 * @since 1.0.0
	 * @param array $messages Array of message objects with 'role' and 'content'.
	 * @param array $options  Optional. Additional options like model, temperature, etc.
	 * @return array|\WP_Error Response array with 'content' and 'usage', or WP_Error on failure.
	 */
	public function chat( array $messages, array $options = array() );

	/**
	 * Get available models for this provider.
	 *
	 * @since 1.0.0
	 * @return array Array of available models with id and name.
	 */
	public function get_available_models();

	/**
	 * Get the default model for this provider.
	 *
	 * @since 1.0.0
	 * @return string Default model ID.
	 */
	public function get_default_model();

	/**
	 * Count tokens in a text string.
	 *
	 * @since 1.0.0
	 * @param string $text Text to count tokens for.
	 * @return int Estimated token count.
	 */
	public function count_tokens( $text );

	/**
	 * Get the maximum context length for the current model.
	 *
	 * @since 1.0.0
	 * @param string $model Optional. Model ID. Defaults to current model.
	 * @return int Maximum context length in tokens.
	 */
	public function get_max_context_length( $model = '' );
}
