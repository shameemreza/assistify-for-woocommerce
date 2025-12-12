<?php
/**
 * Audit Log Admin Page.
 *
 * Displays the audit log viewer in WordPress admin.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.1.0
 */

namespace Assistify_For_WooCommerce\Admin;

use Assistify_For_WooCommerce\Audit_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Log Page Class.
 *
 * @since 1.1.0
 */
class Audit_Log_Page {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var Audit_Log_Page|null
	 */
	private static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @since 1.1.0
	 * @var Audit_Logger
	 */
	private $logger;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.1.0
	 * @return Audit_Log_Page Instance.
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
		$this->logger = Audit_Logger::instance();

		// Priority 100 to appear after Store Health (priority 99).
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Assistify Activity', 'assistify-for-woocommerce' ),
			__( 'Assistify Activity', 'assistify-for-woocommerce' ),
			'manage_woocommerce',
			'assistify-audit-log',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 1.1.0
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_assistify-audit-log' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'assistify-audit-log',
			ASSISTIFY_PLUGIN_URL . 'assets/css/audit-log.css',
			array(),
			ASSISTIFY_VERSION
		);

		wp_enqueue_script(
			'assistify-audit-log',
			ASSISTIFY_PLUGIN_URL . 'assets/js/admin/audit-log.js',
			array( 'jquery' ),
			ASSISTIFY_VERSION,
			true
		);

		wp_localize_script(
			'assistify-audit-log',
			'assistifyAuditLog',
			array(
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'restUrl'  => rest_url( 'assistify/v1/audit-logs' ),
				'statsUrl' => rest_url( 'assistify/v1/audit-logs/stats' ),
				'i18n'     => array(
					'loading'    => __( 'Loading...', 'assistify-for-woocommerce' ),
					'noLogs'     => __( 'No activity logs found.', 'assistify-for-woocommerce' ),
					'error'      => __( 'Error loading logs.', 'assistify-for-woocommerce' ),
					'success'    => __( 'Success', 'assistify-for-woocommerce' ),
					'failed'     => __( 'Failed', 'assistify-for-woocommerce' ),
					'pending'    => __( 'Pending', 'assistify-for-woocommerce' ),
					/* translators: %s: time elapsed */
					'timeAgo'    => __( '%s ago', 'assistify-for-woocommerce' ),
					'viewMore'   => __( 'View Details', 'assistify-for-woocommerce' ),
					'parameters' => __( 'Parameters', 'assistify-for-woocommerce' ),
					'result'     => __( 'Result', 'assistify-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function render_page() {
		// Get stats for header.
		$stats = $this->logger->get_statistics( 30 );

		// Get available categories dynamically.
		$categories = $this->get_available_categories();
		?>
		<div class="wrap assistify-audit-log-wrap">
			<h1><?php esc_html_e( 'Assistify Activity', 'assistify-for-woocommerce' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Track all AI-triggered actions and activity in your store.', 'assistify-for-woocommerce' ); ?>
			</p>

			<!-- Stats Cards -->
			<div class="assistify-audit-stats">
				<div class="assistify-stat-card">
					<span class="assistify-stat-number"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
					<span class="assistify-stat-label"><?php esc_html_e( 'Total Actions (30 days)', 'assistify-for-woocommerce' ); ?></span>
				</div>
				<?php
				$success_count = 0;
				$failed_count  = 0;
				foreach ( $stats['by_status'] as $status ) {
					if ( 'success' === $status['status'] ) {
						$success_count = $status['count'];
					} elseif ( 'failed' === $status['status'] ) {
						$failed_count = $status['count'];
					}
				}
				?>
				<div class="assistify-stat-card assistify-stat-success">
					<span class="assistify-stat-number"><?php echo esc_html( number_format_i18n( $success_count ) ); ?></span>
					<span class="assistify-stat-label"><?php esc_html_e( 'Successful', 'assistify-for-woocommerce' ); ?></span>
				</div>
				<div class="assistify-stat-card assistify-stat-failed">
					<span class="assistify-stat-number"><?php echo esc_html( number_format_i18n( $failed_count ) ); ?></span>
					<span class="assistify-stat-label"><?php esc_html_e( 'Failed', 'assistify-for-woocommerce' ); ?></span>
				</div>
			</div>

			<!-- Filters -->
			<div class="assistify-audit-filters">
				<select id="assistify-filter-category">
					<option value=""><?php esc_html_e( 'All Categories', 'assistify-for-woocommerce' ); ?></option>
					<?php foreach ( $categories as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select id="assistify-filter-status">
					<option value=""><?php esc_html_e( 'All Statuses', 'assistify-for-woocommerce' ); ?></option>
					<option value="success"><?php esc_html_e( 'Success', 'assistify-for-woocommerce' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', 'assistify-for-woocommerce' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'assistify-for-woocommerce' ); ?></option>
				</select>

				<input type="search" id="assistify-filter-search" placeholder="<?php esc_attr_e( 'Search...', 'assistify-for-woocommerce' ); ?>">

				<button type="button" class="button" id="assistify-filter-apply">
					<?php esc_html_e( 'Filter', 'assistify-for-woocommerce' ); ?>
				</button>

				<a href="<?php echo esc_url( rest_url( 'assistify/v1/audit-logs/export' ) ); ?>" class="button" id="assistify-export-csv">
					<?php esc_html_e( 'Export CSV', 'assistify-for-woocommerce' ); ?>
				</a>
			</div>

			<!-- Log Table -->
			<table class="wp-list-table widefat fixed striped assistify-audit-table">
				<thead>
					<tr>
						<th class="column-time"><?php esc_html_e( 'Time', 'assistify-for-woocommerce' ); ?></th>
						<th class="column-user"><?php esc_html_e( 'User', 'assistify-for-woocommerce' ); ?></th>
						<th class="column-action"><?php esc_html_e( 'Action', 'assistify-for-woocommerce' ); ?></th>
						<th class="column-description"><?php esc_html_e( 'Description', 'assistify-for-woocommerce' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'assistify-for-woocommerce' ); ?></th>
						<th class="column-details"><?php esc_html_e( 'Details', 'assistify-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody id="assistify-audit-logs">
					<tr>
						<td colspan="6" class="assistify-loading">
							<?php esc_html_e( 'Loading activity logs...', 'assistify-for-woocommerce' ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Pagination -->
			<div class="assistify-audit-pagination">
				<button type="button" class="button" id="assistify-prev-page" disabled>
					<?php esc_html_e( '← Previous', 'assistify-for-woocommerce' ); ?>
				</button>
				<span id="assistify-page-info"></span>
				<button type="button" class="button" id="assistify-next-page">
					<?php esc_html_e( 'Next →', 'assistify-for-woocommerce' ); ?>
				</button>
			</div>

			<!-- Detail Modal -->
			<div id="assistify-log-detail-modal" class="assistify-modal" style="display: none;">
				<div class="assistify-modal-content">
					<button type="button" class="assistify-modal-close">&times;</button>
					<h2><?php esc_html_e( 'Action Details', 'assistify-for-woocommerce' ); ?></h2>
					<div id="assistify-log-detail-content"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get available categories based on active integrations.
	 *
	 * @since 1.1.0
	 * @return array Array of category value => label pairs.
	 */
	private function get_available_categories() {
		// Core categories always available.
		$categories = array(
			'orders'    => __( 'Orders', 'assistify-for-woocommerce' ),
			'products'  => __( 'Products', 'assistify-for-woocommerce' ),
			'customers' => __( 'Customers', 'assistify-for-woocommerce' ),
			'coupons'   => __( 'Coupons', 'assistify-for-woocommerce' ),
			'content'   => __( 'Content', 'assistify-for-woocommerce' ),
			'image'     => __( 'Images', 'assistify-for-woocommerce' ),
		);

		// Only add integration categories if the plugin is active.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$categories['subscriptions'] = __( 'Subscriptions', 'assistify-for-woocommerce' );
		}

		if ( class_exists( 'WC_Bookings' ) ) {
			$categories['bookings'] = __( 'Bookings', 'assistify-for-woocommerce' );
		}

		if ( function_exists( 'wc_memberships' ) ) {
			$categories['memberships'] = __( 'Memberships', 'assistify-for-woocommerce' );
		}

		/**
		 * Filter available audit log categories.
		 *
		 * @since 1.1.0
		 * @param array $categories Available categories.
		 */
		return apply_filters( 'assistify_audit_log_categories', $categories );
	}
}

