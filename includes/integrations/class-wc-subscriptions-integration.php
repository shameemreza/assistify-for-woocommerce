<?php
/**
 * WooCommerce Subscriptions Integration.
 *
 * Provides AI abilities for WooCommerce Subscriptions extension.
 * Detects WC Subscriptions installation and registers subscription-specific abilities.
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
 * WooCommerce Subscriptions Integration Class.
 *
 * @since 1.1.0
 */
class WC_Subscriptions_Integration {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var WC_Subscriptions_Integration|null
	 */
	private static $instance = null;

	/**
	 * Whether WC Subscriptions is active.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private $is_active = false;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.1.0
	 * @return WC_Subscriptions_Integration Instance.
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
		$this->is_active = $this->detect_wc_subscriptions();

		if ( $this->is_active ) {
			add_action( 'init', array( $this, 'register_subscription_abilities' ), 15 );
		}
	}

	/**
	 * Detect if WooCommerce Subscriptions is installed and active.
	 *
	 * @since 1.1.0
	 * @return bool True if WC Subscriptions is active.
	 */
	private function detect_wc_subscriptions() {
		// Check if WC_Subscriptions class exists (loaded by the plugin).
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return true;
		}

		// Check if the function wcs_get_subscription exists.
		if ( function_exists( 'wcs_get_subscription' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if WC Subscriptions is active.
	 *
	 * @since 1.1.0
	 * @return bool True if active.
	 */
	public function is_active() {
		return $this->is_active;
	}

	/**
	 * Register subscription abilities with the Abilities Registry.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_subscription_abilities() {
		$registry = \Assistify_For_WooCommerce\Abilities\Abilities_Registry::instance();

		// Add subscriptions category.
		$registry->add_category( 'subscriptions', __( 'Subscriptions', 'assistify-for-woocommerce' ) );

		// Register admin abilities.
		$this->register_admin_abilities( $registry );

		// Register customer abilities.
		$this->register_customer_abilities( $registry );
	}

	/**
	 * Register admin subscription abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_admin_abilities( $registry ) {
		// List subscriptions.
		$registry->register(
			'afw/subscriptions/list',
			array(
				'name'        => __( 'List Subscriptions', 'assistify-for-woocommerce' ),
				'description' => __( 'List all subscriptions with optional filters.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscriptions_list' ),
				'parameters'  => array(
					'status'      => array(
						'type'        => 'string',
						'description' => __( 'Filter by status (active, on-hold, cancelled, expired, pending-cancel).', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'Filter by customer ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'product_id'  => array(
						'type'        => 'integer',
						'description' => __( 'Filter by product ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
					'limit'       => array(
						'type'        => 'integer',
						'description' => __( 'Number of subscriptions to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
					'orderby'     => array(
						'type'        => 'string',
						'description' => __( 'Order by field (date, total, ID).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'date',
					),
					'order'       => array(
						'type'        => 'string',
						'description' => __( 'Sort order (ASC or DESC).', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 'DESC',
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Get subscription details.
		$registry->register(
			'afw/subscriptions/get',
			array(
				'name'        => __( 'Get Subscription', 'assistify-for-woocommerce' ),
				'description' => __( 'Get detailed information about a specific subscription.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscriptions_get' ),
				'parameters'  => array(
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Search subscriptions.
		$registry->register(
			'afw/subscriptions/search',
			array(
				'name'        => __( 'Search Subscriptions', 'assistify-for-woocommerce' ),
				'description' => __( 'Search subscriptions by customer name, email, or subscription ID.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscriptions_search' ),
				'parameters'  => array(
					'query'  => array(
						'type'        => 'string',
						'description' => __( 'Search query (customer name, email, or subscription ID).', 'assistify-for-woocommerce' ),
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

		// Subscription analytics.
		$registry->register(
			'afw/subscriptions/analytics',
			array(
				'name'        => __( 'Subscription Analytics', 'assistify-for-woocommerce' ),
				'description' => __( 'Get subscription analytics including MRR, churn rate, and LTV.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscriptions_analytics' ),
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

		// Churn risk analysis.
		$registry->register(
			'afw/subscriptions/churn-risk',
			array(
				'name'        => __( 'Churn Risk Analysis', 'assistify-for-woocommerce' ),
				'description' => __( 'Identify subscriptions at risk of churning based on payment failures and activity.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscriptions_churn_risk' ),
				'parameters'  => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of at-risk subscriptions to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Failed payments.
		$registry->register(
			'afw/subscriptions/failed-payments',
			array(
				'name'        => __( 'Failed Subscription Payments', 'assistify-for-woocommerce' ),
				'description' => __( 'List subscriptions with failed renewal payments.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscriptions_failed_payments' ),
				'parameters'  => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of failed subscriptions to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);

		// Retry failed payment.
		$registry->register(
			'afw/subscriptions/retry-payment',
			array(
				'name'         => __( 'Retry Subscription Payment', 'assistify-for-woocommerce' ),
				'description'  => __( 'Retry a failed subscription renewal payment.', 'assistify-for-woocommerce' ),
				'category'     => 'subscriptions',
				'callback'     => array( $this, 'ability_subscriptions_retry_payment' ),
				'parameters'   => array(
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'   => 'manage_woocommerce',
				'confirmation' => true,
			)
		);

		// Update subscription status.
		$registry->register(
			'afw/subscriptions/update-status',
			array(
				'name'         => __( 'Update Subscription Status', 'assistify-for-woocommerce' ),
				'description'  => __( 'Update the status of a subscription.', 'assistify-for-woocommerce' ),
				'category'     => 'subscriptions',
				'callback'     => array( $this, 'ability_subscriptions_update_status' ),
				'parameters'   => array(
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'status'          => array(
						'type'        => 'string',
						'description' => __( 'New status (active, on-hold, cancelled).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'note'            => array(
						'type'        => 'string',
						'description' => __( 'Optional note to add to the subscription.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'   => 'manage_woocommerce',
				'confirmation' => true,
			)
		);

		// Expiring soon subscriptions.
		$registry->register(
			'afw/subscriptions/expiring-soon',
			array(
				'name'        => __( 'Expiring Subscriptions', 'assistify-for-woocommerce' ),
				'description' => __( 'List subscriptions expiring within a specified timeframe.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscriptions_expiring_soon' ),
				'parameters'  => array(
					'days'  => array(
						'type'        => 'integer',
						'description' => __( 'Number of days to check for expiring subscriptions.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 30,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => __( 'Number of subscriptions to return.', 'assistify-for-woocommerce' ),
						'required'    => false,
						'default'     => 10,
					),
				),
				'capability'  => 'manage_woocommerce',
			)
		);
	}

	/**
	 * Register customer subscription abilities.
	 *
	 * @since 1.1.0
	 * @param \Assistify_For_WooCommerce\Abilities\Abilities_Registry $registry The abilities registry.
	 * @return void
	 */
	private function register_customer_abilities( $registry ) {
		// Get own subscription details.
		$registry->register(
			'afw/subscription/my-subscriptions',
			array(
				'name'        => __( 'My Subscriptions', 'assistify-for-woocommerce' ),
				'description' => __( 'Get customer\'s own subscriptions.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_customer_subscriptions' ),
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

		// Get next payment date.
		$registry->register(
			'afw/subscription/next-payment',
			array(
				'name'        => __( 'Next Payment', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the next payment date and amount for a subscription.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscription_next_payment' ),
				'parameters'  => array(
					'customer_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'Optional specific subscription ID.', 'assistify-for-woocommerce' ),
						'required'    => false,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Pause subscription.
		$registry->register(
			'afw/subscription/pause',
			array(
				'name'         => __( 'Pause Subscription', 'assistify-for-woocommerce' ),
				'description'  => __( 'Pause an active subscription (put on hold).', 'assistify-for-woocommerce' ),
				'category'     => 'subscriptions',
				'callback'     => array( $this, 'ability_subscription_pause' ),
				'parameters'   => array(
					'customer_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID to pause.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'   => 'read',
				'scope'        => 'customer',
				'confirmation' => true,
			)
		);

		// Resume subscription.
		$registry->register(
			'afw/subscription/resume',
			array(
				'name'         => __( 'Resume Subscription', 'assistify-for-woocommerce' ),
				'description'  => __( 'Resume a paused (on-hold) subscription.', 'assistify-for-woocommerce' ),
				'category'     => 'subscriptions',
				'callback'     => array( $this, 'ability_subscription_resume' ),
				'parameters'   => array(
					'customer_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID to resume.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'   => 'read',
				'scope'        => 'customer',
				'confirmation' => true,
			)
		);

		// Cancel subscription.
		$registry->register(
			'afw/subscription/cancel',
			array(
				'name'         => __( 'Cancel Subscription', 'assistify-for-woocommerce' ),
				'description'  => __( 'Cancel an active subscription.', 'assistify-for-woocommerce' ),
				'category'     => 'subscriptions',
				'callback'     => array( $this, 'ability_subscription_cancel' ),
				'parameters'   => array(
					'customer_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID to cancel.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'   => 'read',
				'scope'        => 'customer',
				'confirmation' => true,
			)
		);

		// Update shipping address.
		$registry->register(
			'afw/subscription/update-address',
			array(
				'name'        => __( 'Update Subscription Address', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the URL to update shipping address for a subscription.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscription_update_address' ),
				'parameters'  => array(
					'customer_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
				),
				'capability'  => 'read',
				'scope'       => 'customer',
			)
		);

		// Update payment method.
		$registry->register(
			'afw/subscription/update-payment',
			array(
				'name'        => __( 'Update Payment Method', 'assistify-for-woocommerce' ),
				'description' => __( 'Get the URL to update payment method for a subscription.', 'assistify-for-woocommerce' ),
				'category'    => 'subscriptions',
				'callback'    => array( $this, 'ability_subscription_update_payment' ),
				'parameters'  => array(
					'customer_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The customer ID (from context).', 'assistify-for-woocommerce' ),
						'required'    => true,
					),
					'subscription_id' => array(
						'type'        => 'integer',
						'description' => __( 'The subscription ID.', 'assistify-for-woocommerce' ),
						'required'    => true,
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
	 * List subscriptions with filters.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Subscription list data.
	 */
	public function ability_subscriptions_list( $params ) {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$args = array(
			'subscriptions_per_page' => isset( $params['limit'] ) ? absint( $params['limit'] ) : 10,
			'orderby'                => isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'date',
			'order'                  => isset( $params['order'] ) ? strtoupper( sanitize_text_field( $params['order'] ) ) : 'DESC',
		);

		// Add status filter.
		if ( ! empty( $params['status'] ) ) {
			$status = sanitize_text_field( $params['status'] );
			// Ensure status has wc- prefix.
			if ( strpos( $status, 'wc-' ) !== 0 ) {
				$status = 'wc-' . $status;
			}
			$args['subscription_status'] = $status;
		}

		// Add customer filter.
		if ( ! empty( $params['customer_id'] ) ) {
			$args['customer_id'] = absint( $params['customer_id'] );
		}

		// Add product filter.
		if ( ! empty( $params['product_id'] ) ) {
			$args['product_id'] = absint( $params['product_id'] );
		}

		$subscriptions = wcs_get_subscriptions( $args );
		$result        = array();

		foreach ( $subscriptions as $subscription ) {
			$result[] = $this->format_subscription_data( $subscription );
		}

		return array(
			'success'       => true,
			'subscriptions' => $result,
			'count'         => count( $result ),
			'filters'       => array(
				'status'      => $params['status'] ?? 'all',
				'customer_id' => $params['customer_id'] ?? null,
				'product_id'  => $params['product_id'] ?? null,
			),
		);
	}

	/**
	 * Get subscription details.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Subscription details.
	 */
	public function ability_subscriptions_get( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$subscription_id = absint( $params['subscription_id'] );
		$subscription    = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success'      => true,
			'subscription' => $this->format_subscription_data( $subscription, true ),
		);
	}

	/**
	 * Search subscriptions.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Search results.
	 */
	public function ability_subscriptions_search( $params ) {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$query = sanitize_text_field( $params['query'] );
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$results = array();

		// Check if query is a subscription ID.
		if ( is_numeric( $query ) ) {
			$subscription = wcs_get_subscription( absint( $query ) );
			if ( $subscription ) {
				$results[] = $this->format_subscription_data( $subscription );
			}
		}

		// Search by customer email.
		if ( is_email( $query ) ) {
			$user = get_user_by( 'email', $query );
			if ( $user ) {
				$args = array(
					'customer_id'            => $user->ID,
					'subscriptions_per_page' => $limit,
				);
				if ( ! empty( $params['status'] ) ) {
					$status = sanitize_text_field( $params['status'] );
					if ( strpos( $status, 'wc-' ) !== 0 ) {
						$status = 'wc-' . $status;
					}
					$args['subscription_status'] = $status;
				}
				$subscriptions = wcs_get_subscriptions( $args );
				foreach ( $subscriptions as $subscription ) {
					$results[] = $this->format_subscription_data( $subscription );
				}
			}
		}

		// Search by customer name (if no results yet).
		if ( empty( $results ) && ! is_numeric( $query ) && ! is_email( $query ) ) {
			$users = get_users(
				array(
					'search'         => '*' . $query . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
					'number'         => 10,
				)
			);

			foreach ( $users as $user ) {
				$user_subs = wcs_get_subscriptions(
					array(
						'customer_id'            => $user->ID,
						'subscriptions_per_page' => $limit,
					)
				);
				foreach ( $user_subs as $subscription ) {
					$results[] = $this->format_subscription_data( $subscription );
				}
			}
		}

		// Limit results.
		$results = array_slice( $results, 0, $limit );

		return array(
			'success'       => true,
			'subscriptions' => $results,
			'count'         => count( $results ),
			'query'         => $query,
		);
	}

	/**
	 * Get subscription analytics.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Analytics data.
	 */
	public function ability_subscriptions_analytics( $params ) {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
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

		// Get all active subscriptions for MRR calculation.
		$active_subs = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'wc-active',
				'subscriptions_per_page' => -1,
			)
		);

		// Calculate MRR (Monthly Recurring Revenue).
		$mrr = 0;
		foreach ( $active_subs as $subscription ) {
			$mrr += $this->calculate_subscription_mrr( $subscription );
		}

		// Get subscription counts by status.
		$status_counts = array();
		if ( function_exists( 'wcs_get_subscription_statuses' ) ) {
			foreach ( wcs_get_subscription_statuses() as $status => $label ) {
				$subs = wcs_get_subscriptions(
					array(
						'subscription_status'    => $status,
						'subscriptions_per_page' => -1,
					)
				);
				$status_counts[ str_replace( 'wc-', '', $status ) ] = count( $subs );
			}
		}

		// Calculate churn rate (cancelled/expired in period vs active at start).
		$cancelled_count = 0;
		$cancelled_subs  = wcs_get_subscriptions(
			array(
				'subscription_status'    => array( 'wc-cancelled', 'wc-expired' ),
				'subscriptions_per_page' => -1,
			)
		);
		foreach ( $cancelled_subs as $sub ) {
			$cancelled_date = $sub->get_date( 'cancelled' );
			if ( $cancelled_date && strtotime( $cancelled_date ) >= strtotime( $date_from ) ) {
				++$cancelled_count;
			}
		}

		$total_active = count( $active_subs );
		$churn_rate   = $total_active > 0 ? round( ( $cancelled_count / $total_active ) * 100, 2 ) : 0;

		// Calculate Average Revenue Per User (ARPU).
		$total_revenue = 0;
		foreach ( $active_subs as $subscription ) {
			$total_revenue += floatval( $subscription->get_total() );
		}
		$arpu = $total_active > 0 ? round( $total_revenue / $total_active, 2 ) : 0;

		// Calculate Lifetime Value (LTV) estimate.
		// LTV = ARPU * Average Subscription Length (in months).
		$avg_length_months = 12; // Default estimate.
		$ltv               = round( $arpu * $avg_length_months, 2 );

		// New subscriptions in period.
		$new_count = 0;
		foreach ( $active_subs as $sub ) {
			$start_date = $sub->get_date( 'start' );
			if ( $start_date && strtotime( $start_date ) >= strtotime( $date_from ) ) {
				++$new_count;
			}
		}

		return array(
			'success'   => true,
			'period'    => $period,
			'analytics' => array(
				'mrr'                  => array(
					'value'    => round( $mrr, 2 ),
					'currency' => get_woocommerce_currency(),
					'label'    => __( 'Monthly Recurring Revenue', 'assistify-for-woocommerce' ),
				),
				'arr'                  => array(
					'value'    => round( $mrr * 12, 2 ),
					'currency' => get_woocommerce_currency(),
					'label'    => __( 'Annual Recurring Revenue', 'assistify-for-woocommerce' ),
				),
				'active_subscriptions' => $total_active,
				'new_subscriptions'    => $new_count,
				'cancelled_in_period'  => $cancelled_count,
				'churn_rate'           => array(
					'value' => $churn_rate,
					'label' => __( 'Churn Rate', 'assistify-for-woocommerce' ),
				),
				'arpu'                 => array(
					'value'    => $arpu,
					'currency' => get_woocommerce_currency(),
					'label'    => __( 'Average Revenue Per User', 'assistify-for-woocommerce' ),
				),
				'ltv'                  => array(
					'value'    => $ltv,
					'currency' => get_woocommerce_currency(),
					'label'    => __( 'Estimated Lifetime Value', 'assistify-for-woocommerce' ),
				),
				'status_breakdown'     => $status_counts,
			),
		);
	}

	/**
	 * Identify subscriptions at risk of churning.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array At-risk subscriptions.
	 */
	public function ability_subscriptions_churn_risk( $params ) {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$limit   = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;
		$at_risk = array();

		// Get subscriptions on-hold (payment issues).
		$on_hold_subs = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'wc-on-hold',
				'subscriptions_per_page' => $limit,
			)
		);

		foreach ( $on_hold_subs as $subscription ) {
			$risk_data                = $this->format_subscription_data( $subscription );
			$risk_data['risk_level']  = 'high';
			$risk_data['risk_reason'] = __( 'Subscription is on hold - likely payment failure.', 'assistify-for-woocommerce' );
			$at_risk[]                = $risk_data;
		}

		// Get pending-cancel subscriptions.
		$pending_cancel = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'wc-pending-cancel',
				'subscriptions_per_page' => $limit,
			)
		);

		foreach ( $pending_cancel as $subscription ) {
			$risk_data                = $this->format_subscription_data( $subscription );
			$risk_data['risk_level']  = 'critical';
			$risk_data['risk_reason'] = __( 'Subscription is pending cancellation.', 'assistify-for-woocommerce' );
			$at_risk[]                = $risk_data;
		}

		// Get active subscriptions with multiple payment failures.
		$active_subs = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'wc-active',
				'subscriptions_per_page' => 50,
			)
		);

		foreach ( $active_subs as $subscription ) {
			// Check for failed renewal orders.
			$failed_orders = $subscription->get_related_orders( 'ids', 'renewal' );
			$failed_count  = 0;

			foreach ( $failed_orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order && $order->has_status( 'failed' ) ) {
					++$failed_count;
				}
			}

			if ( $failed_count > 0 ) {
				$risk_data               = $this->format_subscription_data( $subscription );
				$risk_data['risk_level'] = $failed_count >= 2 ? 'high' : 'medium';
				// Translators: %d is the number of failed payment attempts.
				$risk_data['risk_reason']     = sprintf( __( '%d failed payment attempt(s) recorded.', 'assistify-for-woocommerce' ), $failed_count );
				$risk_data['failed_payments'] = $failed_count;
				$at_risk[]                    = $risk_data;
			}
		}

		// Sort by risk level.
		usort(
			$at_risk,
			function ( $a, $b ) {
				$risk_order = array(
					'critical' => 1,
					'high'     => 2,
					'medium'   => 3,
				);
				return ( $risk_order[ $a['risk_level'] ] ?? 4 ) <=> ( $risk_order[ $b['risk_level'] ] ?? 4 );
			}
		);

		// Limit results.
		$at_risk = array_slice( $at_risk, 0, $limit );

		return array(
			'success'               => true,
			'at_risk_subscriptions' => $at_risk,
			'count'                 => count( $at_risk ),
		);
	}

	/**
	 * List subscriptions with failed payments.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Failed payment subscriptions.
	 */
	public function ability_subscriptions_failed_payments( $params ) {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$limit  = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;
		$result = array();

		// Get on-hold subscriptions (usually due to payment failure).
		$on_hold_subs = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'wc-on-hold',
				'subscriptions_per_page' => $limit,
			)
		);

		foreach ( $on_hold_subs as $subscription ) {
			$sub_data = $this->format_subscription_data( $subscription );

			// Get the last failed order.
			$renewal_orders = $subscription->get_related_orders( 'ids', 'renewal' );
			foreach ( $renewal_orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order && $order->has_status( 'failed' ) ) {
					$sub_data['last_failed_order'] = array(
						'id'     => $order->get_id(),
						'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
						'total'  => $order->get_total(),
						'reason' => $order->get_meta( '_failed_order_reason' ) ? $order->get_meta( '_failed_order_reason' ) : __( 'Payment declined', 'assistify-for-woocommerce' ),
					);
					break;
				}
			}

			$result[] = $sub_data;
		}

		return array(
			'success'       => true,
			'subscriptions' => $result,
			'count'         => count( $result ),
		);
	}

	/**
	 * Retry a failed subscription payment.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Retry result.
	 */
	public function ability_subscriptions_retry_payment( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$subscription_id = absint( $params['subscription_id'] );
		$subscription    = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found.', 'assistify-for-woocommerce' ),
			);
		}

		// Check if subscription can have payment retried.
		if ( ! $subscription->has_status( 'on-hold' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Payment can only be retried for on-hold subscriptions.', 'assistify-for-woocommerce' ),
			);
		}

		// Get the payment gateway.
		$payment_gateway = wc_get_payment_gateway_by_order( $subscription );
		if ( ! $payment_gateway ) {
			return array(
				'success' => false,
				'error'   => __( 'No payment gateway found for this subscription.', 'assistify-for-woocommerce' ),
			);
		}

		// Create a new renewal order.
		if ( function_exists( 'wcs_create_renewal_order' ) ) {
			$renewal_order = wcs_create_renewal_order( $subscription );

			if ( is_wp_error( $renewal_order ) ) {
				return array(
					'success' => false,
					'error'   => $renewal_order->get_error_message(),
				);
			}

			// Add a note about the retry.
			$subscription->add_order_note( __( 'Payment retry initiated via Assistify AI.', 'assistify-for-woocommerce' ) );

			return array(
				'success'          => true,
				'message'          => __( 'Renewal order created for payment retry.', 'assistify-for-woocommerce' ),
				'renewal_order_id' => $renewal_order->get_id(),
				'subscription'     => $this->format_subscription_data( $subscription ),
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'Unable to create renewal order.', 'assistify-for-woocommerce' ),
		);
	}

