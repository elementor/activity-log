<?php

class AAL_Test_Cleanup_Schedule extends WP_UnitTestCase {

	public function tearDown(): void {
		wp_clear_scheduled_hook( 'aal/maintenance/clear_old_items' );
		parent::tearDown();
	}

	public function test_maybe_add_schedule_delete_old_items_fresh() {
		wp_clear_scheduled_hook( 'aal/maintenance/clear_old_items' );

		AAL_Main::instance()->api->maybe_add_schedule_delete_old_items();

		$this->assertSame( 'hourly', wp_get_schedule( 'aal/maintenance/clear_old_items' ) );
	}

	public function test_maybe_add_schedule_delete_old_items_migration_from_daily() {
		wp_clear_scheduled_hook( 'aal/maintenance/clear_old_items' );
		wp_schedule_event( time(), 'daily', 'aal/maintenance/clear_old_items' );

		AAL_Main::instance()->api->maybe_add_schedule_delete_old_items();

		$this->assertSame( 'hourly', wp_get_schedule( 'aal/maintenance/clear_old_items' ) );
		$this->assertNotFalse( wp_next_scheduled( 'aal/maintenance/clear_old_items' ) );
		$this->assertSame( 1, $this->count_cron_hook_occurrences( 'aal/maintenance/clear_old_items' ) );
	}

	private function count_cron_hook_occurrences( $hook ) {
		$count = 0;
		$cron  = _get_cron_array();

		if ( ! is_array( $cron ) ) {
			return 0;
		}

		foreach ( $cron as $timestamp => $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				$count += count( $hooks[ $hook ] );
			}
		}

		return $count;
	}
}
