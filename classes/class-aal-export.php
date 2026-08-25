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

		$query = new AAL_Log_Query();

		$query_args = array(
			'per_page' => PHP_INT_MAX,
		);

		$filter_keys = array( 'dateshow', 'capshow', 'usershow', 'typeshow', 'showaction', 'sourceshow', 'filter_ip', 's' );
		foreach ( $filter_keys as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$query_args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		$result = $query->query( $query_args );

		$columns = array(
			'date'        => __( 'Date', 'aryo-activity-log' ),
			'author'      => __( 'User', 'aryo-activity-log' ),
			'source'      => __( 'Source', 'aryo-activity-log' ),
			'type'        => __( 'Topic', 'aryo-activity-log' ),
			'label'       => __( 'Context', 'aryo-activity-log' ),
			'description' => __( 'Meta', 'aryo-activity-log' ),
			'action'      => __( 'Action', 'aryo-activity-log' ),
		);

		$op = array();
		foreach ( $result['items'] as $item ) {
			$op[] = AAL_Log_Presenter::to_export_row( $item, $columns );
		}

		$exporter = $this->exporters[ $exporter_selected ];
		$exporter->write( $op, $columns );
	}

	protected function redirect_back() {
		wp_redirect( menu_page_url( 'activity-log-page', false ) );
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

}
