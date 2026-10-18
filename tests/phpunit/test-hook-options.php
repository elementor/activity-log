<?php

class AAL_Test_Hook_Options extends WP_UnitTestCase {

	/**
	 * @dataProvider whitelisted_core_options
	 */
	public function test_whitelisted_option_update_inserts_log( $option_name ) {
		global $wpdb;

		update_option( $option_name, 'aal-old-value' );
		update_option( $option_name, 'aal-new-value' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '`
					WHERE `action` = %s
						AND `object_type` = %s
						AND `object_name` = %s
					ORDER BY `histid` DESC
					LIMIT 1',
				'updated',
				'Options',
				$option_name
			)
		);

		$this->assertNotEmpty( $row );
	}

	public function whitelisted_core_options() {
		return array(
			array( 'disallowed_keys' ),
			array( 'gmt_offset' ),
		);
	}

	public function test_option_update_stores_old_and_new_values() {
		global $wpdb;

		update_option( 'blogname', 'Before Name' );
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_name` = 'blogname'" );
		update_option( 'blogname', 'After Name' );

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Options' AND `object_name` = 'blogname'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'Before Name', 'After Name' ), $meta['changes']['blogname'] );
	}

	public function test_mailserver_pass_is_redacted() {
		global $wpdb;

		update_option( 'mailserver_pass', 'old-password' );
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_name` = 'mailserver_pass'" );
		update_option( 'mailserver_pass', 'new-password' );

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Options' AND `object_name` = 'mailserver_pass'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '[redacted]', '[redacted]' ), $meta['changes']['mailserver_pass'] );
	}

	public function test_aal_settings_stores_per_key_changes() {
		global $wpdb;

		$old = array( 'logs_lifespan' => '30', 'other' => 'x' );
		$new = array( 'logs_lifespan' => '60', 'other' => 'x' );

		update_option( 'activity-log-settings', $old );
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_subtype` = 'Activity Log'" );
		update_option( 'activity-log-settings', $new );

		$row = $wpdb->get_row(
			"SELECT meta, object_name, object_subtype FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Options' AND `object_name` = 'logs_lifespan'
					AND `object_subtype` = 'Activity Log'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '30', '60' ), $meta['changes']['logs_lifespan'] );
	}
}
