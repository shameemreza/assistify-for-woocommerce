<?php
/**
 * Image Provider Interface.
 *
 * Defines the contract that all image generation providers must implement.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\Image_Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Provider Interface.
 *
 * @since 1.0.0
 */
interface Image_Provider_Interface {

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
	 * Generate an image from a text prompt.
	 *
	 * @since 1.0.0
	 * @param string $prompt  Text description of the image to generate.
	 * @param array  $options Optional. Generation options (size, quality, style, etc.).
	 * @return array|\WP_Error Array with 'url' or 'data' key, or WP_Error on failure.
	 */
	public function generate( $prompt, array $options = array() );

	/**
	 * Edit an existing image based on a prompt.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param string $prompt     Edit instructions.
	 * @param array  $options    Optional. Edit options.
	 * @return array|\WP_Error Array with 'url' or 'data' key, or WP_Error on failure.
	 */
	public function edit( $image_path, $prompt, array $options = array() );

	/**
	 * Create variations of an existing image.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param int    $count      Number of variations to generate.
	 * @param array  $options    Optional. Variation options.
	 * @return array|\WP_Error Array of image URLs/data, or WP_Error on failure.
	 */
	public function create_variations( $image_path, $count = 1, array $options = array() );

	/**
	 * Remove background from an image.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param array  $options    Optional. Background removal options.
	 * @return array|\WP_Error Array with transparent PNG data, or WP_Error on failure.
	 */
	public function remove_background( $image_path, array $options = array() );

	/**
	 * Upscale an image to higher resolution.
	 *
	 * @since 1.0.0
	 * @param string $image_path Path to the source image.
	 * @param int    $scale      Scale factor (2x, 4x).
	 * @param array  $options    Optional. Upscale options.
	 * @return array|\WP_Error Array with upscaled image data, or WP_Error on failure.
	 */
	public function upscale( $image_path, $scale = 2, array $options = array() );

	/**
	 * Check if a specific capability is supported.
	 *
	 * @since 1.0.0
	 * @param string $capability Capability to check (generate, edit, variations, remove_background, upscale).
	 * @return bool True if supported.
	 */
	public function supports( $capability );

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
	 * Get available image sizes for this provider.
	 *
	 * @since 1.0.0
	 * @param string $model Optional. Model ID to get sizes for.
	 * @return array Array of available sizes (e.g., ['1024x1024', '1792x1024']).
	 */
	public function get_available_sizes( $model = '' );

	/**
	 * Get estimated cost for an operation.
	 *
	 * @since 1.0.0
	 * @param string $operation Operation type (generate, edit, variations, etc.).
	 * @param array  $options   Operation options.
	 * @return float Estimated cost in USD.
	 */
	public function get_estimated_cost( $operation, array $options = array() );
}
