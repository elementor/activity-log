<?php

require_once __DIR__ . '/trait-legacy-table-helper.php';

/**
 * @group schema
 */
class AAL_Test_Maintenance_Activation extends WP_UnitTestCase {

	use AAL_Legacy_Table_Helper;

	public function tearDown(): void {
		remove_all_filters( 'aal_auto_upgrade_max_rows' );
		parent::tearDown();
		$this->restore_table();
	}

	public function activation_provider() {
		return array(
			'fresh install'      => array( false, PHP_INT_MAX, '1.1', false ),
			'small legacy table' => array( true, PHP_INT_MAX, '1.1', false ),
			'large legacy table' => array( true, 0, '1.0', true ),
		);
	}

	/**
	 * @dataProvider activation_provider
	 */
	public function test_activation( $legacy_table, $max_rows, $expected_version, $expected_notice ) {
		$this->reset_table( $legacy_table );
		add_filter( 'aal_auto_upgrade_max_rows', static function () use ( $max_rows ) {
			return $max_rows;
		} );

		$this->create_tables();

		$this->assertSame( $expected_version, get_option( 'activity_log_db_version', '1.0' ) );
		$this->assertSame( $expected_notice, (bool) get_option( 'aal_manual_db_upgrade' ) );
		$this->assertSame( '1.1' === $expected_version, $this->has_new_columns() );
	}

}
