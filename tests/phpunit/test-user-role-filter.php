<?php

class AAL_Test_User_Role_Filter extends WP_UnitTestCase {

	public function test_subscriber_stored_as_subscriber() {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		aal_insert_log( array(
			'action'      => 'logged_in',
			'object_type' => 'Users',
			'object_name' => 'test-subscriber',
		) );

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT `user_caps` FROM `' . $wpdb->activity_log . '` WHERE `user_id` = %d AND `object_name` = %s',
			$user_id,
			'test-subscriber'
		) );

		$this->assertNotEmpty( $row );
		$this->assertSame( 'subscriber', $row->user_caps );
	}

	public function test_editor_stored_as_editor() {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		aal_insert_log( array(
			'action'      => 'logged_in',
			'object_type' => 'Users',
			'object_name' => 'test-editor',
		) );

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT `user_caps` FROM `' . $wpdb->activity_log . '` WHERE `user_id` = %d AND `object_name` = %s',
			$user_id,
			'test-editor'
		) );

		$this->assertNotEmpty( $row );
		$this->assertSame( 'editor', $row->user_caps );
	}

	public function test_admin_stored_as_administrator() {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		aal_insert_log( array(
			'action'      => 'logged_in',
			'object_type' => 'Users',
			'object_name' => 'test-admin',
		) );

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT `user_caps` FROM `' . $wpdb->activity_log . '` WHERE `user_id` = %d AND `object_name` = %s',
			$user_id,
			'test-admin'
		) );

		$this->assertNotEmpty( $row );
		$this->assertSame( 'administrator', $row->user_caps );
	}

	public function test_capshow_filters_correctly() {
		global $wpdb;

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$sub_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $admin_id );
		aal_insert_log( array(
			'action'      => 'logged_in',
			'object_type' => 'Users',
			'object_name' => 'capshow-admin',
		) );

		wp_set_current_user( $sub_id );
		aal_insert_log( array(
			'action'      => 'logged_in',
			'object_type' => 'Users',
			'object_name' => 'capshow-sub',
		) );

		wp_set_current_user( $admin_id );

		$query  = new AAL_Log_Query();
		$result = $query->query( array( 'capshow' => 'administrator' ) );

		$names = wp_list_pluck( $result['items'], 'object_name' );
		$this->assertContains( 'capshow-admin', $names );
		$this->assertNotContains( 'capshow-sub', $names );
	}

	public function test_guest_when_no_user() {
		global $wpdb;

		wp_set_current_user( 0 );

		aal_insert_log( array(
			'action'      => 'failed_login',
			'object_type' => 'Users',
			'object_name' => 'test-guest',
			'user_id'     => 0,
		) );

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT `user_caps` FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
			'test-guest'
		) );

		$this->assertNotEmpty( $row );
		$this->assertSame( 'guest', $row->user_caps );
	}
}
