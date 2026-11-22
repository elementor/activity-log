<?php

class AAL_Test_Log_Presenter_Actions extends WP_UnitTestCase {

	private function make_item( $overrides = array() ) {
		$defaults = array(
			'histid'         => 1,
			'hist_time'      => time(),
			'user_id'        => 1,
			'user_caps'      => 'administrator',
			'action'         => 'updated',
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'Test',
			'object_id'      => 0,
			'request_source' => '',
			'meta'           => '',
			'hist_ip'        => '',
		);

		return (object) array_merge( $defaults, $overrides );
	}

	private function get_actions( $item ) {
		$json = AAL_Log_Presenter::to_json( $item, false );

		return $json['description']['actions'];
	}

	private function assert_no_empty_action_values( $actions ) {
		foreach ( $actions as $key => $value ) {
			$this->assertNotSame( '', $value, "Action {$key} must not be an empty string" );
			$this->assertNotFalse( $value, "Action {$key} must not be false" );
			$this->assertNotNull( $value, "Action {$key} must not be null" );
		}
	}

	public function test_post_actions_admin_published_has_view_and_edit() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$actions = $this->get_actions( $this->make_item( array( 'object_id' => $post_id ) ) );

		$this->assertArrayHasKey( 'view', $actions );
		$this->assertArrayHasKey( 'edit', $actions );
		$this->assert_no_empty_action_values( $actions );
	}

	public function test_post_actions_force_deleted_post_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = self::factory()->post->create();
		wp_delete_post( $post_id, true );

		$actions = $this->get_actions( $this->make_item( array( 'object_id' => $post_id ) ) );

		$this->assertSame( array(), $actions );
	}

	public function test_post_actions_trashed_post_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = self::factory()->post->create();
		wp_trash_post( $post_id );

		$actions = $this->get_actions( $this->make_item( array( 'object_id' => $post_id ) ) );

		$this->assertSame( array(), $actions );
	}

	public function test_post_actions_subscriber_view_only() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$sub_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $sub_id );

		$actions = $this->get_actions( $this->make_item( array( 'object_id' => $post_id ) ) );

		$this->assertArrayHasKey( 'view', $actions );
		$this->assertArrayNotHasKey( 'edit', $actions );
		$this->assert_no_empty_action_values( $actions );
	}

	public function test_post_actions_non_viewable_cpt_edit_only_as_admin() {
		register_post_type(
			'aal_test_private',
			array(
				'public'  => false,
				'show_ui' => true,
			)
		);

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = self::factory()->post->create( array( 'post_type' => 'aal_test_private' ) );
		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_id'      => $post_id,
					'object_subtype' => 'aal_test_private',
				)
			)
		);

		unregister_post_type( 'aal_test_private' );

		$this->assertArrayNotHasKey( 'view', $actions );
		$this->assertArrayHasKey( 'edit', $actions );
		$this->assert_no_empty_action_values( $actions );
	}

	public function test_post_actions_object_id_zero_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$actions = $this->get_actions( $this->make_item( array( 'object_id' => 0 ) ) );

		$this->assertSame( array(), $actions );
	}

	public function test_user_actions_existing_user_as_admin_has_edit() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Users',
					'object_id' => $user_id,
					'object_name' => 'sub-user',
				)
			)
		);

		$this->assertArrayHasKey( 'edit', $actions );
		$this->assert_no_empty_action_values( $actions );
	}

	public function test_user_actions_deleted_user_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$user_id = self::factory()->user->create();
		wp_delete_user( $user_id );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Users',
					'object_id'   => $user_id,
				)
			)
		);

		$this->assertSame( array(), $actions );
	}

	public function test_user_actions_failed_login_object_id_zero_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Users',
					'object_id'   => 0,
					'action'      => 'failed_login',
					'object_name' => 'unknown',
				)
			)
		);

		$this->assertSame( array(), $actions );
	}

	public function test_user_actions_subscriber_viewer_empty() {
		$target_id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Users',
					'object_id'   => $target_id,
				)
			)
		);

		$this->assertSame( array(), $actions );
	}

	public function test_comment_actions_edit_url_format() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		$actions    = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Comments',
					'object_id'   => $comment_id,
				)
			)
		);

		$this->assertArrayHasKey( 'edit', $actions );
		$this->assertStringContainsString( '&c=', $actions['edit'] );
		$this->assertStringNotContainsString( '&amp;', $actions['edit'] );
		$this->assert_no_empty_action_values( $actions );
	}

	public function test_comment_actions_deleted_comment_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		wp_delete_comment( $comment_id, true );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Comments',
					'object_id'   => $comment_id,
				)
			)
		);

		$this->assertSame( array(), $actions );
	}

	public function test_comment_actions_trashed_comment_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		wp_trash_comment( $comment_id );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Comments',
					'object_id'   => $comment_id,
				)
			)
		);

		$this->assertSame( array(), $actions );
	}

	public function test_comment_actions_on_trashed_post_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		wp_trash_post( $post_id );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Comments',
					'object_id'   => $comment_id,
				)
			)
		);

		$this->assertSame( array(), $actions );
	}

	public function test_comment_actions_viewer_without_rights_empty() {
		$post_id       = self::factory()->post->create();
		$comment_id    = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type' => 'Comments',
					'object_id'   => $comment_id,
				)
			)
		);

		$this->assertSame( array(), $actions );
	}

	public function test_taxonomy_actions_existing_category_as_admin() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type'    => 'Taxonomies',
					'object_subtype' => 'category',
					'object_id'      => $term_id,
				)
			)
		);

		$this->assertArrayHasKey( 'view', $actions );
		$this->assertArrayHasKey( 'edit', $actions );
		$this->assert_no_empty_action_values( $actions );
	}

	public function test_taxonomy_actions_deleted_term_empty() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		wp_delete_term( $term_id, 'category' );

		$actions = $this->get_actions(
			$this->make_item(
				array(
					'object_type'    => 'Taxonomies',
					'object_subtype' => 'category',
					'object_id'      => $term_id,
				)
			)
		);

		$this->assertSame( array(), $actions );
	}
}