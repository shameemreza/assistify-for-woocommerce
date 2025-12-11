<?php
/**
 * Remove.bg Provider Class.
 *
 * Handles background removal using the remove.bg API.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 * @see     https://www.remove.bg/api
 */

namespace Assistify_For_WooCommerce\Image_Providers;

use Assistify_For_WooCommerce\Assistify_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove.bg Provider Class.
 *
 * @since 1.0.0
 */
class RemoveBG_Provider {

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_base_url = 'https://api.remove.bg/v1.0';

	/**
	 * API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $api_key Optional. API key.
	 */
	public function __construct( $api_key = '' ) {
		$this->api_key = ! empty( $api_key ) ? $api_key : $this->get_stored_api_key();
	}

	/**
	 * Get stored API key.
	 *
	 * @since 1.0.0
	 * @return string API key.
	 */
	private function get_stored_api_key() {
		return get_option( 'assistify_removebg_api_key', '' );
	}

	/**
	 * Check if configured.
	 *
	 * @since 1.0.0
	 * @return bool True if configured.
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Remove background from an image.
	 *
	 * @since 1.0.0
	 * @param string $image_source Image URL or file path.
	 * @param array  $options      Optional. Processing options.
	 * @return array|\WP_Error Result array with image data or WP_Error.
	 */
	public function remove_background( $image_source, $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Remove.bg is not configured. Please add your API key in settings.', 'assistify-for-woocommerce' )
			);
		}

		$defaults = array(
			'size'       => 'auto',
			'type'       => 'auto',
			'format'     => 'png',
			'bg_color'   => '',           // Hex color code for background (empty for transparent).
			'add_shadow' => false,        // Add shadow to the result.
			'crop'       => false,        // Crop result to foreground.
		);

		$options = wp_parse_args( $options, $defaults );

		// Build form data.
		$boundary   = wp_generate_password( 24, false );
		$body       = '';
		$image_data = null;
		$filename   = 'image.png';
		$mime_type  = 'image/png';

		// Handle image source (URL or file path).
		if ( filter_var( $image_source, FILTER_VALIDATE_URL ) ) {
			// Try to convert URL to local file path (for local/dev environments).
			$upload_dir  = wp_upload_dir();
			$upload_url  = $upload_dir['baseurl'];
			$upload_path = $upload_dir['basedir'];

			// Check if URL is from our uploads directory.
			if ( strpos( $image_source, $upload_url ) === 0 ) {
				// Convert URL to file path.
				$file_path = str_replace( $upload_url, $upload_path, $image_source );

				if ( file_exists( $file_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$image_data   = file_get_contents( $file_path );
					$filename     = basename( $file_path );
					$filetype_arr = wp_check_filetype( $filename );
					$mime_type    = ! empty( $filetype_arr['type'] ) ? $filetype_arr['type'] : 'image/png';
					Assistify_Logger::debug( 'Loaded from local file: ' . $file_path, 'removebg' );
				}
			}

			// If not loaded from local file, try attachment ID lookup.
			if ( empty( $image_data ) ) {
				$attachment_id = attachment_url_to_postid( $image_source );
				if ( $attachment_id ) {
					$file_path = get_attached_file( $attachment_id );
					if ( $file_path && file_exists( $file_path ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						$image_data   = file_get_contents( $file_path );
						$filename     = basename( $file_path );
						$filetype_arr = wp_check_filetype( $filename );
						$mime_type    = ! empty( $filetype_arr['type'] ) ? $filetype_arr['type'] : 'image/png';
						Assistify_Logger::debug( 'Loaded from attachment ID ' . $attachment_id, 'removebg' );
					}
				}
			}

			// Fallback: try direct download (for external URLs).
			if ( empty( $image_data ) ) {
				Assistify_Logger::debug( 'Attempting remote download: ' . $image_source, 'removebg' );
				$download_response = wp_remote_get(
					$image_source,
					array(
						'timeout' => 60,
					)
				);

				if ( is_wp_error( $download_response ) ) {
					Assistify_Logger::error( 'Download failed: ' . $download_response->get_error_message(), 'removebg' );
					return new \WP_Error(
						'download_failed',
						__( 'Failed to download image for processing. If using local development, ensure the image URL is accessible.', 'assistify-for-woocommerce' )
					);
				}

				$response_code = wp_remote_retrieve_response_code( $download_response );
				if ( 200 !== $response_code ) {
					Assistify_Logger::error( 'Download failed with code ' . $response_code, 'removebg' );
					return new \WP_Error(
						'download_failed',
						__( 'Failed to download image. Server returned error.', 'assistify-for-woocommerce' )
					);
				}

				$image_data  = wp_remote_retrieve_body( $download_response );
				$parsed_path = wp_parse_url( $image_source, PHP_URL_PATH );
				$filename    = ! empty( $parsed_path ) ? basename( $parsed_path ) : 'image.png';

				// Get mime type from response headers.
				$content_type = wp_remote_retrieve_header( $download_response, 'content-type' );
				if ( $content_type ) {
					$mime_type = explode( ';', $content_type )[0];
				}
			}
		} else {
			// File path - read directly.
			if ( ! file_exists( $image_source ) ) {
				return new \WP_Error( 'file_not_found', __( 'Image file not found.', 'assistify-for-woocommerce' ) );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$image_data   = file_get_contents( $image_source );
			$filename     = basename( $image_source );
			$filetype_arr = wp_check_filetype( $filename );
			$mime_type    = ! empty( $filetype_arr['type'] ) ? $filetype_arr['type'] : 'image/png';
		}

		if ( empty( $image_data ) ) {
			return new \WP_Error( 'no_image_data', __( 'Could not read image data.', 'assistify-for-woocommerce' ) );
		}

		// Add image as file upload (not URL - URLs may not be publicly accessible).
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"image_file\"; filename=\"{$filename}\"\r\n";
		$body .= "Content-Type: {$mime_type}\r\n\r\n";
		$body .= $image_data . "\r\n";

		// Add size.
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"size\"\r\n\r\n";
		$body .= $options['size'] . "\r\n";

		// Add type.
		if ( 'auto' !== $options['type'] ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"type\"\r\n\r\n";
			$body .= $options['type'] . "\r\n";
		}

		// Add format.
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"format\"\r\n\r\n";
		$body .= $options['format'] . "\r\n";

		// Add background color if specified.
		if ( ! empty( $options['bg_color'] ) ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"bg_color\"\r\n\r\n";
			$body .= $options['bg_color'] . "\r\n";
		}

		// Add shadow.
		if ( $options['add_shadow'] ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"shadow_type\"\r\n\r\n";
			$body .= "drop\r\n";
		}

		// Add crop.
		if ( $options['crop'] ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"crop\"\r\n\r\n";
			$body .= "true\r\n";
		}

		$body .= "--{$boundary}--\r\n";

		// Make API request.
		$response = wp_remote_post(
			$this->api_base_url . '/removebg',
			array(
				'timeout' => 120,
				'headers' => array(
					'X-Api-Key'    => $this->api_key,
					'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_message = isset( $response_body['errors'][0]['title'] )
				? $response_body['errors'][0]['title']
				: sprintf(
					/* translators: %d: HTTP response code */
					__( 'Remove.bg API error: %d', 'assistify-for-woocommerce' ),
					$response_code
				);

			return new \WP_Error( 'api_error', $error_message );
		}

		// Get credits information from headers.
		$credits_charged = wp_remote_retrieve_header( $response, 'x-credits-charged' );

		return array(
			'success'         => true,
			'image_data'      => wp_remote_retrieve_body( $response ),
			'mime_type'       => 'image/' . $options['format'],
			'credits_charged' => $credits_charged,
		);
	}

	/**
	 * Get account info including credit balance.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error Account info or WP_Error.
	 */
	public function get_account_info() {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'not_configured', __( 'Remove.bg is not configured.', 'assistify-for-woocommerce' ) );
		}

		$response = wp_remote_get(
			$this->api_base_url . '/account',
			array(
				'timeout' => 30,
				'headers' => array(
					'X-Api-Key' => $this->api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			return new \WP_Error( 'api_error', __( 'Failed to get account info.', 'assistify-for-woocommerce' ) );
		}

		return array(
			'success'              => true,
			'credits_total'        => isset( $response_body['data']['attributes']['credits']['total'] ) ? $response_body['data']['attributes']['credits']['total'] : 0,
			'credits_subscription' => isset( $response_body['data']['attributes']['credits']['subscription'] ) ? $response_body['data']['attributes']['credits']['subscription'] : 0,
			'credits_payg'         => isset( $response_body['data']['attributes']['credits']['payg'] ) ? $response_body['data']['attributes']['credits']['payg'] : 0,
			'credits_enterprise'   => isset( $response_body['data']['attributes']['credits']['enterprise'] ) ? $response_body['data']['attributes']['credits']['enterprise'] : 0,
			'free_calls'           => isset( $response_body['data']['attributes']['api']['free_calls'] ) ? $response_body['data']['attributes']['api']['free_calls'] : 0,
			'sizes'                => isset( $response_body['data']['attributes']['api']['sizes'] ) ? $response_body['data']['attributes']['api']['sizes'] : '',
		);
	}

	/**
	 * Validate API key.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_api_key() {
		$account_info = $this->get_account_info();

		if ( is_wp_error( $account_info ) ) {
			return $account_info;
		}

		return true;
	}
}
