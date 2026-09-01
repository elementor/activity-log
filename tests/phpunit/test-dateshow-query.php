<?php

class AAL_Test_Dateshow_Query extends WP_UnitTestCase {

	/**
	 * Admin user ID used across every test in this class.
	 */
	private static int $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_id );
	}

	private function insert_log_row( int $hist_time, string $name = 'dateshow-test' ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'      => 'updated',
				'object_type' => 'Posts',
				'object_name' => $name,
				'user_caps'   => 'administrator',
				'hist_time'   => $hist_time,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
	}

	public function test_week_includes_row_within_7_days() {
		$this->insert_log_row( current_time( 'timestamp' ) - ( 3 * DAY_IN_SECONDS ), 'week-in' );

		$query  = new AAL_Log_Query();
		$result = $query->query( array( 'dateshow' => 'week' ) );
		$names  = wp_list_pluck( $result['items'], 'object_name' );

		$this->assertContains( 'week-in', $names );
	}

	public function test_week_excludes_row_older_than_7_days() {
		$this->insert_log_row( current_time( 'timestamp' ) - ( 10 * DAY_IN_SECONDS ), 'week-out' );

		$query  = new AAL_Log_Query();
		$result = $query->query( array( 'dateshow' => 'week' ) );
		$names  = wp_list_pluck( $result['items'], 'object_name' );

		$this->assertNotContains( 'week-out', $names );
	}

	public function test_month_includes_row_within_30_days() {
		$this->insert_log_row( current_time( 'timestamp' ) - ( 20 * DAY_IN_SECONDS ), 'month-in' );

		$query  = new AAL_Log_Query();
		$result = $query->query( array( 'dateshow' => 'month' ) );
		$names  = wp_list_pluck( $result['items'], 'object_name' );

		$this->assertContains( 'month-in', $names );
	}

	public function test_month_excludes_row_older_than_30_days() {
		$this->insert_log_row( current_time( 'timestamp' ) - ( 35 * DAY_IN_SECONDS ), 'month-out' );

		$query  = new AAL_Log_Query();
		$result = $query->query( array( 'dateshow' => 'month' ) );
		$names  = wp_list_pluck( $result['items'], 'object_name' );

		$this->assertNotContains( 'month-out', $names );
	}
}
