<?php

class Test_AAL_After_Id_Query extends WP_UnitTestCase {

	private $admin_id;

	public function set_up() {
		parent::set_up();
		global $wpdb;
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		wp_set_current_user( $this->admin_id );
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
	}

	public function test_after_id_returns_rows_in_asc_order_above_cursor() {
		$id1 = $this->insert_log( array( 'object_name' => 'first' ) );
		$id2 = $this->insert_log( array( 'object_name' => 'second' ) );
		$id3 = $this->insert_log( array( 'object_name' => 'third' ) );

		$query  = new AAL_Log_Query();
		$result = $query->query(
			array(
				'after_id' => $id1,
				'per_page' => 10,
			)
		);

		$ids = array_map(
			static function ( $item ) {
				return (int) $item->histid;
			},
			$result['items']
		);

		$this->assertSame( array( $id2, $id3 ), $ids );
		$this->assertNull( $result['total'] );
		$this->assertNull( $result['pages'] );
	}

	public function test_after_id_pagination_has_more_and_next_after_id() {
		$ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$ids[] = $this->insert_log(
				array(
					'object_name' => 'page-' . $i,
					'hist_time'   => current_time( 'timestamp' ) - 60 - $i,
				)
			);
		}

		$query = new AAL_Log_Query();

		$page1 = $query->query(
			array(
				'after_id' => 0,
				'per_page' => 2,
			)
		);

		$this->assertTrue( $page1['has_more'] );
		$this->assertSame( $ids[1], $page1['next_after_id'] );
		$this->assertCount( 2, $page1['items'] );
		$this->assertSame( array( $ids[0], $ids[1] ), $this->item_ids( $page1['items'] ) );

		$page2 = $query->query(
			array(
				'after_id' => $page1['next_after_id'],
				'per_page' => 2,
			)
		);

		$this->assertTrue( $page2['has_more'] );
		$this->assertSame( $ids[3], $page2['next_after_id'] );
		$this->assertSame( array( $ids[2], $ids[3] ), $this->item_ids( $page2['items'] ) );

		$page3 = $query->query(
			array(
				'after_id' => $page2['next_after_id'],
				'per_page' => 2,
			)
		);

		$this->assertFalse( $page3['has_more'] );
		$this->assertSame( $ids[4], $page3['next_after_id'] );
		$this->assertCount( 1, $page3['items'] );
	}

	public function test_after_id_empty_result_returns_requested_cursor_as_next_after_id() {
		$id = $this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) - 60 ) );

		$result = ( new AAL_Log_Query() )->query(
			array(
				'after_id' => $id,
				'per_page' => 10,
			)
		);

		$this->assertSame( array(), $result['items'] );
		$this->assertSame( $id, $result['next_after_id'] );
		$this->assertFalse( $result['has_more'] );
	}

	public function test_after_id_respects_typeshow_filter() {
		$this->insert_log( array( 'object_type' => 'Posts', 'hist_time' => current_time( 'timestamp' ) - 60 ) );
		$users_id = $this->insert_log(
			array(
				'object_type' => 'Users',
				'object_name' => 'user-row',
				'hist_time'   => current_time( 'timestamp' ) - 60,
			)
		);

		$result = ( new AAL_Log_Query() )->query(
			array(
				'after_id' => 0,
				'typeshow' => 'Users',
				'per_page' => 10,
			)
		);

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( $users_id, (int) $result['items'][0]->histid );
	}

	public function test_after_id_does_not_skip_rows_dated_in_the_future() {
		$row_a_id = $this->insert_log(
			array(
				'object_name' => 'dst-future',
				'hist_time'   => current_time( 'timestamp' ) + HOUR_IN_SECONDS,
			)
		);
		$this->insert_log(
			array(
				'object_name' => 'after-dst',
				'hist_time'   => current_time( 'timestamp' ) - 60,
			)
		);

		$after_id = $row_a_id - 1;
		$result   = ( new AAL_Log_Query() )->query(
			array(
				'after_id' => $after_id,
				'per_page' => 10,
			)
		);

		$this->assertSame( array(), $result['items'] );
		$this->assertSame( $after_id, $result['next_after_id'] );
	}

	public function test_after_id_excludes_rows_inside_safety_window() {
		$old_id = $this->insert_log(
			array(
				'object_name' => 'safe',
				'hist_time'   => current_time( 'timestamp' ) - AAL_Log_Query::AFTER_ID_SAFETY_WINDOW_SECONDS - 1,
			)
		);
		$this->insert_log(
			array(
				'object_name' => 'too-new',
				'hist_time'   => current_time( 'timestamp' ),
			)
		);

		$result = ( new AAL_Log_Query() )->query(
			array(
				'after_id' => 0,
				'per_page' => 10,
			)
		);

		$this->assertSame( array( $old_id ), $this->item_ids( $result['items'] ) );
	}

	public function test_after_id_mode_skips_count_query() {
		$this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) - 60 ) );

		$queries = array();
		add_filter(
			'query',
			static function ( $query ) use ( &$queries ) {
				$queries[] = $query;
				return $query;
			}
		);

		( new AAL_Log_Query() )->query(
			array(
				'after_id' => 0,
				'per_page' => 5,
			)
		);

		remove_all_filters( 'query' );

		foreach ( $queries as $sql ) {
			$this->assertStringNotContainsString( 'COUNT(`histid`)', $sql );
		}
		$this->assertNotEmpty( $queries );
	}

	public function test_rest_get_logs_returns_next_after_id() {
		$id1 = $this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) - 60 ) );
		$id2 = $this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) - 61 ) );

		$request = new WP_REST_Request( 'GET', '/activity-log/v1/logs' );
		$request->set_query_params(
			array(
				'after_id' => (string) $id1,
				'per_page' => 10,
			)
		);

		$data = ( new AAL_REST() )->get_logs( $request )->get_data();

		$this->assertNull( $data['total'] );
		$this->assertSame( $id2, $data['next_after_id'] );
		$this->assertCount( 1, $data['items'] );
	}

	public function test_after_id_access_denied_returns_input_cursor() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$result = ( new AAL_Log_Query() )->query( array( 'after_id' => 7 ) );

		$this->assertSame( array(), $result['items'] );
		$this->assertSame( 7, $result['next_after_id'] );
	}

	public function test_after_id_zero_starts_from_beginning() {
		$id1 = $this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) - 60 ) );
		$id2 = $this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) - 61 ) );

		$result = ( new AAL_Log_Query() )->query(
			array(
				'after_id' => 0,
				'per_page' => 10,
			)
		);

		$this->assertSame( array( $id1, $id2 ), $this->item_ids( $result['items'] ) );
	}

	/**
	 * @param object[] $items
	 * @return int[]
	 */
	private function item_ids( $items ) {
		return array_map(
			static function ( $item ) {
				return (int) $item->histid;
			},
			$items
		);
	}

	private function insert_log( $overrides = array() ) {
		global $wpdb;
		$row = array_merge(
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_subtype' => 'post',
				'object_name'    => 'after-id-' . wp_generate_password( 6, false ),
				'object_id'      => 1,
				'user_id'        => $this->admin_id,
				'user_caps'      => 'administrator',
				'hist_ip'        => '203.0.113.10',
				'hist_time'      => current_time( 'timestamp' ) - 60,
				'request_source' => '',
				'meta'           => '',
			),
			$overrides
		);
		$wpdb->insert( $wpdb->activity_log, $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}
}
