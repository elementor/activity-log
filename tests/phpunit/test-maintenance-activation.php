<?php

class AAL_Test_Maintenance_Activation extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option( 'aal_manual_db_upgrade' );
		delete_transient( 'aal_upgrade_failed' );
		remove_all_filters( 'aal_auto_upgrade_max_rows' );

		parent::tearDown();

		$this->restore_activity_log_schema_fixture();
	}

	private function restore_activity_log_schema_fixture() {
		delete_option( 'activity_log_db_version' );
		wp_cache_delete( 'activity_log_db_version', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		AAL_Maintenance::invalidate_schema_ready_cache();
		AAL_Maintenance::run_upgrade_steps();
	}

	/**
	 * @return array<string, array{int, string, bool}>
	 */
	public function activation_over_legacy_table_provider() {
		return array(
			'small_table_auto_upgrade' => array( PHP_INT_MAX, '1.1', false ),
			'large_table_manual_upgrade' => array( 0, '1.0', true ),
		);
	}

	/**
	 * @dataProvider activation_over_legacy_table_provider
	 */
	public function test_activation_over_legacy_table( $max_rows, $expected_version, $expects_manual_flag ) {
		global $wpdb;

		$this->create_legacy_activity_log_table();
		update_option( 'activity_log_db_version', '1.0', false );
		delete_option( 'aal_manual_db_upgrade' );

		add_filter(
			'aal_auto_upgrade_max_rows',
			static function () use ( $max_rows ) {
				return $max_rows;
			}
		);

		$this->invoke_create_tables();

		$this->assertSame( $expected_version, get_option( 'activity_log_db_version' ) );
		$this->assertSame( $expects_manual_flag, (bool) get_option( 'aal_manual_db_upgrade' ) );

		$request_source = $wpdb->get_results(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->activity_log}` LIKE %s", 'request_source' )
		);
		$meta = $wpdb->get_results(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->activity_log}` LIKE %s", 'meta' )
		);

		$expects_columns = '1.1' === $expected_version;
		$this->assertSame( $expects_columns, ! empty( $request_source ) );
		$this->assertSame( $expects_columns, ! empty( $meta ) );
	}

	public function test_activation_fresh_install_sets_version_1_1() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->activity_log}`" );
		delete_option( 'activity_log_db_version' );
		delete_option( 'aal_manual_db_upgrade' );

		$this->invoke_create_tables();

		$this->assertSame( '1.1', get_option( 'activity_log_db_version' ) );
		$this->assertFalse( get_option( 'aal_manual_db_upgrade' ) );
	}

	private function create_legacy_activity_log_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = $wpdb->prefix . 'aryo_activity_log';

		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

		$wpdb->query(
			"CREATE TABLE `{$table}` (
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
				PRIMARY KEY (`histid`),
				KEY `user_caps` (`user_caps`),
				KEY `action` (`action`),
				KEY `object_type` (`object_type`),
				KEY `object_subtype` (`object_subtype`),
				KEY `object_name` (`object_name`),
				KEY `user_id` (`user_id`),
				KEY `hist_ip` (`hist_ip`),
				KEY `hist_time` (`hist_time`)
			) $charset_collate"
		);
	}

	private function invoke_create_tables() {
		$method = new ReflectionMethod( 'AAL_Maintenance', '_create_tables' );
		$method->setAccessible( true );
		$method->invoke( null );
	}
}
