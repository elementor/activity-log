<?php

class AAL_Test_Export extends WP_UnitTestCase {

	private function get_export_instance(): AAL_Export {
		return new AAL_Export();
	}

	private function invoke_add_export_ip_column( AAL_Export $export, array $columns ): array {
		$method = new ReflectionMethod( AAL_Export::class, 'add_export_ip_column' );
		$method->setAccessible( true );

		return $method->invoke( $export, $columns );
	}

	private function set_ip_source_option( string $value ): void {
		update_option( 'activity-log-settings', [ 'log_visitor_ip_source' => $value ] );

		$settings = AAL_Main::instance()->settings;
		$ref = new ReflectionProperty( $settings, 'options' );
		$ref->setAccessible( true );
		$ref->setValue( $settings, null );
	}

	public function test_ip_column_inserted_after_source() {
		$this->set_ip_source_option( 'REMOTE_ADDR' );
		$export = $this->get_export_instance();

		$columns = [
			'date'   => 'Date',
			'author' => 'User',
			'source' => 'Source',
			'type'   => 'Topic',
		];

		$result = $this->invoke_add_export_ip_column( $export, $columns );

		$keys = array_keys( $result );
		$this->assertSame( [ 'date', 'author', 'source', 'ip', 'type' ], $keys );
		$this->assertSame( 'IP', $result['ip'] );
	}

	public function test_ip_column_omitted_when_no_collect() {
		$this->set_ip_source_option( 'no-collect-ip' );
		$export = $this->get_export_instance();

		$columns = [
			'date'   => 'Date',
			'author' => 'User',
			'source' => 'Source',
			'type'   => 'Topic',
		];

		$result = $this->invoke_add_export_ip_column( $export, $columns );

		$this->assertArrayNotHasKey( 'ip', $result );
		$this->assertSame( [ 'date', 'author', 'source', 'type' ], array_keys( $result ) );
	}

	public function test_ip_column_appended_when_source_column_missing() {
		$this->set_ip_source_option( 'REMOTE_ADDR' );
		$export = $this->get_export_instance();

		$columns = [
			'date'   => 'Date',
			'author' => 'User',
			'type'   => 'Topic',
		];

		$result = $this->invoke_add_export_ip_column( $export, $columns );

		$keys = array_keys( $result );
		$this->assertSame( [ 'date', 'author', 'type', 'ip' ], $keys );
		$this->assertSame( 'IP', $result['ip'] );
	}

	public function test_export_row_populates_source_and_ip() {
		$columns = [
			'source' => 'Source',
			'ip'     => 'IP',
		];

		$item = (object) [
			'hist_ip'        => '10.0.0.1',
			'request_source' => 'rest',
			'hist_time'      => time(),
			'user_id'        => 0,
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'hello',
			'action'         => 'updated',
		];

		$row = AAL_Log_Presenter::to_export_row( $item, $columns );

		$this->assertSame( '10.0.0.1', $row['ip'] );
		$this->assertNotEmpty( $row['source'] );
	}

	public function test_export_row_ip_empty_source_when_no_request_source() {
		$columns = [
			'source' => 'Source',
			'ip'     => 'IP',
		];

		$item = (object) [
			'hist_ip'        => '192.168.1.1',
			'request_source' => '',
			'hist_time'      => time(),
			'user_id'        => 0,
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'test',
			'action'         => 'created',
		];

		$row = AAL_Log_Presenter::to_export_row( $item, $columns );

		$this->assertSame( '192.168.1.1', $row['ip'] );
		$this->assertSame( '', $row['source'] );
	}

	public function test_export_row_app_password_column_with_app() {
		$columns = [
			'app_password' => 'App Password',
			'source'       => 'Source',
		];

		$item = (object) [
			'hist_ip'        => '',
			'request_source' => 'rest|app:My App',
			'hist_time'      => time(),
			'user_id'        => 0,
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'hello',
			'action'         => 'updated',
		];

		$row = AAL_Log_Presenter::to_export_row( $item, $columns );

		$this->assertSame( 'My App', $row['app_password'] );
		$this->assertStringNotContainsString( 'App Password', $row['source'] );
	}

	public function test_export_row_app_password_column_without_app() {
		$columns = [
			'app_password' => 'App Password',
			'source'       => 'Source',
		];

		$item = (object) [
			'hist_ip'        => '',
			'request_source' => 'rest',
			'hist_time'      => time(),
			'user_id'        => 0,
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'hello',
			'action'         => 'updated',
		];

		$row = AAL_Log_Presenter::to_export_row( $item, $columns );

		$this->assertSame( '', $row['app_password'] );
	}

	public function test_export_row_changes_column() {
		$columns = [ 'changes' => 'Changes' ];
		$item    = (object) [
			'meta' => wp_json_encode(
				array(
					'changes'         => array( 'blogname' => array( 'Old', 'New' ) ),
					'changes_omitted' => 3,
				)
			),
		];

		$row = AAL_Log_Presenter::to_export_row( $item, $columns );

		$this->assertSame( 'blogname: Old → New; +3 omitted', $row['changes'] );
	}

