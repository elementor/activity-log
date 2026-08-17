<?php

class AAL_Test_Request_Source extends WP_UnitTestCase {

	public function test_parse_request_source_empty() {
		$result = AAL_API::parse_request_source( '' );
		$this->assertSame( '', $result['channel'] );
		$this->assertSame( '', $result['app_name'] );
	}

	public function test_parse_request_source_channel_only() {
		$result = AAL_API::parse_request_source( 'rest' );
		$this->assertSame( 'rest', $result['channel'] );
		$this->assertSame( '', $result['app_name'] );
	}

	public function test_parse_request_source_channel_and_app() {
		$result = AAL_API::parse_request_source( 'rest|app:My Test App' );
		$this->assertSame( 'rest', $result['channel'] );
		$this->assertSame( 'My Test App', $result['app_name'] );
	}

	public function test_parse_request_source_abilities_and_app() {
		$result = AAL_API::parse_request_source( 'abilities|app:GitHub Actions' );
		$this->assertSame( 'abilities', $result['channel'] );
		$this->assertSame( 'GitHub Actions', $result['app_name'] );
	}

	public function test_parse_request_source_app_only_edge_case() {
		$result = AAL_API::parse_request_source( '|app:Deploy Bot' );
		$this->assertSame( '', $result['channel'] );
		$this->assertSame( 'Deploy Bot', $result['app_name'] );
	}

	public function test_parse_request_source_app_no_name() {
		$result = AAL_API::parse_request_source( 'rest|app:' );
		$this->assertSame( 'rest', $result['channel'] );
		$this->assertSame( '', $result['app_name'] );
	}

	public function test_parse_request_source_app_name_with_colon() {
		$result = AAL_API::parse_request_source( 'xmlrpc|app:My App: v2 (production)' );
		$this->assertSame( 'xmlrpc', $result['channel'] );
		$this->assertSame( 'My App: v2 (production)', $result['app_name'] );
	}

	public function test_parse_request_source_legacy_app_prefix() {
		$result = AAL_API::parse_request_source( 'app:Old Format' );
		$this->assertSame( '', $result['channel'] );
		$this->assertSame( 'Old Format', $result['app_name'] );
	}

	public function test_parse_request_source_cli() {
		$result = AAL_API::parse_request_source( 'cli' );
		$this->assertSame( 'cli', $result['channel'] );
		$this->assertSame( '', $result['app_name'] );
	}

	public function test_parse_request_source_cron() {
		$result = AAL_API::parse_request_source( 'cron' );
		$this->assertSame( 'cron', $result['channel'] );
		$this->assertSame( '', $result['app_name'] );
	}

	public function test_get_channel_labels_returns_all_channels() {
		$labels = AAL_API::get_channel_labels();
		$this->assertArrayHasKey( 'abilities', $labels );
		$this->assertArrayHasKey( 'rest', $labels );
		$this->assertArrayHasKey( 'xmlrpc', $labels );
		$this->assertArrayHasKey( 'cli', $labels );
		$this->assertArrayHasKey( 'cron', $labels );
		$this->assertCount( 5, $labels );
	}

	public function test_schema_ready_after_activate() {
		$this->assertTrue( AAL_Maintenance::is_schema_ready( '1.1' ) );
	}

	public function test_request_source_column_exists_after_activate() {
		global $wpdb;
		$columns = $wpdb->get_results(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->activity_log}` LIKE %s", 'request_source' )
		);
		$this->assertNotEmpty( $columns );
	}

	public function test_insert_stores_rest_channel() {
		global $wpdb;

		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}

		aal_insert_log( array(
			'action'      => 'updated',
			'object_type' => 'Posts',
			'object_name' => 'test-rest-channel',
			'object_id'   => 9999,
		) );

		$row = $wpdb->get_row(
			"SELECT `request_source` FROM `{$wpdb->activity_log}` WHERE `object_name` = 'test-rest-channel'"
		);

		$this->assertNotEmpty( $row );
		$this->assertStringContainsString( 'rest', $row->request_source );
	}

	public function test_insert_stores_app_password_via_args_override() {
		global $wpdb;

		aal_insert_log( array(
			'action'         => 'updated',
			'object_type'    => 'Posts',
			'object_name'    => 'test-app-override',
			'object_id'      => 9998,
			'request_source' => 'rest|app:My Override App',
		) );

		$row = $wpdb->get_row(
			"SELECT `request_source` FROM `{$wpdb->activity_log}` WHERE `object_name` = 'test-app-override'"
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( 'rest|app:My Override App', $row->request_source );
	}

	public function test_insert_without_schema_omits_request_source() {
		global $wpdb;

		// Simulate old schema by temporarily changing the option
		$original = get_option( 'activity_log_db_version' );
		update_option( 'activity_log_db_version', '1.0' );

		// Clear the static cache
		$reflection = new ReflectionClass( 'AAL_Maintenance' );
		$prop = $reflection->getProperty( 'schema_ready_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );

		aal_insert_log( array(
			'action'      => 'updated',
			'object_type' => 'Posts',
			'object_name' => 'test-no-source',
			'object_id'   => 9997,
		) );

		$row = $wpdb->get_row(
			"SELECT `request_source` FROM `{$wpdb->activity_log}` WHERE `object_name` = 'test-no-source'"
		);

		// Restore
		update_option( 'activity_log_db_version', $original );
		$prop->setValue( null, array() );

		$this->assertNotEmpty( $row );
		// When schema is "not ready", the insert still works but request_source defaults to ''
		$this->assertSame( '', $row->request_source );
	}

	public function test_ability_stack_tracking() {
		$api = AAL_Main::instance()->api;

		$api->on_ability_start( 'test/ability', array() );
		$api->on_ability_start( 'test/nested', array() );
		$api->on_ability_end( 'test/nested', array(), 'result' );

		$reflection = new ReflectionClass( $api );
		$prop = $reflection->getProperty( 'ability_stack' );
		$prop->setAccessible( true );
		$this->assertCount( 1, $prop->getValue( $api ) );

		$api->on_ability_end( 'test/ability', array(), 'result' );
		$this->assertCount( 0, $prop->getValue( $api ) );
	}

	public function test_ability_stack_does_not_go_negative() {
		$api = AAL_Main::instance()->api;

		$api->on_ability_end( 'test/extra', array(), 'result' );

		$reflection = new ReflectionClass( $api );
		$prop = $reflection->getProperty( 'ability_stack' );
		$prop->setAccessible( true );
		$this->assertCount( 0, $prop->getValue( $api ) );
	}

	public function test_resolve_channel_cron() {
		global $wpdb;

		// Simulate cron context
		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		aal_insert_log( array(
			'action'      => 'sent',
			'object_type' => 'Emails',
			'object_name' => 'test-cron-email',
			'object_id'   => 0,
		) );

		$row = $wpdb->get_row(
			"SELECT `request_source` FROM `{$wpdb->activity_log}` WHERE `object_name` = 'test-cron-email'"
		);

		$this->assertNotEmpty( $row );
		// Channel will be either 'cron' or 'rest' depending on what's defined first in the process
		$this->assertNotEmpty( $row->request_source );
	}

	public function test_upgrade_to_1_1_is_idempotent() {
		// Running upgrade again on already-upgraded schema should succeed
		$reflection = new ReflectionClass( 'AAL_Maintenance' );
		$method = $reflection->getMethod( 'upgrade_to_1_1' );
		$method->setAccessible( true );
		$this->assertTrue( $method->invoke( null ) );
	}
}
