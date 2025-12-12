<?php
/**
 * WooCommerce Memberships Integration.
 *
 * Provides AI abilities for WooCommerce Memberships extension.
 * Detects WC Memberships installation and registers membership-specific abilities.
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
 * WooCommerce Memberships Integration Class.
 *
 * @since 1.1.0
 */
class WC_Memberships_Integration {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var WC_Memberships_Integration|null
	 */
	private static $instance = null;

	/**
	 * Whether WC Memberships is active.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private $is_active = false;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.1.0
	 * @return WC_Memberships_Integration Instance.
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
		$this->is_active = $this->detect_wc_memberships();

		if ( $this->is_active ) {
			add_action( 'init', array( $this, 'register_membership_abilities' ), 15 );
		}
	}

	/**
	 * Detect if WooCommerce Memberships is installed and active.
	 *
	 * @since 1.1.0
	 * @return bool True if WC Memberships is active.
	 */
	private function detect_wc_memberships() {
		// Check if wc_memberships function exists.
		if ( function_exists( 'wc_memberships' ) ) {
			return true;
		}

		// Check if the WC_Memberships class exists.
		if ( class_exists( 'WC_Memberships' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if WC Memberships is active.
	 *
	 * @since 1.1.0
	 * @return bool True if active.
	 */
	public function is_active() {
		return $this->is_active;
	}

	/**
	 * Register membership abilities with the Abilities Registry.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_membership_abilities() {
		$registry = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();

		// Add memberships category.
		$registry->add_category( 'memberships', __( 'Memberships', 'assistify-for-woocommerce' ) );

		// Register admin abilities.
		$this->register_admin_abilities( $registry );

		// Register customer abilities.
		$this->register_customer_abilities( $registry );
	}

	/**
	 * Register admin membership abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_admin_abilities( $registry ) {
		// List user memberships.
		$registry->register(
			'afw/memberships/list',
			array(
				'name'        => __( 'List Memberships', 'assistify-for-woocommerce' ),
				'description' => __( 'List all user memberships with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_memberships_list' ),
				'parameters'  => array(
					'status'      => array(
						'type'        => 'string',
						'description' => __( 'Filter by status (active, paused, expired, cancelled, pending).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'plan_id'     => array(
						'type'        => 'integer',
						'description' => __( 'Filter by membership plan ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'Filter by customer ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'       => array(
						'type'        => 'integer',
						'description' => __( 'Number of memberships to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Get membership details.
		$registry->register(
			'afw/memberships/get',
			array(
				'name'        => __( 'Get Membership', 'assistify-for-woocommerce' ),
				'description' => __( 'Get detailed information about a specific user membership.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_memberships_get' ),
				'parameters'  => array(
					'membership_id' => array(
						'type'        => 'integer',
						'description' => __( 'The user membership ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// List membership plans.
		$registry->register(
			'afw/memberships/plans',
			array(
				'name'        => __( 'List Membership Plans', 'assistify-for-woocommerce' ),
				'description' => __( 'List all available membership plans.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_memberships_plans' ),
				'parameters'  => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of plans to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Search memberships.
		$registry->register(
			'afw/memberships/search',
			array(
				'name'        => __( 'Search Memberships', 'assistify-for-woocommerce' ),
				'description' => __( 'Search memberships by customer name, email, or membership ID.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_memberships_search' ),
				'parameters'  => array(
					'query'  => array(
						'type'        => 'string',
						'description' => __( 'Search query (customer name, email, or membership ID).', 'assistify-for-woocommerce' ),
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

		// Membership analytics.
		$registry->register(
			'afw/memberships/analytics',
			array(
				'name'        => __( 'Membership Analytics', 'assistify-for-woocommerce' ),
				'description' => __( 'Get membership analytics including counts, popular plans, and trends.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_memberships_analytics' ),
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

		// Expiring memberships.
		$registry->register(
			'afw/memberships/expiring',
			array(
				'name'        => __( 'Expiring Memberships', 'assistify-for-woocommerce' ),
				'description' => __( 'List memberships expiring within a specified timeframe.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_memberships_expiring' ),
				'parameters'  => array(
					'days'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of days to check for expiring memberships.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 30,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of results to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Update membership status.
		$registry->register(
			'afw/memberships/update-status',
			array(
				'name'         => __( 'Update Membership Status', 'assistify-for-woocommerce' ),
				'description'  => __( 'Update the status of a user membership.', 'assistify-for-woocommerce' ),
				'category'     => 'memberships',
				'callback'     => array( $this, 'ability_memberships_update_status' ),
				'parameters'   => array(
					'membership_id' => array(
						'type'        => 'integer',
						'description' => __( 'The user membership ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status'        => array(
						'type'        => 'string',
						'description' => __( 'New status (active, paused, cancelled, expired).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'   => 'manage_woocommerce',
				'confirmation' => true,
			)
		);

		// Members by plan.
		$registry->register(
			'afw/memberships/members-by-plan',
			array(
				'name'        => __( 'Members by Plan', 'assistify-for-woocommerce' ),
				'description' => __( 'List members of a specific membership plan.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_memberships_members_by_plan' ),
				'parameters'  => array(
					'plan_id' => array(
						'type'        => 'integer',
						'description' => __( 'The membership plan ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status'  => array(
						'type'        => 'string',
						'description' => __( 'Filter by status.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'   => array(
						'type'        => 'integer',
						'description' => __( 'Number of results to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 20,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);
	}

	/**
	 * Register customer membership abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_customer_abilities( $registry ) {
		// Get customer's memberships.
		$registry->register(
			'afw/membership/my-memberships',
			array(
				'name'        => __( 'My Memberships', 'assistify-for-woocommerce' ),
				'description' => __( 'Get customer\'s own memberships.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_customer_memberships' ),
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

		// Get membership details.
		$registry->register(
			'afw/membership/get-details',
			array(
				'name'        => __( 'Get Membership Details', 'assistify-for-woocommerce' ),
				'description' => __( 'Get details of a specific membership.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_customer_membership_details' ),
				'parameters'  => array(
					'customer_id'   => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'membership_id' => array(
						'type'        => 'integer',
						'description' => __( 'The membership ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Get membership benefits.
		$registry->register(
			'afw/membership/benefits',
			array(
				'name'        => __( 'Membership Benefits', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the benefits and perks of a membership.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_customer_membership_benefits' ),
				'parameters'  => array(
					'customer_id'   => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'membership_id' => array(
						'type'        => 'integer',
						'description' => __( 'The membership ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Check if user is member.
		$registry->register(
			'afw/membership/check-access',
			array(
				'name'        => __( 'Check Membership Access', 'assistify-for-woocommerce' ),
				'description' => __( 'Check if customer has access to specific content or plans.', 'assistify-for-woocommerce' ),
				'category'    => 'memberships',
				'callback'    => array( $this, 'ability_customer_check_access' ),
				'parameters'  => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'plan_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The plan ID to check access for.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Cancel membership.
		$registry->register(
			'afw/membership/cancel',
			array(
				'name'         => __( 'Cancel Membership', 'assistify-for-woocommerce' ),
				'description'  => __( 'Cancel a membership.', 'assistify-for-woocommerce' ),
				'category'     => 'memberships',
				'callback'     => array( $this, 'ability_customer_cancel_membership' ),
				'parameters'   => array(
					'customer_id'   => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'membership_id' => array(
						'type'        => 'integer',
						'description' => __( 'The membership ID to cancel.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'   => 'read',
				'scope'        => 'customer',
				'confirmation' => true,
			)
		);
	}

	// =========================================================================
	// ADMIN ABILITY CALLBACKS
	// =========================================================================

	/**
	 * List memberships with filters.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Membership list data.
	 */
	public function ability_memberships_list( $params ) {
		if ( ! function_exists( 'wc_memberships' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$query_args = array(
			'posts_per_page' => $limit,
			'post_type'      => 'wc_user_membership',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Add status filter.
		if ( ! empty( $params['status'] ) ) {
			$status = sanitize_text_field( $params['status'] );
			// Add wcm- prefix if not present.
			if ( strpos( $status, 'wcm-' ) !== 0 ) {
				$status = 'wcm-' . $status;
			}
			$query_args['post_status'] = $status;
		} else {
			$query_args['post_status'] = array_keys( wc_memberships_get_user_membership_statuses() );
		}

		// Add plan filter.
		if ( ! empty( $params['plan_id'] ) ) {
			$query_args['post_parent'] = absint( $params['plan_id'] );
		}

		// Add customer filter.
		if ( ! empty( $params['customer_id'] ) ) {
			$query_args['author'] = absint( $params['customer_id'] );
		}

		$memberships_query = new \WP_Query( $query_args );
		$result            = array();

		foreach ( $memberships_query->posts as $post ) {
			$membership = wc_memberships_get_user_membership( $post->ID );
			if ( $membership ) {
				$result[] = $this->format_membership_data( $membership );
			}
		}

		return array(
			'success'     => true,
			'memberships' => $result,
			'count'       => count( $result ),
			'filters'     => array(
				'status'      => $params['status'] ?? 'all',
				'plan_id'     => $params['plan_id'] ?? null,
				'customer_id' => $params['customer_id'] ?? null,
			),
		);
	}

	/**
	 * Get membership details.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Membership details.
	 */
	public function ability_memberships_get( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_membership' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$membership_id = absint( $params['membership_id'] );
		$membership    = wc_memberships_get_user_membership( $membership_id );

		if ( ! $membership ) {
			return array(
				'success' => false,
				'error'   => __( 'Membership not found.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success'    => true,
			'membership' => $this->format_membership_data( $membership, true ),
		);
	}

	/**
	 * List membership plans.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Plans list.
	 */
	public function ability_memberships_plans( $params ) {
		if ( ! function_exists( 'wc_memberships_get_membership_plans' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;
		$plans = wc_memberships_get_membership_plans( array( 'posts_per_page' => $limit ) );

		$result = array();
		foreach ( $plans as $plan ) {
			$result[] = $this->format_plan_data( $plan );
		}

		return array(
			'success' => true,
			'plans'   => $result,
			'count'   => count( $result ),
		);
	}

	/**
	 * Search memberships.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Search results.
	 */
	public function ability_memberships_search( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_membership' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$query = sanitize_text_field( $params['query'] );
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$results = array();

		// Check if query is a membership ID.
		if ( is_numeric( $query ) ) {
			$membership = wc_memberships_get_user_membership( absint( $query ) );
			if ( $membership ) {
				$results[] = $this->format_membership_data( $membership );
			}
		}

		// Search by customer email.
		if ( is_email( $query ) ) {
			$user = get_user_by( 'email', $query );
			if ( $user ) {
				$memberships = wc_memberships_get_user_memberships( $user->ID );
				foreach ( array_slice( $memberships, 0, $limit ) as $membership ) {
					$results[] = $this->format_membership_data( $membership );
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
				$memberships = wc_memberships_get_user_memberships( $user->ID );
				foreach ( $memberships as $membership ) {
					$results[] = $this->format_membership_data( $membership );
				}
			}
		}

		// Limit results.
		$results = array_slice( $results, 0, $limit );

		return array(
			'success'     => true,
			'memberships' => $results,
			'count'       => count( $results ),
			'query'       => $query,
		);
	}

	/**
	 * Get membership analytics.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Analytics data.
	 */
	public function ability_memberships_analytics( $params ) {
		if ( ! function_exists( 'wc_memberships_get_membership_plans' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
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

		$date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Get all memberships.
		$all_statuses = array_keys( wc_memberships_get_user_membership_statuses() );

		$memberships_query = new \WP_Query(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'wc_user_membership',
				'post_status'    => $all_statuses,
				'date_query'     => array(
					array(
						'after' => $date_from,
					),
				),
			)
		);

		$total_memberships = $memberships_query->found_posts;
		$status_counts     = array();
		$plan_counts       = array();

		foreach ( $memberships_query->posts as $post ) {
			$membership = wc_memberships_get_user_membership( $post->ID );
			if ( ! $membership ) {
				continue;
			}

			// Count by status.
			$status = str_replace( 'wcm-', '', $membership->get_status() );
			if ( ! isset( $status_counts[ $status ] ) ) {
				$status_counts[ $status ] = 0;
			}
			++$status_counts[ $status ];

			// Count by plan.
			$plan_id = $membership->get_plan_id();
			if ( ! isset( $plan_counts[ $plan_id ] ) ) {
				$plan                    = $membership->get_plan();
				$plan_name               = $plan ? $plan->get_name() : __( 'Unknown Plan', 'assistify-for-woocommerce' );
				$plan_counts[ $plan_id ] = array(
					'id'    => $plan_id,
					'name'  => $plan_name,
					'count' => 0,
				);
			}
			++$plan_counts[ $plan_id ]['count'];
		}

		// Sort plans by count.
		usort(
			$plan_counts,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		// Top 5 plans.
		$top_plans = array_slice( $plan_counts, 0, 5 );

		// Count active memberships.
		$active_count = isset( $status_counts['active'] ) ? $status_counts['active'] : 0;

		// Get total active members (all time).
		$total_active_query = new \WP_Query(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'wc_user_membership',
				'post_status'    => array( 'wcm-active', 'wcm-free_trial', 'wcm-complimentary' ),
				'fields'         => 'ids',
			)
		);
		$total_active       = $total_active_query->found_posts;

		return array(
			'success'   => true,
			'period'    => $period,
			'analytics' => array(
				'new_memberships'  => $total_memberships,
				'total_active'     => $total_active,
				'status_breakdown' => $status_counts,
				'top_plans'        => $top_plans,
			),
		);
	}

	/**
	 * Get expiring memberships.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Expiring memberships.
	 */
	public function ability_memberships_expiring( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_membership' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$days  = isset( $params['days'] ) ? absint( $params['days'] ) : 30;
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;

		$now      = time();
		$end_date = strtotime( "+{$days} days" );

		// Query active memberships.
		$memberships_query = new \WP_Query(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'wc_user_membership',
				'post_status'    => 'wcm-active',
			)
		);

		$expiring = array();

		foreach ( $memberships_query->posts as $post ) {
			$membership = wc_memberships_get_user_membership( $post->ID );
			if ( ! $membership ) {
				continue;
			}

			$membership_end = $membership->get_end_date( 'timestamp' );

			// Check if membership expires within the specified days.
			if ( $membership_end && $membership_end > $now && $membership_end <= $end_date ) {
				$data                      = $this->format_membership_data( $membership );
				$data['days_until_expiry'] = floor( ( $membership_end - $now ) / DAY_IN_SECONDS );
				$expiring[]                = $data;
			}
		}

		// Sort by expiry date.
		usort(
			$expiring,
			function ( $a, $b ) {
				return $a['days_until_expiry'] <=> $b['days_until_expiry'];
			}
		);

		// Limit results.
		$expiring = array_slice( $expiring, 0, $limit );

		return array(
			'success'     => true,
			'memberships' => $expiring,
			'count'       => count( $expiring ),
			'days'        => $days,
		);
	}

	/**
	 * Update membership status.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Update result.
	 */
	public function ability_memberships_update_status( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_membership' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$membership_id = absint( $params['membership_id'] );
		$new_status    = sanitize_text_field( $params['status'] );

		$membership = wc_memberships_get_user_membership( $membership_id );

		if ( ! $membership ) {
			return array(
				'success' => false,
				'error'   => __( 'Membership not found.', 'assistify-for-woocommerce' ),
			);
		}

		// Add wcm- prefix if not present.
		if ( strpos( $new_status, 'wcm-' ) !== 0 ) {
			$new_status = 'wcm-' . $new_status;
		}

		// Validate new status.
		$valid_statuses = array_keys( wc_memberships_get_user_membership_statuses() );
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid membership status.', 'assistify-for-woocommerce' ),
			);
		}

		$old_status = $membership->get_status();

		try {
			$membership->update_status( $new_status );

			return array(
				'success'    => true,
				'message'    => sprintf(
					// Translators: %1$s is the old status, %2$s is the new status.
					__( 'Membership status updated from %1$s to %2$s.', 'assistify-for-woocommerce' ),
					wc_memberships_get_user_membership_status_name( $old_status ),
					wc_memberships_get_user_membership_status_name( $new_status )
				),
				'membership' => $this->format_membership_data( $membership ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get members by plan.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Members list.
	 */
	public function ability_memberships_members_by_plan( $params ) {
		if ( ! function_exists( 'wc_memberships_get_membership_plan' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$plan_id = absint( $params['plan_id'] );
		$limit   = isset( $params['limit'] ) ? absint( $params['limit'] ) : 20;

		$plan = wc_memberships_get_membership_plan( $plan_id );
		if ( ! $plan ) {
			return array(
				'success' => false,
				'error'   => __( 'Membership plan not found.', 'assistify-for-woocommerce' ),
			);
		}

		$query_args = array(
			'posts_per_page' => $limit,
			'post_type'      => 'wc_user_membership',
			'post_parent'    => $plan_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Add status filter.
		if ( ! empty( $params['status'] ) ) {
			$status = sanitize_text_field( $params['status'] );
			if ( strpos( $status, 'wcm-' ) !== 0 ) {
				$status = 'wcm-' . $status;
			}
			$query_args['post_status'] = $status;
		} else {
			$query_args['post_status'] = array_keys( wc_memberships_get_user_membership_statuses() );
		}

		$memberships_query = new \WP_Query( $query_args );
		$result            = array();

		foreach ( $memberships_query->posts as $post ) {
			$membership = wc_memberships_get_user_membership( $post->ID );
			if ( $membership ) {
				$result[] = $this->format_membership_data( $membership );
			}
		}

		return array(
			'success'     => true,
			'plan'        => $this->format_plan_data( $plan ),
			'memberships' => $result,
			'count'       => count( $result ),
		);
	}

	// =========================================================================
	// CUSTOMER ABILITY CALLBACKS
	// =========================================================================

	/**
	 * Get customer's memberships.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Customer memberships.
	 */
	public function ability_customer_memberships( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		$args = array();
		if ( ! empty( $params['status'] ) ) {
			$status = sanitize_text_field( $params['status'] );
			if ( strpos( $status, 'wcm-' ) !== 0 ) {
				$status = 'wcm-' . $status;
			}
			$args['status'] = array( $status );
		}

		$memberships = wc_memberships_get_user_memberships( $customer_id, $args );
		$result      = array();

		foreach ( $memberships as $membership ) {
			$result[] = $this->format_membership_for_customer( $membership );
		}

		if ( empty( $result ) ) {
			return array(
				'success'     => true,
				'message'     => __( 'You do not have any memberships.', 'assistify-for-woocommerce' ),
				'memberships' => array(),
			);
		}

		return array(
			'success'     => true,
			'memberships' => $result,
			'count'       => count( $result ),
		);
	}

	/**
	 * Get customer membership details.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Membership details.
	 */
	public function ability_customer_membership_details( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		// If membership_id specified, use that.
		if ( ! empty( $params['membership_id'] ) ) {
			$membership = wc_memberships_get_user_membership( absint( $params['membership_id'] ) );

			if ( ! $membership || $membership->get_user_id() !== $customer_id ) {
				return array(
					'success' => false,
					'error'   => __( 'Membership not found or does not belong to you.', 'assistify-for-woocommerce' ),
				);
			}

			return array(
				'success'    => true,
				'membership' => $this->format_membership_for_customer( $membership, true ),
			);
		}

		// Otherwise, get their active membership.
		$memberships = wc_memberships_get_user_active_memberships( $customer_id );

		if ( empty( $memberships ) ) {
			return array(
				'success' => false,
				'error'   => __( 'You do not have any active memberships.', 'assistify-for-woocommerce' ),
			);
		}

		// Return the first active membership.
		return array(
			'success'    => true,
			'membership' => $this->format_membership_for_customer( $memberships[0], true ),
		);
	}

	/**
	 * Get membership benefits.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Membership benefits.
	 */
	public function ability_customer_membership_benefits( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		// Get membership.
		$membership = null;
		if ( ! empty( $params['membership_id'] ) ) {
			$membership = wc_memberships_get_user_membership( absint( $params['membership_id'] ) );
			if ( $membership && $membership->get_user_id() !== $customer_id ) {
				$membership = null;
			}
		} else {
			$memberships = wc_memberships_get_user_active_memberships( $customer_id );
			$membership  = ! empty( $memberships ) ? $memberships[0] : null;
		}

		if ( ! $membership ) {
			return array(
				'success' => false,
				'error'   => __( 'No active membership found.', 'assistify-for-woocommerce' ),
			);
		}

		$plan = $membership->get_plan();
		if ( ! $plan ) {
			return array(
				'success' => false,
				'error'   => __( 'Membership plan not found.', 'assistify-for-woocommerce' ),
			);
		}

		$benefits = array(
			'plan_name'   => $plan->get_name(),
			'description' => $plan->post->post_content,
		);

		// Get discount rules if available.
		if ( method_exists( $plan, 'get_member_discount_rules' ) ) {
			$discount_rules = $plan->get_member_discount_rules();
			$discounts      = array();
			foreach ( $discount_rules as $rule ) {
				$discounts[] = array(
					'type'   => $rule->get_discount_type(),
					'amount' => $rule->get_discount_amount(),
				);
			}
			$benefits['discounts'] = $discounts;
		}

		return array(
			'success'    => true,
			'membership' => array(
				'id'     => $membership->get_id(),
				'plan'   => $plan->get_name(),
				'status' => wc_memberships_get_user_membership_status_name( $membership->get_status() ),
			),
			'benefits'   => $benefits,
		);
	}

	/**
	 * Check customer membership access.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Access check result.
	 */
	public function ability_customer_check_access( $params ) {
		if ( ! function_exists( 'wc_memberships_is_user_active_member' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		$plan_id   = ! empty( $params['plan_id'] ) ? absint( $params['plan_id'] ) : null;
		$is_member = wc_memberships_is_user_member( $customer_id, $plan_id );
		$is_active = wc_memberships_is_user_active_member( $customer_id, $plan_id );

		$response = array(
			'success'   => true,
			'is_member' => $is_member,
			'is_active' => $is_active,
		);

		if ( $plan_id ) {
			$plan = wc_memberships_get_membership_plan( $plan_id );
			if ( $plan ) {
				$response['plan'] = array(
					'id'   => $plan->get_id(),
					'name' => $plan->get_name(),
				);
			}
		}

		// Get all active memberships.
		if ( $is_active ) {
			$memberships             = wc_memberships_get_user_active_memberships( $customer_id );
			$response['memberships'] = array();
			foreach ( $memberships as $membership ) {
				$response['memberships'][] = array(
					'id'   => $membership->get_id(),
					'plan' => $membership->get_plan() ? $membership->get_plan()->get_name() : '',
				);
			}
		}

		return $response;
	}

	/**
	 * Cancel customer membership.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Cancel result.
	 */
	public function ability_customer_cancel_membership( $params ) {
		if ( ! function_exists( 'wc_memberships_get_user_membership' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Memberships is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		// Get membership.
		$membership = null;
		if ( ! empty( $params['membership_id'] ) ) {
			$membership = wc_memberships_get_user_membership( absint( $params['membership_id'] ) );
			if ( $membership && $membership->get_user_id() !== $customer_id ) {
				$membership = null;
			}
		} else {
			$memberships = wc_memberships_get_user_active_memberships( $customer_id );
			$membership  = ! empty( $memberships ) ? $memberships[0] : null;
		}

		if ( ! $membership ) {
			return array(
				'success' => false,
				'error'   => __( 'No membership found to cancel.', 'assistify-for-woocommerce' ),
			);
		}

		// Check if can be cancelled.
		if ( ! $membership->can_be_cancelled() ) {
			return array(
				'success' => false,
				'error'   => __( 'This membership cannot be cancelled.', 'assistify-for-woocommerce' ),
			);
		}

		try {
			$membership->cancel_membership();

			return array(
				'success'    => true,
				'message'    => __( 'Your membership has been cancelled.', 'assistify-for-woocommerce' ),
				'membership' => $this->format_membership_for_customer( $membership ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Format membership data for response.
	 *
	 * @since 1.1.0
	 * @param object $membership The WC_Memberships_User_Membership object.
	 * @param bool   $detailed   Whether to include detailed information.
	 * @return array Formatted membership data.
	 */
	private function format_membership_data( $membership, $detailed = false ) {
		$plan      = $membership->get_plan();
		$plan_name = $plan ? $plan->get_name() : __( 'Unknown Plan', 'assistify-for-woocommerce' );

		$user      = $membership->get_user();
		$user_name = $user ? $user->display_name : __( 'Unknown User', 'assistify-for-woocommerce' );

		$data = array(
			'id'           => $membership->get_id(),
			'status'       => str_replace( 'wcm-', '', $membership->get_status() ),
			'status_label' => wc_memberships_get_user_membership_status_name( $membership->get_status() ),
			'customer'     => array(
				'id'    => $membership->get_user_id(),
				'name'  => $user_name,
				'email' => $user ? $user->user_email : '',
			),
			'plan'         => array(
				'id'   => $membership->get_plan_id(),
				'name' => $plan_name,
			),
			'start_date'   => $membership->get_start_date() ? wp_date( get_option( 'date_format' ), strtotime( $membership->get_start_date() ) ) : '',
			'end_date'     => $membership->get_end_date() ? wp_date( get_option( 'date_format' ), strtotime( $membership->get_end_date() ) ) : __( 'Never expires', 'assistify-for-woocommerce' ),
		);

		if ( $detailed ) {
			$data['order_id']   = $membership->get_order() ? $membership->get_order()->get_id() : null;
			$data['product_id'] = $membership->get_product() ? $membership->get_product()->get_id() : null;
			$data['admin_url']  = admin_url( 'post.php?post=' . $membership->get_id() . '&action=edit' );

			// Get notes.
			$notes         = $membership->get_notes();
			$data['notes'] = array();
			foreach ( array_slice( $notes, 0, 5 ) as $note ) {
				$data['notes'][] = array(
					'date'    => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $note->comment_date ) ),
					'content' => $note->comment_content,
				);
			}
		}

		return $data;
	}

	/**
	 * Format membership data for customer response (limited info).
	 *
	 * @since 1.1.0
	 * @param object $membership The WC_Memberships_User_Membership object.
	 * @param bool   $detailed   Whether to include detailed information.
	 * @return array Formatted membership data for customer.
	 */
	private function format_membership_for_customer( $membership, $detailed = false ) {
		$plan      = $membership->get_plan();
		$plan_name = $plan ? $plan->get_name() : __( 'Unknown Plan', 'assistify-for-woocommerce' );

		$data = array(
			'id'           => $membership->get_id(),
			'status'       => str_replace( 'wcm-', '', $membership->get_status() ),
			'status_label' => wc_memberships_get_user_membership_status_name( $membership->get_status() ),
			'plan'         => $plan_name,
			'start_date'   => $membership->get_start_date() ? wp_date( get_option( 'date_format' ), strtotime( $membership->get_start_date() ) ) : '',
			'end_date'     => $membership->get_end_date() ? wp_date( get_option( 'date_format' ), strtotime( $membership->get_end_date() ) ) : __( 'Never expires', 'assistify-for-woocommerce' ),
			'can_cancel'   => $membership->can_be_cancelled(),
			'is_active'    => $membership->is_active(),
		);

		if ( $detailed ) {
			// Add renewal URL if available.
			if ( method_exists( $membership, 'get_renew_membership_url' ) ) {
				$data['renew_url'] = $membership->get_renew_membership_url();
			}

			// Get members area sections.
			if ( $plan && method_exists( $plan, 'get_members_area_sections' ) ) {
				$data['members_area_sections'] = $plan->get_members_area_sections();
			}
		}

		return $data;
	}

	/**
	 * Format plan data for response.
	 *
	 * @since 1.1.0
	 * @param object $plan The WC_Memberships_Membership_Plan object.
	 * @return array Formatted plan data.
	 */
	private function format_plan_data( $plan ) {
		// Count members.
		$members_query = new \WP_Query(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'wc_user_membership',
				'post_parent'    => $plan->get_id(),
				'post_status'    => 'wcm-active',
				'fields'         => 'ids',
			)
		);

		return array(
			'id'             => $plan->get_id(),
			'name'           => $plan->get_name(),
			'slug'           => $plan->get_slug(),
			'active_members' => $members_query->found_posts,
			'access_method'  => $plan->get_access_method(),
			'admin_url'      => admin_url( 'post.php?post=' . $plan->get_id() . '&action=edit' ),
		);
	}
}