	public function test_export_row_changes_empty_for_old_rows() {
		$columns = [ 'changes' => 'Changes' ];
		$item    = (object) [ 'meta' => '' ];

		$row = AAL_Log_Presenter::to_export_row( $item, $columns );

		$this->assertSame( '', $row['changes'] );
	}

	public function test_export_jsonl_omits_ip_while_csv_includes_ip() {
		global $wpdb;

		if ( ! defined( 'AAL_TESTMODE' ) ) {
			define( 'AAL_TESTMODE', true );
		}

		$this->set_ip_source_option( 'REMOTE_ADDR' );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$marker = 'jsonl-export-ip-test';

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_subtype' => 'post',
				'object_name'    => $marker,
				'user_id'        => $user_id,
				'user_caps'      => 'administrator',
				'hist_time'      => time(),
				'hist_ip'        => '203.0.113.10',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		$export = $this->get_export_instance();
		$export->admin_register_exporters();

		$csv_exporter   = new AAL_Exporter_csv();
		$jsonl_exporter = new AAL_Exporter_jsonl();

		$csv_columns   = $this->invoke_get_export_columns( $export, $csv_exporter );
		$jsonl_columns = $this->invoke_get_export_columns( $export, $jsonl_exporter );

		$this->assertArrayHasKey( 'ip', $csv_columns );
		$this->assertArrayNotHasKey( 'ip', $jsonl_columns );

		$query_args = array( 's' => $marker );
		$csv_rows   = $this->invoke_export_rows_generator( $export, $query_args, $csv_columns );
		$jsonl_rows = $this->invoke_export_rows_generator( $export, $query_args, $jsonl_columns );

		ob_start();
		$csv_exporter->write( $csv_rows, $csv_columns );
		$csv_output = ob_get_clean();

		ob_start();
		$jsonl_exporter->write( $jsonl_rows, $jsonl_columns );
		$jsonl_output = ob_get_clean();

		$csv_fp = fopen( 'php://memory', 'r+' );
		fwrite( $csv_fp, $csv_output );
		rewind( $csv_fp );
		$csv_header = fgetcsv( $csv_fp, 0, ',', AAL_Exporter_csv::CSV_ENCLOSURE, AAL_Exporter_csv::CSV_ESCAPE );
		fclose( $csv_fp );

		$this->assertContains( 'IP', $csv_header );

		$jsonl_lines = array_filter( explode( "\n", trim( $jsonl_output ) ) );
		$this->assertCount( 1, $jsonl_lines );

		$decoded = json_decode( $jsonl_lines[0], true );
		$this->assertIsArray( $decoded );
		$this->assertSame( array_keys( $jsonl_columns ), array_keys( $decoded ) );
		$this->assertArrayNotHasKey( 'ip', $decoded );
	}

	public function test_export_csv_trailing_machine_friendly_columns() {
		global $wpdb;

		$this->set_ip_source_option( 'no-collect-ip' );

		$user_id   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$hist_time = 1609459200;
		$object_id = 4242;
		$action    = 'updated';
		$marker    = 'csv-id-cols-export-test';

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'         => $action,
				'object_type'    => 'Posts',
				'object_subtype' => 'post',
				'object_name'    => $marker,
				'object_id'      => $object_id,
				'user_id'        => $user_id,
				'user_caps'      => 'administrator',
				'hist_time'      => $hist_time,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
		);

		$export  = $this->get_export_instance();
		$columns = $this->invoke_get_export_columns( $export );
		$rows    = iterator_to_array(
			$this->invoke_export_rows_generator( $export, array( 's' => $marker ), $columns ),
			false
		);

		$this->assertSame(
			array( 'Time (ISO 8601)', 'User ID', 'Object ID', 'Action Key' ),
			array_slice( array_values( $columns ), -4 )
		);
		$this->assertCount( 1, $rows );

