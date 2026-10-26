<?php

class AAL_Test_Erase_Logs extends WP_UnitTestCase {

	private function seed_log_rows( $count ) {
		global $wpdb;

		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->activity_log,
				array(
					'action'         => 'updated',
					'object_type'    => 'Posts',
					'object_name'    => 'erase-seed',
					'object_subtype' => 'post',
					'hist_time'      => current_time( 'timestamp' ),
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
		}
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

	public function test_erase_all_items_leaves_single_erased_log_row() {
		$this->seed_log_rows( 3 );

		AAL_Main::instance()->api->erase_all_items();

		$this->assertSame( 1, $this->count_all_rows() );
		$this->assertSame( 1, $this->count_erased_activity_log_rows() );
	}

	public function test_erase_all_items_keeps_histid_above_previous_max() {
		global $wpdb;

		$this->seed_log_rows( 3 );

		$max_histid = (int) $wpdb->get_var( 'SELECT MAX(`histid`) FROM `' . $wpdb->activity_log . '`' );

		AAL_Main::instance()->api->erase_all_items();

		$remaining_histid = (int) $wpdb->get_var( 'SELECT `histid` FROM `' . $wpdb->activity_log . '` LIMIT 1' );

		$this->assertGreaterThan( $max_histid, $remaining_histid );
	}
}
