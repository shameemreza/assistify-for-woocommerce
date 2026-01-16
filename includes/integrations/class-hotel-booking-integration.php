<?php
/**
 * Hotel Booking for WooCommerce Integration.
 *
 * Provides AI abilities for Hotel Booking for WooCommerce extension.
 * Detects Hotel Booking installation and registers accommodation-specific abilities.
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
 * Hotel Booking Integration Class.
 *
 * @since 1.1.0
 */
class Hotel_Booking_Integration {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var Hotel_Booking_Integration|null
	 */
	private static $instance = null;

	/**
	 * Whether Hotel Booking is active.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private $is_active = false;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.1.0
	 * @return Hotel_Booking_Integration Instance.
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
		$this->is_active = $this->detect_hotel_booking();

		if ( $this->is_active ) {
			add_action( 'init', array( $this, 'register_hotel_abilities' ), 15 );
		}
	}

	/**
	 * Detect if Hotel Booking for WooCommerce is installed and active.
	 *
	 * @since 1.1.0
	 * @return bool True if Hotel Booking is active.
	 */
	private function detect_hotel_booking() {
		// Check if HBFWC constants are defined.
		if ( defined( 'HBFWC_VERSION' ) ) {
			return true;
		}

		// Check if the main function exists.
		if ( function_exists( 'HBFWC\\HBF_WC' ) ) {
			return true;
		}

		// Check if the product class exists.
		if ( class_exists( 'HBFWC_WC_Product_Accommodation' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Hotel Booking is active.
	 *
	 * @since 1.1.0
	 * @return bool True if active.
	 */
	public function is_active() {
		return $this->is_active;
	}

	/**
	 * Register hotel abilities with the Abilities Registry.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_hotel_abilities() {
		$registry = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();

		// Add accommodations category.
		$registry->add_category( 'accommodations', __( 'Accommodations', 'assistify-for-woocommerce' ) );

		// Register admin abilities.
		$this->register_admin_abilities( $registry );

		// Register customer abilities.
		$this->register_customer_abilities( $registry );
	}

	/**
	 * Register admin hotel abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_admin_abilities( $registry ) {
		// List rooms.
		$registry->register(
			'afw/hotel/rooms/list',
			array(
				'name'        => __( 'List Rooms', 'assistify-for-woocommerce' ),
				'description' => __( 'List all accommodation/room products.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_rooms_list' ),
				'parameters'  => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of rooms to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Get room details.
		$registry->register(
			'afw/hotel/rooms/get',
			array(
				'name'        => __( 'Get Room Details', 'assistify-for-woocommerce' ),
				'description' => __( 'Get detailed information about a specific room.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_rooms_get' ),
				'parameters'  => array(
					'room_id' => array(
						'type'        => 'integer',
						'description' => __( 'The room/product ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Check availability.
		$registry->register(
			'afw/hotel/availability/check',
			array(
				'name'        => __( 'Check Room Availability', 'assistify-for-woocommerce' ),
				'description' => __( 'Check room availability for a date range.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_availability_check' ),
				'parameters'  => array(
					'room_id'   => array(
						'type'        => 'integer',
						'description' => __( 'The room/product ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_in'  => array(
						'type'        => 'string',
						'description' => __( 'Check-in date (Y-m-d format).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_out' => array(
						'type'        => 'string',
						'description' => __( 'Check-out date (Y-m-d format).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// List reservations.
		$registry->register(
			'afw/hotel/reservations/list',
			array(
				'name'        => __( 'List Reservations', 'assistify-for-woocommerce' ),
				'description' => __( 'List hotel reservations/bookings.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_reservations_list' ),
				'parameters'  => array(
					'status'    => array(
						'type'        => 'string',
						'description' => __( 'Filter by order status (processing, completed, on-hold).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'date_from' => array(
						'type'        => 'string',
						'description' => __( 'Filter by check-in from date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'date_to'   => array(
						'type'        => 'string',
						'description' => __( 'Filter by check-in to date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'     => array(
						'type'        => 'integer',
						'description' => __( 'Number of reservations to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Today's check-ins and check-outs.
		$registry->register(
			'afw/hotel/reservations/today',
			array(
				'name'        => __( 'Today\'s Check-ins/Check-outs', 'assistify-for-woocommerce' ),
				'description' => __( 'Get today\'s arrivals and departures.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_reservations_today' ),
				'parameters'  => array(),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Upcoming reservations.
		$registry->register(
			'afw/hotel/reservations/upcoming',
			array(
				'name'        => __( 'Upcoming Reservations', 'assistify-for-woocommerce' ),
				'description' => __( 'List upcoming hotel reservations.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_reservations_upcoming' ),
				'parameters'  => array(
					'days'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of days ahead to check.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 7,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of reservations to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Get reservation details.
		$registry->register(
			'afw/hotel/reservations/get',
			array(
				'name'        => __( 'Get Reservation Details', 'assistify-for-woocommerce' ),
				'description' => __( 'Get detailed information about a specific reservation.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_reservations_get' ),
				'parameters'  => array(
					'order_id' => array(
						'type'        => 'integer',
						'description' => __( 'The order ID containing the reservation.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Hotel analytics.
		$registry->register(
			'afw/hotel/analytics',
			array(
				'name'        => __( 'Hotel Analytics', 'assistify-for-woocommerce' ),
				'description' => __( 'Get hotel booking analytics including occupancy and revenue.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_hotel_analytics' ),
				'parameters'  => array(
					'period' => array(
						'type'        => 'string',
						'description' => __( 'Time period (7days, 30days, 90days, year).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => '30days',
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// List rate plans.
		$registry->register(
			'afw/hotel/rates/list',
			array(
				'name'        => __( 'List Rate Plans', 'assistify-for-woocommerce' ),
				'description' => __( 'List all configured rate plans.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_rates_list' ),
				'parameters'  => array(),
				'capability'  => 'manage_woocommerce',
			)
		);
	}

	/**
	 * Register customer hotel abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_customer_abilities( $registry ) {
		// Search available rooms.
		$registry->register(
			'afw/hotel/search',
			array(
				'name'        => __( 'Search Available Rooms', 'assistify-for-woocommerce' ),
				'description' => __( 'Search for available rooms based on dates and guests.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_customer_search' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'check_in'    => array(
						'type'        => 'string',
						'description' => __( 'Check-in date (Y-m-d or natural language).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_out'   => array(
						'type'        => 'string',
						'description' => __( 'Check-out date (Y-m-d or natural language).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'adults'      => array(
						'type'        => 'integer',
						'description' => __( 'Number of adults.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 1,
					),
					'children'    => array(
						'type'        => 'integer',
						'description' => __( 'Number of children.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 0,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Get room information.
		$registry->register(
			'afw/hotel/room-details',
			array(
				'name'        => __( 'Get Room Information', 'assistify-for-woocommerce' ),
				'description' => __( 'Get details about a specific room including amenities and pricing.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_customer_room_details' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'room_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The room/product ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Check room availability for customer.
		$registry->register(
			'afw/hotel/check-availability',
			array(
				'name'        => __( 'Check Room Availability', 'assistify-for-woocommerce' ),
				'description' => __( 'Check if a specific room is available for given dates.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_customer_check_availability' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'room_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The room/product ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_in'    => array(
						'type'        => 'string',
						'description' => __( 'Check-in date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_out'   => array(
						'type'        => 'string',
						'description' => __( 'Check-out date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Get pricing.
		$registry->register(
			'afw/hotel/get-pricing',
			array(
				'name'        => __( 'Get Room Pricing', 'assistify-for-woocommerce' ),
				'description' => __( 'Get pricing for a room for specific dates.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_customer_get_pricing' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'room_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The room/product ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_in'    => array(
						'type'        => 'string',
						'description' => __( 'Check-in date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_out'   => array(
						'type'        => 'string',
						'description' => __( 'Check-out date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'rooms'       => array(
						'type'        => 'integer',
						'description' => __( 'Number of rooms.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 1,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// My reservations.
		$registry->register(
			'afw/hotel/my-reservations',
			array(
				'name'        => __( 'My Reservations', 'assistify-for-woocommerce' ),
				'description' => __( 'Get customer\'s hotel reservations.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_customer_my_reservations' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status'      => array(
						'type'        => 'string',
						'description' => __( 'Filter by status (upcoming, past, all).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'all',
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Suggest alternatives.
		$registry->register(
			'afw/hotel/suggest-alternatives',
			array(
				'name'        => __( 'Suggest Alternative Rooms', 'assistify-for-woocommerce' ),
				'description' => __( 'Suggest alternative rooms or dates when requested dates are unavailable.', 'assistify-for-woocommerce' ),
				'category'    => 'accommodations',
				'callback'    => array( $this, 'ability_customer_suggest_alternatives' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'room_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The originally requested room ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'check_in'    => array(
						'type'        => 'string',
						'description' => __( 'Requested check-in date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'check_out'   => array(
						'type'        => 'string',
						'description' => __( 'Requested check-out date (Y-m-d).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'adults'      => array(
						'type'        => 'integer',
						'description' => __( 'Number of adults.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 1,
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
	 * List all accommodation rooms.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Room list data.
	 */
	public function ability_rooms_list( $params ) {
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$args = array(
			'type'   => 'accommodation',
			'status' => 'publish',
			'limit'  => $limit,
		);

		$products = wc_get_products( $args );

		if ( empty( $products ) ) {
			return array(
				'success' => true,
				'message' => __( 'No accommodation rooms found.', 'assistify-for-woocommerce' ),
				'rooms'   => array(),
			);
		}

		$rooms = array();
		foreach ( $products as $product ) {
			$rooms[] = $this->format_room_data( $product );
		}

		return array(
			'success' => true,
			'rooms'   => $rooms,
			'count'   => count( $rooms ),
		);
	}

	/**
	 * Get room details.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Room details.
	 */
	public function ability_rooms_get( $params ) {
		$room_id = absint( $params['room_id'] );
		$product = wc_get_product( $room_id );

		if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
			return array(
				'success' => false,
				'error'   => __( 'Room not found or is not an accommodation product.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success' => true,
			'room'    => $this->format_room_data( $product, true ),
		);
	}

	/**
	 * Check room availability for date range.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Availability data.
	 */
	public function ability_availability_check( $params ) {
		$room_id   = absint( $params['room_id'] );
		$check_in  = sanitize_text_field( $params['check_in'] );
		$check_out = sanitize_text_field( $params['check_out'] );

		$product = wc_get_product( $room_id );

		if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
			return array(
				'success' => false,
				'error'   => __( 'Room not found or is not an accommodation product.', 'assistify-for-woocommerce' ),
			);
		}

		// Validate dates.
		$check_in_ts  = strtotime( $check_in );
		$check_out_ts = strtotime( $check_out );

		if ( ! $check_in_ts || ! $check_out_ts ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format. Please use Y-m-d format.', 'assistify-for-woocommerce' ),
			);
		}

		if ( $check_out_ts <= $check_in_ts ) {
			return array(
				'success' => false,
				'error'   => __( 'Check-out date must be after check-in date.', 'assistify-for-woocommerce' ),
			);
		}

		// Get availability data.
		if ( function_exists( 'hbfwc_get_availibility' ) ) {
			$availability = hbfwc_get_availibility( $room_id, $check_in, $check_out );
		} else {
			return array(
				'success' => false,
				'error'   => __( 'Hotel Booking functions not available.', 'assistify-for-woocommerce' ),
			);
		}

		// Calculate nights.
		$nights = floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS );

		// Check minimum quantity available across all dates.
		$min_available = PHP_INT_MAX;
		$unavailable_dates = array();

		foreach ( $availability as $day_data ) {
			$qty = isset( $day_data['qty'] ) ? absint( $day_data['qty'] ) : 0;
			$status = isset( $day_data['status'] ) ? absint( $day_data['status'] ) : 1;

			if ( 0 === $status || 0 === $qty ) {
				$unavailable_dates[] = isset( $day_data['booking_date'] ) ? $day_data['booking_date'] : '';
				$min_available = 0;
			} elseif ( $qty < $min_available ) {
				$min_available = $qty;
			}
		}

		$is_available = $min_available > 0;

		return array(
			'success'           => true,
			'room'              => array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			),
			'check_in'          => wp_date( get_option( 'date_format' ), $check_in_ts ),
			'check_out'         => wp_date( get_option( 'date_format' ), $check_out_ts ),
			'nights'            => $nights,
			'is_available'      => $is_available,
			'rooms_available'   => $is_available ? $min_available : 0,
			'unavailable_dates' => $unavailable_dates,
		);
	}