	/**
	 * Update subscription status.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Update result.
	 */
	public function ability_subscriptions_update_status( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$subscription_id = absint( $params['subscription_id'] );
		$new_status      = sanitize_text_field( $params['status'] );
		$note            = isset( $params['note'] ) ? sanitize_textarea_field( $params['note'] ) : '';

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found.', 'assistify-for-woocommerce' ),
			);
		}

		// Validate new status.
		$valid_statuses = array( 'active', 'on-hold', 'cancelled', 'pending-cancel' );
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid subscription status.', 'assistify-for-woocommerce' ),
			);
		}

		$old_status = $subscription->get_status();

		// Update the status.
		try {
			$subscription->update_status( $new_status, $note ? $note : __( 'Status updated via Assistify AI.', 'assistify-for-woocommerce' ) );

			return array(
				'success'      => true,
				'message'      => sprintf(
					// Translators: %1$s is the old status, %2$s is the new status.
					__( 'Subscription status updated from %1$s to %2$s.', 'assistify-for-woocommerce' ),
					$old_status,
					$new_status
				),
				'subscription' => $this->format_subscription_data( $subscription ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get subscriptions expiring soon.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Expiring subscriptions.
	 */
	public function ability_subscriptions_expiring_soon( $params ) {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$days  = isset( $params['days'] ) ? absint( $params['days'] ) : 30;
		$limit = isset( $params['limit'] ) ? absint( $params['limit'] ) : 10;

		$expiring_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
		$now           = gmdate( 'Y-m-d H:i:s' );

		// Get active subscriptions.
		$active_subs = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'wc-active',
				'subscriptions_per_page' => -1,
			)
		);

		$expiring = array();

		foreach ( $active_subs as $subscription ) {
			$end_date = $subscription->get_date( 'end' );
			if ( $end_date && strtotime( $end_date ) >= strtotime( $now ) && strtotime( $end_date ) <= strtotime( $expiring_date ) ) {
				$sub_data                      = $this->format_subscription_data( $subscription );
				$sub_data['days_until_expiry'] = ceil( ( strtotime( $end_date ) - time() ) / DAY_IN_SECONDS );
				$expiring[]                    = $sub_data;
			}
		}

		// Sort by days until expiry.
		usort(
			$expiring,
			function ( $a, $b ) {
				return $a['days_until_expiry'] <=> $b['days_until_expiry'];
			}
		);

		// Limit results.
		$expiring = array_slice( $expiring, 0, $limit );

		return array(
			'success'       => true,
			'subscriptions' => $expiring,
			'count'         => count( $expiring ),
			'days_checked'  => $days,
		);
	}

	// =========================================================================
	// CUSTOMER ABILITY CALLBACKS
	// =========================================================================

	/**
	 * Get customer's subscriptions.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Customer subscriptions.
	 */
	public function ability_customer_subscriptions( $params ) {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		$subscriptions = wcs_get_users_subscriptions( $customer_id );
		$result        = array();

		foreach ( $subscriptions as $subscription ) {
			// Filter by status if provided.
			if ( ! empty( $params['status'] ) ) {
				$status = sanitize_text_field( $params['status'] );
				if ( ! $subscription->has_status( $status ) ) {
					continue;
				}
			}

			$result[] = $this->format_subscription_for_customer( $subscription );
		}

		if ( empty( $result ) ) {
			return array(
				'success'       => true,
				'message'       => __( 'You do not have any subscriptions.', 'assistify-for-woocommerce' ),
				'subscriptions' => array(),
			);
		}

		return array(
			'success'       => true,
			'subscriptions' => $result,
			'count'         => count( $result ),
		);
	}

	/**
	 * Get next payment date for customer subscription.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Next payment info.
	 */
	public function ability_subscription_next_payment( $params ) {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id = absint( $params['customer_id'] );
		if ( ! $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Customer ID is required.', 'assistify-for-woocommerce' ),
			);
		}

		// If specific subscription ID provided.
		if ( ! empty( $params['subscription_id'] ) ) {
			$subscription = wcs_get_subscription( absint( $params['subscription_id'] ) );

			if ( ! $subscription || $subscription->get_user_id() !== $customer_id ) {
				return array(
					'success' => false,
					'error'   => __( 'Subscription not found or does not belong to you.', 'assistify-for-woocommerce' ),
				);
			}

			$next_payment = $subscription->get_date( 'next_payment' );

			return array(
				'success'             => true,
				'subscription_id'     => $subscription->get_id(),
				'next_payment_date'   => $next_payment ? date_i18n( get_option( 'date_format' ), strtotime( $next_payment ) ) : __( 'N/A', 'assistify-for-woocommerce' ),
				'next_payment_amount' => wc_price( $subscription->get_total() ),
				'billing_period'      => $subscription->get_billing_period(),
				'billing_interval'    => $subscription->get_billing_interval(),
			);
		}

		// Get all active subscriptions for customer.
		$subscriptions = wcs_get_users_subscriptions( $customer_id );
		$results       = array();

		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->has_status( 'active' ) ) {
				$next_payment = $subscription->get_date( 'next_payment' );
				$results[]    = array(
					'subscription_id'     => $subscription->get_id(),
					'product'             => $this->get_subscription_product_name( $subscription ),
					'next_payment_date'   => $next_payment ? date_i18n( get_option( 'date_format' ), strtotime( $next_payment ) ) : __( 'N/A', 'assistify-for-woocommerce' ),
					'next_payment_amount' => wc_price( $subscription->get_total() ),
				);
			}
		}

		if ( empty( $results ) ) {
			return array(
				'success' => true,
				'message' => __( 'You do not have any active subscriptions with upcoming payments.', 'assistify-for-woocommerce' ),
			);
		}

		return array(
			'success'       => true,
			'next_payments' => $results,
		);
	}

	/**
	 * Pause customer subscription.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Pause result.
	 */
	public function ability_subscription_pause( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id     = absint( $params['customer_id'] );
		$subscription_id = absint( $params['subscription_id'] );

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription || $subscription->get_user_id() !== $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found or does not belong to you.', 'assistify-for-woocommerce' ),
			);
		}

		// Check if customer can suspend.
		if ( ! $subscription->can_be_updated_to( 'on-hold' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This subscription cannot be paused at this time.', 'assistify-for-woocommerce' ),
			);
		}

		// Check suspension limits.
		if ( function_exists( 'wcs_can_user_put_subscription_on_hold' ) ) {
			if ( ! wcs_can_user_put_subscription_on_hold( $subscription, $customer_id ) ) {
				return array(
					'success' => false,
					'error'   => __( 'You have reached the maximum number of suspensions allowed for this subscription.', 'assistify-for-woocommerce' ),
				);
			}
		}

		try {
			$subscription->update_status( 'on-hold', __( 'Customer paused subscription via Assistify.', 'assistify-for-woocommerce' ) );

			return array(
				'success'      => true,
				'message'      => __( 'Your subscription has been paused. You can resume it anytime.', 'assistify-for-woocommerce' ),
				'subscription' => $this->format_subscription_for_customer( $subscription ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Resume customer subscription.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Resume result.
	 */
	public function ability_subscription_resume( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id     = absint( $params['customer_id'] );
		$subscription_id = absint( $params['subscription_id'] );

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription || $subscription->get_user_id() !== $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found or does not belong to you.', 'assistify-for-woocommerce' ),
			);
		}

		// Check if subscription is on-hold.
		if ( ! $subscription->has_status( 'on-hold' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Only paused subscriptions can be resumed.', 'assistify-for-woocommerce' ),
			);
		}

		// Check if can be reactivated.
		if ( ! $subscription->can_be_updated_to( 'active' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This subscription cannot be resumed at this time.', 'assistify-for-woocommerce' ),
			);
		}

		try {
			$subscription->update_status( 'active', __( 'Customer resumed subscription via Assistify.', 'assistify-for-woocommerce' ) );

			return array(
				'success'      => true,
				'message'      => __( 'Your subscription has been resumed and is now active.', 'assistify-for-woocommerce' ),
				'subscription' => $this->format_subscription_for_customer( $subscription ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Cancel customer subscription.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Cancel result.
	 */
	public function ability_subscription_cancel( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id     = absint( $params['customer_id'] );
		$subscription_id = absint( $params['subscription_id'] );

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription || $subscription->get_user_id() !== $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found or does not belong to you.', 'assistify-for-woocommerce' ),
			);
		}

		// Check if can be cancelled.
		if ( ! $subscription->can_be_updated_to( 'cancelled' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This subscription cannot be cancelled at this time.', 'assistify-for-woocommerce' ),
			);
		}

		try {
			$subscription->update_status( 'cancelled', __( 'Customer cancelled subscription via Assistify.', 'assistify-for-woocommerce' ) );

			return array(
				'success'      => true,
				'message'      => __( 'Your subscription has been cancelled. We\'re sorry to see you go!', 'assistify-for-woocommerce' ),
				'subscription' => $this->format_subscription_for_customer( $subscription ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get URL to update subscription shipping address.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Address update URL.
	 */
	public function ability_subscription_update_address( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id     = absint( $params['customer_id'] );
		$subscription_id = absint( $params['subscription_id'] );

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription || $subscription->get_user_id() !== $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found or does not belong to you.', 'assistify-for-woocommerce' ),
			);
		}

		// Get the edit address URL.
		$edit_url = wc_get_endpoint_url( 'edit-address', 'shipping', wc_get_page_permalink( 'myaccount' ) );

		return array(
			'success'         => true,
			'message'         => __( 'You can update your shipping address using the link below.', 'assistify-for-woocommerce' ),
			'url'             => $edit_url,
			'current_address' => array(
				'address_1' => $subscription->get_shipping_address_1(),
				'address_2' => $subscription->get_shipping_address_2(),
				'city'      => $subscription->get_shipping_city(),
				'state'     => $subscription->get_shipping_state(),
				'postcode'  => $subscription->get_shipping_postcode(),
				'country'   => $subscription->get_shipping_country(),
			),
		);
	}

	/**
	 * Get URL to update subscription payment method.
	 *
	 * @since 1.1.0
	 * @param array $params Ability parameters.
	 * @return array Payment update URL.
	 */
	public function ability_subscription_update_payment( $params ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'WooCommerce Subscriptions is not available.', 'assistify-for-woocommerce' ),
			);
		}

		$customer_id     = absint( $params['customer_id'] );
		$subscription_id = absint( $params['subscription_id'] );

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription || $subscription->get_user_id() !== $customer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Subscription not found or does not belong to you.', 'assistify-for-woocommerce' ),
			);
		}

		// Get the change payment method URL.
		$change_payment_url = '';
		if ( function_exists( 'wcs_get_change_payment_method_url' ) ) {
			$change_payment_url = wcs_get_change_payment_method_url( $subscription );
		} else {
			// Fallback: construct the URL manually.
			$change_payment_url = add_query_arg(
				array(
					'change_payment_method' => $subscription_id,
				),
				$subscription->get_checkout_payment_url()
			);
		}

		return array(
			'success'        => true,
			'message'        => __( 'You can update your payment method using the link below.', 'assistify-for-woocommerce' ),
			'url'            => $change_payment_url,
			'current_method' => $subscription->get_payment_method_title(),
		);
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Format subscription data for response.
	 *
	 * @since 1.1.0
	 * @param object $subscription The WC_Subscription object.
	 * @param bool   $detailed     Whether to include detailed information.
	 * @return array Formatted subscription data.
	 */
	private function format_subscription_data( $subscription, $detailed = false ) {
		$data = array(
			'id'               => $subscription->get_id(),
			'status'           => $subscription->get_status(),
			'status_label'     => wcs_get_subscription_status_name( $subscription->get_status() ),
			'customer'         => array(
				'id'    => $subscription->get_user_id(),
				'name'  => $subscription->get_formatted_billing_full_name(),
				'email' => $subscription->get_billing_email(),
			),
			'product'          => $this->get_subscription_product_name( $subscription ),
			'total'            => $subscription->get_total(),
			'total_formatted'  => $subscription->get_formatted_order_total(),
			'billing_period'   => $subscription->get_billing_period(),
			'billing_interval' => $subscription->get_billing_interval(),
			'start_date'       => $subscription->get_date( 'start' ),
			'next_payment'     => $subscription->get_date( 'next_payment' ),
			'end_date'         => $subscription->get_date( 'end' ),
			'payment_method'   => $subscription->get_payment_method_title(),
		);

		if ( $detailed ) {
			$data['trial_end']               = $subscription->get_date( 'trial_end' );
			$data['requires_manual_renewal'] = $subscription->get_requires_manual_renewal();
			$data['suspension_count']        = $subscription->get_suspension_count();
			$data['billing_address']         = array(
				'first_name' => $subscription->get_billing_first_name(),
				'last_name'  => $subscription->get_billing_last_name(),
				'company'    => $subscription->get_billing_company(),
				'address_1'  => $subscription->get_billing_address_1(),
				'address_2'  => $subscription->get_billing_address_2(),
				'city'       => $subscription->get_billing_city(),
				'state'      => $subscription->get_billing_state(),
				'postcode'   => $subscription->get_billing_postcode(),
				'country'    => $subscription->get_billing_country(),
				'email'      => $subscription->get_billing_email(),
				'phone'      => $subscription->get_billing_phone(),
			);
			$data['shipping_address']        = array(
				'first_name' => $subscription->get_shipping_first_name(),
				'last_name'  => $subscription->get_shipping_last_name(),
				'company'    => $subscription->get_shipping_company(),
				'address_1'  => $subscription->get_shipping_address_1(),
				'address_2'  => $subscription->get_shipping_address_2(),
				'city'       => $subscription->get_shipping_city(),
				'state'      => $subscription->get_shipping_state(),
				'postcode'   => $subscription->get_shipping_postcode(),
				'country'    => $subscription->get_shipping_country(),
			);
			$data['line_items']              = array();
			foreach ( $subscription->get_items() as $item ) {
				$data['line_items'][] = array(
					'name'     => $item->get_name(),
					'quantity' => $item->get_quantity(),
					'total'    => $item->get_total(),
				);
			}
			$data['related_orders'] = array(
				'parent'   => $subscription->get_parent_id(),
				'renewals' => $subscription->get_related_orders( 'ids', 'renewal' ),
			);
			$data['admin_url']      = admin_url( 'post.php?post=' . $subscription->get_id() . '&action=edit' );
		}

		return $data;
	}

	/**
	 * Format subscription data for customer response (limited info).
	 *
	 * @since 1.1.0
	 * @param object $subscription The WC_Subscription object.
	 * @return array Formatted subscription data for customer.
	 */
	private function format_subscription_for_customer( $subscription ) {
		return array(
			'id'                => $subscription->get_id(),
			'status'            => $subscription->get_status(),
			'status_label'      => wcs_get_subscription_status_name( $subscription->get_status() ),
			'product'           => $this->get_subscription_product_name( $subscription ),
			'total'             => $subscription->get_formatted_order_total(),
			'billing_schedule'  => sprintf(
				// Translators: %1$s is the billing interval, %2$s is the billing period (e.g., "every 1 month").
				__( 'Every %1$s %2$s', 'assistify-for-woocommerce' ),
				$subscription->get_billing_interval(),
				$subscription->get_billing_period()
			),
			'start_date'        => date_i18n( get_option( 'date_format' ), strtotime( $subscription->get_date( 'start' ) ) ),
			'next_payment_date' => $subscription->get_date( 'next_payment' ) ? date_i18n( get_option( 'date_format' ), strtotime( $subscription->get_date( 'next_payment' ) ) ) : __( 'N/A', 'assistify-for-woocommerce' ),
			'end_date'          => $subscription->get_date( 'end' ) ? date_i18n( get_option( 'date_format' ), strtotime( $subscription->get_date( 'end' ) ) ) : __( 'N/A', 'assistify-for-woocommerce' ),
			'can_pause'         => $subscription->can_be_updated_to( 'on-hold' ),
			'can_resume'        => $subscription->has_status( 'on-hold' ) && $subscription->can_be_updated_to( 'active' ),
			'can_cancel'        => $subscription->can_be_updated_to( 'cancelled' ),
			'view_url'          => $subscription->get_view_order_url(),
		);
	}

	/**
	 * Get subscription product name(s).
	 *
	 * @since 1.1.0
	 * @param object $subscription The WC_Subscription object.
	 * @return string Product name(s).
	 */
	private function get_subscription_product_name( $subscription ) {
		$items = $subscription->get_items();
		$names = array();

		foreach ( $items as $item ) {
			$names[] = $item->get_name();
		}

		return implode( ', ', $names );
	}

	/**
	 * Calculate MRR for a subscription.
	 *
	 * @since 1.1.0
	 * @param object $subscription The WC_Subscription object.
	 * @return float Monthly recurring revenue.
	 */
	private function calculate_subscription_mrr( $subscription ) {
		$total    = floatval( $subscription->get_total() );
		$period   = $subscription->get_billing_period();
		$interval = absint( $subscription->get_billing_interval() );

		if ( $interval <= 0 ) {
			$interval = 1;
		}

		// Convert to monthly.
		switch ( $period ) {
			case 'day':
				return ( $total / $interval ) * 30;
			case 'week':
				return ( $total / $interval ) * 4.33;
			case 'month':
				return $total / $interval;
			case 'year':
				return ( $total / $interval ) / 12;
			default:
				return $total;
		}
	}
}
