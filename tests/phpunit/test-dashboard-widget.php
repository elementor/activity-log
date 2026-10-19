<?php

class Test_AAL_Dashboard_Widget extends WP_UnitTestCase {

	public function tear_down() {
		set_current_screen( 'front' );
		$GLOBALS['wp_meta_boxes'] = array();
		parent::tear_down();
	}

	private function is_widget_registered() {
		global $wp_meta_boxes;

		foreach ( (array) $wp_meta_boxes as $contexts ) {
			foreach ( (array) $contexts as $priorities ) {
				foreach ( (array) $priorities as $boxes ) {
					if ( isset( $boxes[ AAL_Dashboard_Widget::WIDGET_ID ] ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public function test_widget_registered_for_user_who_can_view_logs() {
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		set_current_screen( 'dashboard' );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		$GLOBALS['wp_meta_boxes'] = array();

		do_action( 'wp_dashboard_setup' );

		$this->assertTrue( $this->is_widget_registered() );
	}

	public function test_widget_not_registered_for_user_without_access() {
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		set_current_screen( 'dashboard' );

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$GLOBALS['wp_meta_boxes'] = array();

		do_action( 'wp_dashboard_setup' );

		$this->assertFalse( $this->is_widget_registered() );
	}
}
