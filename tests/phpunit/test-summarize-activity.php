<?php

class AAL_Test_Summarize_Activity extends WP_Test_REST_TestCase {

	use AAL_Abilities_Test_Helper;

	const TEST_LOG_IP = '203.0.113.10';
	const DAY_RANGE   = array( 'date_from' => '2026-03-10', 'date_to' => '2026-03-10' );

	private $admin_id;

	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API requires WP 6.9 or newer.' );
		}
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		update_option( 'activity-log-settings', array_merge( get_option( 'activity-log-settings', array() ), array( 'abilities_agent_exposure' => 'yes' ) ) );
		$this->reset_settings_cache();
		$this->reregister_plugin_abilities();
	}

	/** @dataProvider summarize_window_provider */
	public function test_summarize_window( $timezone, $input, $now_hist, $insert_rows, $expected ) {
		update_option( 'timezone_string', $timezone );
		foreach ( $insert_rows as $row ) {
			$this->insert_log( $row );
		}
		$result = $this->run_summarize( $input, $now_hist );
		$this->assertSame( $expected, array( 'total' => $result['total'], 'by_action' => $result['by_action'], 'range' => $result['range'] ) );
		$this->assertStringNotContainsString( self::TEST_LOG_IP, (string) wp_json_encode( $result ) );
	}

	public function summarize_window_provider() {
		$day_range = array( 'from' => '2026-03-10T00:00:00+02:00', 'to' => '2026-03-10T23:59:59+02:00' );
		return array(
			'date boundary jerusalem' => array( 'Asia/Jerusalem', self::DAY_RANGE, 1773144000, array( array( 'hist_time' => 1773100800 ), array( 'hist_time' => 1773100799 ) ), array( 'total' => 1, 'by_action' => array( 'updated' => 1 ), 'range' => $day_range ) ),
			'rolling 24h frozen now'  => array( 'Asia/Jerusalem', array( 'period' => '24h' ), 1773161040, array( array( 'hist_time' => 1773161040 ) ), array( 'total' => 1, 'by_action' => array( 'updated' => 1 ), 'range' => array( 'from' => '2026-03-09T16:44:00+02:00', 'to' => '2026-03-10T16:44:00+02:00' ) ) ),
			'today frozen now'        => array( 'Asia/Jerusalem', array( 'period' => 'today' ), 1773137700, array( array( 'hist_time' => 1773137700 ) ), array( 'total' => 1, 'by_action' => array( 'updated' => 1 ), 'range' => array( 'from' => '2026-03-10T00:00:00+02:00', 'to' => '2026-03-10T10:15:00+02:00' ) ) ),
			'excludes earlier utc day' => array( 'UTC', self::DAY_RANGE, 1773144000, array( array( 'hist_time' => 1773136800 ), array( 'hist_time' => 1772359200 ) ), array( 'total' => 1, 'by_action' => array( 'updated' => 1 ), 'range' => array( 'from' => '2026-03-10T00:00:00+00:00', 'to' => '2026-03-10T23:59:59+00:00' ) ) ),
		);
	}

	/** @dataProvider summarize_action_counts_provider */
	public function test_summarize_action_counts( $insert_rows, $expected ) {
		update_option( 'timezone_string', 'UTC' );
		foreach ( $insert_rows as $row ) {
			$this->insert_log( $row );
		}
		$result = $this->run_summarize( self::DAY_RANGE, 1773144000 );
		$this->assertSame( $expected, array( 'total' => $result['total'], 'by_action' => $result['by_action'], 'range' => $result['range'] ) );
	}

	public function summarize_action_counts_provider() {
		return array(
			'counts by action' => array(
				array(
					array( 'hist_time' => 1773144000, 'action' => 'created' ),
					array( 'hist_time' => 1773144000, 'action' => 'created' ),
					array( 'hist_time' => 1773144000, 'action' => 'updated' ),
				),
				array( 'total' => 3, 'by_action' => array( 'created' => 2, 'updated' => 1 ), 'range' => array( 'from' => '2026-03-10T00:00:00+00:00', 'to' => '2026-03-10T23:59:59+00:00' ) ),
			),
		);
	}

	/** @dataProvider summarize_unknown_channel_provider */
	public function test_summarize_unknown_channel( $expected ) {
		update_option( 'timezone_string', 'UTC' );
		$this->insert_log( array( 'hist_time' => 1773144000, 'request_source' => '' ) );
		$result = $this->run_summarize( self::DAY_RANGE, 1773144000 );
		$this->assertSame( $expected, array( 'by_channel' => $result['by_channel'], 'total' => $result['total'] ) );
	}

	public function summarize_unknown_channel_provider() {
		return array( 'empty source' => array( array( 'by_channel' => array( 'unknown' => 1 ), 'total' => 1 ) ) );
	}

	/** @dataProvider summarize_by_app_name_provider */
	public function test_summarize_by_app_name( $expected ) {
		update_option( 'timezone_string', 'UTC' );
		$this->insert_log( array( 'hist_time' => 1773144000, 'request_source' => 'rest|app:Deploy Bot' ) );
		$this->insert_log( array( 'hist_time' => 1773144000, 'request_source' => 'cli|app:Deploy Bot' ) );
		$result = $this->run_summarize( self::DAY_RANGE, 1773144000 );
		$this->assertSame( $expected, array( 'by_app_name' => $result['by_app_name'], 'by_channel' => $result['by_channel'] ) );
	}

	public function summarize_by_app_name_provider() {
		return array( 'app totals' => array( array( 'by_app_name' => array( 'Deploy Bot' => 2 ), 'by_channel' => array( 'cli' => 1, 'rest' => 1 ) ) ) );
	}

	/** @dataProvider summarize_bucket_provider */
	public function test_summarize_buckets( $input, $hist_time, $expected_buckets ) {
		update_option( 'timezone_string', 'Asia/Jerusalem' );
		$this->insert_log( array( 'hist_time' => $hist_time ) );
		$result = $this->run_summarize( $input, 1773144000 );
		$this->assertSame( $expected_buckets, $result['time_buckets'] );
	}

	public function summarize_bucket_provider() {
		return array(
			'hourly within 48h' => array( self::DAY_RANGE, 1773161040, array( array( 'bucket' => '2026-03-10T16:00:00+02:00', 'count' => 1 ) ) ),
			'daily beyond 48h'  => array( array( 'date_from' => '2026-03-08', 'date_to' => '2026-03-10' ), 1773066600, array( array( 'bucket' => '2026-03-09T00:00:00+02:00', 'count' => 1 ) ) ),
		);
	}

	/** @dataProvider summarize_dst_fallback_provider */
	public function test_summarize_dst_fallback_hour( $insert_hists, $expected_buckets ) {
		update_option( 'timezone_string', 'Asia/Jerusalem' );
		foreach ( $insert_hists as $hist_time ) {
			$this->insert_log( array( 'hist_time' => $hist_time ) );
		}
		$result = $this->run_summarize( array( 'date_from' => '2026-10-25', 'date_to' => '2026-10-25' ), 1792929600 );
		$this->assertSame( $expected_buckets, $result['time_buckets'] );
	}

	public function summarize_dst_fallback_provider() {
		return array( 'merged fall-back hour' => array( array( 1792891800, 1792892700 ), array( array( 'bucket' => '2026-10-25T01:00:00+03:00', 'count' => 2 ) ) ) );
	}

	public function test_summarize_editor_sees_posts_only() {
		update_option( 'timezone_string', 'UTC' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->insert_log( array( 'hist_time' => 1773136800, 'user_caps' => 'editor' ) );
		$this->insert_log( array( 'hist_time' => 1773144000, 'object_type' => 'Users', 'user_caps' => 'editor' ) );
		$result = $this->run_summarize( self::DAY_RANGE, 1773144000 );
		$this->assertSame( array( 'total' => 1, 'by_object_type' => array( 'Posts' => 1 ) ), array( 'total' => $result['total'], 'by_object_type' => $result['by_object_type'] ) );
	}

	public function test_summarize_top_users_limit_ten() {
		update_option( 'timezone_string', 'UTC' );
		$leader_id = self::factory()->user->create( array( 'role' => 'administrator', 'user_nicename' => 'summary-leader' ) );
		$other_ids = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$other_ids[] = self::factory()->user->create( array( 'role' => 'administrator' ) );
		}
		sort( $other_ids );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->insert_log( array( 'hist_time' => 1773144000, 'user_id' => $leader_id ) );
		}
		foreach ( $other_ids as $user_id ) {
			$this->insert_log( array( 'hist_time' => 1773144000, 'user_id' => $user_id ) );
		}
		$result = $this->run_summarize( self::DAY_RANGE, 1773144000 );
		$expected_ids = array_merge( array( $leader_id ), array_slice( $other_ids, 0, 9 ) );
		$this->assertSame(
			array(
				'user_ids' => $expected_ids,
				'lead'     => array( 'name' => 'summary-leader', 'count' => 5 ),
			),
			array(
				'user_ids' => wp_list_pluck( $result['top_users'], 'user_id' ),
				'lead'     => array( 'name' => $result['top_users'][0]['name'], 'count' => $result['top_users'][0]['count'] ),
			)
		);
	}

	public function test_summarize_empty_when_role_filter_denies() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$result = ( new AAL_Log_Query() )->summarize_activity( array( 'from_ts' => 0, 'to_ts' => PHP_INT_MAX ) );
		$this->assertSame( array( 'total' => 0, 'by_action' => array() ), array( 'total' => $result['total'], 'by_action' => $result['by_action'] ) );
	}

	public function test_summarize_denied_for_subscriber_rest() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY . '/run' );
		$request->set_query_params( array( 'input' => self::DAY_RANGE ) );
		$this->assertErrorResponse( 'rest_forbidden', rest_get_server()->dispatch( $request ), \WP_Http::FORBIDDEN );
	}

	/** @dataProvider ability_hist_time_iso_provider */
	public function test_query_logs_and_summarize_use_same_hist_time_iso( $timezone, $hist_time, $day_range, $now_hist, $expected ) {
		update_option( 'timezone_string', $timezone );
		$this->insert_log( array( 'hist_time' => $hist_time ) );
		global $wpdb;
		$item    = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => (int) $wpdb->insert_id ) );
		$summary = $this->run_summarize( $day_range, $now_hist );
		$this->assertSame( $expected, array( 'time' => $item['time'], 'range' => $summary['range'] ) );
	}

	public function ability_hist_time_iso_provider() {
		return array(
			'utc event and day range' => array(
				'UTC',
				1773144000,
				self::DAY_RANGE,
				1773144000,
				array(
					'time'  => '2026-03-10T12:00:00+00:00',
					'range' => array(
						'from' => '2026-03-10T00:00:00+00:00',
						'to'   => '2026-03-10T23:59:59+00:00',
					),
				),
			),
		);
	}

	public function test_summarize_empty_input_defaults_to_24h() {
		$now    = 1773161040;
		$before = $this->run_summarize( array(), $now );
		$this->insert_log( array( 'hist_time' => $now ) );
		$after  = $this->run_summarize( array(), $now );
		$this->assertSame( $before['total'] + 1, $after['total'] );
	}

	/** @dataProvider invalid_summarize_input_provider */
	public function test_summarize_rejects_invalid_input( $input ) {
		$result = wp_get_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY )->execute( $input );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function invalid_summarize_input_provider() {
		return array(
			'from after to'         => array( array( 'date_from' => '2026-03-15', 'date_to' => '2026-03-01' ) ),
			'over 90 days'          => array( array( 'date_from' => '2026-01-01', 'date_to' => '2026-04-15' ) ),
			'period and dates'      => array( array( 'period' => '24h', 'date_from' => '2026-03-10', 'date_to' => '2026-03-10' ) ),
			'only date_from'        => array( array( 'date_from' => '2026-03-10' ) ),
			'bad period'            => array( array( 'period' => '365d' ) ),
			'invalid calendar date' => array( array( 'date_from' => '2026-02-30', 'date_to' => '2026-03-01' ) ),
			'bad date format'       => array( array( 'date_from' => '10/03/2026', 'date_to' => '2026-03-10' ) ),
		);
	}

	private function run_summarize( $input, $now_hist ) {
		return AAL_Main::instance()->abilities->execute_summarize_activity( $input, $now_hist );
	}

	private function insert_log( $overrides = array() ) {
		global $wpdb;
		$row = array_merge(
			array(
				'action' => 'updated', 'object_type' => 'Posts', 'object_subtype' => 'post',
				'object_name' => 'summary-test-' . wp_generate_password( 6, false ), 'object_id' => 1,
				'user_id' => $this->admin_id, 'user_caps' => 'administrator', 'hist_ip' => self::TEST_LOG_IP,
				'hist_time' => current_time( 'timestamp' ), 'request_source' => 'rest|app:Summary Agent', 'meta' => '',
			),
			$overrides
		);
		$wpdb->insert( $wpdb->activity_log, $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
	}
}
