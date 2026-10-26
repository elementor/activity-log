<?php

class AAL_Test_CLI_Erase extends WP_UnitTestCase {

	private function insert_row_with_hist_time( $hist_time ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_name'    => 'erase-cli-seed',
				'object_subtype' => 'post',
				'hist_time'      => (int) $hist_time,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
	}

	private function count_all_rows() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '`' );
	}

	private function count_erased_activity_log_rows() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '` WHERE `action` = %s AND `object_subtype` = %s',
				'erased',
				'Activity Log'
			)
		);
	}

	private function count_seed_rows_with_hist_times( array $hist_times ) {
		global $wpdb;

		if ( empty( $hist_times ) ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $hist_times ), '%d' ) );
		$params       = array_merge( array( 'erase-cli-seed' ), array_map( 'intval', $hist_times ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '`
					WHERE `object_name` = %s AND `hist_time` IN (' . $placeholders . ')',
				...$params
			)
		);
	}

	public function test_parse_before_date_rejects_invalid_date() {
		$this->assertNull( AAL_CLI_Erase::parse_before_date( 'not-a-date' ) );
		$this->assertNull( AAL_CLI_Erase::parse_before_date( '2026-02-30' ) );
	}

	public function test_count_items_before_matches_old_rows() {
		$this->insert_row_with_hist_time( 100 );
		$this->insert_row_with_hist_time( 200 );
		$this->insert_row_with_hist_time( 400 );

		$api = AAL_Main::instance()->api;

		$this->assertSame( 2, $api->count_items_before( 300 ) );
	}

	public function test_erase_items_before_removes_only_older_rows_and_logs_erased() {
		$this->insert_row_with_hist_time( 100 );
		$this->insert_row_with_hist_time( 200 );
		$this->insert_row_with_hist_time( 400 );
		$this->insert_row_with_hist_time( 500 );

		$api     = AAL_Main::instance()->api;
		$deleted = $api->erase_items_before( 300 );

		$this->assertSame( 2, $deleted );
		$this->assertSame( 0, $this->count_seed_rows_with_hist_times( array( 100, 200 ) ) );
		$this->assertSame( 2, $this->count_seed_rows_with_hist_times( array( 400, 500 ) ) );
		$this->assertSame( 1, $this->count_erased_activity_log_rows() );
		$this->assertSame( 3, $this->count_all_rows() );
	}
}
