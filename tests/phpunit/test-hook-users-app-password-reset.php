<?php

class AAL_Test_Hook_Users_App_Password_Reset extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
	}

	public function tear_down() {
		remove_filter( 'wp_is_application_passwords_available', '__return_true' );
		parent::tear_down();
	}

	public function test_application_password_create_and_delete_log_without_secret() {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$this->markTestSkipped( 'Application passwords are not available in this WordPress version.' );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		$created = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array( 'name' => 'Integration App' )
		);
		$this->assertIsArray( $created );
		list( $plain_password, $item ) = $created;

		WP_Application_Passwords::delete_application_password( $user_id, $item['uuid'] );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Application Password' AND `object_id` = %d
					ORDER BY histid ASC",
				$user_id
			)
		);

		$this->assertCount( 2, $rows );
		$this->assertSame( 'app_password_created', $rows[0]->action );
		$this->assertSame( 'app_password_revoked', $rows[1]->action );
		$this->assertSame( 'Integration App', $rows[0]->object_name );
		$this->assertSame( 'Integration App', $rows[1]->object_name );

		foreach ( $rows as $row ) {
			$blob = wp_json_encode( $row );
			$this->assertStringNotContainsString( $plain_password, $blob );
		}
	}

	public function test_retrieve_password_key_logs_password_reset_requested() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'resetme',
				'user_email' => 'resetme@example.com',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		retrieve_password( 'resetme' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Profile' AND `action` = 'password_reset_requested' AND `object_id` = %d",
				$user_id
			)
		);

		$this->assertNotEmpty( $row );
		$user = get_user_by( 'id', $user_id );
		$this->assertSame( $user->user_nicename, $row->object_name );
	}

	public function test_after_password_reset_logs_password_reset() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'afterreset' ) );
		$user    = get_user_by( 'id', $user_id );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$user_id}" );

		reset_password( $user, 'new-secure-password-value' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Users' AND `object_subtype` = 'Profile' AND `action` = 'password_reset' AND `object_id` = %d",
				$user_id
			)
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( $user->user_nicename, $row->object_name );
		$blob = wp_json_encode( $row );
		$this->assertStringNotContainsString( 'new-secure-password-value', $blob );
	}
}
