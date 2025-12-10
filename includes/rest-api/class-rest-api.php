<?php
/**
 * REST API Main Class.
 *
 * Initializes and registers all REST API controllers.
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
 * REST API Main Class.
 *
 * @since 1.0.0
 */
class REST_API {

	/**
	 * REST API controllers.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $controllers = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_controllers();
	}

	/**
	 * Initialize REST API controllers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_controllers() {
		$this->controllers = array(
			'chat' => new REST_Chat_Controller(),
		);

		/**
		 * Filter REST API controllers.
		 *
		 * Allows adding additional controllers.
		 *
		 * @since 1.0.0
		 * @param array $controllers Array of controller instances.
		 */
		$this->controllers = apply_filters( 'assistify_rest_api_controllers', $this->controllers );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Get a specific controller.
	 *
	 * @since 1.0.0
	 * @param string $name Controller name.
	 * @return REST_API_Controller|null Controller instance or null.
	 */
	public function get_controller( $name ) {
		return isset( $this->controllers[ $name ] ) ? $this->controllers[ $name ] : null;
	}
}

