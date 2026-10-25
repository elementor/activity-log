<?php

class AAL_Test_Privacy_Eraser extends WP_UnitTestCase {

	private $user_a_id;
	private $user_b_id;
	private $performed_by_a_histid;
	private $users_row_histid;
	private $performed_by_b_histid;
	private $user_a_caps = 'subscriber';

	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$this->user_a_id = $this->factory->user->create(
			array(
				'user_email'   => 'privacy-eraser-a@example.com',
				'display_name' => 'Privacy Eraser User A',
				'role'         => 'subscriber',
			)
		);
		$this->user_b_id = $this->factory->user->create(
			array(
				'user_email' => 'privacy-eraser-b@example.com',
				'role'       => 'subscriber',
			)
		);

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_subtype' => 'post',
				'object_name'    => 'privacy-eraser-performed-by-a',
				'object_id'      => 1,
				'user_id'        => $this->user_a_id,
				'user_caps'      => $this->user_a_caps,
				'hist_ip'        => '203.0.113.50',
				'hist_time'      => time(),
				'request_source' => '',
				'meta'           => '',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->performed_by_a_histid = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'         => 'updated',
				'object_type'    => 'Users',
				'object_subtype' => '',
				'object_name'    => 'Privacy Eraser User A',
				'object_id'      => $this->user_a_id,
				'user_id'        => $this->user_a_id,
				'user_caps'      => $this->user_a_caps,
				'hist_ip'        => '203.0.113.51',
				'hist_time'      => time(),
				'request_source' => '',
				'meta'           => wp_json_encode( array( 'email' => 'privacy-eraser-a@example.com' ) ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->users_row_histid = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_subtype' => 'post',
				'object_name'    => 'privacy-eraser-performed-by-b',
				'object_id'      => 2,
				'user_id'        => $this->user_b_id,
				'user_caps'      => 'subscriber',
				'hist_ip'        => '203.0.113.52',
				'hist_time'      => time(),
				'request_source' => '',
				'meta'           => '',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->performed_by_b_histid = (int) $wpdb->insert_id;
	}

	public function tearDown(): void {
		global $wpdb;

		$histids = array(
			$this->performed_by_a_histid,
			$this->users_row_histid,
			$this->performed_by_b_histid,
		);
		$histids = array_filter( $histids );

		if ( ! empty( $histids ) ) {
			$wpdb->query(
				'DELETE FROM `' . $wpdb->activity_log . '` WHERE `histid` IN (' . implode( ',', array_map( 'intval', $histids ) ) . ')'
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $wpdb->activity_log . '` WHERE `action` = %s AND `object_id` = %d',
				'personal_data_erased',
				123
			)
		);

		parent::tearDown();
	}

	private function get_privacy_instance(): AAL_Privacy {
		return new AAL_Privacy();
	}

	public function test_wp_eraser_anonymizes_user_activity_log_rows() {
		global $wpdb;

		$user_a = get_userdata( $this->user_a_id );
		$privacy = $this->get_privacy_instance();

		$result = $privacy->wp_eraser( $user_a->user_email );

		$this->assertTrue( $result['done'] );
		$this->assertTrue( $result['items_removed'] );

		$performed_by_a = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '` WHERE `histid` = %d',
				$this->performed_by_a_histid
			)
		);

		$this->assertNotEmpty( $performed_by_a );
		$this->assertSame( 0, (int) $performed_by_a->user_id );
		$this->assertSame( AAL_Privacy::ANONYMIZED_IP, $performed_by_a->hist_ip );
		$this->assertSame( $this->user_a_caps, $performed_by_a->user_caps );

		$users_row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '` WHERE `histid` = %d',
				$this->users_row_histid
			)
		);

		$this->assertNotEmpty( $users_row );
		$this->assertSame( 'Anonymized user', $users_row->object_name );
		$this->assertNull( $users_row->meta );

		$performed_by_b = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '` WHERE `histid` = %d',
				$this->performed_by_b_histid
			)
		);

		$this->assertNotEmpty( $performed_by_b );
		$this->assertSame( $this->user_b_id, (int) $performed_by_b->user_id );
		$this->assertSame( '203.0.113.52', $performed_by_b->hist_ip );
	}

	public function test_wp_eraser_unknown_email_returns_done_without_removing_items() {
		$privacy = $this->get_privacy_instance();

		$result = $privacy->wp_eraser( 'unknown-privacy-eraser@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertFalse( $result['items_removed'] );
	}

	public function test_log_personal_data_erased_creates_activity_log_row() {
		global $wpdb;

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		do_action( 'wp_privacy_personal_data_erased', 123 );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '`
					WHERE `action` = %s AND `object_id` = %d AND `user_id` = %d',
				'personal_data_erased',
				123,
				$admin_id
			)
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( 'Personal data request #123', $row->object_name );
	}
}
