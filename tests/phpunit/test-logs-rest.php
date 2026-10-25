<?php

class Test_AAL_Logs_REST extends WP_UnitTestCase {

	private $admin;

	public function set_up() {
		parent::set_up();
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
	}

	/** @dataProvider logs_payload_provider */
	public function test_get_logs_date_and_source( $request_source, $app_name ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		$this->insert_sample_log( array( 'hist_time' => 1700000000, 'request_source' => $request_source ) );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/activity-log/v1/logs' ) );
		$item     = $response->get_data()['items'][0];
		$this->assertSame( '2023-11-14T22:13:20+00:00', $item['date']['iso8601'] );
		$this->assertSame( $app_name, $item['source']['app_name'] );
	}

	public function logs_payload_provider() {
		return array(
			'app' => array( 'app:Claude', 'Claude' ),
			'empty' => array( '', '' ),
		);
	}

	public function test_options_logs_schema_iso8601_format() {
		rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/activity-log/v1/logs' ) );
		$handler  = rest_get_server()->get_routes()['/activity-log/v1/logs'][0];
		$schema   = call_user_func( $handler['schema'] );
		$iso8601  = $schema['properties']['items']['items']['properties']['date']['properties']['iso8601'];
		$this->assertSame( 'date-time', $iso8601['format'] );
	}

	public function test_get_logs_includes_changes() {
		$this->insert_sample_log(
			array(
				'meta' => wp_json_encode(
					array(
						'changes'         => array( 'blogname' => array( 'Old', 'New' ) ),
						'changes_omitted' => 2,
					),
					JSON_UNESCAPED_UNICODE
				),
			)
		);

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/activity-log/v1/logs' ) );
		$item     = $response->get_data()['items'][0];

		$this->assertSame( array( 'Old', 'New' ), $item['changes']['blogname'] );
		$this->assertSame( 2, $item['changes_omitted'] );
	}

	public function test_get_logs_omits_changes_for_old_rows() {
		$this->insert_sample_log( array( 'meta' => '' ) );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/activity-log/v1/logs' ) );
		$item     = $response->get_data()['items'][0];

		$this->assertArrayNotHasKey( 'changes', $item );
		$this->assertArrayNotHasKey( 'changes_omitted', $item );
	}

	public function test_get_logs_skip_count_has_more_without_total() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		$this->insert_sample_log( array( 'hist_time' => time() ) );
		$this->insert_sample_log( array( 'hist_time' => time() - 1, 'object_name' => 'rest-test-2' ) );
		$this->insert_sample_log( array( 'hist_time' => time() - 2, 'object_name' => 'rest-test-3' ) );

		$request = new WP_REST_Request( 'GET', '/activity-log/v1/logs' );
		$request->set_query_params(
			array(
				'per_page'   => 2,
				'skip_count' => true,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['has_more'] );
		$this->assertNull( $data['total'] );
		$this->assertNull( $data['pages'] );
		$this->assertCount( 2, $data['items'] );

		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		$this->insert_sample_log();

		$request->set_query_params(
			array(
				'per_page'   => 2,
				'skip_count' => true,
			)
		);
		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertFalse( $data['has_more'] );

		$default = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/activity-log/v1/logs' ) )->get_data();
		$this->assertIsInt( $default['total'] );
		$this->assertIsInt( $default['pages'] );
	}

	public function test_get_logs_after_id_incremental() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		$hist_time = current_time( 'timestamp' ) - 60;
		$id1       = $this->insert_sample_log( array( 'hist_time' => $hist_time, 'object_name' => 'rest-after-1' ) );
		$id2       = $this->insert_sample_log( array( 'hist_time' => $hist_time - 1, 'object_name' => 'rest-after-2' ) );

		$request = new WP_REST_Request( 'GET', '/activity-log/v1/logs' );
		$request->set_query_params(
			array(
				'after_id' => (string) $id1,
				'per_page' => 10,
			)
		);
		$data = $this->dispatch_logs_request( $request );

		$this->assertNull( $data['total'] );
		$this->assertNull( $data['pages'] );
		$this->assertFalse( $data['has_more'] );
		$this->assertSame( $id2, $data['next_after_id'] );
		$this->assertCount( 1, $data['items'] );
		$this->assertSame( $id2, $data['items'][0]['id'] );
	}

	public function test_get_logs_after_id_zero_explicit() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		$hist_time = current_time( 'timestamp' ) - 60;
		$id1       = $this->insert_sample_log( array( 'hist_time' => $hist_time ) );

		$request = new WP_REST_Request( 'GET', '/activity-log/v1/logs' );
		$request->set_query_params(
			array(
				'after_id' => '0',
				'per_page' => 10,
			)
		);
		$data = $this->dispatch_logs_request( $request );

		$this->assertArrayHasKey( 'next_after_id', $data );
		$this->assertSame( $id1, $data['next_after_id'] );
	}

	public function test_logs_schema_includes_changes() {
		$handler = rest_get_server()->get_routes()['/activity-log/v1/logs'][0];
		$schema  = call_user_func( $handler['schema'] );
		$props   = $schema['properties']['items']['items']['properties'];

		$this->assertArrayHasKey( 'changes', $props );
		$this->assertArrayHasKey( 'changes_omitted', $props );
	}

	private function dispatch_logs_request( WP_REST_Request $request ) {
		return ( new AAL_REST() )->get_logs( $request )->get_data();
	}

	private function insert_sample_log( $overrides = array() ) {
		global $wpdb;
		$row = array_merge(
			array(
				'action' => 'updated', 'object_type' => 'Posts', 'object_subtype' => 'post',
				'object_name' => 'rest-test', 'object_id' => 1, 'user_id' => $this->admin,
				'user_caps' => 'administrator', 'hist_ip' => '203.0.113.10', 'hist_time' => time(),
				'request_source' => '', 'meta' => '',
			),
			$overrides
		);
		$wpdb->insert( $wpdb->activity_log, $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
	}
}
