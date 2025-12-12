<?php
/**
 * Audit Logger Class.
 *
 * Logs all AI-triggered actions for accountability and debugging.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.1.0
 */

namespace Assistify_For_WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Logger Class.
 *
 * @since 1.1.0
 */
class Audit_Logger {

	/**
	 * Singleton instance.
	 *
	 * @since 1.1.0
	 * @var Audit_Logger|null
	 */
	private static $instance = null;

	/**
	 * Database table name.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Log retention days.
	 *
	 * @since 1.1.0
	 * @var int
	 */
	private $retention_days = 90;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.1.0
	 * @return Audit_Logger Instance.
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
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'afw_audit_log';

		// Schedule cleanup.
		add_action( 'afw_audit_log_cleanup', array( $this, 'cleanup_old_logs' ) );

		// Register REST endpoint for log viewing.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Create audit log table.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'afw_audit_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT UNSIGNED NOT NULL,
			user_type VARCHAR(20) NOT NULL DEFAULT 'admin',
			action_type VARCHAR(100) NOT NULL,
			action_category VARCHAR(50) NOT NULL,
			description TEXT NOT NULL,
			ability_id VARCHAR(100) DEFAULT NULL,
			parameters LONGTEXT DEFAULT NULL,
			result LONGTEXT DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'success',
			ip_address VARCHAR(100) DEFAULT NULL,
			session_id VARCHAR(64) DEFAULT NULL,
			object_type VARCHAR(50) DEFAULT NULL,
			object_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			INDEX idx_user_id (user_id),
			INDEX idx_action_type (action_type),
			INDEX idx_action_category (action_category),
			INDEX idx_status (status),
			INDEX idx_created_at (created_at),
			INDEX idx_object (object_type, object_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log an action.
	 *
	 * @since 1.1.0
	 * @param array $data Log data.
	 * @return int|false Log ID or false on failure.
	 */
	public function log( $data ) {
		global $wpdb;

		$defaults = array(
			'user_id'         => get_current_user_id(),
			'user_type'       => 'admin',
			'action_type'     => '',
			'action_category' => 'general',
			'description'     => '',
			'ability_id'      => null,
			'parameters'      => null,
			'result'          => null,
			'status'          => 'success',
			'ip_address'      => $this->get_client_ip(),
			'session_id'      => null,
			'object_type'     => null,
			'object_id'       => null,
			'created_at'      => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Encode arrays to JSON.
		if ( is_array( $data['parameters'] ) || is_object( $data['parameters'] ) ) {
			$data['parameters'] = wp_json_encode( $data['parameters'] );
		}
		if ( is_array( $data['result'] ) || is_object( $data['result'] ) ) {
			$data['result'] = wp_json_encode( $data['result'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'         => absint( $data['user_id'] ),
				'user_type'       => sanitize_text_field( $data['user_type'] ),
				'action_type'     => sanitize_text_field( $data['action_type'] ),
				'action_category' => sanitize_text_field( $data['action_category'] ),
				'description'     => sanitize_text_field( $data['description'] ),
				'ability_id'      => $data['ability_id'] ? sanitize_text_field( $data['ability_id'] ) : null,
				'parameters'      => $data['parameters'],
				'result'          => $data['result'],
				'status'          => sanitize_text_field( $data['status'] ),
				'ip_address'      => sanitize_text_field( $data['ip_address'] ),
				'session_id'      => $data['session_id'] ? sanitize_text_field( $data['session_id'] ) : null,
				'object_type'     => $data['object_type'] ? sanitize_text_field( $data['object_type'] ) : null,
				'object_id'       => $data['object_id'] ? absint( $data['object_id'] ) : null,
				'created_at'      => $data['created_at'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( $inserted ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Log an ability execution.
	 *
	 * @since 1.1.0
	 * @param string $ability_id  The ability identifier.
	 * @param array  $parameters  The parameters passed.
	 * @param mixed  $result      The result of execution.
	 * @param string $status      Status (success, failed, pending).
	 * @param string $user_type   User type (admin, customer).
	 * @return int|false Log ID or false.
	 */
	public function log_ability( $ability_id, $parameters, $result, $status = 'success', $user_type = 'admin' ) {
		$category = $this->get_category_from_ability( $ability_id );

		return $this->log(
			array(
				'action_type'     => $this->get_action_type_from_ability( $ability_id ),
				'action_category' => $category,
				'description'     => $this->generate_description( $ability_id, $parameters, $result ),
				'ability_id'      => $ability_id,
				'parameters'      => $parameters,
				'result'          => $result,
				'status'          => $status,
				'user_type'       => $user_type,
				'object_type'     => $this->get_object_type( $ability_id ),
				'object_id'       => $this->get_object_id( $parameters ),
			)
		);
	}

	/**
	 * Log a content generation action.
	 *
	 * @since 1.1.0
	 * @param string $content_type Type of content (title, description, etc.).
	 * @param int    $object_id    The object ID (post/product).
	 * @param string $status       Status.
	 * @return int|false Log ID.
	 */
	public function log_content_generation( $content_type, $object_id, $status = 'success' ) {
		return $this->log(
			array(
				'action_type'     => 'content_generate',
				'action_category' => 'content',
				'description'     => sprintf(
					/* translators: %s: content type being generated */
					__( 'Generated AI %s', 'assistify-for-woocommerce' ),
					$content_type
				),
				'object_type'     => 'post',
				'object_id'       => $object_id,
				'status'          => $status,
			)
		);
	}

	/**
	 * Log an image generation action.
	 *
	 * @since 1.1.0
	 * @param string $prompt    The image prompt.
	 * @param int    $object_id The object ID.
	 * @param string $status    Status.
	 * @return int|false Log ID.
	 */
	public function log_image_generation( $prompt, $object_id = null, $status = 'success' ) {
		return $this->log(
			array(
				'action_type'     => 'image_generate',
				'action_category' => 'image',
				'description'     => __( 'Generated AI image', 'assistify-for-woocommerce' ),
				'parameters'      => array( 'prompt' => $prompt ),
				'object_type'     => 'attachment',
				'object_id'       => $object_id,
				'status'          => $status,
			)
		);
	}

	/**
	 * Get logs with filters.
	 *
	 * Uses static SQL with conditional logic to satisfy WordPress Plugin Check.
	 * All filter conditions are handled in SQL using OR with null checks.
	 *
	 * @since 1.1.0
	 * @param array $args Query arguments.
	 * @return array Logs.
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id'         => 0,
			'user_type'       => '',
			'action_type'     => '',
			'action_category' => '',
			'status'          => '',
			'object_type'     => '',
			'object_id'       => 0,
			'date_from'       => '',
			'date_to'         => '',
			'search'          => '',
			'limit'           => 50,
			'offset'          => 0,
			'orderby'         => 'created_at',
			'order'           => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize all inputs.
		$user_id         = absint( $args['user_id'] );
		$user_type       = sanitize_text_field( $args['user_type'] );
		$action_type     = sanitize_text_field( $args['action_type'] );
		$action_category = sanitize_text_field( $args['action_category'] );
		$status          = sanitize_text_field( $args['status'] );
		$object_type     = sanitize_text_field( $args['object_type'] );
		$object_id       = absint( $args['object_id'] );
		$date_from       = sanitize_text_field( $args['date_from'] );
		$date_to         = sanitize_text_field( $args['date_to'] );
		$search          = sanitize_text_field( $args['search'] );
		$search_pattern  = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		// Validate orderby against whitelist - use switch for fully static result.
		switch ( $args['orderby'] ) {
			case 'action_type':
			case 'status':
			case 'user_id':
				$orderby = $args['orderby'];
				break;
			default:
				$orderby = 'created_at';
		}

		// Validate order direction.
		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Execute query based on orderby/order combination.
		// Using separate queries for each sort option to keep SQL fully static.
		$results = $this->execute_logs_query(
			$user_id,
			$user_type,
			$action_type,
			$action_category,
			$status,
			$object_type,
			$object_id,
			$date_from,
			$date_to,
			$search_pattern,
			$orderby,
			$order,
			$limit,
			$offset
		);

		// Decode JSON fields.
		if ( is_array( $results ) ) {
			foreach ( $results as &$row ) {
				if ( ! empty( $row['parameters'] ) ) {
					$row['parameters'] = json_decode( $row['parameters'], true );
				}
				if ( ! empty( $row['result'] ) ) {
					$row['result'] = json_decode( $row['result'], true );
				}
			}
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Execute logs query with static SQL.
	 *
	 * @since 1.1.0
	 * @param int    $user_id         User ID filter (0 = no filter).
	 * @param string $user_type       User type filter ('' = no filter).
	 * @param string $action_type     Action type filter.
	 * @param string $action_category Action category filter.
	 * @param string $status          Status filter.
	 * @param string $object_type     Object type filter.
	 * @param int    $object_id       Object ID filter (0 = no filter).
	 * @param string $date_from       Date from filter.
	 * @param string $date_to         Date to filter.
	 * @param string $search_pattern  Search pattern with wildcards.
	 * @param string $orderby         Order by column.
	 * @param string $order           Order direction.
	 * @param int    $limit           Result limit.
	 * @param int    $offset          Result offset.
	 * @return array Query results.
	 */
	private function execute_logs_query( $user_id, $user_type, $action_type, $action_category, $status, $object_type, $object_id, $date_from, $date_to, $search_pattern, $orderby, $order, $limit, $offset ) {
		global $wpdb;

		$table = $this->table_name;

		// Prepare values array - each filter value appears twice (for condition check and actual filter).
		// Then add limit and offset at the end.
		$values = array(
			$user_id,
			$user_id,
			$user_type,
			$user_type,
			$action_type,
			$action_type,
			$action_category,
			$action_category,
			$status,
			$status,
			$object_type,
			$object_type,
			$object_id,
			$object_id,
			$date_from,
			$date_from,
			$date_to,
			$date_to,
			$search_pattern,
			$search_pattern,
			$search_pattern,
			$limit,
			$offset,
		);

		// Execute query based on sort option.
		// Each case has a complete, literal SQL string - no concatenation allowed by PluginCheck.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		switch ( $orderby . '_' . $order ) {
			case 'action_type_ASC':
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY action_type ASC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
			case 'action_type_DESC':
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY action_type DESC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
			case 'status_ASC':
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY status ASC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
			case 'status_DESC':
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY status DESC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
			case 'user_id_ASC':
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY user_id ASC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
			case 'user_id_DESC':
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY user_id DESC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
			case 'created_at_ASC':
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY created_at ASC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
			default: // created_at_DESC.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM `{$table}` WHERE (%d = 0 OR user_id = %d) AND (%s = '' OR user_type = %s) AND (%s = '' OR action_type = %s) AND (%s = '' OR action_category = %s) AND (%s = '' OR status = %s) AND (%s = '' OR object_type = %s) AND (%d = 0 OR object_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) AND (%s = '' OR description LIKE %s OR ability_id LIKE %s) ORDER BY created_at DESC LIMIT %d OFFSET %d",
						...$values
					),
					ARRAY_A
				);
				break;
		}
		// phpcs:enable

		return $results;
	}

	/**
	 * Get total log count with filters.
	 *
	 * Uses static SQL with conditional logic to satisfy WordPress Plugin Check.
	 *
	 * @since 1.1.0
	 * @param array $args Query arguments.
	 * @return int Count.
	 */
	public function get_logs_count( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id'         => 0,
			'user_type'       => '',
			'action_type'     => '',
			'action_category' => '',
			'status'          => '',
			'date_from'       => '',
			'date_to'         => '',
			'search'          => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize all inputs.
		$user_id         = absint( $args['user_id'] );
		$user_type       = sanitize_text_field( $args['user_type'] );
		$action_category = sanitize_text_field( $args['action_category'] );
		$status          = sanitize_text_field( $args['status'] );
		$date_from       = sanitize_text_field( $args['date_from'] );
		$date_to         = sanitize_text_field( $args['date_to'] );
		$search          = sanitize_text_field( $args['search'] );
		$search_pattern  = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		$table = $this->table_name;

		// Static SQL with conditional WHERE logic.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = "SELECT COUNT(*) FROM `{$table}` WHERE
			(%d = 0 OR user_id = %d) AND
			(%s = '' OR user_type = %s) AND
			(%s = '' OR action_category = %s) AND
			(%s = '' OR status = %s) AND
			(%s = '' OR created_at >= %s) AND
			(%s = '' OR created_at <= %s) AND
			(%s = '' OR description LIKE %s OR ability_id LIKE %s)";

		$count = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a static literal string.
			$wpdb->prepare( $sql, $user_id, $user_id, $user_type, $user_type, $action_category, $action_category, $status, $status, $date_from, $date_from, $date_to, $date_to, $search_pattern, $search_pattern, $search_pattern )
		);
		// phpcs:enable

		return $count;
	}

	/**
	 * Get log statistics.
	 *
	 * @since 1.1.0
	 * @param int $days Number of days to include.
	 * @return array Statistics.
	 */
	public function get_statistics( $days = 30 ) {
		global $wpdb;

		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$table = $this->table_name;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Total actions.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
				$date_from
			)
		);

		// By status.
		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$table} WHERE created_at >= %s GROUP BY status",
				$date_from
			),
			ARRAY_A
		);

