<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AAL_Privacy
 * @since 2.1.0
 */
class AAL_Privacy {

	/**
	 * Stored in hist_ip for anonymized rows. Empty on purpose so it is not
	 * confused with the 127.0.0.1 column default used when no IP is known (cron, CLI).
	 */
	const ANONYMIZED_IP = '';

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'wp_privacy_personal_data_erased', array( $this, 'log_personal_data_erased' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	public function register_exporter( $exporters ) {
		$exporters['activity-log'] = array(
			'exporter_friendly_name' => __( 'Activity Log Plugin', 'aryo-activity-log' ),
			'callback' => array( $this, 'wp_exporter' ),
		);
		return $exporters;
	}

	public function register_eraser( $erasers ) {
		$erasers['activity-log'] = array(
			'eraser_friendly_name' => __( 'Activity Log Plugin', 'aryo-activity-log' ),
			'callback' => array( $this, 'wp_eraser' ),
		);
		return $erasers;
	}

	public function wp_eraser( $email_address, $page = 1 ) {
		$number = 500;

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'items_removed' => false,
				'items_retained' => false,
				'messages' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->activity_log SET user_id = 0, hist_ip = %s WHERE user_id = %d LIMIT %d",
				self::ANONYMIZED_IP,
				$user->ID,
				$number
			)
		);
		$affected_performed = $wpdb->rows_affected;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->activity_log SET object_name = 'Anonymized user', meta = NULL WHERE object_type = 'Users' AND object_id = %d AND object_name <> 'Anonymized user' LIMIT %d",
				$user->ID,
				$number
			)
		);
		$affected_about = $wpdb->rows_affected;

		$done = $affected_performed < $number && $affected_about < $number;

		return array(
			'items_removed' => ( $affected_performed + $affected_about ) > 0,
			'items_retained' => false,
			'messages' => array(),
			'done' => $done,
		);
	}

	public function log_personal_data_erased( $request_id ) {
		aal_insert_log(
			array(
				'action' => 'personal_data_erased',
				'object_type' => 'Users',
				'object_id' => absint( $request_id ),
				'object_name' => sprintf( 'Personal data request #%d', absint( $request_id ) ),
			)
		);
	}

	public function wp_exporter( $email_address, $page = 1 ) {
		$number = 500; // Limit us to avoid timing out
		$page = (int) $page;

		$export_items = array();

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$offset = ( $page - 1 ) * $number;

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $wpdb->activity_log
			WHERE `user_id` = %d
			ORDER BY `hist_time` ASC, `histid` ASC
			LIMIT %d, %d;",
			$user->ID, $offset, $number
		) );

		$group_id = 'activity-log';
		$group_label = __( 'Activity Log', 'aryo-activity-log' );

		foreach ( $items as $item ) {
			$item_id = "activity-log-{$item->histid}";
			$created = date( 'Y-m-d H:i:s', $item->hist_time );
			$data = array(
				array(
					'name' => __( 'Time', 'aryo-activity-log' ),
					'value' => get_date_from_gmt( $created, 'Y/m/d h:i:s A' ),
				),
				array(
					'name' => __( 'Action', 'aryo-activity-log' ),
					'value' => $this->get_action_label( $item->action ),
				),
				array(
					'name' => __( 'Topic', 'aryo-activity-log' ),
					'value' => $item->object_type,
				),
				array(
					'name' => __( 'Context', 'aryo-activity-log' ),
					'value' => $item->object_subtype,
				),
				array(
					'name' => __( 'Meta', 'aryo-activity-log' ),
					'value' => $item->object_name,
				),
				array(
					'name' => __( 'IP', 'aryo-activity-log' ),
					'value' => $item->hist_ip,
				),
			);

			if ( AAL_Maintenance::is_schema_ready( '1.1' ) && ! empty( $item->request_source ) ) {
				$parsed = AAL_API::parse_request_source( $item->request_source );
				$source_value = $this->format_source_for_privacy( $parsed, $item->request_source );
				if ( '' !== $source_value ) {
					$data[] = array(
						'name'  => __( 'Source', 'aryo-activity-log' ),
						'value' => $source_value,
					);
				}
			}

			$export_items[] = array(
				'group_id' => $group_id,
				'group_label' => $group_label,
				'item_id' => $item_id,
				'data' => $data,
			);
		} // End foreach().

		$done = count( $items ) < $number;
		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			__( 'If you are a registered user, we save your content activity like create/update/delete posts and comments. When a profile or comment is updated, we may also store the previous and new values of the changed fields (for example email address or display name) in the activity log.', 'aryo-activity-log' )
		) . ' ' . __( 'When you request erasure of your personal data, your activity log entries are anonymized and a record of the erasure request is kept.', 'aryo-activity-log' );

		wp_add_privacy_policy_content(
			__( 'Activity Log', 'aryo-activity-log' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	public function get_action_label( $action ) {
		return AAL_Log_Presenter::get_action_label( $action );
	}

	private function format_source_for_privacy( $parsed, $raw ) {
		$parts = array();
		$channel_labels = AAL_API::get_channel_labels();

		if ( ! empty( $parsed['channel'] ) && isset( $channel_labels[ $parsed['channel'] ] ) ) {
			$parts[] = $channel_labels[ $parsed['channel'] ];
		}

		if ( ! empty( $parsed['app_name'] ) ) {
			$parts[] = __( 'App Password', 'aryo-activity-log' ) . ': ' . $parsed['app_name'];
		} elseif ( false !== strpos( $raw, 'app:' ) ) {
			$parts[] = __( 'App Password', 'aryo-activity-log' );
		}

		return implode( '; ', $parts );
	}
}
