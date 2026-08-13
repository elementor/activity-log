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

		if ( empty( $_GET['aal-record-action'] ) || ! wp_verify_nonce( $_GET['aal_actions_nonce'], 'aal_actions_nonce' ) ) {
			$this->redirect_back();
		}

		if ( isset( $_GET['page'] ) && 'activity-log-page' !== $_GET['page'] ) {
			$this->redirect_back();
		}

		$exporter_selected = $_GET['aal-record-action'];

		// If exporter doesn't exist or isn't registered, bail
		if ( ! array_key_exists( $exporter_selected, $this->get_exporters() ) ) {
			$this->redirect_back();
		}

		$this->insert_export_log();

		// Disable row limit
		add_filter( 'edit_aal_logs_per_page', array( $this, 'increase_throughput' ) );

		// Prep items for export
		$list_table->prepare_items();
		$items = $list_table->items;
		$columns = $list_table->get_columns();

		$op = array();
		foreach ( $items as $item ) {
			$op[] = $this->prep_row( $item, $columns, $list_table );
		}

		$exporter = $this->exporters[ $exporter_selected ];
		$exporter->write( $op, $columns );
	}

	protected function redirect_back() {
		wp_redirect( menu_page_url( 'activity-log-page', false ) );
		exit;
	}

	/**
	 * @param stdClass                    $item
	 * @param array                       $columns
	 * @param AAL_Activity_Log_List_Table $list_table
	 *
	 * @return array
	 */
	private function prep_row( $item, $columns, $list_table ) {
		$row = array();

		foreach ( array_keys( $columns ) as $column ) {
			switch ( $column ) {
				case 'date':
					$row[ $column ] = date_i18n( get_option( 'date_format' ), $item->hist_time ) . ' ' . date_i18n( get_option( 'time_format' ), $item->hist_time );;
					break;

				case 'author':
					$user = get_userdata( $item->user_id );
					$row[ $column ] = isset( $user->display_name ) ? $user->display_name : 'unknown';
					break;

				case 'source':
					if ( AAL_Maintenance::is_schema_ready( '1.1' ) && ! empty( $item->request_source ) ) {
						$row[ $column ] = self::format_source_label( $item->request_source );
					} else {
						$row[ $column ] = '';
					}
					break;

				case 'ip':
					$row[ $column ] = $item->hist_ip;
					break;

				case 'type':
					$row[ $column ] = $item->object_type;
					break;

				case 'label':
					$row[ $column ] = $item->object_subtype;
					break;

				case 'action':
					$row[ $column ] = $list_table->get_action_label( $item->action );
					break;

				case 'description':
					$row[ $column ] = $item->object_name;
					break;
			}
		}

		return $row;
	}

	private function insert_export_log() {
		aal_insert_log( array(
			'action' => 'exported',
			'object_type' => 'Options',
			'object_name' => 'exported',
			'object_subtype' => 'Activity Log',
		) );
	}

	public function admin_register_exporters() {
		$builtin_exporters = array(
			'csv',
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

	/**
	 * Increase throughput
	 *
	 * @param int $records_per_page Old limit of records
	 *
	 * @return int
	 */
	public function increase_throughput( $records_per_page ) {
		return PHP_INT_MAX;
	}

	private static function format_source_label( $raw ) {
		$parsed = AAL_API::parse_request_source( $raw );
		$parts = array();
		$channel_labels = AAL_API::get_channel_labels();

		if ( ! empty( $parsed['channel'] ) && isset( $channel_labels[ $parsed['channel'] ] ) ) {
			$parts[] = $channel_labels[ $parsed['channel'] ];
		}

		if ( ! empty( $parsed['app_name'] ) ) {
			$parts[] = 'App Password: ' . $parsed['app_name'];
		} elseif ( false !== strpos( $raw, 'app:' ) ) {
			$parts[] = 'App Password';
		}

		return implode( '; ', $parts );
	}
}
