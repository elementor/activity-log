<?php

class AAL_Test_Maintenance_Activation extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		update_option( 'activity_log_db_version', '1.0' );
		$wpdb->query( "ALTER TABLE `{$wpdb->activity_log}` DROP KEY `request_source`, DROP COLUMN `meta`, DROP COLUMN `request_source`" );
	}

	public function tearDown(): void {
		parent::tearDown();
		$m = new ReflectionMethod( 'AAL_Maintenance', '_create_tables' );
		$m->setAccessible( true );
		$m->invoke( null );
	}

	public function test_activation_upgrades_legacy_table() {
		global $wpdb;
		AAL_Maintenance::activate( false );
		$this->assertSame( '1.1', get_option( 'activity_log_db_version' ) );
		$this->assertNotEmpty( $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $wpdb->activity_log, 'request_source' ) ) );
	}
}
