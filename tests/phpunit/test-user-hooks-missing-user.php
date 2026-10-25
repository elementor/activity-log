<?php

class AAL_Test_User_Hooks_Missing_User extends WP_UnitTestCase {

	public function test_missing_user_does_not_insert_log_rows() {
		global $wpdb;

		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->activity_log}`" );

		$hook = new AAL_Hook_Users();
		$hook->hooks_user_register( 999999 );
		$hook->hooks_delete_user( 999999 );
		$hook->hooks_profile_update( 999999 );

		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->activity_log}`" );

		$this->assertSame( $before, $after );
	}
}
