<?php
/**
 * WooCommerce Bookings Integration.
 *
 * Provides AI abilities for WooCommerce Bookings extension.
 * Detects WC Bookings installation and registers booking-specific abilities.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.1.0
 */

namespace Assistify_For_WooCommerce\Integrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Bookings Integration Class.
 *
 * @since 1.1.0
 */
class WC_Bookings_Integration {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var WC_Bookings_Integration|null
	 */
	private static $instance = null;

	/**
	 * Whether WC Bookings is active.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private $is_active = false;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.1.0
	 * @return WC_Bookings_Integration Instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		$this->is_active = $this->detect_wc_bookings();

		if ( $this->is_active ) {
			add_action( 'init', array( $this, 'register_booking_abilities' ), 15 );
		}
	}

	/**
	 * Detect if WooCommerce Bookings is installed and active.
	 *
	 * @since 1.1.0
	 * @return bool True if WC Bookings is active.
	 */
	private function detect_wc_bookings() {
		// Check if WC_Bookings class exists.
		if ( class_exists( 'WC_Bookings' ) ) {
			return true;
		}

		// Check if the function get_wc_booking exists.
		if ( function_exists( 'get_wc_booking' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if WC Bookings is active.
	 *
	 * @since 1.1.0
	 * @return bool True if active.
	 */
	public function is_active() {
		return $this->is_active;
	}

	/**
	 * Register booking abilities with the Abilities Registry.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_booking_abilities() {
		$registry = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();

		// Add bookings category.
		$registry->add_category( 'bookings', __( 'Bookings', 'assistify-for-woocommerce' ) );

		// Register admin abilities.
		$this->register_admin_abilities( $registry );

		// Register customer abilities.
		$this->register_customer_abilities( $registry );
	}

	/**
	 * Register admin booking abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_admin_abilities( $registry ) {
		// List bookings.
		$registry->register(
			'afw/bookings/list',
			array(
				'name'        => __( 'List Bookings', 'assistify-for-woocommerce' ),
				'description' => __( 'List all bookings with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_list' ),
				'parameters'  => array(
					'status'      => array(
						'type'        => 'string',
						'description' => __( 'Filter by status (unpaid, pending-confirmation, confirmed, paid, cancelled, complete).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'Filter by customer ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'product_id'  => array(
						'type'        => 'integer',
						'description' => __( 'Filter by bookable product ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'date_from'   => array(
						'type'        => 'string',
						'description' => __( 'Filter from date (Y-m-d format).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'date_to'     => array(
						'type'        => 'string',
						'description' => __( 'Filter to date (Y-m-d format).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'       => array(
						'type'        => 'integer',
						'description' => __( 'Number of bookings to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Get booking details.
		$registry->register(
			'afw/bookings/get',
			array(
				'name'        => __( 'Get Booking', 'assistify-for-woocommerce' ),
				'description' => __( 'Get detailed information about a specific booking.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_get' ),
				'parameters'  => array(
					'booking_id' => array(
						'type'        => 'integer',
						'description' => __( 'The booking ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Today's bookings.
		$registry->register(
			'afw/bookings/today',
			array(
				'name'        => __( 'Today\'s Bookings', 'assistify-for-woocommerce' ),
				'description' => __( 'Get all bookings scheduled for today.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_today' ),
				'parameters'  => array(
					'status' => array(
						'type'        => 'string',
						'description' => __( 'Filter by status.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Search bookings.
		$registry->register(
			'afw/bookings/search',
			array(
				'name'        => __( 'Search Bookings', 'assistify-for-woocommerce' ),
				'description' => __( 'Search bookings by customer name, email, or booking ID.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_search' ),
				'parameters'  => array(
					'query'  => array(
						'type'        => 'string',
						'description' => __( 'Search query (customer name, email, or booking ID).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status' => array(
						'type'        => 'string',
						'description' => __( 'Filter by status.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of results to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Booking analytics.
		$registry->register(
			'afw/bookings/analytics',
			array(
				'name'        => __( 'Booking Analytics', 'assistify-for-woocommerce' ),
				'description' => __( 'Get booking analytics including revenue, counts, and popular products.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_analytics' ),
				'parameters'  => array(
					'period' => array(
						'type'        => 'string',
						'description' => __( 'Time period for analytics (7days, 30days, 90days, year).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => '30days',
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Check availability.
		$registry->register(
			'afw/bookings/availability',
			array(
				'name'        => __( 'Check Availability', 'assistify-for-woocommerce' ),
				'description' => __( 'Check availability for a bookable product on a specific date.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_availability' ),
				'parameters'  => array(
					'product_id' => array(
						'type'        => 'integer',
						'description' => __( 'The bookable product ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'date'       => array(
						'type'        => 'string',
						'description' => __( 'Date to check (Y-m-d format).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Update booking status.
		$registry->register(
			'afw/bookings/update-status',
			array(
				'name'         => __( 'Update Booking Status', 'assistify-for-woocommerce' ),
				'description'  => __( 'Update the status of a booking.', 'assistify-for-woocommerce' ),
				'category'     => 'bookings',
				'callback'     => array( $this, 'ability_bookings_update_status' ),
				'parameters'   => array(
					'booking_id' => array(
						'type'        => 'integer',
						'description' => __( 'The booking ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status'     => array(
						'type'        => 'string',
						'description' => __( 'New status (confirmed, cancelled, complete).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'   => 'manage_woocommerce',
				'confirmation' => true,
			)
		);

		// Upcoming bookings.
		$registry->register(
			'afw/bookings/upcoming',
			array(
				'name'        => __( 'Upcoming Bookings', 'assistify-for-woocommerce' ),
				'description' => __( 'List upcoming bookings within a specified timeframe.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_upcoming' ),
				'parameters'  => array(
					'days'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of days ahead to check.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 7,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of bookings to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Resources list.
		$registry->register(
			'afw/bookings/resources',
			array(
				'name'        => __( 'List Resources', 'assistify-for-woocommerce' ),
				'description' => __( 'List all bookable resources.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_bookings_resources' ),
				'parameters'  => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of resources to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);
	}

	/**
	 * Register customer booking abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_customer_abilities( $registry ) {
		// Get customer's bookings.
		$registry->register(
			'afw/booking/my-bookings',
			array(
				'name'        => __( 'My Bookings', 'assistify-for-woocommerce' ),
				'description' => __( 'Get customer\'s own bookings.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_customer_bookings' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status'      => array(
						'type'        => 'string',
						'description' => __( 'Filter by status.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Get upcoming bookings.
		$registry->register(
			'afw/booking/upcoming',
			array(
				'name'        => __( 'My Upcoming Bookings', 'assistify-for-woocommerce' ),
				'description' => __( 'Get customer\'s upcoming bookings.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_customer_upcoming_bookings' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Get booking details.
		$registry->register(
			'afw/booking/get-details',
			array(
				'name'        => __( 'Get Booking Details', 'assistify-for-woocommerce' ),
				'description' => __( 'Get details of a specific booking.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_customer_booking_details' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'booking_id'  => array(
						'type'        => 'integer',
						'description' => __( 'The booking ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Cancel booking.
		$registry->register(
			'afw/booking/cancel',
			array(
				'name'         => __( 'Cancel Booking', 'assistify-for-woocommerce' ),
				'description'  => __( 'Cancel a booking.', 'assistify-for-woocommerce' ),
				'category'     => 'bookings',
				'callback'     => array( $this, 'ability_customer_cancel_booking' ),
				'parameters'   => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'booking_id'  => array(
						'type'        => 'integer',
						'description' => __( 'The booking ID to cancel.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'   => 'read',
				'scope'        => 'customer',
				'confirmation' => true,
			)
		);

		// Check availability for customer.
		$registry->register(
			'afw/booking/check-availability',
			array(
				'name'        => __( 'Check Availability', 'assistify-for-woocommerce' ),
				'description' => __( 'Check availability for a bookable service.', 'assistify-for-woocommerce' ),
				'category'    => 'bookings',
				'callback'    => array( $this, 'ability_customer_check_availability' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'product_id'  => array(
						'type'        => 'integer',
						'description' => __( 'The bookable product ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'date'        => array(
						'type'        => 'string',
						'description' => __( 'Date to check (Y-m-d or natural language).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);
	}

	// =========================================================================
	// ADMIN ABILITY CALLBACKS
	// =========================================================================

	/**
	 * List bookings with filters.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Booking list data.
	 */
	public function ability_bookings_list( $params ) {
		if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$query_args = array(
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Add status filter.
		if ( ! empty( $params['status'] ) ) {
			$query_args['post_status'] = sanitize_text_field( $params['status'] );
		}

		// Add customer filter.
		if ( ! empty( $params['customer_id'] ) ) {
			$query_args['meta_query'][] = array(
				'key'   => '_booking_customer_id',
				'value' => absint( $params['customer_id'] ),
			);
		}

		// Add product filter.
		if ( ! empty( $params['product_id'] ) ) {
			$query_args['meta_query'][] = array(
				'key'   => '_booking_product_id',
				'value' => absint( $params['product_id'] ),
			);
		}

		// Date range filter.
		if ( ! empty( $params['date_from'] ) || ! empty( $params['date_to'] ) ) {
			$date_from = ! empty( $params['date_from'] ) ? strtotime( $params['date_from'] ) : strtotime( 'today' );
			$date_to   = ! empty( $params['date_to'] ) ? strtotime( $params['date_to'] . ' 23:59:59' ) : strtotime( '+1 year' );

			$booking_ids = \WC_Booking_Data_Store::get_booking_ids_by(
				array(
					'date_after'  => $date_from,
					'date_before' => $date_to,
					'limit'       => $limit,
				)
			);

			$result = array();
			foreach ( $booking_ids as $booking_id ) {
				$booking = get_wc_booking( $booking_id );
				if ( $booking ) {
					$result[] = $this->format_booking_data( $booking );
				}
			}

			return array(
				'success'  => true,
				'bookings' => $result,
				'count'    => count( $result ),
			);
		}

		// Default query.
		$booking_ids = \WC_Booking_Data_Store::get_booking_ids_by(
			array(
				'status' => ! empty( $params['status'] ) ? array( sanitize_text_field( $params['status'] ) ) : get_wc_booking_statuses( 'user' ),
				'limit'  => $limit,
			)
		);

		$result = array();
		foreach ( $booking_ids as $booking_id ) {
			$booking = get_wc_booking( $booking_id );
			if ( $booking ) {
				// Apply customer filter manually if needed.
				if ( ! empty( $params['customer_id'] ) && $booking->get_customer_id() !== absint( $params['customer_id'] ) ) {
					continue;
				}
				// Apply product filter manually if needed.
				if ( ! empty( $params['product_id'] ) && $booking->get_product_id() !== absint( $params['product_id'] ) ) {
					continue;
				}
				$result[] = $this->format_booking_data( $booking );
			}
		}

		return array(
			'success'  => true,
			'bookings' => $result,
			'count'    => count( $result ),
			'filters'  => array(
				'status'      => $params['status'] ?? 'all',
				'customer_id' => $params['customer_id'] ?? null,
				'product_id'  => $params['product_id'] ?? null,
			),
		);
	}

	/**
	 * Get booking details.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Booking details.
	 */
	public function ability_bookings_get( $params ) {
		if ( ! function_exists( 'get_wc_booking' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$booking_id = absint( $params['booking_id'] );
		$booking    = get_wc_booking( $booking_id );

		if ( ! $booking ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success' => true,
			'booking' => $this->format_booking_data( $booking, true ),
		);
	}

	/**
	 * Get today's bookings.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Today's bookings.
	 */
	public function ability_bookings_today( $params ) {
		if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$today_start = strtotime( 'today midnight' );
		$today_end   = strtotime( 'tomorrow midnight' ) - 1;

		$status = array( 'confirmed', 'paid' );
		if ( ! empty( $params['status'] ) ) {
			$status = array( sanitize_text_field( $params['status'] ) );
		}

		$booking_ids = \WC_Booking_Data_Store::get_booking_ids_by(
			array(
				'status'      => $status,
				'date_after'  => $today_start,
				'date_before' => $today_end,
			)
		);

		$result = array();
		foreach ( $booking_ids as $booking_id ) {
			$booking = get_wc_booking( $booking_id );
			if ( $booking ) {
				$result[] = $this->format_booking_data( $booking );
			}
		}

		// Sort by start time.
		usort(
			$result,
			function ( $a, $b ) {
				return strtotime( $a['start'] ) <=> strtotime( $b['start'] );
			}
		);

		return array(
			'success'  => true,
			'bookings' => $result,
			'count'    => count( $result ),
			'date'     => wp_date( get_option( 'date_format' ) ),
		);
	}

	/**
	 * Search bookings.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Search results.
	 */
	public function ability_bookings_search( $params ) {
		if ( ! function_exists( 'get_wc_booking' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$query = sanitize_text_field( $params['query'] );
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$results = array();

		// Check if query is a booking ID.
		if ( is_numeric( $query ) ) {
			$booking = get_wc_booking( absint( $query ) );
			if ( $booking ) {
				$results[] = $this->format_booking_data( $booking );
			}
		}

		// Search by customer email.
		if ( is_email( $query ) ) {
			$user = get_user_by( 'email', $query );
			if ( $user ) {
				$booking_ids = \WC_Booking_Data_Store::get_bookings_for_user( $user->ID );
				foreach ( array_slice( $booking_ids, 0, $limit ) as $booking_id ) {
					$booking = get_wc_booking( $booking_id );
					if ( $booking ) {
						$results[] = $this->format_booking_data( $booking );
					}
				}
			}
		}

		// Search by customer name.
		if ( empty( $results ) && ! is_numeric( $query ) && ! is_email( $query ) ) {
			$users = get_users(
				array(
					'search'         => '*' . $query . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
					'number'         => 10,
				)
			);

			foreach ( $users as $user ) {
				$booking_ids = \WC_Booking_Data_Store::get_bookings_for_user( $user->ID );
				foreach ( $booking_ids as $booking_id ) {
					$booking = get_wc_booking( $booking_id );
					if ( $booking ) {
						$results[] = $this->format_booking_data( $booking );
					}
				}
			}
		}

		// Limit results.
		$results = array_slice( $results, 0, $limit );

		return array(
			'success'  => true,
			'bookings' => $results,
			'count'    => count( $results ),
			'query'    => $query,
		);
	}

	/**
	 * Get booking analytics.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Analytics data.
	 */
	public function ability_bookings_analytics( $params ) {
		if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$period = isset( $params['period'] ) ? sanitize_text_field( $params['period'] ) : '30days';

		// Calculate date range.
		$days = 30;
		switch ( $period ) {
			case '7days':
				$days = 7;
				break;
			case '90days':
				$days = 90;
				break;
			case 'year':
				$days = 365;
				break;
			default:
				$days = 30;
		}

		$date_from = strtotime( "-{$days} days" );
		$date_to   = time();

		// Get all bookings in period.
		$booking_ids = \WC_Booking_Data_Store::get_booking_ids_by(
			array(
				'date_after'  => $date_from,
				'date_before' => $date_to,
				'limit'       => -1,
			)
		);

		$total_revenue   = 0;
		$status_counts   = array();
		$product_counts  = array();
		$confirmed_count = 0;
		$cancelled_count = 0;

		foreach ( $booking_ids as $booking_id ) {
			$booking = get_wc_booking( $booking_id );
			if ( ! $booking ) {
				continue;
			}

			$status = $booking->get_status();

			// Count by status.
			if ( ! isset( $status_counts[ $status ] ) ) {
				$status_counts[ $status ] = 0;
			}
			++$status_counts[ $status ];

			// Count revenue (only for paid/complete).
			if ( in_array( $status, array( 'paid', 'complete' ), true ) ) {
				$total_revenue += floatval( $booking->get_cost() );
				++$confirmed_count;
			}

			if ( 'cancelled' === $status ) {
				++$cancelled_count;
			}

			// Count by product.
			$product_id = $booking->get_product_id();
			if ( ! isset( $product_counts[ $product_id ] ) ) {
				$product                       = wc_get_product( $product_id );
				$product_name                  = $product ? $product->get_name() : __( 'Unknown Product', 'assistify-for-woocommerce' );
				$product_counts[ $product_id ] = array(
					'id'    => $product_id,
					'name'  => $product_name,
					'count' => 0,
				);
			}
			++$product_counts[ $product_id ]['count'];
		}

		// Sort products by count.
		usort(
			$product_counts,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		// Top 5 products.
		$top_products = array_slice( $product_counts, 0, 5 );

		// Calculate cancellation rate.
		$total_bookings    = count( $booking_ids );
		$cancellation_rate = $total_bookings > 0 ? round( ( $cancelled_count / $total_bookings ) * 100, 2 ) : 0;

		// Average booking value.
		$avg_booking_value = $confirmed_count > 0 ? round( $total_revenue / $confirmed_count, 2 ) : 0;

		return array(
			'success'   => true,
			'period'    => $period,
			'analytics' => array(
				'total_bookings'     => $total_bookings,
				'confirmed_bookings' => $confirmed_count,
				'cancelled_bookings' => $cancelled_count,
				'total_revenue'      => array(
					'value'    => round( $total_revenue, 2 ),
					'currency' => get_woocommerce_currency(),
				),
				'avg_booking_value'  => array(
					'value'    => $avg_booking_value,
					'currency' => get_woocommerce_currency(),
				),
				'cancellation_rate'  => array(
					'value' => $cancellation_rate,
					'label' => __( 'Cancellation Rate', 'assistify-for-woocommerce' ),
				),
				'status_breakdown'   => $status_counts,
				'top_products'       => $top_products,
			),
		);
	}

	/**
	 * Check availability for a bookable product.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Availability data.
	 */
	public function ability_bookings_availability( $params ) {
		if ( ! function_exists( 'get_wc_product_booking' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$product_id = absint( $params['product_id'] );
		$date       = sanitize_text_field( $params['date'] );

		$product = get_wc_product_booking( $product_id );
		if ( ! $product ) {
			return array(
				'success' => false,
				'error'   => __( 'Bookable product not found.', 'assistify-for-woocommerce' ),
			);
		}

		$check_date = strtotime( $date );
		if ( ! $check_date ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format.', 'assistify-for-woocommerce' ),
			);
		}

		// Get existing bookings for that date.
		$day_start = strtotime( gmdate( 'Y-m-d', $check_date ) . ' 00:00:00' );
		$day_end   = strtotime( gmdate( 'Y-m-d', $check_date ) . ' 23:59:59' );

		$existing_bookings = $product->get_bookings_in_date_range( $day_start, $day_end );

		return array(
			'success'          => true,
			'product'          => array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			),
			'date'             => wp_date( get_option( 'date_format' ), $check_date ),
			'bookings_on_date' => count( $existing_bookings ),
			'is_bookable'      => $product->is_bookable(),
		);
	}

	/**
	 * Update booking status.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Update result.
	 */
	public function ability_bookings_update_status( $params ) {
		if ( ! function_exists( 'get_wc_booking' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$booking_id = absint( $params['booking_id'] );
		$new_status = sanitize_text_field( $params['status'] );

		$booking = get_wc_booking( $booking_id );

		if ( ! $booking ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found.', 'assistify-for-woocommerce' ),
			);
		}

		// Validate new status.
		$valid_statuses = array( 'unpaid', 'pending-confirmation', 'confirmed', 'paid', 'cancelled', 'complete' );
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid booking status.', 'assistify-for-woocommerce' ),
			);
		}

		$old_status = $booking->get_status();

		try {
			$booking->set_status( $new_status );
			$booking->save();

			return array(
				'success' => true,
				'message' => sprintf(
					// Translators: %1$s is the old status, %2$s is the new status.
					__( 'Booking status updated from %1$s to %2$s.', 'assistify-for-woocommerce' ),
					wc_bookings_get_status_label( $old_status ),
					wc_bookings_get_status_label( $new_status )
				),
				'booking' => $this->format_booking_data( $booking ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get upcoming bookings.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Upcoming bookings.
	 */
	public function ability_bookings_upcoming( $params ) {
		if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$days  = isset( $params['days'] ) ? absint( $params['days'] ) : 7;
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;

		$date_from = time();
		$date_to   = strtotime( "+{$days} days" );

		$booking_ids = \WC_Booking_Data_Store::get_booking_ids_by(
			array(
				'status'      => array( 'confirmed', 'paid' ),
				'date_after'  => $date_from,
				'date_before' => $date_to,
				'limit'       => $limit,
			)
		);

		$result = array();
		foreach ( $booking_ids as $booking_id ) {
			$booking = get_wc_booking( $booking_id );
			if ( $booking ) {
				$result[] = $this->format_booking_data( $booking );
			}
		}

		// Sort by start date.
		usort(
			$result,
			function ( $a, $b ) {
				return strtotime( $a['start'] ) <=> strtotime( $b['start'] );
			}
		);

		return array(
			'success'  => true,
			'bookings' => $result,
			'count'    => count( $result ),
			'days'     => $days,
		);
	}

	/**
	 * List bookable resources.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Resources list.
	 */
	public function ability_bookings_resources( $params ) {
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;

		$resources = get_posts(
			array(
				'post_type'      => 'bookable_resource',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
			)
		);

		$result = array();
		foreach ( $resources as $resource ) {
			$result[] = array(
				'id'   => $resource->ID,
				'name' => $resource->post_title,
				'url'  => admin_url( 'post.php?post=' . $resource->ID . '&action=edit' ),
			);
		}

		return array(
			'success'   => true,
			'resources' => $result,
			'count'     => count( $result ),
		);
	}

	// =========================================================================
	// CUSTOMER ABILITY CALLBACKS
	// =========================================================================

	/**
	 * Get customer's bookings.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Customer bookings.
	 */
	public function ability_customer_bookings( $params ) {
		if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		$booking_ids = \WC_Booking_Data_Store::get_bookings_for_user( $customer_id );
		$result      = array();

		foreach ( $booking_ids as $booking_id ) {
			$booking = get_wc_booking( $booking_id );
			if ( ! $booking ) {
				continue;
			}

			// Filter by status if provided.
			if ( ! empty( $params['status'] ) ) {
				$status = sanitize_text_field( $params['status'] );
				if ( $booking->get_status() !== $status ) {
					continue;
				}
			}

			$result[] = $this->format_booking_for_customer( $booking );
		}

		if ( empty( $result ) ) {
			return array(
				'success'  => true,
				'message'  => __( 'You do not have any bookings.', 'assistify-for-woocommerce' ),
				'bookings' => array(),
			);
		}

		return array(
			'success'  => true,
			'bookings' => $result,
			'count'    => count( $result ),
		);
	}

	/**
	 * Get customer's upcoming bookings.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Upcoming bookings.
	 */
	public function ability_customer_upcoming_bookings( $params ) {
		if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		$booking_ids = \WC_Booking_Data_Store::get_bookings_for_user( $customer_id );
		$result      = array();
		$now         = time();

		foreach ( $booking_ids as $booking_id ) {
			$booking = get_wc_booking( $booking_id );
			if ( ! $booking ) {
				continue;
			}

			// Only include upcoming bookings (start time in the future).
			if ( $booking->get_start() > $now && in_array( $booking->get_status(), array( 'confirmed', 'paid' ), true ) ) {
				$result[] = $this->format_booking_for_customer( $booking );
			}
		}

		// Sort by start date.
		usort(
			$result,
			function ( $a, $b ) {
				return strtotime( $a['start_raw'] ) <=> strtotime( $b['start_raw'] );
			}
		);

		if ( empty( $result ) ) {
			return array(
				'success'  => true,
				'message'  => __( 'You do not have any upcoming bookings.', 'assistify-for-woocommerce' ),
				'bookings' => array(),
			);
		}

		return array(
			'success'  => true,
			'bookings' => $result,
			'count'    => count( $result ),
		);
	}

	/**
	 * Get customer booking details.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Booking details.
	 */
	public function ability_customer_booking_details( $params ) {
		if ( ! function_exists( 'get_wc_booking' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		$booking_id  = absint( $params['booking_id'] );

		$booking = get_wc_booking( $booking_id );

		if ( ! $booking || $booking->get_customer_id() !== $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found or does not belong to you.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success' => true,
			'booking' => $this->format_booking_for_customer( $booking, true ),
		);
	}

	/**
	 * Cancel customer booking.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Cancel result.
	 */
	public function ability_customer_cancel_booking( $params ) {
		if ( ! function_exists( 'get_wc_booking' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Bookings is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		$booking_id  = absint( $params['booking_id'] );

		$booking = get_wc_booking( $booking_id );

		if ( ! $booking || $booking->get_customer_id() !== $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found or does not belong to you.', 'assistify-for-woocommerce' ),
			);
		}

		// Check if booking can be cancelled.
		$cancellable_statuses = get_wc_booking_statuses( 'cancel' );
		if ( ! in_array( $booking->get_status(), $cancellable_statuses, true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This booking cannot be cancelled.', 'assistify-for-woocommerce' ),
			);
		}

		try {
			$booking->set_status( 'cancelled' );
			$booking->save();

			return array(
				'success' => true,
				'message' => __( 'Your booking has been cancelled.', 'assistify-for-woocommerce' ),
				'booking' => $this->format_booking_for_customer( $booking ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Check availability for customer.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Availability info.
	 */
	public function ability_customer_check_availability( $params ) {
		// Get all bookable products if no product ID specified.
		if ( empty( $params['product_id'] ) ) {
			$bookable_products = wc_get_products(
				array(
					'type'   => 'booking',
					'status' => 'publish',
					'limit'  => 10,
				)
			);

			$products = array();
			foreach ( $bookable_products as $product ) {
				$products[] = array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'url'  => $product->get_permalink(),
				);
			}

			return array(
				'success'  => true,
				'message'  => __( 'Here are our bookable services. Please specify which one you\'d like to check availability for.', 'assistify-for-woocommerce' ),
				'products' => $products,
			);
		}

		$product_id = absint( $params['product_id'] );
		$product    = get_wc_product_booking( $product_id );

		if ( ! $product ) {
			return array(
				'success' => false,
				'error'   => __( 'Bookable product not found.', 'assistify-for-woocommerce' ),
			);
		}

		// If no date specified, return general availability.
		if ( empty( $params['date'] ) ) {
			return array(
				'success' => true,
				'product' => array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'url'  => $product->get_permalink(),
				),
				'message' => __( 'Please specify a date to check availability.', 'assistify-for-woocommerce' ),
			);
		}

		$check_date = strtotime( sanitize_text_field( $params['date'] ) );
		if ( ! $check_date ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format.', 'assistify-for-woocommerce' ),
			);
		}

		// Get existing bookings for that date.
		$day_start = strtotime( gmdate( 'Y-m-d', $check_date ) . ' 00:00:00' );
		$day_end   = strtotime( gmdate( 'Y-m-d', $check_date ) . ' 23:59:59' );

		$existing_bookings = $product->get_bookings_in_date_range( $day_start, $day_end );

		return array(
			'success'           => true,
			'product'           => array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'url'  => $product->get_permalink(),
			),
			'date'              => wp_date( get_option( 'date_format' ), $check_date ),
			'existing_bookings' => count( $existing_bookings ),
			'booking_url'       => $product->get_permalink(),
		);
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Format booking data for response.
	 *
	 * @since 1.1.0
	 * @param object $booking  The WC_Booking object.
	 * @param bool   $detailed Whether to include detailed information.
	 * @return array Formatted booking data.
	 */
	private function format_booking_data( $booking, $detailed = false ) {
		$product      = wc_get_product( $booking->get_product_id() );
		$product_name = $product ? $product->get_name() : __( 'Unknown Product', 'assistify-for-woocommerce' );

		$customer_id = $booking->get_customer_id();
		$customer    = $customer_id ? get_user_by( 'id', $customer_id ) : null;

		$data = array(
			'id'             => $booking->get_id(),
			'status'         => $booking->get_status(),
			'status_label'   => wc_bookings_get_status_label( $booking->get_status() ),
			'customer'       => array(
				'id'    => $customer_id,
				'name'  => $customer ? $customer->display_name : __( 'Guest', 'assistify-for-woocommerce' ),
				'email' => $customer ? $customer->user_email : '',
			),
			'product'        => array(
				'id'   => $booking->get_product_id(),
				'name' => $product_name,
			),
			'start'          => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->get_start() ),
			'end'            => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->get_end() ),
			'all_day'        => $booking->get_all_day(),
			'cost'           => $booking->get_cost(),
			'cost_formatted' => wc_price( $booking->get_cost() ),
		);

		if ( $detailed ) {
			$data['order_id']      = $booking->get_order_id();
			$data['resource_id']   = $booking->get_resource_id();
			$data['person_counts'] = $booking->get_person_counts();
			$data['date_created']  = $booking->get_date_created() ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->get_date_created() ) : '';
			$data['admin_url']     = admin_url( 'post.php?post=' . $booking->get_id() . '&action=edit' );

			// Get resource name if exists.
			if ( $booking->get_resource_id() ) {
				$resource         = get_post( $booking->get_resource_id() );
				$data['resource'] = $resource ? $resource->post_title : '';
			}
		}

		return $data;
	}

	/**
	 * Format booking data for customer response (limited info).
	 *
	 * @since 1.1.0
	 * @param object $booking  The WC_Booking object.
	 * @param bool   $detailed Whether to include detailed information.
	 * @return array Formatted booking data for customer.
	 */
	private function format_booking_for_customer( $booking, $detailed = false ) {
		$product      = wc_get_product( $booking->get_product_id() );
		$product_name = $product ? $product->get_name() : __( 'Unknown Product', 'assistify-for-woocommerce' );

		$data = array(
			'id'           => $booking->get_id(),
			'status'       => $booking->get_status(),
			'status_label' => wc_bookings_get_status_label( $booking->get_status() ),
			'product'      => $product_name,
			'start'        => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->get_start() ),
			'end'          => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->get_end() ),
			'start_raw'    => gmdate( 'Y-m-d H:i:s', $booking->get_start() ),
			'all_day'      => $booking->get_all_day(),
			'cost'         => wc_price( $booking->get_cost() ),
			'can_cancel'   => in_array( $booking->get_status(), get_wc_booking_statuses( 'cancel' ), true ),
		);

		if ( $detailed ) {
			$data['order_id'] = $booking->get_order_id();

			// Get resource name if exists.
			if ( $booking->get_resource_id() ) {
				$resource         = get_post( $booking->get_resource_id() );
				$data['resource'] = $resource ? $resource->post_title : '';
			}

			// Get person counts.
			$person_counts = $booking->get_person_counts();
			if ( ! empty( $person_counts ) ) {
				$data['persons'] = array_sum( $person_counts );
			}
		}

		return $data;
	}
}