		// By category.
		$by_category = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT action_category, COUNT(*) as count FROM {$table} WHERE created_at >= %s GROUP BY action_category ORDER BY count DESC",
				$date_from
			),
			ARRAY_A
		);

		// By user type.
		$by_user_type = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_type, COUNT(*) as count FROM {$table} WHERE created_at >= %s GROUP BY user_type",
				$date_from
			),
			ARRAY_A
		);

		// Daily trend.
		$daily_trend = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as count FROM {$table} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY date ASC",
				$date_from
			),
			ARRAY_A
		);

		// phpcs:enable

		return array(
			'total'        => $total,
			'by_status'    => $by_status,
			'by_category'  => $by_category,
			'by_user_type' => $by_user_type,
			'daily_trend'  => $daily_trend,
			'period_days'  => $days,
		);
	}

	/**
	 * Clean up old logs.
	 *
	 * Used both as cron callback and direct method call.
	 *
	 * @since 1.1.0
	 * @return int|bool Number of deleted rows or false on error.
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		// Get retention from settings, default to class property.
		$setting_days   = absint( get_option( 'assistify_audit_log_retention', $this->retention_days ) );
		$retention_days = apply_filters( 'assistify_audit_log_retention_days', $setting_days );
		$cutoff_date    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
		$table          = $this->table_name;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff_date
			)
		);
		// phpcs:enable

		return $deleted;
	}

	/**
	 * Export logs to CSV.
	 *
	 * @since 1.1.0
	 * @param array $args Query arguments.
	 * @return string CSV content.
	 */
	public function export_csv( $args = array() ) {
		$args['limit'] = 10000; // Max export.
		$logs          = $this->get_logs( $args );

		$csv = "ID,User ID,User Type,Action Type,Category,Description,Ability,Status,IP Address,Created At\n";

		foreach ( $logs as $log ) {
			$csv .= sprintf(
				'%d,%d,"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
				$log['id'],
				$log['user_id'],
				$log['user_type'],
				$log['action_type'],
				$log['action_category'],
				str_replace( '"', '""', $log['description'] ),
				$log['ability_id'] ?? '',
				$log['status'],
				$log['ip_address'] ?? '',
				$log['created_at']
			);
		}

		return $csv;
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'assistify/v1',
			'/audit-logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_logs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'category' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'   => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'search'   => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'assistify/v1',
			'/audit-logs/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			'assistify/v1',
			'/audit-logs/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_export_logs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @since 1.1.0
	 * @return bool Whether user has permission.
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * REST endpoint: Get logs.
	 *
	 * @since 1.1.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_logs( $request ) {
		$page     = $request->get_param( 'page' );
		$per_page = min( $request->get_param( 'per_page' ), 100 );
		$offset   = ( $page - 1 ) * $per_page;

		$args = array(
			'action_category' => $request->get_param( 'category' ),
			'status'          => $request->get_param( 'status' ),
			'search'          => $request->get_param( 'search' ),
			'limit'           => $per_page,
			'offset'          => $offset,
		);

		$logs  = $this->get_logs( $args );
		$total = $this->get_logs_count( $args );

		// Enrich logs with user info.
		foreach ( $logs as &$log ) {
			$user = get_user_by( 'id', $log['user_id'] );
			if ( $user ) {
				$log['user_name']  = $user->display_name;
				$log['user_email'] = $user->user_email;
			} else {
				$log['user_name']  = __( 'Unknown User', 'assistify-for-woocommerce' );
				$log['user_email'] = '';
			}

			// Format time - use time() for UTC comparison.
			$log['time_ago'] = human_time_diff( strtotime( $log['created_at'] ), time() );
		}

		return rest_ensure_response(
			array(
				'logs'        => $logs,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * REST endpoint: Get statistics.
	 *
	 * @since 1.1.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_stats( $request ) {
		$days  = $request->get_param( 'days' ) ?? 30;
		$stats = $this->get_statistics( absint( $days ) );

		return rest_ensure_response( $stats );
	}

	/**
	 * REST endpoint: Export logs.
	 *
	 * @since 1.1.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_export_logs( $request ) {
		$args = array(
			'action_category' => $request->get_param( 'category' ),
			'status'          => $request->get_param( 'status' ),
			'date_from'       => $request->get_param( 'date_from' ),
			'date_to'         => $request->get_param( 'date_to' ),
		);

		$csv = $this->export_csv( $args );

		return new \WP_REST_Response(
			$csv,
			200,
			array(
				'Content-Type'        => 'text/csv',
				'Content-Disposition' => 'attachment; filename="assistify-audit-log-' . gmdate( 'Y-m-d' ) . '.csv"',
			)
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.1.0
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Get action category from ability ID.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @return string Category.
	 */
	private function get_category_from_ability( $ability_id ) {
		$parts = explode( '/', $ability_id );

		if ( count( $parts ) >= 2 ) {
			return $parts[1]; // afw/orders/get -> orders.
		}

		return 'general';
	}

	/**
	 * Get action type from ability ID.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @return string Action type.
	 */
	private function get_action_type_from_ability( $ability_id ) {
		$parts = explode( '/', $ability_id );

		if ( count( $parts ) >= 3 ) {
			return $parts[2]; // afw/orders/get -> get.
		}

		return $ability_id;
	}

	/**
	 * Get object type from ability ID.
	 *
	 * @since 1.1.0
	 * @param string $ability_id Ability ID.
	 * @return string|null Object type.
	 */
	private function get_object_type( $ability_id ) {
		$category = $this->get_category_from_ability( $ability_id );

		$type_map = array(
			'orders'        => 'order',
			'products'      => 'product',
			'customers'     => 'customer',
			'coupons'       => 'coupon',
			'subscriptions' => 'subscription',
			'bookings'      => 'booking',
			'memberships'   => 'membership',
		);

		return $type_map[ $category ] ?? null;
	}

	/**
	 * Get object ID from parameters.
	 *
	 * @since 1.1.0
	 * @param array $parameters Parameters.
	 * @return int|null Object ID.
	 */
	private function get_object_id( $parameters ) {
		if ( ! is_array( $parameters ) ) {
			return null;
		}

		// Common ID parameter names.
		$id_keys = array( 'order_id', 'product_id', 'customer_id', 'coupon_id', 'subscription_id', 'booking_id', 'membership_id', 'id' );

		foreach ( $id_keys as $key ) {
			if ( ! empty( $parameters[ $key ] ) ) {
				return absint( $parameters[ $key ] );
			}
		}

		return null;
	}

	/**
	 * Generate human-readable description.
	 *
	 * @since 1.1.0
	 * @param string $ability_id  Ability ID.
	 * @param array  $parameters  Parameters.
	 * @param mixed  $result      Result (unused, reserved for future use).
	 * @return string Description.
	 */
	private function generate_description( $ability_id, $parameters, $result ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$category  = $this->get_category_from_ability( $ability_id );
		$action    = $this->get_action_type_from_ability( $ability_id );
		$object_id = $this->get_object_id( $parameters );

		$descriptions = array(
			'orders'    => array(
				'get'           => __( 'Viewed order details', 'assistify-for-woocommerce' ),
				'list'          => __( 'Listed orders', 'assistify-for-woocommerce' ),
				'search'        => __( 'Searched orders', 'assistify-for-woocommerce' ),
				'update-status' => __( 'Updated order status', 'assistify-for-woocommerce' ),
				'refund'        => __( 'Processed order refund', 'assistify-for-woocommerce' ),
				'add-note'      => __( 'Added order note', 'assistify-for-woocommerce' ),
			),
			'products'  => array(
				'get'    => __( 'Viewed product details', 'assistify-for-woocommerce' ),
				'list'   => __( 'Listed products', 'assistify-for-woocommerce' ),
				'search' => __( 'Searched products', 'assistify-for-woocommerce' ),
				'update' => __( 'Updated product', 'assistify-for-woocommerce' ),
				'create' => __( 'Created product', 'assistify-for-woocommerce' ),
			),
			'customers' => array(
				'get'    => __( 'Viewed customer details', 'assistify-for-woocommerce' ),
				'list'   => __( 'Listed customers', 'assistify-for-woocommerce' ),
				'search' => __( 'Searched customers', 'assistify-for-woocommerce' ),
			),
			'coupons'   => array(
				'get'    => __( 'Viewed coupon details', 'assistify-for-woocommerce' ),
				'list'   => __( 'Listed coupons', 'assistify-for-woocommerce' ),
				'create' => __( 'Created coupon', 'assistify-for-woocommerce' ),
				'update' => __( 'Updated coupon', 'assistify-for-woocommerce' ),
				'delete' => __( 'Deleted coupon', 'assistify-for-woocommerce' ),
			),
			'content'   => array(
				'product-title'       => __( 'Generated product title', 'assistify-for-woocommerce' ),
				'product-description' => __( 'Generated product description', 'assistify-for-woocommerce' ),
				'short-description'   => __( 'Generated short description', 'assistify-for-woocommerce' ),
				'meta-description'    => __( 'Generated meta description', 'assistify-for-woocommerce' ),
				'product-tags'        => __( 'Generated product tags', 'assistify-for-woocommerce' ),
			),
			'image'     => array(
				'text-to-image'     => __( 'Generated image from text', 'assistify-for-woocommerce' ),
				'featured-image'    => __( 'Generated featured image', 'assistify-for-woocommerce' ),
				'product-image'     => __( 'Generated product image', 'assistify-for-woocommerce' ),
				'edit'              => __( 'Edited image with AI', 'assistify-for-woocommerce' ),
				'remove-background' => __( 'Removed image background', 'assistify-for-woocommerce' ),
			),
		);

		$description = $descriptions[ $category ][ $action ] ?? sprintf(
			/* translators: %s: ability identifier */
			__( 'Executed ability: %s', 'assistify-for-woocommerce' ),
			$ability_id
		);

		if ( $object_id ) {
			$description .= ' #' . $object_id;
		}

		return $description;
	}
}
