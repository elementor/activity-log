<?php

/**
 * @group schema
 */
class AAL_Test_Maintenance_Activation extends WP_UnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'aal_auto_upgrade_max_rows' );
		parent::tearDown();

		delete_option( 'aal_manual_db_upgrade' );
		delete_transient( 'aal_upgrade_failed' );
		delete_option( 'activity_log_db_version' );

		add_filter( 'aal_auto_upgrade_max_rows', static function () {
			return PHP_INT_MAX;
		} );

		$method = new ReflectionMethod( 'AAL_Maintenance', '_create_tables' );
		$method->setAccessible( true );
		$method->invoke( null );

		remove_all_filters( 'aal_auto_upgrade_max_rows' );

		$ref = new ReflectionClass( 'AAL_Maintenance' );
		$prop = $ref->getProperty( 'schema_ready_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );
	}

	public function test_activation_upgrades_legacy_table() {
		global $wpdb;

		wp_cache_flush();
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->activity_log}`" );
		update_option( 'activity_log_db_version', '1.0' );
		$wpdb->query(
			"CREATE TABLE `{$wpdb->activity_log}` (
				`histid` int(11) NOT NULL AUTO_INCREMENT,
				`user_caps` varchar(70) NOT NULL DEFAULT 'guest',
				`action` varchar(255) NOT NULL,
				`object_type` varchar(255) NOT NULL,
				`object_subtype` varchar(255) NOT NULL DEFAULT '',
				`object_name` varchar(255) NOT NULL,
				`object_id` int(11) NOT NULL DEFAULT '0',
				`user_id` int(11) NOT NULL DEFAULT '0',
				`hist_ip` varchar(55) NOT NULL DEFAULT '127.0.0.1',
				`hist_time` int(11) NOT NULL DEFAULT '0',
				PRIMARY KEY (`histid`)
			) {$wpdb->get_charset_collate()}"
		);

		add_filter( 'aal_auto_upgrade_max_rows', static function () {
			return PHP_INT_MAX;
		} );

		$method = new ReflectionMethod( 'AAL_Maintenance', '_create_tables' );
		$method->setAccessible( true );
		$method->invoke( null );

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$wpdb->activity_log}`" );

		$this->assertSame( '1.1', get_option( 'activity_log_db_version' ) );
		$this->assertContains( 'request_source', $columns );
		$this->assertContains( 'meta', $columns );
	}
}
