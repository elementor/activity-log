<?php

class AAL_Test_Privacy_Export extends WP_UnitTestCase {

	private function get_privacy_instance(): AAL_Privacy {
		return new AAL_Privacy();
	}

	private function insert_privacy_export_logs( int $user_id, int $count ): void {
		global $wpdb;

		$base_time    = 1700000000;
		$placeholders = array();
		$values       = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$placeholders[] = '(%s, %s, %s, %s, %d, %d)';
			array_push( $values, 'updated', 'Posts', '', 'privacy-export-' . $i, $user_id, $base_time + $i );
		}

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO `' . $wpdb->activity_log . '` (`action`, `object_type`, `object_subtype`, `object_name`, `user_id`, `hist_time`) VALUES ' . implode( ', ', $placeholders ),
				...$values
			)
		);
	}

	public function test_wp_exporter_returns_done_for_user_with_no_rows() {
		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'privacy-empty@example.com',
			)
		);
		$user    = get_userdata( $user_id );
		$privacy = $this->get_privacy_instance();

		$result = $privacy->wp_exporter( $user->user_email, 1 );

		$this->assertTrue( $result['done'] );
		$this->assertSame( array(), $result['data'] );
	}

	public function test_wp_exporter_paginates_all_rows_exactly_once() {
		$batch_size = 500;
		$extra_rows = 3;
		$total_rows = $batch_size + $extra_rows;

		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'privacy-many@example.com',
			)
		);
		$user    = get_userdata( $user_id );

		$this->insert_privacy_export_logs( $user_id, $total_rows );

		$privacy  = $this->get_privacy_instance();
		$page     = 1;
		$item_ids = array();

		do {
			$result = $privacy->wp_exporter( $user->user_email, $page );
			foreach ( $result['data'] as $export_item ) {
				$item_ids[] = $export_item['item_id'];
			}
			$done = $result['done'];
			$page++;
		} while ( ! $done );

		$this->assertCount( $total_rows, $item_ids );
		$this->assertSame( $total_rows, count( array_unique( $item_ids ) ) );
		$this->assertTrue( $done );
	}
}
