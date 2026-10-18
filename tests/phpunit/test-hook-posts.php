<?php

class AAL_Test_Hook_Posts extends WP_UnitTestCase {

	private function get_latest_post_log( $post_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Posts' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$post_id
			)
		);
	}

	public function test_title_change_stores_old_and_new() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Original Title',
				'post_status' => 'publish',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$post_id}" );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated Title',
			)
		);

		$row  = $this->get_latest_post_log( $post_id );
		$meta = json_decode( $row->meta, true );

		$this->assertSame( 'updated', $row->action );
		$this->assertSame( array( 'Original Title', 'Updated Title' ), $meta['changes']['post_title'] );
	}

	public function test_content_change_stores_null_pair() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Content Post',
				'post_content' => 'Before content',
				'post_status'  => 'publish',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$post_id}" );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'After content',
			)
		);

		$row  = $this->get_latest_post_log( $post_id );
		$meta = json_decode( $row->meta, true );

		$this->assertSame( array( null, null ), $meta['changes']['post_content'] );
	}

	public function test_password_is_redacted() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Secret Post',
				'post_password' => 'old-pass',
				'post_status'   => 'publish',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$post_id}" );

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'new-pass',
			)
		);

		$row  = $this->get_latest_post_log( $post_id );
		$meta = json_decode( $row->meta, true );

		$this->assertSame( array( '[redacted]', '[redacted]' ), $meta['changes']['post_password'] );
	}

	public function test_noop_save_has_no_changes() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Stable Title',
				'post_status' => 'publish',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$post_id}" );

		$post = get_post( $post_id );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $post->post_title,
				'post_status' => $post->post_status,
			)
		);

		$row = $this->get_latest_post_log( $post_id );
		$this->assertNotEmpty( $row );

		if ( ! empty( $row->meta ) ) {
			$meta = json_decode( $row->meta, true );
			$this->assertTrue( empty( $meta['changes'] ) );
		}
	}
}
