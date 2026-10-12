<?php

/** @group schema */
class AAL_Test_Maintenance_Activation extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		update_option( 'activity_log_db_version', '1.0' );
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->activity_log}`" );
		$wpdb->query( "CREATE TABLE `{$wpdb->activity_log}` (`histid` int(11) NOT NULL AUTO_INCREMENT,`user_caps` varchar(70) NOT NULL DEFAULT 'guest',`action` varchar(255) NOT NULL,`object_type` varchar(255) NOT NULL,`object_subtype` varchar(255) NOT NULL DEFAULT '',`object_name` varchar(255) NOT NULL,`object_id` int(11) NOT NULL DEFAULT '0',`user_id` int(11) NOT NULL DEFAULT '0',`hist_ip` varchar(55) NOT NULL DEFAULT '127.0.0.1',`hist_time` int(11) NOT NULL DEFAULT '0',PRIMARY KEY (`histid`)) {$wpdb->get_charset_collate()}" );
	}

	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'activity_log_db_version' );
		AAL_Maintenance::activate( false );
		$ref = new ReflectionClass( 'AAL_Maintenance' );
		$p = $ref->getProperty( 'schema_ready_cache' );
		$p->setAccessible( true );
		$p->setValue( null, array() );
	}

	public function test_activation_upgrades_legacy_table() {
		global $wpdb;
		AAL_Maintenance::activate( false );
		$this->assertSame( '1.1', get_option( 'activity_log_db_version' ) );
		$this->assertNotEmpty( $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $wpdb->activity_log, 'request_source' ) ) );
		$this->assertNotEmpty( $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $wpdb->activity_log, 'meta' ) ) );
	}
}
