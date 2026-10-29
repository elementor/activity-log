<?php

class AAL_Test_CLI_List extends WP_UnitTestCase {

	private static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_id );
	}

	public function test_build_query_args_maps_filters() {
		$args = AAL_CLI_List::build_query_args(
			array(
				'type'     => 'Posts',
				'action'   => 'updated',
				'per-page' => '10',
				'page'     => '2',
				'search'   => 'hello',
			)
		);

		$this->assertSame( 'Posts', $args['typeshow'] );
		$this->assertSame( 'updated', $args['showaction'] );
		$this->assertSame( 10, $args['per_page'] );
		$this->assertSame( 2, $args['page'] );
		$this->assertSame( 'hello', $args['s'] );
		$this->assertSame( 'histid', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
	}

	public function test_fetch_list_without_wp_cli_and_no_user_stays_restricted() {
		global $wpdb;

		wp_set_current_user( 0 );

		$wpdb->insert(
			$wpdb->activity_log,
			array(
				'action'      => 'updated',
				'object_type' => 'Posts',
				'object_name' => 'fetch-list-no-cli',
				'user_caps'   => 'administrator',
				'hist_time'   => current_time( 'timestamp' ),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		$result = AAL_CLI_List::fetch_list( array( 'type' => 'Posts' ) );

		$this->assertSame( array(), $result['rows'] );
		$this->assertSame( 0, $result['total'] );
	}

	public function test_build_list_rows_includes_id_and_action() {
		$item = (object) array(
			'histid'         => 42,
			'hist_time'      => current_time( 'timestamp' ),
			'user_id'        => self::$admin_id,
			'object_type'    => 'Posts',
			'object_subtype' => 'post',
			'object_name'    => 'cli-list-row',
			'action'         => 'updated',
			'hist_ip'        => '127.0.0.1',
			'request_source' => 'cli',
			'meta'           => '',
		);

		$rows = AAL_CLI_List::build_list_rows( array( $item ), array() );

		$this->assertCount( 1, $rows );
		$this->assertSame( 42, $rows[0]['id'] );
		$this->assertSame( 'Posts', $rows[0]['type'] );
		$this->assertSame( 'cli-list-row', $rows[0]['description'] );
	}

	public function test_rows_to_jsonl_lines_one_object_per_row_with_unescaped_unicode_and_slashes() {
		$rows = array(
			array(
				'id'          => 1,
				'description' => 'path/to/file — café',
			),
			array(
				'id'          => 2,
				'description' => 'https://example.com/test',
			),
		);

		$lines = AAL_CLI_List::rows_to_jsonl_lines( $rows );

		$this->assertCount( 2, $lines );
		$this->assertSame( $rows[0], json_decode( $lines[0], true ) );
		$this->assertSame( $rows[1], json_decode( $lines[1], true ) );
		$this->assertStringContainsString( 'café', $lines[0] );
		$this->assertStringNotContainsString( '\u00e9', $lines[0] );
		$this->assertStringContainsString( 'path/to/file', $lines[0] );
		$this->assertStringNotContainsString( 'path\\/to', $lines[0] );
		$this->assertStringContainsString( 'https://example.com/test', $lines[1] );
		$this->assertStringNotContainsString( 'https:\\/\\/', $lines[1] );
	}

	public function test_iterate_list_rows_batches_all_entries_ordered_by_id_desc() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );

		$inserted_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$wpdb->insert(
				$wpdb->activity_log,
				array(
					'action'      => 'updated',
					'object_type' => 'Posts',
					'object_name' => 'iterate-all-' . $i,
					'user_caps'   => 'administrator',
					'hist_time'   => current_time( 'timestamp' ),
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
			$inserted_ids[] = (int) $wpdb->insert_id;
		}

		$batches = array();
		foreach ( AAL_CLI_List::iterate_list_rows( array(), 2 ) as $batch ) {
			$batches[] = $batch;
		}

		$this->assertCount( 2, $batches );

		$all_rows = array_merge( $batches[0], $batches[1] );
		$this->assertCount( 3, $all_rows );

		$ids = wp_list_pluck( $all_rows, 'id' );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );
		$this->assertSame( $sorted, $ids );

		foreach ( $inserted_ids as $inserted_id ) {
			$this->assertContains( $inserted_id, $ids );
		}
	}
}
