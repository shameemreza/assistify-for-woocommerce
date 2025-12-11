<?php
/**
 * Image Provider Factory Class.
 *
 * Factory pattern for creating image generation provider instances.
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
 * Image Provider Factory Class.
 *
 * @since 1.0.0
 */
class Image_Provider_Factory {

	/**
	 * Registered providers.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $providers = array(
		'openai' => Image_Provider_OpenAI::class,
		'google' => Image_Provider_Google::class,
		'xai'    => Image_Provider_XAI::class,
	);

	/**
	 * Cached provider instances.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Create or get a provider instance.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID.
	 * @param string $api_key     Optional. API key to use.
	 * @return Image_Provider_Interface|\WP_Error Provider instance or WP_Error.
	 */
	public static function create( $provider_id, $api_key = '' ) {
		// Check if provider exists.
		if ( ! isset( self::$providers[ $provider_id ] ) ) {
			return new \WP_Error(
				'assistify_invalid_image_provider',
				sprintf(
					/* translators: %s: Provider ID. */
					__( 'Invalid image provider: %s', 'assistify-for-woocommerce' ),
					$provider_id
				)
			);
		}

		// Create cache key based on provider and API key.
		$cache_key = $provider_id . '_image_' . md5( $api_key );

		// Return cached instance if available.
		if ( isset( self::$instances[ $cache_key ] ) ) {
			return self::$instances[ $cache_key ];
		}

		// Create new instance.
		$class_name = self::$providers[ $provider_id ];

		// Check if class exists.
		if ( ! class_exists( $class_name ) ) {
			return new \WP_Error(
				'assistify_class_not_found',
				sprintf(
					/* translators: %s: Provider ID. */
					__( 'Image provider class not found for: %s', 'assistify-for-woocommerce' ),
					$provider_id
				)
			);
		}

		$instance = new $class_name( $api_key );

		// Cache the instance.
		self::$instances[ $cache_key ] = $instance;

		return $instance;
	}

	/**
	 * Get the currently configured image provider.
	 *
	 * Determines the provider based on the selected image model.
	 *
	 * @since 1.0.0
	 * @return Image_Provider_Interface|\WP_Error Provider instance or WP_Error.
	 */
	public static function get_configured_provider() {
		$provider_id = self::get_provider_from_model();

		// Get API key for the selected provider.
		$api_key = self::get_api_key_for_provider( $provider_id );

		return self::create( $provider_id, $api_key );
	}

	/**
	 * Determine the image provider from the selected image model.
	 *
	 * @since 1.0.0
	 * @return string Provider ID.
	 */
	public static function get_provider_from_model() {
		$model = get_option( 'assistify_image_model', 'gpt-image-1' );

		// Map models to providers.
		if ( strpos( $model, 'gpt-image' ) === 0 || strpos( $model, 'dall-e' ) === 0 ) {
			return 'openai';
		}

		if ( strpos( $model, 'imagen' ) === 0 ) {
			return 'google';
		}

		if ( strpos( $model, 'grok' ) === 0 ) {
			return 'xai';
		}

		// Fallback to main AI provider (for providers without image support).
		return get_option( 'assistify_ai_provider', 'openai' );
	}

	/**
	 * Get API key for a specific provider.
	 *
	 * Uses the same API key as the text provider (shared per provider).
	 * Falls back to the generic API key if the provider matches the main selected provider.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID.
	 * @return string API key or empty string.
	 */
	public static function get_api_key_for_provider( $provider_id ) {
		// Use the AI Provider Factory to get the API key (which handles fallback).
		if ( class_exists( '\Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory' ) ) {
			return \Assistify_For_WooCommerce\AI_Providers\AI_Provider_Factory::get_api_key_for_provider( $provider_id );
		}

		// Fallback: Try provider-specific key first.
		$option_key = 'assistify_' . $provider_id . '_api_key';
		$api_key    = get_option( $option_key, '' );

		if ( ! empty( $api_key ) ) {
			return $api_key;
		}

		// Fallback: Use generic API key if provider matches main provider.
		$main_provider = get_option( 'assistify_ai_provider', 'openai' );

		if ( $provider_id === $main_provider ) {
			return get_option( 'assistify_api_key', '' );
		}

		return '';
	}

