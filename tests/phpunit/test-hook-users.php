<?php

class AAL_Test_Hook_Users extends WP_UnitTestCase {

	public function test_profile_update_stores_email_change() {
		$user_id = self::factory()->user->create(
			array(
				'user_email'   => 'before@example.com',
				'display_name' => 'Before Name',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => 'after@example.com',
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$user_id
			)
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'before@example.com', 'after@example.com' ), $meta['changes']['user_email'] );
	}

	public function test_profile_update_stores_display_name_change() {
		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Old Display',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => 'New Display',
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$user_id
			)
		);

		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'Old Display', 'New Display' ), $meta['changes']['display_name'] );
	}

	public function test_set_role_stores_old_and_new() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$user = get_user_by( 'id', $user_id );
		$user->set_role( 'editor' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d",
				$user_id
			)
		);
		$this->assertSame( 1, (int) $count );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$user_id
			)
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '["subscriber"]', '["editor"]' ), $meta['changes']['roles'] );
	}

	public function test_set_role_keeps_multi_role_old_list() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$user = get_user_by( 'id', $user_id );
		$user->add_role( 'editor' );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		$user->set_role( 'administrator' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$user_id
			)
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '["editor","subscriber"]', '["administrator"]' ), $meta['changes']['roles'] );
	}

	public function test_unchanged_set_role_writes_nothing() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		$user = get_user_by( 'id', $user_id );
		$user->set_role( 'subscriber' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `object_id` = %d",
				$user_id
			)
		);

		$this->assertEmpty( $row );
	}

	public function test_empty_role_stores_empty_list() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		$user = get_user_by( 'id', $user_id );
		$user->set_role( '' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$user_id
			)
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '["subscriber"]', '[]' ), $meta['changes']['roles'] );
	}

	public function test_new_user_writes_no_role_row() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `object_id` = %d",
				$user_id
			)
		);

		$this->assertEmpty( $row );
	}
}
