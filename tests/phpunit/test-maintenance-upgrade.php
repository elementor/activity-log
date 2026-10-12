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

	public function maybe_upgrade_threshold_provider() {
		return array(
			'large table' => array( 0, '1.0', true ),
			'small table' => array( PHP_INT_MAX, '1.1', false ),
		);
	}

	/**
	 * @dataProvider maybe_upgrade_threshold_provider
	 */
	public function test_maybe_upgrade_respects_row_threshold( $max_rows, $expected_version, $expected_manual ) {
		update_option( 'activity_log_db_version', '1.0' );
		add_filter( 'aal_auto_upgrade_max_rows', static function () use ( $max_rows ) {
			return $max_rows;
		} );

		AAL_Maintenance::maybe_upgrade();

		$this->assertSame( $expected_version, get_option( 'activity_log_db_version' ) );
		$this->assertSame( $expected_manual, (bool) get_option( 'aal_manual_db_upgrade' ) );
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
