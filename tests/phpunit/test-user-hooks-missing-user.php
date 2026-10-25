<?php

class AAL_Test_User_Hooks_Missing_User extends WP_UnitTestCase {

	public function test_missing_user_logs_unknown_user() {
		global $wpdb;

		$before_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->activity_log}`" );
		$max_histid   = (int) $wpdb->get_var( "SELECT MAX( histid ) FROM `{$wpdb->activity_log}`" );

		$hook = new AAL_Hook_Users();
		$hook->hooks_user_register( 999999 );
		$hook->hooks_delete_user( 999999 );
		$hook->hooks_profile_update( 999999 );

		$after_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->activity_log}`" );

		$this->assertSame( $before_count + 3, $after_count );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}` WHERE histid > %d ORDER BY histid ASC",
				$max_histid
			)
		);

		$this->assertCount( 3, $rows );

		$expected_actions = array( 'registered', 'deleted', 'updated' );
		foreach ( $rows as $index => $row ) {
			$this->assertSame( '999999', $row->object_id );
			$this->assertSame( 'Unknown user', $row->object_name );
			$this->assertSame( $expected_actions[ $index ], $row->action );
		}
	}
}
