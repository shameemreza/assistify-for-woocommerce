<?php
/**
 * Health Alerts - Email notification system
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify\Health_Monitor;

defined( 'ABSPATH' ) || exit;

/**
 * Health Alerts Class
 *
 * Handles sending email alerts for health issues.
 *
 * @since 1.0.0
 */
class Health_Alerts {

	/**
	 * Singleton instance.
	 *
	 * @var Health_Alerts|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Health_Alerts
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Private constructor for singleton.
	}

	/**
	 * Get admin email for alerts.
	 *
	 * @return string Admin email address.
	 */
	private function get_alert_email() {
		// First check custom alert email setting.
		$email = get_option( 'assistify_alert_email', '' );

		// Then try WooCommerce admin email.
		if ( empty( $email ) ) {
			$email = get_option( 'woocommerce_email_from_address', '' );
		}

		// Finally fall back to WordPress admin email.
		if ( empty( $email ) ) {
			$email = get_option( 'admin_email' );
		}

		return $email;
	}

	/**
	 * Check if health monitoring is enabled.
	 *
	 * @return bool Whether monitoring is enabled.
	 */
	public function is_monitoring_enabled() {
		return 'yes' === get_option( 'assistify_enable_health_monitoring', 'yes' );
	}

	/**
	 * Check if critical alerts are enabled.
	 *
	 * @return bool Whether critical alerts are enabled.
	 */
	public function is_critical_alert_enabled() {
		return $this->is_monitoring_enabled() && 'yes' === get_option( 'assistify_alert_critical', 'yes' );
	}

	/**
	 * Check if payment alerts are enabled.
	 *
	 * @return bool Whether payment alerts are enabled.
	 */
	public function is_payment_alert_enabled() {
		return $this->is_monitoring_enabled() && 'yes' === get_option( 'assistify_alert_payment', 'yes' );
	}

	/**
	 * Check if stock alerts are enabled.
	 *
	 * @return bool Whether stock alerts are enabled.
	 */
	public function is_stock_alert_enabled() {
		return $this->is_monitoring_enabled() && 'yes' === get_option( 'assistify_alert_stock', 'yes' );
	}

	/**
	 * Get store name for emails.
	 *
	 * @return string Store name.
	 */
	private function get_store_name() {
		$name = get_option( 'woocommerce_email_from_name', '' );
		if ( empty( $name ) ) {
			$name = get_bloginfo( 'name' );
		}
		return $name;
	}

