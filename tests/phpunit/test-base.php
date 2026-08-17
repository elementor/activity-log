<?php

class AAL_Test_Base extends WP_UnitTestCase {
	
	public function test_getinstance() {
		$this->assertInstanceOf( 'AAL_Main', AAL_Main::instance() );
	}

	public function test_create_post() {
		global $wpdb;
		
		$post = $this->factory->post->create_and_get(
			array(
				'post_title' => 'aal-test-create-post',
			)
		);
		
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '`
					WHERE `action` = \'updated\'
						AND `object_type` = \'Posts\'
						AND `object_subtype` = %s
						AND `object_name` = %s
				',
				$post->post_type,
				$post->post_title
			)
		);
		
		$this->assertNotEmpty( $row );
	}

	public function test_trash_post() {
		global $wpdb;

		$post = $this->factory->post->create_and_get(
			array(
				'post_title' => 'aal-test-trash-post',
			)
		);

		wp_delete_post( $post->ID );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '`
					WHERE `action` = \'trashed\'
						AND `object_type` = \'Posts\'
						AND `object_subtype` = %s
						AND `object_name` = %s
				',
				$post->post_type,
				$post->post_title
			)
		);

		$this->assertNotEmpty( $row );
	}
	
	public function test_delete_post() {
		global $wpdb;
		
		$post = $this->factory->post->create_and_get(
			array(
				'post_title' => 'aal-test-delete-post',
			)
		);
		
		wp_delete_post( $post->ID, true );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '`
					WHERE `action` = \'deleted\'
						AND `object_type` = \'Posts\'
						AND `object_subtype` = %s
						AND `object_name` = %s
				',
				$post->post_type,
				$post->post_title
			)
		);
		
		$this->assertNotEmpty( $row );
	}
	
}
