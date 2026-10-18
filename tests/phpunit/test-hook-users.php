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
}
