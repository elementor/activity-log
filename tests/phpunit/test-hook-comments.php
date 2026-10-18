<?php

class AAL_Test_Hook_Comments extends WP_UnitTestCase {

	public function test_edit_comment_stores_content_change() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => 'Original comment',
				'comment_author'       => 'Author',
				'comment_author_email' => 'author@example.com',
				'comment_author_url'   => 'https://example.com',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$comment_id}" );

		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'Updated comment',
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Comments' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$comment_id
			)
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'Original comment', 'Updated comment' ), $meta['changes']['comment_content'] );
	}

	public function test_edit_comment_stores_email_change() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => 'Hi',
				'comment_author_email' => 'old@example.com',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$comment_id}" );

		wp_update_comment(
			array(
				'comment_ID'           => $comment_id,
				'comment_author_email' => 'new@example.com',
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Comments' AND `action` = 'updated' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$comment_id
			)
		);

		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'old@example.com', 'new@example.com' ), $meta['changes']['comment_author_email'] );
	}
}
