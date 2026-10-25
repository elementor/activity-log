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

	public function test_add_role_administrator_logs_one_role_row() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		$user = get_user_by( 'id', $user_id );
		$user->add_role( 'administrator' );
		$this->flush_pending_role_logs();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d",
				$user_id
			)
		);
		$this->assertSame( 1, $count );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$user_id
			)
		);

		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '["editor"]', '["administrator","editor"]' ), $meta['changes']['roles'] );
	}

	public function test_add_role_then_deleted_user_logs_unknown_user_on_flush() {
		$reassign_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id     = self::factory()->user->create( array( 'role' => 'editor' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		$user = get_user_by( 'id', $user_id );
		$user->add_role( 'subscriber' );
		wp_delete_user( $user_id, $reassign_id );
		$this->flush_pending_role_logs();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d",
				$user_id
			)
		);
		$this->assertSame( 1, $count );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d",
				$user_id
			)
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( (string) $user_id, $row->object_id );
		$this->assertSame( 'Unknown user', $row->object_name );
	}

	public function test_add_and_remove_role_in_one_request_logs_one_row() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		$user = get_user_by( 'id', $user_id );
		$user->add_role( 'subscriber' );
		$user->remove_role( 'editor' );
		$this->flush_pending_role_logs();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d",
				$user_id
			)
		);
		$this->assertSame( 1, $count );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$user_id
			)
		);

		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '["editor"]', '["subscriber"]' ), $meta['changes']['roles'] );
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

	public function test_rest_persisted_preferences_does_not_log_profile_update() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$admin_id}" );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users/me' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'meta' => array(
						'persisted_preferences' => array(
							'core' => array( 'foo' => true ),
						),
					),
				)
			)
		);

		rest_get_server()->dispatch( $request );

		$this->assertSame( 0, $this->count_profile_updates( $admin_id ) );
	}

	public function test_rest_name_change_logs_profile_update() {
		$admin_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Old Name',
			)
		);
		wp_set_current_user( $admin_id );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$admin_id}" );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users/me' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'name' => 'New Name' ) ) );

		rest_get_server()->dispatch( $request );

		$this->assertSame( 1, $this->count_profile_updates( $admin_id ) );
	}

	public function test_profile_update_logs_meta_change_and_skips_noop() {
		$user_id = self::factory()->user->create( array( 'first_name' => 'Old' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		wp_update_user(
			array(
				'ID'         => $user_id,
				'first_name' => 'New',
			)
		);

		$this->assertSame( 1, $this->count_profile_updates( $user_id ) );

		wp_update_user( array( 'ID' => $user_id ) );

		$this->assertSame( 1, $this->count_profile_updates( $user_id ) );
	}

	private function flush_pending_role_logs() {
		global $wp_filter;

		if ( empty( $wp_filter['shutdown'] ) ) {
			return;
		}

		$hook = $wp_filter['shutdown'];
		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) && $function[0] instanceof AAL_Hook_Users && 'flush_buffered_role_changes' === $function[1] ) {
					call_user_func( $function );
					return;
				}
			}
		}
	}

	private function count_profile_updates( $user_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Users' AND `object_subtype` = 'Profile' AND `action` = 'updated' AND `object_id` = %d",
				$user_id
			)
		);
	}
}