		$row = $rows[0];
		$this->assertSame( AAL_Log_Query::hist_time_to_iso8601( $hist_time ), $row['time_iso'] );
		$this->assertSame( $user_id, $row['user_id'] );
		$this->assertSame( (string) $object_id, $row['object_id'] );
		$this->assertSame( $action, $row['action_key'] );
	}

	public function test_export_batches_yield_all_rows_in_histid_desc_order() {
		global $wpdb;

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$names = array( 'batch-a', 'batch-b', 'batch-c', 'batch-d', 'batch-e' );
		foreach ( $names as $name ) {
			$wpdb->insert(
				$wpdb->activity_log,
				array(
					'action'      => 'updated',
					'object_type' => 'Posts',
					'object_name' => $name,
					'user_caps'   => 'administrator',
					'hist_time'   => time(),
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
		}

		add_filter( 'aal_export_batch_size', static function () {
			return 2;
		} );

		$export  = $this->get_export_instance();
		$columns = array( 'description' => 'Meta' );
		$rows    = $this->invoke_export_rows_generator( $export, array( 's' => 'batch-' ), $columns );
		$labels  = array();
		foreach ( $rows as $row ) {
			$labels[] = $row['description'];
		}

		$expected = $wpdb->get_col(
			"SELECT `object_name` FROM `{$wpdb->activity_log}` WHERE `object_name` LIKE 'batch-%' ORDER BY `histid` DESC"
		);

		$this->assertCount( 5, $labels );
		$this->assertSame( $expected, $labels );
		$this->assertSame( $labels, array_unique( $labels ) );
	}

	public function test_export_batches_respect_typeshow_and_dateshow() {
		global $wpdb;

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$in_time  = current_time( 'timestamp' ) - ( 3 * DAY_IN_SECONDS );
		$out_time = current_time( 'timestamp' ) - ( 10 * DAY_IN_SECONDS );

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'      => 'updated',
				'object_type' => 'Posts',
				'object_name' => 'export-week-in',
				'user_caps'   => 'administrator',
				'hist_time'   => $in_time,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'      => 'updated',
				'object_type' => 'Users',
				'object_name' => 'export-week-wrong-type',
				'user_caps'   => 'administrator',
				'hist_time'   => $in_time,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'      => 'updated',
				'object_type' => 'Posts',
				'object_name' => 'export-week-out',
				'user_caps'   => 'administrator',
				'hist_time'   => $out_time,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		add_filter( 'aal_export_batch_size', static function () {
			return 1;
		} );

		$export = $this->get_export_instance();
		$rows   = $this->invoke_export_rows_generator(
			$export,
			array(
				'dateshow' => 'week',
				'typeshow' => 'Posts',
				's'        => 'export-week',
			),
			array( 'description' => 'Meta' )
		);

		$labels = array();
		foreach ( $rows as $row ) {
			$labels[] = $row['description'];
		}

		$this->assertSame( array( 'export-week-in' ), $labels );
	}

	public function test_query_histid_pagination_with_before_id_and_min_id() {
		global $wpdb;

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		foreach ( array( 'page-a', 'page-b', 'page-c', 'page-d' ) as $name ) {
			$wpdb->insert(
				$wpdb->activity_log,
				array(
					'action'      => 'updated',
					'object_type' => 'Posts',
					'object_name' => $name,
					'user_caps'   => 'administrator',
					'hist_time'   => time(),
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
		}

		$histids = $wpdb->get_col(
			"SELECT `histid` FROM `{$wpdb->activity_log}` WHERE `object_name` LIKE 'page-%' ORDER BY `histid` ASC"
		);
		$min_id  = (int) $histids[1];
		$query   = new AAL_Log_Query();

		$first = $query->query(
			array(
				's'          => 'page-',
				'orderby'    => 'histid',
				'order'      => 'DESC',
				'skip_count' => true,
				'per_page'   => 2,
				'min_id'     => $min_id,
			)
		);

		$this->assertCount( 2, $first['items'] );
		$this->assertTrue( $first['has_more'] );

		$last_histid = (int) $first['items'][ count( $first['items'] ) - 1 ]->histid;

		$second = $query->query(
			array(
				's'          => 'page-',
				'orderby'    => 'histid',
				'order'      => 'DESC',
				'skip_count' => true,
				'per_page'   => 2,
				'min_id'     => $min_id,
				'before_id'  => $last_histid,
			)
		);

		$names = array_merge(
			wp_list_pluck( $first['items'], 'object_name' ),
			wp_list_pluck( $second['items'], 'object_name' )
		);

		$this->assertSame( array( 'page-d', 'page-c', 'page-b' ), $names );
		$this->assertFalse( $second['has_more'] );
	}

	public function test_legacy_exporter_receives_array() {
		$export   = $this->get_export_instance();
		$exporter = new stdClass();
		$rows     = ( function () {
			yield array( 'label' => 'one' );
		} )();

		$payload = $this->invoke_get_export_write_payload( $export, $exporter, $rows );

		$this->assertIsArray( $payload );
		$this->assertSame( array( array( 'label' => 'one' ) ), $payload );
	}

	public function test_iterable_exporter_receives_generator() {
		$export   = $this->get_export_instance();
		$exporter = new stdClass();
		$exporter->supports_iterable = true;
		$rows     = ( function () {
			yield array( 'label' => 'one' );
		} )();

		$payload = $this->invoke_get_export_write_payload( $export, $exporter, $rows );

		$this->assertInstanceOf( Generator::class, $payload );
	}

	/**
	 * @return Generator<int, array<string, string>>
	 */
	private function invoke_get_export_columns( AAL_Export $export, $exporter = null ): array {
		$method = new ReflectionMethod( AAL_Export::class, 'get_export_columns' );
		$method->setAccessible( true );

		return $method->invoke( $export, $exporter );
	}

	private function invoke_export_rows_generator( AAL_Export $export, array $query_args, array $columns ) {
		$method = new ReflectionMethod( AAL_Export::class, 'export_rows_generator' );
		$method->setAccessible( true );

		return $method->invoke( $export, $query_args, $columns );
	}

	/**
	 * @return array|Traversable
	 */
	private function invoke_get_export_write_payload( AAL_Export $export, $exporter, $rows ) {
		$method = new ReflectionMethod( AAL_Export::class, 'get_export_write_payload' );
		$method->setAccessible( true );

		return $method->invoke( $export, $exporter, $rows );
	}
}
