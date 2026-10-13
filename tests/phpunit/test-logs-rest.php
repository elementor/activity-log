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
