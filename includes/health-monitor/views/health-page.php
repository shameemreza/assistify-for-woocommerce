<?php
/**
 * Store Health Page View
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 *
 * @var array $results Health check results from Health_Monitor.
 */

defined( 'ABSPATH' ) || exit;

$assistify_score      = $results['score'] ?? 100;
$assistify_summary    = $results['summary'] ?? array();
$assistify_issues     = $results['issues'] ?? array();
$assistify_categories = $results['categories'] ?? array();
$assistify_timestamp  = $results['timestamp'] ?? current_time( 'mysql' );

// Get payment stats.
$assistify_monitor       = \Assistify\Health_Monitor\Health_Monitor::get_instance();
$assistify_payment_stats = $assistify_monitor->get_payment_stats();
$assistify_failures      = $assistify_monitor->get_recent_failures( 5 );

// Score status.
$assistify_score_status = 'excellent';
$assistify_score_color  = '#28a745';
if ( $assistify_score < 90 ) {
	$assistify_score_status = 'good';
	$assistify_score_color  = '#6861F2';
}
if ( $assistify_score < 70 ) {
	$assistify_score_status = 'fair';
	$assistify_score_color  = '#ffc107';
}
if ( $assistify_score < 50 ) {
	$assistify_score_status = 'poor';
	$assistify_score_color  = '#dc3545';
}
?>

