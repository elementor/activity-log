<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AAL_Export {

	private $exporters;

	public function __construct() {
		add_action( 'aal_admin_page_load', array( $this, 'admin_register_exporters' ) );
		add_action( 'aal_admin_page_load', array( $this, 'admin_capture_action' ), 20 );

		add_filter( 'aal_record_actions', array( $this, 'filter_register_actions' ) );
	}

	public function filter_register_actions( $actions ) {
		foreach ( $this->get_exporters() as $exporter ) {
			$actions[ $exporter->id ] = $exporter->name;
		}

		return $actions;
	}

	public function admin_capture_action( $list_table ) {
		if ( empty( $_GET['aal-record-actions-submit'] ) ) {
			return;
		}

		if ( empty( $_GET['aal_actions_nonce'] ) ) {
			$this->redirect_back();
		}

		if ( empty( $_GET['aal-record-action'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['aal_actions_nonce'] ) ), 'aal_actions_nonce' ) ) {
			$this->redirect_back();
		}

		if ( isset( $_GET['page'] ) && 'activity-log-page' !== $_GET['page'] ) {
			$this->redirect_back();
		}

		$exporter_selected = sanitize_text_field( wp_unslash( $_GET['aal-record-action'] ) );

		// If exporter doesn't exist or isn't registered, bail
		if ( ! array_key_exists( $exporter_selected, $this->get_exporters() ) ) {
			$this->redirect_back();
		}

		$this->insert_export_log();

		$exporter = $this->exporters[ $exporter_selected ];

		$query_args = $this->get_export_query_args_from_request();
		$columns    = $this->get_export_columns( $exporter );
		$rows     = $this->export_rows_generator( $query_args, $columns );
		$payload  = $this->get_export_write_payload( $exporter, $rows );

		if ( ! empty( $exporter->supports_iterable ) && $this->is_live_export() ) {
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			nocache_headers();
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 0 );
			}
		}

		$exporter->write( $payload, $columns );
	}

	/**
	 * @param object $exporter
	 * @param Traversable $rows
	 * @return array|Traversable
	 */
	protected function get_export_write_payload( $exporter, $rows ) {
		if ( ! empty( $exporter->supports_iterable ) ) {
			return $rows;
		}

		return iterator_to_array( $rows, false );
	}

	protected function redirect_back() {
		wp_safe_redirect( menu_page_url( 'activity-log-page', false ) );
		exit;
	}

	private function insert_export_log() {
		aal_insert_log( array(
			'action' => 'exported',
			'object_type' => 'Options',
			'object_name' => 'exported',
			'object_subtype' => 'Activity Log',
		) );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_export_columns( $exporter = null ): array {
		$columns = array(
			'date'         => __( 'Date', 'aryo-activity-log' ),
			'author'       => __( 'User', 'aryo-activity-log' ),
			'app_password' => __( 'App Password', 'aryo-activity-log' ),
			'source'       => __( 'Source', 'aryo-activity-log' ),
			'type'         => __( 'Topic', 'aryo-activity-log' ),
			'label'       => __( 'Context', 'aryo-activity-log' ),
			'description' => __( 'Meta', 'aryo-activity-log' ),
			'action'      => __( 'Action', 'aryo-activity-log' ),
			'changes'     => __( 'Changes', 'aryo-activity-log' ),
			'time_iso'    => __( 'Time (ISO 8601)', 'aryo-activity-log' ),
			'user_id'     => __( 'User ID', 'aryo-activity-log' ),
			'object_id'   => __( 'Object ID', 'aryo-activity-log' ),
			'action_key'  => __( 'Action Key', 'aryo-activity-log' ),
		);

		if ( isset( $exporter->include_ip ) && false === $exporter->include_ip ) {
			return $columns;
		}

		return $this->add_export_ip_column( $columns );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_export_query_args_from_request(): array {
		$query_args = array();

		$filter_keys = array( 'dateshow', 'capshow', 'usershow', 'typeshow', 'showaction', 'sourceshow', 'filter_ip', 's' );
		foreach ( $filter_keys as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$query_args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		return $query_args;
	}

	/**
	 * @param array<string, string> $query_args
	 * @param array<string, string> $columns
	 * @return Generator<int, array<string, string>>
	 */
	private function export_rows_generator( array $query_args, array $columns ) {
		$query      = new AAL_Log_Query();
		$batch_size = (int) apply_filters( 'aal_export_batch_size', 1000 );
		$batch_size = max( 1, $batch_size );
		$min_id     = 0;

		if ( ! empty( $query_args['dateshow'] ) ) {
			$min_id = $this->get_export_min_hist_id( $query_args['dateshow'] );
		}

		$iterate_args = array_merge(
			$query_args,
			array(
				'orderby' => 'histid',
				'order'   => 'DESC',
			)
		);

		if ( $min_id > 0 ) {
			$iterate_args['min_id'] = $min_id;
		}

		foreach ( $query->iterate_items( $iterate_args, $batch_size ) as $items ) {
			foreach ( $items as $item ) {
				yield AAL_Log_Presenter::to_export_row( $item, $columns );
			}

			if ( $this->is_live_export() ) {
				flush();
			}
		}
	}

	private function get_export_min_hist_id( $dateshow ) {
		global $wpdb;

		$date_range = AAL_Log_Query::get_date_range( $dateshow );
		if ( null === $date_range ) {
			return 0;
		}

		$min_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MIN(`histid`) FROM `' . $wpdb->activity_log . '` WHERE `hist_time` >= %d',
				$date_range['start']
			)
		);

		return $min_id ? (int) $min_id : 0;
	}

	private function is_live_export(): bool {
		return ! defined( 'AAL_TESTMODE' ) || ! AAL_TESTMODE;
	}

	public function admin_register_exporters() {
		$builtin_exporters = array(
			'csv',
			'jsonl',
		);

		$exporter_instances = array();

		foreach ( $builtin_exporters as $exporter ) {
			include_once sprintf( '%s/exporters/%s', dirname( ACTIVITY_LOG__FILE__ ), 'class-aal-exporter-' . $exporter . '.php' );

			$classname = sprintf( 'AAL_Exporter_%s', str_replace( '-', '_', $exporter ) );
			if ( ! class_exists( $classname ) ) {
				continue;
			}

			$instance = new $classname;
			if ( ! property_exists( $instance, 'id' ) ) {
				continue;
			}

			$exporter_instances[ $instance->id ] = $instance;
		}

		/**
		 * Allows for adding additional exporters via classes that extend Exporter.
		 *
		 * @param array $classes An array of Exporter objects. In the format exporter_slug => Exporter_Class()
		 */
		$this->exporters = apply_filters( 'aal_exporters', $exporter_instances );
	}

	/**
	 * Returns an array with all available exporters
	 *
	 * @return array
	 */
	private function get_exporters() {
		return $this->exporters;
	}

	private function add_export_ip_column( array $columns ): array {
		if ( 'no-collect-ip' === AAL_Main::instance()->settings->get_option( 'log_visitor_ip_source' ) ) {
			return $columns;
		}

		$ip_label = __( 'IP', 'aryo-activity-log' );

		$result = array();
		$inserted = false;
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'source' === $key ) {
				$result['ip'] = $ip_label;
				$inserted = true;
			}
		}

		if ( ! $inserted ) {
			$result['ip'] = $ip_label;
		}

		return $result;
	}
}