	/**
	 * List hotel reservations.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Reservations list.
	 */
	public function ability_reservations_list( $params ) {
		$limit     = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;
		$status    = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '';
		$date_from = isset( $params['date_from'] ) ? sanitize_text_field( $params['date_from'] ) : '';
		$date_to   = isset( $params['date_to'] ) ? sanitize_text_field( $params['date_to'] ) : '';

		$order_statuses = $status ? array( 'wc-' . $status ) : array( 'wc-processing', 'wc-completed', 'wc-on-hold' );

		$orders = wc_get_orders(
			array(
				'limit'    => $limit * 3, // Get more to filter.
				'status'   => $order_statuses,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$reservations = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();

				if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
					continue;
				}

				$booking_date = $item->get_meta( 'booking_date' );
				if ( empty( $booking_date ) ) {
					continue;
				}

				$booking_data = json_decode( $booking_date, true );
				if ( ! $booking_data ) {
					continue;
				}

				$check_in_ts = isset( $booking_data['check_in'] ) ? absint( $booking_data['check_in'] ) : 0;

				// Apply date filters.
				if ( $date_from ) {
					$from_ts = strtotime( $date_from );
					if ( $check_in_ts < $from_ts ) {
						continue;
					}
				}

				if ( $date_to ) {
					$to_ts = strtotime( $date_to . ' 23:59:59' );
					if ( $check_in_ts > $to_ts ) {
						continue;
					}
				}

				$reservations[] = $this->format_reservation_data( $order, $item, $booking_data );

				if ( count( $reservations ) >= $limit ) {
					break 2;
				}
			}
		}

