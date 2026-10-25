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
}