<div class="wrap assistify-health-page">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Store Health', 'assistify-for-woocommerce' ); ?>
	</h1>

	<button type="button" class="page-title-action" id="assistify-refresh-health">
		<?php esc_html_e( 'Refresh', 'assistify-for-woocommerce' ); ?>
	</button>

	<hr class="wp-header-end">

	<!-- Score Overview -->
	<div class="assistify-health-overview">
		<div class="assistify-score-card">
			<div class="assistify-score-circle" style="--score-color: <?php echo esc_attr( $assistify_score_color ); ?>;">
				<span class="assistify-score-number"><?php echo (int) $assistify_score; ?></span>
				<span class="assistify-score-label">/100</span>
			</div>
			<div class="assistify-score-details">
				<h2>
					<?php
					switch ( $assistify_score_status ) {
						case 'excellent':
							esc_html_e( 'Excellent Health', 'assistify-for-woocommerce' );
							break;
						case 'good':
							esc_html_e( 'Good Health', 'assistify-for-woocommerce' );
							break;
						case 'fair':
							esc_html_e( 'Needs Attention', 'assistify-for-woocommerce' );
							break;
						default:
							esc_html_e( 'Critical Issues', 'assistify-for-woocommerce' );
					}
					?>
				</h2>
				<p><?php echo esc_html( $assistify_summary['message'] ?? '' ); ?></p>
				<div class="assistify-issue-counts">
					<?php if ( ( $assistify_summary['critical_count'] ?? 0 ) > 0 ) : ?>
						<span class="assistify-count-critical">
							<?php
							printf(
								/* translators: %d: number of critical issues */
								esc_html__( '%d Critical', 'assistify-for-woocommerce' ),
								(int) $assistify_summary['critical_count']
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( ( $assistify_summary['warning_count'] ?? 0 ) > 0 ) : ?>
						<span class="assistify-count-warning">
							<?php
							printf(
								/* translators: %d: number of warnings */
								esc_html__( '%d Warnings', 'assistify-for-woocommerce' ),
								(int) $assistify_summary['warning_count']
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( ( $assistify_summary['info_count'] ?? 0 ) > 0 ) : ?>
						<span class="assistify-count-info">
							<?php
							printf(
								/* translators: %d: number of suggestions */
								esc_html__( '%d Suggestions', 'assistify-for-woocommerce' ),
								(int) $assistify_summary['info_count']
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="assistify-quick-stats">
			<div class="assistify-stat-card">
				<span class="assistify-stat-value"><?php echo esc_html( $assistify_payment_stats['success_rate'] ); ?>%</span>
				<span class="assistify-stat-label"><?php esc_html_e( 'Payment Success', 'assistify-for-woocommerce' ); ?></span>
			</div>
			<div class="assistify-stat-card">
				<span class="assistify-stat-value"><?php echo (int) $assistify_payment_stats['failed']; ?></span>
				<span class="assistify-stat-label"><?php esc_html_e( 'Failed Orders (24h)', 'assistify-for-woocommerce' ); ?></span>
			</div>
			<div class="assistify-stat-card">
				<span class="assistify-stat-value"><?php echo (int) count( $assistify_issues['critical'] ?? array() ); ?></span>
				<span class="assistify-stat-label"><?php esc_html_e( 'Critical Issues', 'assistify-for-woocommerce' ); ?></span>
			</div>
		</div>
	</div>

	<div class="assistify-health-columns">
		<!-- Left Column: Issues -->
		<div class="assistify-health-main">
			<!-- Critical Issues -->
			<?php if ( ! empty( $assistify_issues['critical'] ) ) : ?>
			<div class="assistify-health-section assistify-section-critical">
				<h3><?php esc_html_e( 'Critical Issues', 'assistify-for-woocommerce' ); ?></h3>
				<div class="assistify-issues-list">
					<?php foreach ( $assistify_issues['critical'] as $assistify_issue ) : ?>
						<?php assistify_render_issue_item( $assistify_issue, 'critical' ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Warnings -->
			<?php if ( ! empty( $assistify_issues['warning'] ) ) : ?>
			<div class="assistify-health-section assistify-section-warning">
				<h3><?php esc_html_e( 'Warnings', 'assistify-for-woocommerce' ); ?></h3>
				<div class="assistify-issues-list">
					<?php foreach ( $assistify_issues['warning'] as $assistify_issue ) : ?>
						<?php assistify_render_issue_item( $assistify_issue, 'warning' ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Info/Suggestions -->
			<?php if ( ! empty( $assistify_issues['info'] ) ) : ?>
			<div class="assistify-health-section assistify-section-info">
				<h3><?php esc_html_e( 'Suggestions', 'assistify-for-woocommerce' ); ?></h3>
				<div class="assistify-issues-list">
					<?php foreach ( $assistify_issues['info'] as $assistify_issue ) : ?>
						<?php assistify_render_issue_item( $assistify_issue, 'info' ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- All Good -->
			<?php if ( empty( $assistify_issues['critical'] ) && empty( $assistify_issues['warning'] ) && empty( $assistify_issues['info'] ) ) : ?>
			<div class="assistify-health-section assistify-section-good">
				<div class="assistify-all-good">
					<span class="dashicons dashicons-yes-alt"></span>
					<h3><?php esc_html_e( 'Everything looks great!', 'assistify-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Your store is running smoothly. Keep up the good work!', 'assistify-for-woocommerce' ); ?></p>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<!-- Right Column: Actions & AI -->
		<div class="assistify-health-sidebar">
			<!-- Quick Actions -->
			<div class="assistify-sidebar-card">
				<h3><?php esc_html_e( 'Quick Actions', 'assistify-for-woocommerce' ); ?></h3>
				<div class="assistify-quick-actions">
					<button type="button" class="button assistify-action-button" data-action="clear_transients">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear Transients', 'assistify-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button assistify-action-button" data-action="optimize_autoload">
						<span class="dashicons dashicons-performance"></span>
						<?php esc_html_e( 'Optimize Autoload', 'assistify-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button assistify-action-button" data-action="clear_wc_sessions">
						<span class="dashicons dashicons-admin-users"></span>
						<?php esc_html_e( 'Clear Sessions', 'assistify-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button assistify-action-button" data-action="clear_wc_logs">
						<span class="dashicons dashicons-media-text"></span>
						<?php esc_html_e( 'Clear Old Logs', 'assistify-for-woocommerce' ); ?>
					</button>
				</div>
			</div>

			<!-- AI Recommendations -->
			<div class="assistify-sidebar-card assistify-ai-card">
				<h3>
					<span class="dashicons dashicons-lightbulb"></span>
					<?php esc_html_e( 'AI Recommendations', 'assistify-for-woocommerce' ); ?>
				</h3>
				<div class="assistify-ai-content" id="assistify-ai-recommendations">
					<p class="assistify-ai-placeholder">
						<?php esc_html_e( 'Get personalized recommendations based on your store data.', 'assistify-for-woocommerce' ); ?>
					</p>
					<button type="button" class="button button-primary" id="assistify-get-recommendations">
						<?php esc_html_e( 'Analyze My Store', 'assistify-for-woocommerce' ); ?>
					</button>
				</div>
				<p class="assistify-ai-source">
					<?php esc_html_e( 'Analysis based on: health checks, sales data, order history, and inventory levels from your store.', 'assistify-for-woocommerce' ); ?>
				</p>
			</div>

			<!-- Category Scores -->
			<div class="assistify-sidebar-card">
				<h3><?php esc_html_e( 'Category Scores', 'assistify-for-woocommerce' ); ?></h3>
				<div class="assistify-category-scores">
					<?php foreach ( $assistify_categories as $assistify_cat_id => $assistify_cat_data ) : ?>
					<div class="assistify-category-row">
						<span class="assistify-category-label"><?php echo esc_html( $assistify_cat_data['label'] ); ?></span>
						<div class="assistify-category-bar">
							<div class="assistify-category-fill" style="width: <?php echo (int) $assistify_cat_data['score']; ?>%;"></div>
						</div>
						<span class="assistify-category-score"><?php echo (int) $assistify_cat_data['score']; ?></span>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Recent Failed Orders -->
			<?php if ( ! empty( $assistify_failures ) ) : ?>
			<div class="assistify-sidebar-card">
				<h3><?php esc_html_e( 'Recent Failed Orders', 'assistify-for-woocommerce' ); ?></h3>
				<table class="assistify-failures-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'assistify-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Gateway', 'assistify-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Total', 'assistify-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $assistify_failures as $assistify_failure ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $assistify_failure['order_id'] . '&action=edit' ) ); ?>">
									#<?php echo (int) $assistify_failure['order_id']; ?>
								</a>
							</td>
							<td><?php echo esc_html( $assistify_failure['gateway_title'] ?? '-' ); ?></td>
							<td><?php echo esc_html( $assistify_failure['total'] . ' ' . ( $assistify_failure['currency'] ?? '' ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order&post_status=wc-failed' ) ); ?>" class="assistify-view-all">
					<?php esc_html_e( 'View all failed orders', 'assistify-for-woocommerce' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Footer -->
	<div class="assistify-health-footer">
		<p>
			<?php
			printf(
				/* translators: %s: timestamp */
				esc_html__( 'Last checked: %s', 'assistify-for-woocommerce' ),
				esc_html( $assistify_timestamp )
			);
			?>
			&bull;
			<?php esc_html_e( 'Powered by Assistify', 'assistify-for-woocommerce' ); ?>
		</p>
	</div>
</div>

<?php
/**
 * Render an issue item with all details, links, and actions.
 *
 * @param array  $issue    Issue data.
 * @param string $severity Issue severity.
 * @return void
 */
function assistify_render_issue_item( $issue, $severity ) {
	$issue_key = $issue['issue_key'] ?? '';
	$check_id  = $issue['check_id'] ?? '';
	$data      = $issue['data'] ?? array();
	$icon      = 'critical' === $severity ? '!' : ( 'warning' === $severity ? '*' : 'i' );
	$fix_label = 'critical' === $severity ? __( 'How to fix:', 'assistify-for-woocommerce' ) : __( 'Suggestion:', 'assistify-for-woocommerce' );
	?>
	<div class="assistify-issue-item assistify-issue-<?php echo esc_attr( $severity ); ?>" data-issue-key="<?php echo esc_attr( $issue_key ); ?>">
		<div class="assistify-issue-icon"><?php echo esc_html( $icon ); ?></div>
		<div class="assistify-issue-content">
			<h4><?php echo esc_html( $issue['title'] ); ?></h4>
			<p><?php echo esc_html( $issue['message'] ); ?></p>

			<?php
			// Show sample errors if available.
			if ( ! empty( $data['samples'] ) ) :
				?>
			<div class="assistify-issue-samples">
				<details>
					<summary><?php esc_html_e( 'View error details', 'assistify-for-woocommerce' ); ?></summary>
					<pre><?php echo esc_html( implode( "\n", array_slice( $data['samples'], 0, 3 ) ) ); ?></pre>
				</details>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $issue['fix'] ) ) : ?>
			<p class="assistify-issue-fix">
				<strong><?php echo esc_html( $fix_label ); ?></strong>
				<?php echo esc_html( $issue['fix'] ); ?>
			</p>
			<?php endif; ?>

			<?php
			// Show direct log link if available.
			if ( ! empty( $data['log_url'] ) ) :
				?>
			<p class="assistify-issue-log-link">
				<a href="<?php echo esc_url( $data['log_url'] ); ?>" target="_blank">
					<?php
					printf(
						/* translators: %s: log file name */
						esc_html__( 'View log: %s', 'assistify-for-woocommerce' ),
						esc_html( $data['log_file'] ?? 'log' )
					);
					?>
				</a>
			</p>
			<?php endif; ?>

			<?php
			// Show payment settings link if available.
			if ( ! empty( $data['settings_url'] ) ) :
				?>
			<p class="assistify-issue-settings-link">
				<a href="<?php echo esc_url( $data['settings_url'] ); ?>">
					<?php esc_html_e( 'Open payment settings', 'assistify-for-woocommerce' ); ?>
				</a>
			</p>
			<?php endif; ?>
		</div>

		<div class="assistify-issue-actions">
			<?php
			$action = assistify_get_issue_action( $issue );
			if ( $action ) :
				if ( ! empty( $action['ajax'] ) ) :
					?>
				<button type="button" class="button assistify-action-button" data-action="<?php echo esc_attr( $action['ajax'] ); ?>">
					<?php echo esc_html( $action['label'] ); ?>
				</button>
				<?php else : ?>
				<a href="<?php echo esc_url( $action['url'] ); ?>" class="button" <?php echo ! empty( $action['external'] ) ? 'target="_blank"' : ''; ?>>
					<?php echo esc_html( $action['label'] ); ?>
				</a>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $issue_key ) ) : ?>
			<button type="button" class="button button-link assistify-resolve-button" data-issue-key="<?php echo esc_attr( $issue_key ); ?>" title="<?php esc_attr_e( 'Mark as resolved', 'assistify-for-woocommerce' ); ?>">
				<?php esc_html_e( 'Resolved', 'assistify-for-woocommerce' ); ?>
			</button>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Get action button/link for an issue.
 *
 * @param array $issue Issue data.
 * @return array|null Action data or null.
 */
function assistify_get_issue_action( $issue ) {
	$check_id = $issue['check_id'] ?? '';
	$category = $issue['category'] ?? '';
	$data     = $issue['data'] ?? array();

	// Check for direct log URL first.
	if ( ! empty( $data['log_url'] ) ) {
		return array(
			'label' => __( 'View Log', 'assistify-for-woocommerce' ),
			'url'   => $data['log_url'],
		);
	}

	// Define actions based on check/category.
	$actions = array(
		// Updates - send to specific pages.
		'plugin_updates'    => array(
			'label' => __( 'View Plugins', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'plugins.php?plugin_status=upgrade' ),
		),
		'theme_updates'     => array(
			'label' => __( 'View Themes', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'themes.php' ),
		),
		'wordpress_updates' => array(
			'label' => __( 'Update WordPress', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'update-core.php' ),
		),
		// Inventory.
		'low_stock'         => array(
			'label' => __( 'Manage Stock', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'edit.php?post_type=product&stock_status=lowstock' ),
		),
		'out_of_stock'      => array(
			'label' => __( 'View Products', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'edit.php?post_type=product&stock_status=outofstock' ),
		),
		// Orders.
		'failed_orders'     => array(
			'label' => __( 'View Orders', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'edit.php?post_type=shop_order&post_status=wc-failed' ),
		),
		// Database.
		'database_health'   => array(
			'label' => __( 'Optimize', 'assistify-for-woocommerce' ),
			'ajax'  => 'clear_transients',
		),
		// Logs - all use consistent "View Log" label.
		'php_errors'        => array(
			'label' => __( 'View Log', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'admin.php?page=wc-status&tab=logs' ),
		),
		'wc_logs'           => array(
			'label' => __( 'View Log', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'admin.php?page=wc-status&tab=logs' ),
		),
		// Security.
		'ssl_certificate'   => array(
			'label'    => __( 'Check SSL', 'assistify-for-woocommerce' ),
			'url'      => 'https://www.ssllabs.com/ssltest/analyze.html?d=' . rawurlencode( wp_parse_url( home_url(), PHP_URL_HOST ) ),
			'external' => true,
		),
	);

	// Skip action for settings that require manual file edits.
	$issue_key      = $issue['issue_key'] ?? '';
	$no_action_keys = array( 'security_wp_debug', 'security_wp_debug_display', 'security_wp_debug_log' );
	foreach ( $no_action_keys as $key ) {
		if ( strpos( $issue_key, $key ) === 0 ) {
			return null; // No action button - requires manual wp-config.php edit.
		}
	}

	if ( isset( $actions[ $check_id ] ) ) {
		return $actions[ $check_id ];
	}

	// Category-based fallbacks.
	$category_actions = array(
		'updates'   => array(
			'label' => __( 'Check Updates', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'update-core.php' ),
		),
		'inventory' => array(
			'label' => __( 'Manage Products', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'edit.php?post_type=product' ),
		),
		'security'  => array(
			'label' => __( 'Site Health', 'assistify-for-woocommerce' ),
			'url'   => admin_url( 'site-health.php' ),
		),
	);

	if ( isset( $category_actions[ $category ] ) ) {
		return $category_actions[ $category ];
	}

	return null;
}
?>
