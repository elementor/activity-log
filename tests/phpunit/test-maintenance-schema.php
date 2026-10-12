<?php

require_once __DIR__ . '/trait-legacy-table-helper.php';

/**
 * @group schema
 */
class AAL_Test_Maintenance_Schema extends WP_UnitTestCase {

	use AAL_Legacy_Table_Helper;

	public function tearDown(): void {
		remove_filter( 'query', array( $this, 'skip_alter' ) );
		parent::tearDown();
		$this->restore_table();
	}

	public function skip_alter( $query ) {
		return 0 === stripos( $query, 'ALTER TABLE' ) ? 'SELECT 1' : $query;
	}

	public function test_version_is_not_stored_when_columns_are_still_missing() {
		$this->reset_table( true );
		add_filter( 'query', array( $this, 'skip_alter' ) );

		$this->assertFalse( AAL_Maintenance::run_upgrade_steps() );
		$this->assertFalse( get_option( 'activity_log_db_version' ) );
		$this->assertSame( '1.1', get_transient( 'aal_upgrade_failed' ) );
	}
}
