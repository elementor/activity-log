<?php

class AAL_Test_Core_Version_Diff extends WP_UnitTestCase {

	private $saved_wp_version;

	public function setUp(): void {
		parent::setUp();
		$this->saved_wp_version = $GLOBALS['wp_version'];
	}

	public function tearDown(): void {
		$GLOBALS['wp_version'] = $this->saved_wp_version;
		parent::tearDown();
	}

	public function test_core_update_stores_version_diff() {
		$GLOBALS['wp_version'] = '6.8';

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Core'" );

		$hook = new AAL_Hook_Core();
		$hook->core_updated_successfully( '6.9' );

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Core' AND `action` = 'updated'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '6.8', '6.9' ), $meta['changes']['version'] );
	}
}