	/**
	 * Get all available image providers.
	 *
	 * @since 1.0.0
	 * @return array Array of provider info with id, name, configured status, and capabilities.
	 */
	public static function get_available_providers() {
		$providers = array();

		foreach ( self::$providers as $provider_id => $class_name ) {
			$instance = self::create( $provider_id );

			if ( is_wp_error( $instance ) ) {
				continue;
			}

			$providers[] = array(
				'id'           => $provider_id,
				'name'         => $instance->get_name(),
				'configured'   => $instance->is_configured(),
				'capabilities' => array(
					'generate'          => $instance->supports( 'generate' ),
					'edit'              => $instance->supports( 'edit' ),
					'variations'        => $instance->supports( 'variations' ),
					'remove_background' => $instance->supports( 'remove_background' ),
					'upscale'           => $instance->supports( 'upscale' ),
				),
				'models'       => $instance->get_available_models(),
			);
		}

		return $providers;
	}

	/**
	 * Get all available image models organized by provider.
	 *
	 * @since 1.0.0
	 * @return array Array of models by provider.
	 */
	public static function get_models_by_provider() {
		$models_by_provider = array();

		foreach ( self::$providers as $provider_id => $class_name ) {
			$instance = self::create( $provider_id );

			if ( is_wp_error( $instance ) ) {
				continue;
			}

			$available_models = $instance->get_available_models();
			$flat_models      = array();

			foreach ( $available_models as $model_id => $model_info ) {
				$flat_models[ $model_id ] = is_array( $model_info ) ? $model_info['name'] : $model_info;
			}

			$models_by_provider[ $provider_id ] = $flat_models;
		}

		return $models_by_provider;
	}

	/**
	 * Get default models for each provider.
	 *
	 * @since 1.0.0
	 * @return array Default model for each provider.
	 */
	public static function get_default_models() {
		$defaults = array();

		foreach ( self::$providers as $provider_id => $class_name ) {
			$instance = self::create( $provider_id );

			if ( ! is_wp_error( $instance ) ) {
				$defaults[ $provider_id ] = $instance->get_default_model();
			}
		}

		return $defaults;
	}

	/**
	 * Register a custom provider.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID.
	 * @param string $class_name  Fully qualified class name.
	 * @return bool True on success, false if provider already exists.
	 */
	public static function register_provider( $provider_id, $class_name ) {
		if ( isset( self::$providers[ $provider_id ] ) ) {
			return false;
		}

		self::$providers[ $provider_id ] = $class_name;

		return true;
	}

	/**
	 * Get configured image model.
	 *
	 * @since 1.0.0
	 * @return string Model ID.
	 */
	public static function get_configured_model() {
		$model = get_option( 'assistify_image_model', '' );

		if ( empty( $model ) ) {
			$provider_id = self::get_provider_from_model();
			$provider    = self::create( $provider_id );
			if ( ! is_wp_error( $provider ) ) {
				$model = $provider->get_default_model();
			}
		}

		return $model;
	}

	/**
	 * Get configured image size.
	 *
	 * @since 1.0.0
	 * @return string Size in format WIDTHxHEIGHT.
	 */
	public static function get_configured_size() {
		return get_option( 'assistify_image_size', '1024x1024' );
	}

	/**
	 * Get configured image quality.
	 *
	 * @since 1.0.0
	 * @return string Quality setting (standard, hd, auto).
	 */
	public static function get_configured_quality() {
		return get_option( 'assistify_image_quality', 'auto' );
	}

	/**
	 * Get configured image style.
	 *
	 * @since 1.0.0
	 * @return string Style setting (natural, vivid).
	 */
	public static function get_configured_style() {
		return get_option( 'assistify_image_style', 'natural' );
	}
}
