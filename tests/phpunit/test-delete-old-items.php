<?php

class AAL_Test_Delete_Old_Items extends WP_UnitTestCase {

	use AAL_Abilities_Test_Helper;

	public function test_delete_old_items_limits_each_run() {
		global $wpdb;

		update_option( 'activity-log-settings', array( 'logs_lifespan' => '30' ) );
		$this->reset_settings_cache();

		$old_time = strtotime( '-60 days', current_time( 'timestamp' ) );
		$new_time = current_time( 'timestamp' );
		$row      = array(
			'action'      => 'updated',
			'object_type' => 'Posts',
			'object_name' => 'delete-old-items',
		);
		$format   = array( '%s', '%s', '%s', '%d' );

		$wpdb->insert( $wpdb->activity_log, array_merge( $row, array( 'hist_time' => $old_time ) ), $format );
		$wpdb->insert( $wpdb->activity_log, array_merge( $row, array( 'hist_time' => $old_time ) ), $format );
		$wpdb->insert( $wpdb->activity_log, array_merge( $row, array( 'hist_time' => $old_time ) ), $format );
		$wpdb->insert( $wpdb->activity_log, array_merge( $row, array( 'hist_time' => $new_time ) ), $format );

		add_filter(
			'aal/maintenance/delete_batch_size',
			function () {
				return 2;
			}
		);

		AAL_Main::instance()->api->delete_old_items();

		$this->assertSame( 1, $this->count_rows( $old_time ) );
		$this->assertSame( 1, $this->count_rows( $new_time ) );

		AAL_Main::instance()->api->delete_old_items();

		$this->assertSame( 0, $this->count_rows( $old_time ) );
		$this->assertSame( 1, $this->count_rows( $new_time ) );
	}

	private function count_rows( $hist_time ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s AND `hist_time` = %d',
				'delete-old-items',
				$hist_time
			)
		);
	}
}
