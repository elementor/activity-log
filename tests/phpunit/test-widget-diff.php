<?php

class AAL_Test_Widget_Diff extends WP_UnitTestCase {

	public function test_widget_update_stores_instance_diff() {
		$_REQUEST['sidebar'] = 'sidebar-1';

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Widget'" );

		$hook   = new AAL_Hook_Widgets();
		$widget = new WP_Widget_Text();

		$hook->hooks_widget_update_callback(
			array( 'title' => 'New' ),
			array( 'title' => 'New' ),
			array( 'title' => 'Old' ),
			$widget
		);

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Widget' AND `action` = 'updated'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'Old', 'New' ), $meta['changes']['title'] );

		unset( $_REQUEST['sidebar'] );
	}

	public function test_false_instance_logs_no_meta() {
		$_REQUEST['sidebar'] = 'sidebar-1';

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Widget'" );

		$hook   = new AAL_Hook_Widgets();
		$widget = new WP_Widget_Text();

		$hook->hooks_widget_update_callback(
			false,
			array( 'title' => 'New' ),
			array( 'title' => 'Old' ),
			$widget
		);

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Widget' AND `action` = 'updated'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( (string) $row->meta, true );
		$this->assertFalse( is_array( $meta ) && isset( $meta['changes'] ) );

		unset( $_REQUEST['sidebar'] );
	}
}