		if ( empty( $reservations ) ) {
			return array(
				'success'      => true,
				'message'      => __( 'No reservations found.', 'assistify-for-woocommerce' ),
				'reservations' => array(),
			);
		}

		return array(
			'success'      => true,
			'reservations' => $reservations,
			'count'        => count( $reservations ),
		);
	}

	/**
	 * Get today's check-ins and check-outs.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Today's arrivals and departures.
	 */
	public function ability_reservations_today( $params ) {
		$today_start = strtotime( 'today midnight' );
		$today_end   = strtotime( 'tomorrow midnight' ) - 1;

		$orders = wc_get_orders(
			array(
				'limit'  => 100,
				'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			)
		);

		$check_ins  = array();
		$check_outs = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();

				if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
					continue;
				}

				$booking_date = $item->get_meta( 'booking_date' );
				if ( empty( $booking_date ) ) {
					continue;
				}

				$booking_data = json_decode( $booking_date, true );
				if ( ! $booking_data ) {
					continue;
				}

				$check_in_ts  = isset( $booking_data['check_in'] ) ? absint( $booking_data['check_in'] ) : 0;
				$check_out_ts = isset( $booking_data['check_out'] ) ? absint( $booking_data['check_out'] ) : 0;

				// Check if check-in is today.
				if ( $check_in_ts >= $today_start && $check_in_ts <= $today_end ) {
					$check_ins[] = $this->format_reservation_data( $order, $item, $booking_data );
				}

				// Check if check-out is today.
				if ( $check_out_ts >= $today_start && $check_out_ts <= $today_end ) {
					$check_outs[] = $this->format_reservation_data( $order, $item, $booking_data );
				}
			}
		}

		return array(
			'success'    => true,
			'date'       => wp_date( get_option( 'date_format' ) ),
			'check_ins'  => array(
				'count'        => count( $check_ins ),
				'reservations' => $check_ins,
			),
			'check_outs' => array(
				'count'        => count( $check_outs ),
				'reservations' => $check_outs,
			),
		);
	}

	/**
	 * Get upcoming reservations.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Upcoming reservations.
	 */
	public function ability_reservations_upcoming( $params ) {
		$days  = isset( $params['days'] ) ? absint( $params['days'] ) : 7;
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;

		$today_start = strtotime( 'today midnight' );
		$end_date    = strtotime( "+{$days} days" );

		$orders = wc_get_orders(
			array(
				'limit'  => 200,
				'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			)
		);

		$reservations = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();

				if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
					continue;
				}

				$booking_date = $item->get_meta( 'booking_date' );
				if ( empty( $booking_date ) ) {
					continue;
				}

				$booking_data = json_decode( $booking_date, true );
				if ( ! $booking_data ) {
					continue;
				}

				$check_in_ts = isset( $booking_data['check_in'] ) ? absint( $booking_data['check_in'] ) : 0;

				// Check if check-in is within the upcoming period.
				if ( $check_in_ts >= $today_start && $check_in_ts <= $end_date ) {
					$reservations[] = array_merge(
						$this->format_reservation_data( $order, $item, $booking_data ),
						array( 'check_in_timestamp' => $check_in_ts )
					);
				}
			}
		}

		// Sort by check-in date.
		usort(
			$reservations,
			function ( $a, $b ) {
				return $a['check_in_timestamp'] <=> $b['check_in_timestamp'];
			}
		);

		// Limit and remove timestamp.
		$reservations = array_slice( $reservations, 0, $limit );
		foreach ( $reservations as &$res ) {
			unset( $res['check_in_timestamp'] );
		}

		return array(
			'success'      => true,
			'days'         => $days,
			'reservations' => $reservations,
			'count'        => count( $reservations ),
		);
	}

	/**
	 * Get reservation details.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Reservation details.
	 */
	public function ability_reservations_get( $params ) {
		$order_id = absint( $params['order_id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'success' => false,
				'error'   => __( 'Order not found.', 'assistify-for-woocommerce' ),
			);
		}

		$reservations = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
				continue;
			}

			$booking_date = $item->get_meta( 'booking_date' );
			if ( empty( $booking_date ) ) {
				continue;
			}

			$booking_data = json_decode( $booking_date, true );
			if ( ! $booking_data ) {
				continue;
			}

			$reservations[] = $this->format_reservation_data( $order, $item, $booking_data, true );
		}

		if ( empty( $reservations ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No accommodation bookings found in this order.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success'      => true,
			'order_id'     => $order_id,
			'order_status' => $order->get_status(),
			'reservations' => $reservations,
		);
	}

	/**
	 * Get hotel analytics.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Analytics data.
	 */
	public function ability_hotel_analytics( $params ) {
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

		// Get all rooms.
		$rooms = wc_get_products(
			array(
				'type'   => 'accommodation',
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		$total_rooms     = count( $rooms );
		$total_inventory = 0;

		foreach ( $rooms as $room ) {
			$allotment = $room->get_meta( '_allotment' );
			$total_inventory += $allotment ? absint( $allotment ) : 0;
		}

		// Get orders with accommodation items.
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array( 'wc-processing', 'wc-completed' ),
				'date_after' => gmdate( 'Y-m-d', $date_from ),
			)
		);

		$total_revenue       = 0;
		$total_reservations  = 0;
		$total_nights_booked = 0;
		$room_bookings       = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();

				if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
					continue;
				}

				$booking_date = $item->get_meta( 'booking_date' );
				if ( empty( $booking_date ) ) {
					continue;
				}

				$booking_data = json_decode( $booking_date, true );
				if ( ! $booking_data ) {
					continue;
				}

				$check_in_ts  = isset( $booking_data['check_in'] ) ? absint( $booking_data['check_in'] ) : 0;
				$check_out_ts = isset( $booking_data['check_out'] ) ? absint( $booking_data['check_out'] ) : 0;

				if ( $check_in_ts < $date_from ) {
					continue;
				}

				++$total_reservations;
				$total_revenue += floatval( $item->get_total() );

				// Calculate nights.
				if ( $check_in_ts && $check_out_ts ) {
					$nights = floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS );
					$total_nights_booked += absint( $nights ) * absint( $item->get_quantity() );
				}

				// Track by room.
				$room_id = $product->get_id();
				if ( ! isset( $room_bookings[ $room_id ] ) ) {
					$room_bookings[ $room_id ] = array(
						'id'    => $room_id,
						'name'  => $product->get_name(),
						'count' => 0,
					);
				}
				++$room_bookings[ $room_id ]['count'];
			}
		}

		// Calculate occupancy rate.
		$total_possible_nights = $total_inventory * $days;
		$occupancy_rate        = $total_possible_nights > 0
			? round( ( $total_nights_booked / $total_possible_nights ) * 100, 2 )
			: 0;

		// Average booking value.
		$avg_booking_value = $total_reservations > 0
			? round( $total_revenue / $total_reservations, 2 )
			: 0;

		// Sort rooms by popularity.
		usort(
			$room_bookings,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		$popular_rooms = array_slice( array_values( $room_bookings ), 0, 5 );

		return array(
			'success'   => true,
			'period'    => $period,
			'analytics' => array(
				'total_rooms'        => $total_rooms,
				'total_inventory'    => $total_inventory,
				'total_reservations' => $total_reservations,
				'total_revenue'      => array(
					'value'     => round( $total_revenue, 2 ),
					'currency'  => get_woocommerce_currency(),
					'formatted' => wc_price( $total_revenue ),
				),
				'avg_booking_value'  => array(
					'value'     => $avg_booking_value,
					'currency'  => get_woocommerce_currency(),
					'formatted' => wc_price( $avg_booking_value ),
				),
				'occupancy_rate'     => array(
					'value' => $occupancy_rate,
					'label' => __( 'Occupancy Rate', 'assistify-for-woocommerce' ),
				),
				'nights_booked'      => $total_nights_booked,
				'popular_rooms'      => $popular_rooms,
			),
		);
	}

	/**
	 * List rate plans.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Rate plans list.
	 */
	public function ability_rates_list( $params ) {
		if ( ! function_exists( 'hbfwc_get_rateplans' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Hotel Booking functions not available.', 'assistify-for-woocommerce' ),
			);
		}

		$rate_plans   = hbfwc_get_rateplans();
		$default_rate = function_exists( 'hbfwc_get_default_rate' ) ? hbfwc_get_default_rate() : null;

		$rates = array();
		foreach ( $rate_plans as $rate ) {
			$is_default = $default_rate && $default_rate->term_id === $rate->term_id;

			$rates[] = array(
				'id'          => $rate->term_id,
				'name'        => $rate->name,
				'slug'        => $rate->slug,
				'description' => $rate->description,
				'is_default'  => $is_default,
			);
		}

		return array(
			'success' => true,
			'rates'   => $rates,
			'count'   => count( $rates ),
		);
	}

	// =========================================================================
	// CUSTOMER ABILITY CALLBACKS
	// =========================================================================

	/**
	 * Search available rooms for customer.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Available rooms.
	 */
	public function ability_customer_search( $params ) {
		$check_in  = sanitize_text_field( $params['check_in'] );
		$check_out = sanitize_text_field( $params['check_out'] );
		$adults    = isset( $params['adults'] ) ? absint( $params['adults'] ) : 1;
		$children  = isset( $params['children'] ) ? absint( $params['children'] ) : 0;

		// Parse dates.
		$check_in_ts  = strtotime( $check_in );
		$check_out_ts = strtotime( $check_out );

		if ( ! $check_in_ts || ! $check_out_ts ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format. Please specify check-in and check-out dates.', 'assistify-for-woocommerce' ),
			);
		}

		if ( $check_out_ts <= $check_in_ts ) {
			return array(
				'success' => false,
				'error'   => __( 'Check-out date must be after check-in date.', 'assistify-for-woocommerce' ),
			);
		}

		if ( $check_in_ts < strtotime( 'today' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Check-in date cannot be in the past.', 'assistify-for-woocommerce' ),
			);
		}

		// Get all accommodation products.
		$rooms = wc_get_products(
			array(
				'type'   => 'accommodation',
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		$available_rooms = array();
		$nights          = floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS );

		foreach ( $rooms as $room ) {
			// Check guest capacity.
			$max_adults   = $room->get_meta( '_max_adults' ) ? absint( $room->get_meta( '_max_adults' ) ) : 99;
			$max_children = $room->get_meta( '_max_children' ) ? absint( $room->get_meta( '_max_children' ) ) : 99;

			if ( $adults > $max_adults || $children > $max_children ) {
				continue;
			}

			// Check minimum stay.
			$min_stay = method_exists( $room, 'get_minimum_stay' ) ? $room->get_minimum_stay() : 1;
			if ( $nights < $min_stay ) {
				continue;
			}

			// Check availability.
			if ( function_exists( 'hbfwc_get_availibility' ) ) {
				$availability = hbfwc_get_availibility(
					$room->get_id(),
					gmdate( 'Y-m-d', $check_in_ts ),
					gmdate( 'Y-m-d', $check_out_ts )
				);

				$is_available = true;
				$rooms_available = PHP_INT_MAX;

				foreach ( $availability as $day_data ) {
					$qty    = isset( $day_data['qty'] ) ? absint( $day_data['qty'] ) : 0;
					$status = isset( $day_data['status'] ) ? absint( $day_data['status'] ) : 1;

					if ( 0 === $status || 0 === $qty ) {
						$is_available = false;
						break;
					}

					if ( $qty < $rooms_available ) {
						$rooms_available = $qty;
					}
				}

				if ( ! $is_available ) {
					continue;
				}
			}

			// Calculate pricing.
			$total_price = 0;
			if ( method_exists( $room, 'get_cart_price' ) ) {
				$default_rate = function_exists( 'hbfwc_get_default_rate' ) ? hbfwc_get_default_rate() : null;
				if ( $default_rate ) {
					$total_price = $room->get_cart_price( $check_in_ts, $check_out_ts, $default_rate->term_id );
				}
			}

			$available_rooms[] = array(
				'id'              => $room->get_id(),
				'name'            => $room->get_name(),
				'max_adults'      => $max_adults,
				'max_children'    => $max_children,
				'rooms_available' => $rooms_available,
				'price_per_stay'  => array(
					'value'     => round( floatval( $total_price ), 2 ),
					'formatted' => wc_price( $total_price ),
				),
				'url'             => $room->get_permalink(),
				'image'           => wp_get_attachment_url( $room->get_image_id() ),
			);
		}

		if ( empty( $available_rooms ) ) {
			return array(
				'success'    => true,
				'message'    => __( 'No rooms available for the selected dates and guest count. Would you like me to suggest alternatives?', 'assistify-for-woocommerce' ),
				'rooms'      => array(),
				'search'     => array(
					'check_in'  => wp_date( get_option( 'date_format' ), $check_in_ts ),
					'check_out' => wp_date( get_option( 'date_format' ), $check_out_ts ),
					'nights'    => $nights,
					'adults'    => $adults,
					'children'  => $children,
				),
			);
		}

		// Sort by price.
		usort(
			$available_rooms,
			function ( $a, $b ) {
				return $a['price_per_stay']['value'] <=> $b['price_per_stay']['value'];
			}
		);

		return array(
			'success' => true,
			'rooms'   => $available_rooms,
			'count'   => count( $available_rooms ),
			'search'  => array(
				'check_in'  => wp_date( get_option( 'date_format' ), $check_in_ts ),
				'check_out' => wp_date( get_option( 'date_format' ), $check_out_ts ),
				'nights'    => $nights,
				'adults'    => $adults,
				'children'  => $children,
			),
		);
	}

	/**
	 * Get room details for customer.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Room details.
	 */
	public function ability_customer_room_details( $params ) {
		$room_id = absint( $params['room_id'] );
		$product = wc_get_product( $room_id );

		if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
			return array(
				'success' => false,
				'error'   => __( 'Room not found.', 'assistify-for-woocommerce' ),
			);
		}

		// Get amenities.
		$amenities = array();
		if ( function_exists( 'hbfwc_get_amenities_group' ) ) {
			$groups = hbfwc_get_amenities_group();
			foreach ( $groups as $group_key => $group_name ) {
				if ( function_exists( 'hbfwc_get_post_amenities_by_group' ) ) {
					$group_amenities = hbfwc_get_post_amenities_by_group( $room_id, $group_key );
					if ( ! empty( $group_amenities ) && ! is_wp_error( $group_amenities ) ) {
						foreach ( $group_amenities as $amenity ) {
							$amenities[] = $amenity->name;
						}
					}
				}
			}
		}

		// Get basic info.
		$beds      = $product->get_meta( '_no_of_beds' );
		$room_size = $product->get_meta( '_room_size' );

		// Get pricing.
		$prices = array();
		if ( method_exists( $product, 'get_accommodation_prices' ) ) {
			$price_data = $product->get_accommodation_prices();
			if ( ! empty( $price_data['price'] ) ) {
				$min_price = min( $price_data['price'] );
				$prices['from'] = array(
					'value'     => round( floatval( $min_price ), 2 ),
					'formatted' => wc_price( $min_price ),
				);
			}
		}

		// Get gallery images.
		$gallery = array();
		$gallery_ids = $product->get_gallery_image_ids();
		foreach ( $gallery_ids as $image_id ) {
			$gallery[] = wp_get_attachment_url( $image_id );
		}

		return array(
			'success' => true,
			'room'    => array(
				'id'           => $product->get_id(),
				'name'         => $product->get_name(),
				'description'  => $product->get_short_description() ?: wp_trim_words( $product->get_description(), 50 ),
				'max_adults'   => $product->get_meta( '_max_adults' ) ? absint( $product->get_meta( '_max_adults' ) ) : null,
				'max_children' => $product->get_meta( '_max_children' ) ? absint( $product->get_meta( '_max_children' ) ) : null,
				'beds'         => $beds ?: null,
				'room_size'    => $room_size ?: null,
				'min_stay'     => method_exists( $product, 'get_minimum_stay' ) ? $product->get_minimum_stay() : 1,
				'amenities'    => $amenities,
				'pricing'      => $prices,
				'image'        => wp_get_attachment_url( $product->get_image_id() ),
				'gallery'      => $gallery,
				'url'          => $product->get_permalink(),
			),
		);
	}

	/**
	 * Check room availability for customer.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Availability info.
	 */
	public function ability_customer_check_availability( $params ) {
		$room_id   = absint( $params['room_id'] );
		$check_in  = sanitize_text_field( $params['check_in'] );
		$check_out = sanitize_text_field( $params['check_out'] );

		$product = wc_get_product( $room_id );

		if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
			return array(
				'success' => false,
				'error'   => __( 'Room not found.', 'assistify-for-woocommerce' ),
			);
		}

		$check_in_ts  = strtotime( $check_in );
		$check_out_ts = strtotime( $check_out );

		if ( ! $check_in_ts || ! $check_out_ts ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format.', 'assistify-for-woocommerce' ),
			);
		}

		$nights = floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS );

		// Check minimum stay.
		$min_stay = method_exists( $product, 'get_minimum_stay' ) ? $product->get_minimum_stay() : 1;
		if ( $nights < $min_stay ) {
			return array(
				'success'      => true,
				'is_available' => false,
				'reason'       => sprintf(
					/* translators: %d: minimum nights required. */
					__( 'This room requires a minimum stay of %d nights.', 'assistify-for-woocommerce' ),
					$min_stay
				),
				'room'         => array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
				),
			);
		}

		// Check availability.
		$is_available    = true;
		$rooms_available = 0;

		if ( function_exists( 'hbfwc_get_availibility' ) ) {
			$availability = hbfwc_get_availibility(
				$room_id,
				gmdate( 'Y-m-d', $check_in_ts ),
				gmdate( 'Y-m-d', $check_out_ts )
			);

			$rooms_available = PHP_INT_MAX;

			foreach ( $availability as $day_data ) {
				$qty    = isset( $day_data['qty'] ) ? absint( $day_data['qty'] ) : 0;
				$status = isset( $day_data['status'] ) ? absint( $day_data['status'] ) : 1;

				if ( 0 === $status || 0 === $qty ) {
					$is_available = false;
					$rooms_available = 0;
					break;
				}

				if ( $qty < $rooms_available ) {
					$rooms_available = $qty;
				}
			}
		}

		// Calculate price if available.
		$total_price = 0;
		if ( $is_available && method_exists( $product, 'get_cart_price' ) ) {
			$default_rate = function_exists( 'hbfwc_get_default_rate' ) ? hbfwc_get_default_rate() : null;
			if ( $default_rate ) {
				$total_price = $product->get_cart_price( $check_in_ts, $check_out_ts, $default_rate->term_id );
			}
		}

		return array(
			'success'         => true,
			'is_available'    => $is_available,
			'rooms_available' => $rooms_available,
			'room'            => array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'url'  => $product->get_permalink(),
			),
			'dates'           => array(
				'check_in'  => wp_date( get_option( 'date_format' ), $check_in_ts ),
				'check_out' => wp_date( get_option( 'date_format' ), $check_out_ts ),
				'nights'    => $nights,
			),
			'pricing'         => $is_available ? array(
				'total'     => round( floatval( $total_price ), 2 ),
				'formatted' => wc_price( $total_price ),
				'per_night' => wc_price( $total_price / max( 1, $nights ) ),
			) : null,
		);
	}

	/**
	 * Get pricing for customer.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Pricing info.
	 */
	public function ability_customer_get_pricing( $params ) {
		$room_id   = absint( $params['room_id'] );
		$check_in  = sanitize_text_field( $params['check_in'] );
		$check_out = sanitize_text_field( $params['check_out'] );
		$rooms     = isset( $params['rooms'] ) ? absint( $params['rooms'] ) : 1;

		$product = wc_get_product( $room_id );

		if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
			return array(
				'success' => false,
				'error'   => __( 'Room not found.', 'assistify-for-woocommerce' ),
			);
		}

		$check_in_ts  = strtotime( $check_in );
		$check_out_ts = strtotime( $check_out );

		if ( ! $check_in_ts || ! $check_out_ts ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format.', 'assistify-for-woocommerce' ),
			);
		}

		$nights = floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS );

		// Get available rates.
		$rates = array();
		$default_rate = function_exists( 'hbfwc_get_default_rate' ) ? hbfwc_get_default_rate() : null;

		if ( $default_rate && method_exists( $product, 'has_active_rates_by_date' ) ) {
			$active_rates = $product->has_active_rates_by_date( $check_in_ts, $check_out_ts );

			foreach ( $active_rates as $rate_id ) {
				$rate_term = get_term_by( 'term_id', $rate_id, 'rate_plan' );
				if ( ! $rate_term ) {
					continue;
				}

				$is_default  = $rate_id === $default_rate->term_id;
				$rate_price  = 0;

				if ( method_exists( $product, 'get_cart_price' ) ) {
					$rate_price = $product->get_cart_price( $check_in_ts, $check_out_ts, $rate_id );
				}

				$total_for_rooms = $rate_price * $rooms;

				$rates[] = array(
					'id'          => $rate_id,
					'name'        => $rate_term->name,
					'is_default'  => $is_default,
					'price'       => array(
						'per_stay'       => round( floatval( $rate_price ), 2 ),
						'per_stay_fmt'   => wc_price( $rate_price ),
						'per_night'      => round( floatval( $rate_price ) / max( 1, $nights ), 2 ),
						'per_night_fmt'  => wc_price( $rate_price / max( 1, $nights ) ),
						'total'          => round( floatval( $total_for_rooms ), 2 ),
						'total_fmt'      => wc_price( $total_for_rooms ),
					),
				);
			}
		}

		if ( empty( $rates ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No rates available for these dates.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success' => true,
			'room'    => array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'url'  => $product->get_permalink(),
			),
			'dates'   => array(
				'check_in'  => wp_date( get_option( 'date_format' ), $check_in_ts ),
				'check_out' => wp_date( get_option( 'date_format' ), $check_out_ts ),
				'nights'    => $nights,
			),
			'rooms'   => $rooms,
			'rates'   => $rates,
		);
	}

	/**
	 * Get customer's reservations.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Customer reservations.
	 */
	public function ability_customer_my_reservations( $params ) {
		$customer_id = absint( $params['customer_id'] );
		$status      = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'all';

		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Please log in to view your reservations.', 'assistify-for-woocommerce' ),
			);
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => 50,
				'status'      => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$now          = time();
		$reservations = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();

				if ( ! $product || ! $product instanceof \HBFWC_WC_Product_Accommodation ) {
					continue;
				}

				$booking_date = $item->get_meta( 'booking_date' );
				if ( empty( $booking_date ) ) {
					continue;
				}

				$booking_data = json_decode( $booking_date, true );
				if ( ! $booking_data ) {
					continue;
				}

				$check_in_ts  = isset( $booking_data['check_in'] ) ? absint( $booking_data['check_in'] ) : 0;
				$check_out_ts = isset( $booking_data['check_out'] ) ? absint( $booking_data['check_out'] ) : 0;

				// Filter by status.
				$is_upcoming = $check_in_ts > $now;
				$is_past     = $check_out_ts < $now;

				if ( 'upcoming' === $status && ! $is_upcoming ) {
					continue;
				}

				if ( 'past' === $status && ! $is_past ) {
					continue;
				}

				$reservations[] = $this->format_reservation_for_customer( $order, $item, $booking_data );
			}
		}

		if ( empty( $reservations ) ) {
			return array(
				'success'      => true,
				'message'      => __( 'You do not have any hotel reservations.', 'assistify-for-woocommerce' ),
				'reservations' => array(),
			);
		}

		return array(
			'success'      => true,
			'reservations' => $reservations,
			'count'        => count( $reservations ),
		);
	}

	/**
	 * Suggest alternative rooms or dates.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Alternative suggestions.
	 */
	public function ability_customer_suggest_alternatives( $params ) {
		$room_id   = isset( $params['room_id'] ) ? absint( $params['room_id'] ) : 0;
		$check_in  = sanitize_text_field( $params['check_in'] );
		$check_out = sanitize_text_field( $params['check_out'] );
		$adults    = isset( $params['adults'] ) ? absint( $params['adults'] ) : 1;

		$check_in_ts  = strtotime( $check_in );
		$check_out_ts = strtotime( $check_out );

		if ( ! $check_in_ts || ! $check_out_ts ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format.', 'assistify-for-woocommerce' ),
			);
		}

		$nights = floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS );

		$suggestions = array(
			'alternative_rooms' => array(),
			'alternative_dates' => array(),
		);

		// Get all rooms.
		$rooms = wc_get_products(
			array(
				'type'   => 'accommodation',
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		// Find alternative rooms for same dates.
		foreach ( $rooms as $room ) {
			if ( $room_id && $room->get_id() === $room_id ) {
				continue;
			}

			$max_adults = $room->get_meta( '_max_adults' ) ? absint( $room->get_meta( '_max_adults' ) ) : 99;
			if ( $adults > $max_adults ) {
				continue;
			}

			// Check availability.
			if ( function_exists( 'hbfwc_get_availibility' ) ) {
				$availability = hbfwc_get_availibility(
					$room->get_id(),
					gmdate( 'Y-m-d', $check_in_ts ),
					gmdate( 'Y-m-d', $check_out_ts )
				);

				$is_available = true;
				foreach ( $availability as $day_data ) {
					$qty    = isset( $day_data['qty'] ) ? absint( $day_data['qty'] ) : 0;
					$status = isset( $day_data['status'] ) ? absint( $day_data['status'] ) : 1;

					if ( 0 === $status || 0 === $qty ) {
						$is_available = false;
						break;
					}
				}

				if ( $is_available ) {
					$total_price = 0;
					if ( method_exists( $room, 'get_cart_price' ) ) {
						$default_rate = function_exists( 'hbfwc_get_default_rate' ) ? hbfwc_get_default_rate() : null;
						if ( $default_rate ) {
							$total_price = $room->get_cart_price( $check_in_ts, $check_out_ts, $default_rate->term_id );
						}
					}

					$suggestions['alternative_rooms'][] = array(
						'id'    => $room->get_id(),
						'name'  => $room->get_name(),
						'price' => wc_price( $total_price ),
						'url'   => $room->get_permalink(),
					);
				}
			}
		}

		// Suggest alternative dates if original room requested.
		if ( $room_id ) {
			$original_room = wc_get_product( $room_id );

			if ( $original_room && $original_room instanceof \HBFWC_WC_Product_Accommodation ) {
				// Check dates before and after requested dates.
				$date_offsets = array( -7, -3, -1, 1, 3, 7 );

				foreach ( $date_offsets as $offset ) {
					$alt_check_in  = strtotime( "{$offset} days", $check_in_ts );
					$alt_check_out = strtotime( "{$offset} days", $check_out_ts );

					// Skip past dates.
					if ( $alt_check_in < strtotime( 'today' ) ) {
						continue;
					}

					if ( function_exists( 'hbfwc_get_availibility' ) ) {
						$availability = hbfwc_get_availibility(
							$room_id,
							gmdate( 'Y-m-d', $alt_check_in ),
							gmdate( 'Y-m-d', $alt_check_out )
						);

						$is_available = true;
						foreach ( $availability as $day_data ) {
							$qty    = isset( $day_data['qty'] ) ? absint( $day_data['qty'] ) : 0;
							$status = isset( $day_data['status'] ) ? absint( $day_data['status'] ) : 1;

							if ( 0 === $status || 0 === $qty ) {
								$is_available = false;
								break;
							}
						}

						if ( $is_available ) {
							$total_price = 0;
							if ( method_exists( $original_room, 'get_cart_price' ) ) {
								$default_rate = function_exists( 'hbfwc_get_default_rate' ) ? hbfwc_get_default_rate() : null;
								if ( $default_rate ) {
									$total_price = $original_room->get_cart_price( $alt_check_in, $alt_check_out, $default_rate->term_id );
								}
							}

							$suggestions['alternative_dates'][] = array(
								'check_in'  => wp_date( get_option( 'date_format' ), $alt_check_in ),
								'check_out' => wp_date( get_option( 'date_format' ), $alt_check_out ),
								'price'     => wc_price( $total_price ),
							);
						}
					}
				}
			}
		}

		$has_suggestions = ! empty( $suggestions['alternative_rooms'] ) || ! empty( $suggestions['alternative_dates'] );

		return array(
			'success'           => true,
			'has_alternatives'  => $has_suggestions,
			'original_request'  => array(
				'room_id'   => $room_id,
				'check_in'  => wp_date( get_option( 'date_format' ), $check_in_ts ),
				'check_out' => wp_date( get_option( 'date_format' ), $check_out_ts ),
				'nights'    => $nights,
			),
			'alternative_rooms' => array_slice( $suggestions['alternative_rooms'], 0, 5 ),
			'alternative_dates' => array_slice( $suggestions['alternative_dates'], 0, 5 ),
			'message'           => $has_suggestions
				? __( 'Here are some alternatives for your stay:', 'assistify-for-woocommerce' )
				: __( 'Unfortunately, no alternatives are available. Please try different dates.', 'assistify-for-woocommerce' ),
		);
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Format room data for response.
	 *
	 * @since 1.1.0
	 * @param \WC_Product $product  The product object.
	 * @param bool        $detailed Whether to include detailed information.
	 * @return array Formatted room data.
	 */
	private function format_room_data( $product, $detailed = false ) {
		$data = array(
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'status'       => $product->get_status(),
			'max_adults'   => $product->get_meta( '_max_adults' ) ? absint( $product->get_meta( '_max_adults' ) ) : null,
			'max_children' => $product->get_meta( '_max_children' ) ? absint( $product->get_meta( '_max_children' ) ) : null,
			'allotment'    => $product->get_meta( '_allotment' ) ? absint( $product->get_meta( '_allotment' ) ) : null,
			'price_html'   => $product->get_price_html(),
			'url'          => $product->get_permalink(),
			'admin_url'    => admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ),
		);

		if ( $detailed ) {
			$data['description']   = $product->get_description();
			$data['short_desc']    = $product->get_short_description();
			$data['beds']          = $product->get_meta( '_no_of_beds' ) ?: null;
			$data['room_size']     = $product->get_meta( '_room_size' ) ?: null;
			$data['min_stay']      = method_exists( $product, 'get_minimum_stay' ) ? $product->get_minimum_stay() : 1;
			$data['image']         = wp_get_attachment_url( $product->get_image_id() );

			// Get amenities.
			$amenities = array();
			if ( function_exists( 'hbfwc_get_amenities_group' ) ) {
				$groups = hbfwc_get_amenities_group();
				foreach ( $groups as $group_key => $group_name ) {
					if ( function_exists( 'hbfwc_get_post_amenities_by_group' ) ) {
						$group_amenities = hbfwc_get_post_amenities_by_group( $product->get_id(), $group_key );
						if ( ! empty( $group_amenities ) && ! is_wp_error( $group_amenities ) ) {
							$amenities[ $group_name ] = wp_list_pluck( $group_amenities, 'name' );
						}
					}
				}
			}
			$data['amenities'] = $amenities;

			// Get rate plans.
			if ( method_exists( $product, 'get_active_rates' ) ) {
				$rates = $product->get_active_rates();
				$data['rates'] = array();
				foreach ( $rates as $rate ) {
					if ( $rate ) {
						$data['rates'][] = array(
							'id'   => $rate->term_id,
							'name' => $rate->name,
						);
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Format reservation data for admin response.
	 *
	 * @since 1.1.0
	 * @param \WC_Order      $order        The order object.
	 * @param \WC_Order_Item $item         The order item.
	 * @param array          $booking_data The booking data.
	 * @param bool           $detailed     Whether to include detailed information.
	 * @return array Formatted reservation data.
	 */
	private function format_reservation_data( $order, $item, $booking_data, $detailed = false ) {
		$product      = $item->get_product();
		$check_in_ts  = isset( $booking_data['check_in'] ) ? absint( $booking_data['check_in'] ) : 0;
		$check_out_ts = isset( $booking_data['check_out'] ) ? absint( $booking_data['check_out'] ) : 0;
		$nights       = $check_in_ts && $check_out_ts ? floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS ) : 0;

		// Get guest info.
		$booking_guests = $item->get_meta( 'booking_guests' );
		$guest_data     = $booking_guests ? json_decode( $booking_guests, true ) : array();

		$data = array(
			'order_id'     => $order->get_id(),
			'order_status' => $order->get_status(),
			'room'         => array(
				'id'   => $product ? $product->get_id() : 0,
				'name' => $product ? $product->get_name() : $item->get_name(),
			),
			'check_in'     => $check_in_ts ? wp_date( get_option( 'date_format' ), $check_in_ts ) : '',
			'check_out'    => $check_out_ts ? wp_date( get_option( 'date_format' ), $check_out_ts ) : '',
			'nights'       => $nights,
			'rooms'        => $item->get_quantity(),
			'guests'       => array(
				'adults'   => isset( $guest_data['adults'] ) ? absint( $guest_data['adults'] ) : 0,
				'children' => isset( $guest_data['children'] ) ? absint( $guest_data['children'] ) : 0,
			),
			'customer'     => array(
				'name'  => $order->get_formatted_billing_full_name(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
			),
			'total'        => wc_price( $item->get_total() ),
		);

		if ( $detailed ) {
			$data['rate_id']     = $item->get_meta( 'rate_id' );
			$data['order_date']  = $order->get_date_created() ? $order->get_date_created()->date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
			$data['order_total'] = wc_price( $order->get_total() );
			$data['admin_url']   = $order->get_edit_order_url();

			// Get add-ons.
			$addons = $item->get_meta( 'booking_line_addons' );
			if ( $addons ) {
				$addon_data = json_decode( $addons, true );
				if ( is_array( $addon_data ) ) {
					$data['addons'] = array();
					foreach ( $addon_data as $addon ) {
						$addon_post = get_post( absint( $addon['id'] ) );
						$data['addons'][] = array(
							'name'  => $addon_post ? $addon_post->post_title : __( 'Add-on', 'assistify-for-woocommerce' ),
							'price' => wc_price( floatval( $addon['price'] ) * absint( $addon['qty'] ) ),
							'qty'   => absint( $addon['qty'] ),
						);
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Format reservation data for customer response.
	 *
	 * @since 1.1.0
	 * @param \WC_Order      $order        The order object.
	 * @param \WC_Order_Item $item         The order item.
	 * @param array          $booking_data The booking data.
	 * @return array Formatted reservation data for customer.
	 */
	private function format_reservation_for_customer( $order, $item, $booking_data ) {
		$product      = $item->get_product();
		$check_in_ts  = isset( $booking_data['check_in'] ) ? absint( $booking_data['check_in'] ) : 0;
		$check_out_ts = isset( $booking_data['check_out'] ) ? absint( $booking_data['check_out'] ) : 0;
		$nights       = $check_in_ts && $check_out_ts ? floor( ( $check_out_ts - $check_in_ts ) / DAY_IN_SECONDS ) : 0;

		$now         = time();
		$is_upcoming = $check_in_ts > $now;
		$is_current  = $check_in_ts <= $now && $check_out_ts >= $now;

		$status_label = __( 'Past', 'assistify-for-woocommerce' );
		if ( $is_upcoming ) {
			$status_label = __( 'Upcoming', 'assistify-for-woocommerce' );
		} elseif ( $is_current ) {
			$status_label = __( 'Current', 'assistify-for-woocommerce' );
		}

		return array(
			'order_id'     => $order->get_id(),
			'room'         => $product ? $product->get_name() : $item->get_name(),
			'check_in'     => $check_in_ts ? wp_date( get_option( 'date_format' ), $check_in_ts ) : '',
			'check_out'    => $check_out_ts ? wp_date( get_option( 'date_format' ), $check_out_ts ) : '',
			'nights'       => $nights,
			'rooms'        => $item->get_quantity(),
			'total'        => wc_price( $item->get_total() ),
			'status'       => $status_label,
			'is_upcoming'  => $is_upcoming,
		);
	}
}
