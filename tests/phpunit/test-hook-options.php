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
}