	/**
	 * Send general health alert email.
	 *
	 * @param array $results Health check results.
	 * @return bool Whether email was sent.
	 */
	public function send_health_alert( $results ) {
		// Check if critical alerts are enabled.
		if ( ! $this->is_critical_alert_enabled() ) {
			return false;
		}

		$to = $this->get_alert_email();
		if ( empty( $to ) ) {
			return false;
		}

		$store_name = $this->get_store_name();
		$site_url   = home_url();
		$admin_url  = admin_url( 'admin.php?page=wc-settings&tab=assistify' );

		$subject = sprintf(
			'[%s] Store Health Alert - %d Critical Issue(s) Found',
			$store_name,
			count( $results['issues']['critical'] )
		);

		// Build email content.
		$message = $this->build_health_alert_content( $results, $store_name, $site_url, $admin_url );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $store_name . ' <' . $to . '>',
		);

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			\Assistify_For_WooCommerce\Assistify_Logger::info( 'Health alert email sent to: ' . $to );
		} else {
			\Assistify_For_WooCommerce\Assistify_Logger::error( 'Failed to send health alert email to: ' . $to );
		}

		return $sent;
	}

	/**
	 * Build health alert email content.
	 *
	 * @param array  $results    Health check results.
	 * @param string $store_name Store name.
	 * @param string $site_url   Site URL.
	 * @param string $admin_url  Admin settings URL (unused, kept for future use).
	 * @return string HTML email content.
	 */
	private function build_health_alert_content( $results, $store_name, $site_url, $admin_url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
		</head>
		<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5;">
			<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
				<tr>
					<td align="center">
						<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
							<!-- Header -->
							<tr>
								<td style="background-color: #dc3545; padding: 30px; text-align: center;">
									<h1 style="margin: 0; color: #ffffff; font-size: 24px;">Store Health Alert</h1>
									<p style="margin: 10px 0 0; color: #ffffff; opacity: 0.9;"><?php echo esc_html( $store_name ); ?></p>
								</td>
							</tr>

							<!-- Score -->
							<tr>
								<td style="padding: 30px; text-align: center; border-bottom: 1px solid #eee;">
									<p style="margin: 0 0 10px; color: #666; font-size: 14px;">Overall Health Score</p>
									<p style="margin: 0; font-size: 48px; font-weight: bold; color: <?php echo $results['score'] >= 70 ? '#28a745' : ( $results['score'] >= 50 ? '#ffc107' : '#dc3545' ); ?>;">
										<?php echo (int) $results['score']; ?>/100
									</p>
								</td>
							</tr>

							<!-- Summary -->
							<tr>
								<td style="padding: 30px;">
									<h2 style="margin: 0 0 20px; color: #333; font-size: 18px;">Issues Found</h2>

									<?php if ( ! empty( $results['issues']['critical'] ) ) : ?>
									<div style="margin-bottom: 20px;">
										<h3 style="margin: 0 0 10px; color: #dc3545; font-size: 14px; text-transform: uppercase;">Critical Issues (<?php echo count( $results['issues']['critical'] ); ?>)</h3>
										<?php foreach ( $results['issues']['critical'] as $issue ) : ?>
										<div style="background-color: #fff5f5; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 10px;">
											<p style="margin: 0 0 5px; font-weight: bold; color: #333;"><?php echo esc_html( $issue['title'] ); ?></p>
											<p style="margin: 0 0 10px; color: #666; font-size: 14px;"><?php echo esc_html( $issue['message'] ); ?></p>
											<?php if ( ! empty( $issue['fix'] ) ) : ?>
											<p style="margin: 0; color: #28a745; font-size: 13px;"><strong>How to fix:</strong> <?php echo esc_html( $issue['fix'] ); ?></p>
											<?php endif; ?>
										</div>
										<?php endforeach; ?>
									</div>
									<?php endif; ?>

									<?php if ( ! empty( $results['issues']['warning'] ) ) : ?>
									<div style="margin-bottom: 20px;">
										<h3 style="margin: 0 0 10px; color: #ffc107; font-size: 14px; text-transform: uppercase;">Warnings (<?php echo count( $results['issues']['warning'] ); ?>)</h3>
										<?php foreach ( array_slice( $results['issues']['warning'], 0, 5 ) as $issue ) : ?>
										<div style="background-color: #fffbf0; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 10px;">
											<p style="margin: 0 0 5px; font-weight: bold; color: #333;"><?php echo esc_html( $issue['title'] ); ?></p>
											<p style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html( $issue['message'] ); ?></p>
										</div>
										<?php endforeach; ?>
										<?php if ( count( $results['issues']['warning'] ) > 5 ) : ?>
										<p style="color: #666; font-size: 13px;">...and <?php echo count( $results['issues']['warning'] ) - 5; ?> more warnings</p>
										<?php endif; ?>
									</div>
									<?php endif; ?>
								</td>
							</tr>

							<!-- Action Button -->
							<tr>
								<td style="padding: 0 30px 30px; text-align: center;">
									<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" style="display: inline-block; background-color: #6861F2; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">View Dashboard</a>
								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee;">
									<p style="margin: 0; color: #999; font-size: 12px;">
										This alert was sent by Assistify for WooCommerce.<br>
										<?php echo esc_html( $site_url ); ?>
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send payment failure spike alert.
	 *
	 * @param array $failures     Recent failure data.
	 * @param int   $failure_count Number of failures in last hour.
	 * @return bool Whether email was sent.
	 */
	public function send_payment_failure_alert( $failures, $failure_count ) {
		// Check if payment alerts are enabled.
		if ( ! $this->is_payment_alert_enabled() ) {
			return false;
		}

		$to = $this->get_alert_email();
		if ( empty( $to ) ) {
			return false;
		}

		$store_name = $this->get_store_name();

		$subject = sprintf(
			'[%s] Payment Alert - %d Failed Orders in Last Hour',
			$store_name,
			$failure_count
		);

		$message = $this->build_payment_alert_content( $failures, $failure_count, $store_name );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $store_name . ' <' . $to . '>',
		);

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			\Assistify_For_WooCommerce\Assistify_Logger::info( 'Payment failure alert sent. Count: ' . $failure_count );
		}

		return $sent;
	}

	/**
	 * Build payment failure alert email content.
	 *
	 * @param array  $failures      Recent failure data.
	 * @param int    $failure_count Number of failures.
	 * @param string $store_name    Store name.
	 * @return string HTML email content.
	 */
	private function build_payment_alert_content( $failures, $failure_count, $store_name ) {
		// Group failures by gateway.
		$by_gateway = array();
		$recent     = array_slice( array_reverse( $failures ), 0, 10 );

		foreach ( $recent as $failure ) {
			$gateway = $failure['gateway_title'] ?? 'Unknown';
			if ( ! isset( $by_gateway[ $gateway ] ) ) {
				$by_gateway[ $gateway ] = 0;
			}
			++$by_gateway[ $gateway ];
		}

		// Analyze possible reasons.
		$possible_reasons = $this->analyze_failure_reasons( $recent );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5;">
			<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
				<tr>
					<td align="center">
						<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
							<!-- Header -->
							<tr>
								<td style="background-color: #dc3545; padding: 30px; text-align: center;">
									<h1 style="margin: 0; color: #ffffff; font-size: 24px;">Payment Failure Alert</h1>
									<p style="margin: 10px 0 0; color: #ffffff; opacity: 0.9;"><?php echo esc_html( $store_name ); ?></p>
								</td>
							</tr>

							<!-- Alert Message -->
							<tr>
								<td style="padding: 30px;">
									<div style="background-color: #fff5f5; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
										<p style="margin: 0; font-size: 16px; color: #333;">
											<strong><?php echo (int) $failure_count; ?> payment(s) failed</strong> in the last hour.
											This is higher than normal and may indicate an issue with your payment gateway.
										</p>
									</div>

									<!-- By Gateway -->
									<h3 style="margin: 0 0 15px; color: #333; font-size: 16px;">Failures by Payment Gateway</h3>
									<table width="100%" style="border-collapse: collapse; margin-bottom: 20px;">
										<?php foreach ( $by_gateway as $gateway => $count ) : ?>
										<tr>
											<td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo esc_html( $gateway ); ?></td>
											<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;"><?php echo (int) $count; ?> failures</td>
										</tr>
										<?php endforeach; ?>
									</table>

									<!-- Possible Reasons -->
									<?php if ( ! empty( $possible_reasons ) ) : ?>
									<h3 style="margin: 0 0 15px; color: #333; font-size: 16px;">Possible Reasons</h3>
									<ul style="margin: 0 0 20px; padding-left: 20px; color: #666;">
										<?php foreach ( $possible_reasons as $reason ) : ?>
										<li style="margin-bottom: 8px;"><?php echo esc_html( $reason ); ?></li>
										<?php endforeach; ?>
									</ul>
									<?php endif; ?>

									<!-- Recent Failures -->
									<h3 style="margin: 0 0 15px; color: #333; font-size: 16px;">Recent Failed Orders</h3>
									<table width="100%" style="border-collapse: collapse;">
										<tr style="background-color: #f8f9fa;">
											<th style="padding: 10px; text-align: left; font-size: 12px; color: #666;">Order</th>
											<th style="padding: 10px; text-align: left; font-size: 12px; color: #666;">Gateway</th>
											<th style="padding: 10px; text-align: right; font-size: 12px; color: #666;">Amount</th>
											<th style="padding: 10px; text-align: right; font-size: 12px; color: #666;">Time</th>
										</tr>
										<?php foreach ( array_slice( $recent, 0, 5 ) as $failure ) : ?>
										<tr>
											<td style="padding: 10px; border-bottom: 1px solid #eee;">
												<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $failure['order_id'] . '&action=edit' ) ); ?>" style="color: #6861F2;">#<?php echo (int) $failure['order_id']; ?></a>
											</td>
											<td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo esc_html( $failure['gateway_title'] ?? '-' ); ?></td>
											<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;"><?php echo esc_html( $failure['currency'] ?? '' ); ?> <?php echo esc_html( $failure['total'] ?? '0' ); ?></td>
											<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-size: 12px; color: #999;"><?php echo esc_html( $failure['timestamp'] ?? '' ); ?></td>
										</tr>
										<?php endforeach; ?>
									</table>
								</td>
							</tr>

							<!-- Action Buttons -->
							<tr>
								<td style="padding: 0 30px 30px; text-align: center;">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>" style="display: inline-block; background-color: #6861F2; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-right: 10px;">Check Payment Settings</a>
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order&post_status=wc-failed' ) ); ?>" style="display: inline-block; background-color: #6c757d; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px;">View Failed Orders</a>
								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee;">
									<p style="margin: 0; color: #999; font-size: 12px;">
										This alert was sent by Assistify for WooCommerce.<br>
										You will not receive another payment alert for at least 1 hour.
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Analyze failure reasons from order notes.
	 *
	 * @param array $failures Recent failure data.
	 * @return array Possible reasons.
	 */
	private function analyze_failure_reasons( $failures ) {
		$reasons  = array();
		$patterns = array(
			'card declined'      => 'Customer cards are being declined. This could indicate fraud detection or expired cards.',
			'insufficient funds' => 'Customers have insufficient funds in their accounts.',
			'authentication'     => 'Payment authentication is failing. Check 3D Secure settings.',
			'api'                => 'API connection issues detected. Verify your payment gateway API credentials.',
			'timeout'            => 'Payment requests are timing out. Check server performance and gateway status.',
			'invalid'            => 'Invalid payment details being submitted. Check your checkout form configuration.',
			'expired'            => 'Expired card errors detected. Consider adding card expiry validation.',
			'cvv'                => 'CVV/CVC verification failures. Ensure CVV field is properly configured.',
		);

		foreach ( $failures as $failure ) {
			if ( empty( $failure['order_notes'] ) ) {
				continue;
			}

			foreach ( $failure['order_notes'] as $note ) {
				$content = strtolower( $note['content'] ?? '' );
				foreach ( $patterns as $pattern => $reason ) {
					if ( strpos( $content, $pattern ) !== false && ! in_array( $reason, $reasons, true ) ) {
						$reasons[] = $reason;
					}
				}
			}
		}

		// Add generic reason if none found.
		if ( empty( $reasons ) ) {
			$reasons[] = 'Check your payment gateway dashboard for detailed error logs.';
			$reasons[] = 'Verify API credentials are correct and not expired.';
		}

		return array_slice( $reasons, 0, 4 ); // Max 4 reasons.
	}

	/**
	 * Send error spike alert.
	 *
	 * @param array $errors     Recent error data.
	 * @param int   $error_count Number of errors.
	 * @return bool Whether email was sent.
	 */
	public function send_error_alert( $errors, $error_count ) {
		$to = $this->get_alert_email();
		if ( empty( $to ) ) {
			return false;
		}

		$store_name = $this->get_store_name();

		$subject = sprintf(
			'[%s] Error Alert - %d PHP Errors Detected',
			$store_name,
			$error_count
		);

		// Build simple error summary.
		$message = $this->build_error_alert_content( $errors, $error_count, $store_name );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $store_name . ' <' . $to . '>',
		);

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Build error alert email content.
	 *
	 * @param array  $errors      Error data.
	 * @param int    $error_count Number of errors.
	 * @param string $store_name  Store name.
	 * @return string HTML email content.
	 */
	private function build_error_alert_content( $errors, $error_count, $store_name ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5;">
			<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
				<tr>
					<td align="center">
						<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
							<!-- Header -->
							<tr>
								<td style="background-color: #dc3545; padding: 30px; text-align: center;">
									<h1 style="margin: 0; color: #ffffff; font-size: 24px;">PHP Error Alert</h1>
									<p style="margin: 10px 0 0; color: #ffffff; opacity: 0.9;"><?php echo esc_html( $store_name ); ?></p>
								</td>
							</tr>

							<!-- Content -->
							<tr>
								<td style="padding: 30px;">
									<div style="background-color: #fff5f5; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
										<p style="margin: 0; font-size: 16px; color: #333;">
											<strong><?php echo (int) $error_count; ?> PHP error(s)</strong> detected recently.
											This may affect your store's functionality.
										</p>
									</div>

									<h3 style="margin: 0 0 15px; color: #333; font-size: 16px;">Recent Errors</h3>
									<?php foreach ( array_slice( $errors, 0, 5 ) as $error ) : ?>
									<div style="background-color: #f8f9fa; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 10px; font-family: monospace; font-size: 12px; overflow-x: auto;">
										<?php echo esc_html( $error['message'] ?? 'Unknown error' ); ?>
										<?php if ( ! empty( $error['file'] ) ) : ?>
										<br><small style="color: #999;"><?php echo esc_html( $error['file'] ); ?>:<?php echo (int) ( $error['line'] ?? 0 ); ?></small>
										<?php endif; ?>
									</div>
									<?php endforeach; ?>

									<h3 style="margin: 20px 0 15px; color: #333; font-size: 16px;">Recommended Actions</h3>
									<ul style="margin: 0; padding-left: 20px; color: #666;">
										<li style="margin-bottom: 8px;">Check if a recently installed or updated plugin is causing the issue.</li>
										<li style="margin-bottom: 8px;">Review your theme for compatibility issues.</li>
										<li style="margin-bottom: 8px;">Enable WP_DEBUG_LOG to capture more details.</li>
										<li style="margin-bottom: 8px;">Contact your hosting provider if server errors persist.</li>
									</ul>
								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee;">
									<p style="margin: 0; color: #999; font-size: 12px;">
										This alert was sent by Assistify for WooCommerce.
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send low stock alert.
	 *
	 * @param array $products Low stock products.
	 * @return bool Whether email was sent.
	 */
	public function send_low_stock_alert( $products ) {
		// Check if stock alerts are enabled.
		if ( ! $this->is_stock_alert_enabled() ) {
			return false;
		}

		$to = $this->get_alert_email();
		if ( empty( $to ) ) {
			return false;
		}

		$store_name = $this->get_store_name();

		$subject = sprintf(
			'[%s] Inventory Alert - %d Product(s) Low in Stock',
			$store_name,
			count( $products )
		);

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5;">
			<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
				<tr>
					<td align="center">
						<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
							<tr>
								<td style="background-color: #ffc107; padding: 30px; text-align: center;">
									<h1 style="margin: 0; color: #333; font-size: 24px;">Low Stock Alert</h1>
								</td>
							</tr>
							<tr>
								<td style="padding: 30px;">
									<p style="margin: 0 0 20px; color: #333;"><?php echo count( $products ); ?> product(s) are running low on stock:</p>
									<table width="100%" style="border-collapse: collapse;">
										<tr style="background-color: #f8f9fa;">
											<th style="padding: 10px; text-align: left;">Product</th>
											<th style="padding: 10px; text-align: right;">Stock</th>
										</tr>
										<?php foreach ( array_slice( $products, 0, 10 ) as $product ) : ?>
										<tr>
											<td style="padding: 10px; border-bottom: 1px solid #eee;">
												<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $product['id'] . '&action=edit' ) ); ?>" style="color: #6861F2;"><?php echo esc_html( $product['name'] ); ?></a>
											</td>
											<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; color: <?php echo $product['stock'] <= 0 ? '#dc3545' : '#ffc107'; ?>; font-weight: bold;">
												<?php echo (int) $product['stock']; ?>
											</td>
										</tr>
										<?php endforeach; ?>
									</table>
								</td>
							</tr>
							<tr>
								<td style="padding: 0 30px 30px; text-align: center;">
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&stock_status=lowstock' ) ); ?>" style="display: inline-block; background-color: #6861F2; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Manage Inventory</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		$message = ob_get_clean();

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $store_name . ' <' . $to . '>',
		);

		return wp_mail( $to, $subject, $message, $headers );
	}
}
