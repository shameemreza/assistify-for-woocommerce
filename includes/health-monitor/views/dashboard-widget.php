<?php
/**
 * Dashboard Widget View - Store Health
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 *
 * @var array $results Health check results.
 */

defined( 'ABSPATH' ) || exit;

// Extract data with prefixed variable names to avoid global scope issues.
$assistify_score     = $results['score'] ?? 100;
$assistify_summary   = $results['summary'] ?? array();
$assistify_issues    = $results['issues'] ?? array();
$assistify_timestamp = $results['timestamp'] ?? current_time( 'mysql' );

// Determine score class.
$assistify_score_class = 'assistify-score-excellent';
if ( $assistify_score < 90 ) {
	$assistify_score_class = 'assistify-score-good';
}
if ( $assistify_score < 70 ) {
	$assistify_score_class = 'assistify-score-fair';
}
if ( $assistify_score < 50 ) {
	$assistify_score_class = 'assistify-score-poor';
}

// Merge all issues for display.
$assistify_all_issues = array_merge(
	$assistify_issues['critical'] ?? array(),
	$assistify_issues['warning'] ?? array(),
	array_slice( $assistify_issues['info'] ?? array(), 0, 2 )
);
?>

<div class="assistify-health-widget">
	<!-- Score -->
	<div class="assistify-health-score">
		<div class="assistify-score-circle <?php echo esc_attr( $assistify_score_class ); ?>">
			<?php echo (int) $assistify_score; ?>
		</div>
		<div class="assistify-health-summary">
			<h4>
				<?php
				if ( $assistify_score >= 90 ) {
					esc_html_e( 'Excellent', 'assistify-for-woocommerce' );
				} elseif ( $assistify_score >= 70 ) {
					esc_html_e( 'Good', 'assistify-for-woocommerce' );
				} elseif ( $assistify_score >= 50 ) {
					esc_html_e( 'Needs Attention', 'assistify-for-woocommerce' );
				} else {
					esc_html_e( 'Critical Issues', 'assistify-for-woocommerce' );
				}
				?>
			</h4>
			<p><?php echo esc_html( $assistify_summary['message'] ?? __( 'Store health check complete.', 'assistify-for-woocommerce' ) ); ?></p>
		</div>
	</div>

	<!-- Issues List -->
	<?php if ( empty( $assistify_all_issues ) ) : ?>
		<div class="assistify-no-issues">
			<?php esc_html_e( 'No issues found. Your store is running smoothly.', 'assistify-for-woocommerce' ); ?>
		</div>
	<?php else : ?>
		<ul class="assistify-health-issues">
			<?php foreach ( array_slice( $assistify_all_issues, 0, 5 ) as $assistify_issue ) : ?>
				<?php
				// Get action URL based on category/check.
				$assistify_action_url   = '';
				$assistify_action_label = '';
				$assistify_category     = $assistify_issue['category'] ?? '';
				$assistify_check_id     = $assistify_issue['check_id'] ?? '';

				// Determine action based on issue type.
				if ( 'updates' === $assistify_category || 'plugin_updates' === $assistify_check_id || 'theme_updates' === $assistify_check_id || 'wordpress_updates' === $assistify_check_id ) {
					$assistify_action_url   = admin_url( 'update-core.php' );
					$assistify_action_label = __( 'View Updates', 'assistify-for-woocommerce' );
				} elseif ( 'inventory' === $assistify_category || 'low_stock' === $assistify_check_id || 'out_of_stock' === $assistify_check_id ) {
					$assistify_action_url   = admin_url( 'edit.php?post_type=product' );
					$assistify_action_label = __( 'Manage Products', 'assistify-for-woocommerce' );
				} elseif ( 'failed_orders' === $assistify_check_id || 'payment_success_rate' === $assistify_check_id ) {
					$assistify_action_url   = admin_url( 'edit.php?post_type=shop_order&post_status=wc-failed' );
					$assistify_action_label = __( 'View Failed Orders', 'assistify-for-woocommerce' );
				} elseif ( 'security' === $assistify_category || 'ssl_certificate' === $assistify_check_id ) {
					$assistify_action_url   = admin_url( 'admin.php?page=assistify-health' );
					$assistify_action_label = __( 'View Details', 'assistify-for-woocommerce' );
				} elseif ( 'php_errors' === $assistify_check_id || 'wc_logs' === $assistify_check_id ) {
					$assistify_action_url   = admin_url( 'admin.php?page=wc-status&tab=logs' );
					$assistify_action_label = __( 'View Logs', 'assistify-for-woocommerce' );
				} elseif ( 'performance' === $assistify_category ) {
					$assistify_action_url   = admin_url( 'admin.php?page=assistify-health' );
					$assistify_action_label = __( 'View Details', 'assistify-for-woocommerce' );
				}
				?>
				<li class="assistify-issue-<?php echo esc_attr( $assistify_issue['severity'] ?? 'info' ); ?>">
					<div class="assistify-issue-title">
						<span class="assistify-issue-indicator"></span>
						<?php echo esc_html( $assistify_issue['title'] ?? '' ); ?>
					</div>
					<div class="assistify-issue-message">
						<?php echo esc_html( $assistify_issue['message'] ?? '' ); ?>
					</div>
					<?php if ( ! empty( $assistify_action_url ) ) : ?>
					<div class="assistify-issue-action">
						<a href="<?php echo esc_url( $assistify_action_url ); ?>"><?php echo esc_html( $assistify_action_label ); ?> &rarr;</a>
					</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( count( $assistify_all_issues ) > 5 ) : ?>
			<p class="assistify-more-issues">
				<?php
				printf(
					/* translators: %d: number of additional issues */
					esc_html__( '...and %d more issue(s)', 'assistify-for-woocommerce' ),
					count( $assistify_all_issues ) - 5
				);
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<!-- Timestamp -->
	<div class="assistify-timestamp">
		<?php
		printf(
			/* translators: %s: time of last check */
			esc_html__( 'Last checked: %s', 'assistify-for-woocommerce' ),
			esc_html( $assistify_timestamp )
		);
		?>
	</div>

	<!-- Footer -->
	<div class="assistify-health-footer">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=assistify-health' ) ); ?>">
			<?php esc_html_e( 'View Full Report', 'assistify-for-woocommerce' ); ?>
		</a>
		<span class="assistify-footer-separator">|</span>
		<span class="assistify-powered-by"><?php esc_html_e( 'Powered by Assistify', 'assistify-for-woocommerce' ); ?></span>
	</div>
</div>
