<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AAL_Privacy
 * @since 2.1.0
 */
class AAL_Privacy {

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	public function register_exporter( $exporters ) {
		$exporters['activity-log'] = array(
			'exporter_friendly_name' => __( 'Activity Log Plugin', 'aryo-activity-log' ),
			'callback' => array( $this, 'wp_exporter' ),
		);
		return $exporters;
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

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS * FROM $wpdb->activity_log
			WHERE `user_id` = %d
			ORDER BY `hist_time` ASC
			LIMIT %d, %d;",
			$user->ID, $page, $number
		) );

		$found_rows = $wpdb->get_var( 'SELECT FOUND_ROWS();' );

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

		// Tell core if we have more comments to work on still
		$done = $found_rows < $number;
		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf( __( 'If you are a registered user, we save your content activity like create/update/delete posts and comments.', 'aryo-activity-log' ) );

		wp_add_privacy_policy_content(
			__( 'Activity Log', 'aryo-activity-log' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	public function get_action_label( $action ) {
		return ucwords( str_replace( '_', ' ', __( $action, 'aryo-activity-log' ) ) );
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
