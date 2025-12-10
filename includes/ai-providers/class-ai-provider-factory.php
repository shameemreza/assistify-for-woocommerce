<?php
/**
 * AI Provider Factory Class.
 *
 * Factory pattern for creating AI provider instances.
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
 * AI Provider Factory Class.
 *
 * @since 1.0.0
 */
class AI_Provider_Factory {

	/**
	 * Registered providers.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $providers = array(
		'openai'    => AI_Provider_OpenAI::class,
		'anthropic' => AI_Provider_Anthropic::class,
		'google'    => AI_Provider_Google::class,
		'xai'       => AI_Provider_XAI::class,
		'deepseek'  => AI_Provider_DeepSeek::class,
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
	 * @return AI_Provider_Interface|\WP_Error Provider instance or WP_Error.
	 */
	public static function create( $provider_id, $api_key = '' ) {
		// Check if provider exists.
		if ( ! isset( self::$providers[ $provider_id ] ) ) {
			return new \WP_Error(
				'assistify_invalid_provider',
				sprintf(
					/* translators: %s: Provider ID. */
					__( 'Invalid AI provider: %s', 'assistify-for-woocommerce' ),
					$provider_id
				)
			);
		}

		// Create cache key based on provider and API key.
		$cache_key = $provider_id . '_' . md5( $api_key );

		// Return cached instance if available.
		if ( isset( self::$instances[ $cache_key ] ) ) {
			return self::$instances[ $cache_key ];
		}

		// Create new instance.
		$class_name = self::$providers[ $provider_id ];
		$instance   = new $class_name( $api_key );

		// Cache the instance.
		self::$instances[ $cache_key ] = $instance;

		return $instance;
	}

	/**
	 * Get the currently configured provider.
	 *
	 * @since 1.0.0
	 * @return AI_Provider_Interface|\WP_Error Provider instance or WP_Error.
	 */
	public static function get_configured_provider() {
		$provider_id = get_option( 'assistify_ai_provider', 'openai' );
		$api_key     = self::get_api_key_for_provider( $provider_id );

		return self::create( $provider_id, $api_key );
	}

	/**
	 * Get API key for a specific provider.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID.
	 * @return string API key or empty string.
	 */
	public static function get_api_key_for_provider( $provider_id ) {
		$option_key = 'assistify_' . $provider_id . '_api_key';
		$api_key    = get_option( $option_key, '' );

		// Decrypt if needed.
		if ( ! empty( $api_key ) ) {
			$api_key = self::decrypt_api_key( $api_key );
		}

		return $api_key;
	}

	/**
	 * Save API key for a provider.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID.
	 * @param string $api_key     API key to save.
	 * @return bool True on success, false on failure.
	 */
	public static function save_api_key( $provider_id, $api_key ) {
		$option_key    = 'assistify_' . $provider_id . '_api_key';
		$encrypted_key = self::encrypt_api_key( $api_key );

		return update_option( $option_key, $encrypted_key );
	}

	/**
	 * Encrypt an API key for storage.
	 *
	 * @since 1.0.0
	 * @param string $api_key API key to encrypt.
	 * @return string Encrypted API key.
	 */
	private static function encrypt_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		// Use WordPress salt for encryption.
		$key = wp_salt( 'auth' );

		// Simple XOR encryption with base64 encoding.
		// For production, consider using OpenSSL or Sodium.
		$encrypted = '';
		$key_len   = strlen( $key );

		for ( $i = 0, $len = strlen( $api_key ); $i < $len; $i++ ) {
			$encrypted .= $api_key[ $i ] ^ $key[ $i % $key_len ];
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $encrypted );
	}

	/**
	 * Decrypt an API key from storage.
	 *
	 * @since 1.0.0
	 * @param string $encrypted_key Encrypted API key.
	 * @return string Decrypted API key.
	 */
	private static function decrypt_api_key( $encrypted_key ) {
		if ( empty( $encrypted_key ) ) {
			return '';
		}

		// Use WordPress salt for decryption.
		$key = wp_salt( 'auth' );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$encrypted = base64_decode( $encrypted_key );

		if ( false === $encrypted ) {
			return '';
		}

		// XOR decryption (same as encryption).
		$decrypted = '';
		$key_len   = strlen( $key );

		for ( $i = 0, $len = strlen( $encrypted ); $i < $len; $i++ ) {
			$decrypted .= $encrypted[ $i ] ^ $key[ $i % $key_len ];
		}

		return $decrypted;
	}

	/**
	 * Get all available providers.
	 *
	 * @since 1.0.0
	 * @return array Array of provider info with id, name, and configured status.
	 */
	public static function get_available_providers() {
		$providers = array();

		foreach ( self::$providers as $provider_id => $class_name ) {
			$instance    = self::create( $provider_id );
			$providers[] = array(
				'id'         => $provider_id,
				'name'       => is_wp_error( $instance ) ? $provider_id : $instance->get_name(),
				'configured' => ! is_wp_error( $instance ) && $instance->is_configured(),
			);
		}

		return $providers;
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
	 * Validate a provider's API key.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider ID.
	 * @param string $api_key     API key to validate.
	 * @return bool|\WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_api_key( $provider_id, $api_key ) {
		$provider = self::create( $provider_id, $api_key );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		return $provider->validate_api_key();
	}
}

