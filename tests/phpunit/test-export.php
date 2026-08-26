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

	private function invoke_prep_row( AAL_Export $export, $item, array $columns ): array {
		$method = new ReflectionMethod( AAL_Export::class, 'prep_row' );
		$method->setAccessible( true );

		$list_table = $this->getMockBuilder( AAL_Activity_Log_List_Table::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_action_label' ] )
			->getMock();

		$list_table->method( 'get_action_label' )
			->willReturnArgument( 0 );

		return $method->invoke( $export, $item, $columns, $list_table );
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

	public function test_prep_row_populates_source_and_ip() {
		$this->set_ip_source_option( 'REMOTE_ADDR' );
		$export = $this->get_export_instance();

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

		$row = $this->invoke_prep_row( $export, $item, $columns );

		$this->assertSame( '10.0.0.1', $row['ip'] );
		$this->assertNotEmpty( $row['source'] );
	}

	public function test_prep_row_ip_empty_source_when_no_request_source() {
		$this->set_ip_source_option( 'REMOTE_ADDR' );
		$export = $this->get_export_instance();

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

		$row = $this->invoke_prep_row( $export, $item, $columns );

		$this->assertSame( '192.168.1.1', $row['ip'] );
		$this->assertSame( '', $row['source'] );
	}
}
