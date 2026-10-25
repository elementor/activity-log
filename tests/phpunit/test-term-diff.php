<?php

class AAL_Test_Term_Diff extends WP_UnitTestCase {

	public function test_term_update_stores_name_and_slug_diff() {
		$term_id = self::factory()->term->create(
			array(
				'name'     => 'Original Name',
				'slug'     => 'original-slug',
				'taxonomy' => 'category',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$term_id}" );

		wp_update_term(
			$term_id,
			'category',
			array(
				'name' => 'Updated Name',
				'slug' => 'updated-slug',
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Taxonomies' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$term_id
			)
		);

		$meta = json_decode( $row->meta, true );

		$this->assertSame( 'updated', $row->action );
		$this->assertSame( array( 'Original Name', 'Updated Name' ), $meta['changes']['name'] );
		$this->assertSame( array( 'original-slug', 'updated-slug' ), $meta['changes']['slug'] );
	}
}
