<?php
/**
 * GDPR and Privacy compliance handler.
 *
 * Handles WordPress privacy tools integration including:
 * - Privacy policy suggested text
 * - Personal data export
 * - Personal data erasure
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

namespace Assistify_For_WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy class for GDPR compliance.
 *
 * @since 1.0.0
 */
class Assistify_Privacy {

	/**
	 * Initialize privacy hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Add privacy policy suggested text.
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );

		// Register personal data exporter.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );

		// Register personal data eraser.
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Add privacy policy suggested text.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = $this->get_privacy_policy_text();

		wp_add_privacy_policy_content(
			__( 'Assistify for WooCommerce', 'assistify-for-woocommerce' ),
			wp_kses_post( $content )
		);
	}

	/**
	 * Get privacy policy suggested text.
	 *
	 * @since 1.0.0
	 * @return string Privacy policy content.
	 */
	private function get_privacy_policy_text() {
		$content = '<h2>' . esc_html__( 'AI Chat Assistant', 'assistify-for-woocommerce' ) . '</h2>';

		$content .= '<p>' . esc_html__( 'When you use our AI chat assistant, we collect and process the following data:', 'assistify-for-woocommerce' ) . '</p>';

		$content .= '<h3>' . esc_html__( 'What data we collect', 'assistify-for-woocommerce' ) . '</h3>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Chat messages and conversations with the AI assistant', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Session identifiers to maintain conversation context', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Timestamps of when conversations occurred', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'For logged-in users: your user ID to associate conversations with your account', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h3>' . esc_html__( 'How we use this data', 'assistify-for-woocommerce' ) . '</h3>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'To provide AI-powered customer support and assistance', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'To maintain conversation history during your session', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'To improve our support services', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h3>' . esc_html__( 'Third-party AI services', 'assistify-for-woocommerce' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Your chat messages are processed by third-party AI services (such as OpenAI, Anthropic, or Google) to generate responses. These services receive only the conversation content necessary to provide assistance. We do not send sensitive personal data like payment information or passwords to these services.', 'assistify-for-woocommerce' ) . '</p>';

		$content .= '<h3>' . esc_html__( 'Data retention', 'assistify-for-woocommerce' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Chat conversations are stored on our servers. You can request export or deletion of your chat data through the standard WordPress data request process.', 'assistify-for-woocommerce' ) . '</p>';

		$content .= '<h3>' . esc_html__( 'Your rights', 'assistify-for-woocommerce' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Under GDPR and similar regulations, you have the right to:', 'assistify-for-woocommerce' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Access your chat conversation data', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Request deletion of your chat data', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Export your chat data in a portable format', 'assistify-for-woocommerce' ) . '</li>';
		$content .= '</ul>';

		return $content;
	}

