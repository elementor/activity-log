<?php

class AAL_Test_Maintenance_Upgrade extends WP_UnitTestCase {

	private $original_db_version;

	public function setUp(): void {
		parent::setUp();

		$this->original_db_version = get_option( 'activity_log_db_version' );

		$this->clear_schema_cache();
	}

	public function tearDown(): void {
		update_option( 'activity_log_db_version', $this->original_db_version );
		delete_option( 'aal_manual_db_upgrade' );
		delete_transient( 'aal_upgrade_failed' );
		remove_all_filters( 'aal_auto_upgrade_max_rows' );

		$this->clear_schema_cache();

		parent::tearDown();
	}

	private function clear_schema_cache() {
		$ref = new ReflectionClass( 'AAL_Maintenance' );
		$prop = $ref->getProperty( 'schema_ready_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );
	}

	public function test_large_table_skips_auto_upgrade() {
		update_option( 'activity_log_db_version', '1.0' );

		add_filter( 'aal_auto_upgrade_max_rows', function () {
			return 0;
		} );

		AAL_Maintenance::maybe_upgrade();

		$this->assertSame( '1.0', get_option( 'activity_log_db_version' ) );
		$this->assertTrue( (bool) get_option( 'aal_manual_db_upgrade' ) );
	}

	public function test_small_table_auto_upgrades() {
		update_option( 'activity_log_db_version', '1.0' );

		add_filter( 'aal_auto_upgrade_max_rows', function () {
			return PHP_INT_MAX;
		} );

		AAL_Maintenance::maybe_upgrade();

		$this->assertSame( AAL_Maintenance::TARGET_DB_VERSION, get_option( 'activity_log_db_version' ) );
		$this->assertFalse( get_option( 'aal_manual_db_upgrade' ) );
	}

	public function test_maybe_upgrade_returns_early_when_manual_flag_set() {
		update_option( 'activity_log_db_version', '1.0' );
		update_option( 'aal_manual_db_upgrade', true );

		AAL_Maintenance::maybe_upgrade();

		$this->assertSame( '1.0', get_option( 'activity_log_db_version' ) );
	}

	public function test_run_upgrade_steps_clears_manual_flag() {
		update_option( 'activity_log_db_version', '1.0' );
		update_option( 'aal_manual_db_upgrade', true );

		$result = AAL_Maintenance::run_upgrade_steps();

		$this->assertTrue( $result );
		$this->assertSame( AAL_Maintenance::TARGET_DB_VERSION, get_option( 'activity_log_db_version' ) );
		$this->assertFalse( get_option( 'aal_manual_db_upgrade' ) );
		$this->assertFalse( get_transient( 'aal_upgrade_failed' ) );
	}

	public function test_run_upgrade_steps_is_idempotent() {
		$this->assertTrue( AAL_Maintenance::run_upgrade_steps() );
		$this->assertSame( AAL_Maintenance::TARGET_DB_VERSION, get_option( 'activity_log_db_version' ) );
	}
}
