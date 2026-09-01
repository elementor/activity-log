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
}
