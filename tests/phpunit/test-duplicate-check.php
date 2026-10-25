<?php

class AAL_Test_Duplicate_Check extends WP_UnitTestCase {

	public function test_identical_inserts_create_single_row() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Test'
			)
		);

		$args = array(
			'action'         => 'updated',
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'Duplicate Test',
			'object_id'      => 1,
			'hist_time'      => 1700000000,
		);

		aal_insert_log( $args );
		aal_insert_log( $args );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Test'
			)
		);

		$this->assertSame( '1', $count );
	}

	public function test_different_action_creates_two_rows() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Test'
			)
		);

		$args = array(
			'action'         => 'updated',
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'Duplicate Test',
			'object_id'      => 1,
			'hist_time'      => 1700000000,
		);

		aal_insert_log( $args );

		$args['action'] = 'deleted';
		aal_insert_log( $args );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Test'
			)
		);

		$this->assertSame( '2', $count );
	}

	public function test_different_meta_in_same_second_creates_two_rows() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Meta Test'
			)
		);

		$args = array(
			'action'         => 'updated',
			'object_type'    => 'Users',
			'object_subtype' => 'user',
			'object_name'    => 'Duplicate Meta Test',
			'object_id'      => 1,
			'hist_time'      => 1700000001,
			'meta'           => array(
				'role' => 'editor',
			),
		);

		aal_insert_log( $args );

		$args['meta'] = array(
			'role' => 'author',
		);
		aal_insert_log( $args );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Meta Test'
			)
		);

		$this->assertSame( '2', $count );
	}

	public function test_identical_meta_in_same_second_creates_single_row() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Meta Same Test'
			)
		);

		$args = array(
			'action'         => 'updated',
			'object_type'    => 'Users',
			'object_subtype' => 'user',
			'object_name'    => 'Duplicate Meta Same Test',
			'object_id'      => 1,
			'hist_time'      => 1700000002,
			'meta'           => array(
				'role' => 'editor',
			),
		);

		aal_insert_log( $args );
		aal_insert_log( $args );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '` WHERE `object_name` = %s',
				'Duplicate Meta Same Test'
			)
		);

		$this->assertSame( '1', $count );
	}
}
