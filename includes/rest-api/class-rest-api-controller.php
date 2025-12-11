<?php
/**
 * REST API Controller Base Class.
 *
 * Base class for all REST API controllers.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce\REST_API;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Controller Base Class.
 *
 * @since 1.0.0
 */
class REST_API_Controller extends \WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'assistify/v1';

	/**
	 * The base of this controller's route.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Check if the current user can perform admin actions.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if can, WP_Error otherwise.
	 */
	public function admin_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'assistify_rest_forbidden',
				__( 'Sorry, you are not allowed to access this resource.', 'assistify-for-woocommerce' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Check if the current user can perform customer actions.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if can, WP_Error otherwise.
	 */
	public function customer_permissions_check( $request ) {
		// Allow logged-in customers or guests with valid nonce.
		if ( is_user_logged_in() ) {
			return true;
		}

		// Check for guest access nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'assistify_rest_forbidden',
				__( 'Invalid nonce provided.', 'assistify-for-woocommerce' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Sanitize messages array from request.
	 *
	 * @since 1.0.0
	 * @param array $messages Messages array.
	 * @return array Sanitized messages array.
	 */
	protected function sanitize_messages( $messages ) {
		if ( ! is_array( $messages ) ) {
			return array();
		}

		$sanitized     = array();
		$allowed_roles = array( 'user', 'assistant', 'system' );

		foreach ( $messages as $message ) {
			if ( ! isset( $message['role'] ) || ! isset( $message['content'] ) ) {
				continue;
			}

			$role = sanitize_text_field( $message['role'] );
			if ( ! in_array( $role, $allowed_roles, true ) ) {
				continue;
			}

			$sanitized[] = array(
				'role'    => $role,
				'content' => sanitize_textarea_field( $message['content'] ),
			);
		}

		return $sanitized;
	}

	/**
	 * Get rate limit for current user context.
	 *
	 * @since 1.0.0
	 * @return array Rate limit configuration.
	 */
	protected function get_rate_limit() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'limit'    => 100,
				'burst'    => 150,
				'cooldown' => 60,
			);
		}

		if ( is_user_logged_in() ) {
			return array(
				'limit'    => 20,
				'burst'    => 30,
				'cooldown' => 60,
			);
		}

		return array(
			'limit'    => 5,
			'burst'    => 10,
			'cooldown' => 120,
		);
	}

	/**
	 * Check rate limit for current user.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error True if within limits, WP_Error otherwise.
	 */
	protected function check_rate_limit() {
		$rate_limit = $this->get_rate_limit();
		$user_id    = get_current_user_id();
		$ip_address = $this->get_client_ip();

		// Create rate limit key.
		$key           = $user_id > 0 ? "user_{$user_id}" : "ip_{$ip_address}";
		$transient_key = 'assistify_rate_' . md5( $key );

		// Get current count.
		$current = get_transient( $transient_key );
		if ( false === $current ) {
			$current = 0;
		}

		// Check if over limit.
		if ( $current >= $rate_limit['burst'] ) {
			return new \WP_Error(
				'assistify_rate_limit_exceeded',
				sprintf(
					/* translators: %d: cooldown seconds. */
					__( 'Rate limit exceeded. Please try again in %d seconds.', 'assistify-for-woocommerce' ),
					$rate_limit['cooldown']
				),
				array( 'status' => 429 )
			);
		}

		// Increment counter.
		set_transient( $transient_key, $current + 1, $rate_limit['cooldown'] );

		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 * @return string Client IP address.
	 */
	protected function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			// Get first IP if multiple.
			$ip = explode( ',', $ip )[0];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return trim( $ip );
	}
}
