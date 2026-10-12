<?php

/**
 * @group schema
 */
class AAL_Test_Maintenance_Schema extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option( 'aal_manual_db_upgrade' );
		delete_transient( 'aal_upgrade_failed' );

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

	public function test_broken_schema_flags_mismatch_then_upgrade_logs_with_source() {
		global $wpdb;

		update_option( 'activity_log_db_version', AAL_Maintenance::TARGET_DB_VERSION, false );
		AAL_Maintenance::invalidate_schema_ready_cache();

		$wpdb->query(
			"ALTER TABLE `{$wpdb->activity_log}`
				DROP COLUMN `request_source`,
				DROP COLUMN `meta`"
		);

		aal_insert_log(
			array(
				'action'      => 'updated',
				'object_type' => 'Posts',
				'object_name' => 'test-schema-heal-row',
				'object_id'   => 88001,
			)
		);

		$this->assertSame( AAL_Maintenance::BASE_DB_VERSION, get_option( 'activity_log_db_version' ) );
		$this->assertFalse( AAL_Maintenance::is_schema_ready() );

		$missing_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `histid` FROM `{$wpdb->activity_log}` WHERE `object_name` = %s",
				'test-schema-heal-row'
			)
		);
		$this->assertEmpty( $missing_row );

		$this->assertTrue( AAL_Maintenance::run_upgrade_steps() );
		$this->assertSame( AAL_Maintenance::TARGET_DB_VERSION, get_option( 'activity_log_db_version' ) );

		aal_insert_log(
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_name'    => 'test-schema-heal-source',
				'object_id'      => 88002,
				'request_source' => 'rest|app:Heal Test',
			)
		);

		$source_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `request_source` FROM `{$wpdb->activity_log}` WHERE `object_name` = %s",
				'test-schema-heal-source'
			)
		);

		$this->assertNotEmpty( $source_row );
		$this->assertSame( 'rest|app:Heal Test', $source_row->request_source );
	}

}