	/**
	 * Register personal data exporter.
	 *
	 * @since 1.0.0
	 * @param array $exporters Array of registered exporters.
	 * @return array Modified exporters array.
	 */
	public function register_exporter( $exporters ) {
		$exporters['assistify-chat-data'] = array(
			'exporter_friendly_name' => __( 'Assistify Chat Data', 'assistify-for-woocommerce' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Register personal data eraser.
	 *
	 * @since 1.0.0
	 * @param array $erasers Array of registered erasers.
	 * @return array Modified erasers array.
	 */
	public function register_eraser( $erasers ) {
		$erasers['assistify-chat-data'] = array(
			'eraser_friendly_name' => __( 'Assistify Chat Data', 'assistify-for-woocommerce' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Export personal data for a user.
	 *
	 * @since 1.0.0
	 * @param string $email_address User's email address.
	 * @param int    $page          Page number for pagination.
	 * @return array Export data array.
	 */
	public function export_personal_data( $email_address, $page = 1 ) {
		global $wpdb;

		$export_items = array();
		$done         = true;
		$page         = (int) $page;
		$limit        = 100;
		$offset       = ( $page - 1 ) * $limit;

		// Get user by email.
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => $export_items,
				'done' => true,
			);
		}

		$user_id = $user->ID;

		// Get user's chat sessions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT session_id, context, started_at, last_activity 
				FROM ' . $wpdb->prefix . 'afw_sessions 
				WHERE user_id = %d 
				ORDER BY started_at DESC 
				LIMIT %d OFFSET %d',
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $sessions ) ) {
			return array(
				'data' => $export_items,
				'done' => true,
			);
		}

		foreach ( $sessions as $session ) {
			// Get messages for this session.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT role, content, created_at 
					FROM ' . $wpdb->prefix . 'afw_messages 
					WHERE session_id = %s 
					ORDER BY created_at ASC',
					$session['session_id']
				),
				ARRAY_A
			);

			$conversation_data = array();
			foreach ( $messages as $message ) {
				$conversation_data[] = sprintf(
					'[%s] %s: %s',
					$message['created_at'],
					ucfirst( $message['role'] ),
					wp_strip_all_tags( $message['content'] )
				);
			}

			$data = array(
				array(
					'name'  => __( 'Chat Type', 'assistify-for-woocommerce' ),
					'value' => ucfirst( $session['context'] ),
				),
				array(
					'name'  => __( 'Started', 'assistify-for-woocommerce' ),
					'value' => $session['started_at'],
				),
				array(
					'name'  => __( 'Last Activity', 'assistify-for-woocommerce' ),
					'value' => $session['last_activity'],
				),
				array(
					'name'  => __( 'Conversation', 'assistify-for-woocommerce' ),
					'value' => implode( "\n", $conversation_data ),
				),
			);

			$export_items[] = array(
				'group_id'          => 'assistify-chat-sessions',
				'group_label'       => __( 'AI Chat Sessions', 'assistify-for-woocommerce' ),
				'group_description' => __( 'Your conversations with the AI assistant.', 'assistify-for-woocommerce' ),
				'item_id'           => 'session-' . $session['session_id'],
				'data'              => $data,
			);
		}

		// Check if there are more sessions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_sessions = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'afw_sessions WHERE user_id = %d',
				$user_id
			)
		);

		$done = ( $offset + count( $sessions ) ) >= $total_sessions;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	/**
	 * Erase personal data for a user.
	 *
	 * @since 1.0.0
	 * @param string $email_address User's email address.
	 * @param int    $page          Page number for pagination.
	 * @return array Eraser result array.
	 */
	public function erase_personal_data( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();
		$done           = true;

		// Get user by email.
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$user_id = $user->ID;

		// Get user's session IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT session_id FROM ' . $wpdb->prefix . 'afw_sessions WHERE user_id = %d',
				$user_id
			)
		);

		if ( ! empty( $session_ids ) ) {
			// Delete messages for these sessions.
			foreach ( $session_ids as $session_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete(
					$wpdb->prefix . 'afw_messages',
					array( 'session_id' => $session_id ),
					array( '%s' )
				);
			}

			// Delete sessions.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted_sessions = $wpdb->delete(
				$wpdb->prefix . 'afw_sessions',
				array( 'user_id' => $user_id ),
				array( '%d' )
			);

			if ( $deleted_sessions > 0 ) {
				$items_removed = true;
				$messages[]    = sprintf(
					/* translators: %d: Number of deleted chat sessions */
					__( 'Removed %d chat session(s) and associated messages.', 'assistify-for-woocommerce' ),
					$deleted_sessions
				);
			}
		}

		// Delete action logs for this user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted_actions = $wpdb->delete(
			$wpdb->prefix . 'afw_actions',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		if ( $deleted_actions > 0 ) {
			$items_removed = true;
			$messages[]    = sprintf(
				/* translators: %d: Number of deleted action log records */
				__( 'Removed %d action log record(s).', 'assistify-for-woocommerce' ),
				$deleted_actions
			);
		}

		// Clear any transients.
		delete_transient( 'assistify_chat_history_' . $user_id );

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Get privacy policy page URL.
	 *
	 * @since 1.0.0
	 * @return string|false Privacy policy URL or false if not set.
	 */
	public static function get_privacy_policy_url() {
		$privacy_page_id = (int) get_option( 'wp_page_for_privacy_policy' );

		if ( ! $privacy_page_id ) {
			return false;
		}

		$privacy_page = get_post( $privacy_page_id );

		if ( ! $privacy_page || 'publish' !== $privacy_page->post_status ) {
			return false;
		}

		return get_permalink( $privacy_page_id );
	}
}
