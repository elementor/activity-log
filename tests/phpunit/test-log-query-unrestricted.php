<?php

class AAL_Test_Log_Query_Unrestricted extends WP_UnitTestCase {

	private function insert_row( $object_type, $user_caps = 'administrator', $object_name = 'unrestricted-test' ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'      => 'updated',
				'object_type' => $object_type,
				'object_name' => $object_name,
				'user_caps'   => $user_caps,
				'hist_time'   => current_time( 'timestamp' ),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
	}

	public function test_no_current_user_returns_nothing_by_default() {
		wp_set_current_user( 0 );
		$this->insert_row( 'Posts', 'administrator', 'no-user-default' );

		$query  = new AAL_Log_Query();
		$result = $query->query();

		$this->assertSame( array(), $result['items'] );
		$this->assertSame( 0, $result['total'] );
	}

	public function test_unrestricted_includes_unregistered_object_type() {
		wp_set_current_user( 0 );
		$this->insert_row( 'NotInInitRoles', 'administrator', 'unrestricted-row' );

		$query  = new AAL_Log_Query( array( 'unrestricted' => true ) );
		$result = $query->query();

		$names = wp_list_pluck( $result['items'], 'object_name' );
		$this->assertContains( 'unrestricted-row', $names );
	}

	public function test_editor_stays_filtered_without_unrestricted() {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->insert_row( 'Core', 'administrator', 'editor-hidden-core' );
		$this->insert_row( 'Posts', 'editor', 'editor-visible-post' );

		$query  = new AAL_Log_Query();
		$result = $query->query();
		$names  = wp_list_pluck( $result['items'], 'object_name' );

		$this->assertContains( 'editor-visible-post', $names );
		$this->assertNotContains( 'editor-hidden-core', $names );
	}
}
