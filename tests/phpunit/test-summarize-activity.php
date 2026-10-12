<?php

class AAL_Test_Summarize_Activity extends WP_Test_REST_TestCase {

	use AAL_Abilities_Test_Helper;

	const TEST_LOG_IP = '203.0.113.10';

	private $admin_id;

	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API requires WP 6.9 or newer.' );
		}

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		update_option(
			'activity-log-settings',
			array_merge(
				get_option( 'activity-log-settings', array() ),
				array( 'abilities_agent_exposure' => 'yes' )
			)
		);
		$this->reset_settings_cache();
		$this->reregister_plugin_abilities();
	}

	/** @dataProvider summarize_window_provider */
	public function test_summarize_window( $timezone, $input, $now_local, $events, $expected_total, $expected_by_action, $role ) {
		update_option( 'timezone_string', $timezone );
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		$now_shifted    = $this->hist_from_local( $now_local );
		$expected_range = AAL_Abilities::resolve_summarize_activity_range( $input, $now_shifted );
		foreach ( $events as $event ) {
			$this->insert_log(
				array(
					'hist_time'   => $this->hist_from_local( $event['local'] ),
					'object_type' => $event['object_type'],
					'user_caps'   => $event['user_caps'],
				)
			);
		}

		$result = $this->run_summarize( $input, $now_shifted );

		$this->assertSame( $expected_total, $result['total'] );
		$this->assertSame( $expected_by_action, $result['by_action'] );
		$this->assertSame( AAL_Log_Query::hist_time_to_iso8601( $expected_range['from_ts'] ), $result['range']['from'] );
		$this->assertSame( AAL_Log_Query::hist_time_to_iso8601( $expected_range['to_ts'] ), $result['range']['to'] );
		$this->assertStringNotContainsString( self::TEST_LOG_IP, (string) wp_json_encode( $result ) );
	}

	public function summarize_window_provider() {
		$day    = '2026-03-10';
		$event  = array(
			'object_type' => 'Posts',
			'user_caps'   => 'administrator',
		);
		$events = static function ( $locals ) use ( $event ) {
			$rows = array();
			foreach ( $locals as $local ) {
				$rows[] = array_merge( $event, array( 'local' => $local ) );
			}

			return $rows;
		};

		return array(
			'action counts in date range' => array(
				'UTC',
				array(
					'date_from' => $day,
					'date_to'   => $day,
				),
				$day . ' 12:00:00',
				$events( array( $day . ' 12:00:00', $day . ' 12:00:00', $day . ' 12:01:00' ) ),
				3,
				array( 'updated' => 3 ),
				'administrator',
			),
			'excludes earlier calendar day' => array(
				'UTC',
				array(
					'date_from' => $day,
					'date_to'   => $day,
				),
				$day . ' 12:00:00',
				$events( array( $day . ' 10:00:00', '2026-03-01 10:00:00' ) ),
				1,
				array( 'updated' => 1 ),
				'administrator',
			),
			'day boundary jerusalem' => array(
				'Asia/Jerusalem',
				array(
					'date_from' => $day,
					'date_to'   => $day,
				),
				$day . ' 12:00:00',
				$events( array( $day . ' 00:00:00', '2026-03-09 23:59:59' ) ),
				1,
				array( 'updated' => 1 ),
				'administrator',
			),
			'rolling 24h frozen now' => array(
				'Asia/Jerusalem',
				array( 'period' => '24h' ),
				$day . ' 16:44:00',
				$events( array( $day . ' 16:44:00' ) ),
				1,
				array( 'updated' => 1 ),
				'administrator',
			),
			'today frozen now' => array(
				'Asia/Jerusalem',
				array( 'period' => 'today' ),
				$day . ' 10:15:00',
				$events( array( $day . ' 10:15:00' ) ),
				1,
				array( 'updated' => 1 ),
				'administrator',
			),
			'explicit date bounds jerusalem' => array(
				'Asia/Jerusalem',
				array(
					'date_from' => $day,
					'date_to'   => $day,
				),
				$day . ' 12:30:00',
				$events( array( $day . ' 12:30:00' ) ),
				1,
				array( 'updated' => 1 ),
				'administrator',
			),
			'editor role visibility' => array(
				'UTC',
				array(
					'date_from' => $day,
					'date_to'   => $day,
				),
				$day . ' 12:00:00',
				array(
					array_merge( $event, array( 'local' => $day . ' 11:00:00', 'user_caps' => 'editor' ) ),
					array_merge( $event, array( 'local' => $day . ' 12:00:00', 'object_type' => 'Users', 'user_caps' => 'editor' ) ),
				),
				1,
				array( 'updated' => 1 ),
				'editor',
			),
		);
	}

	/** @dataProvider summarize_aggregation_provider */
	public function test_summarize_aggregation( $timezone, $input, $events, $expected_total, $expected_by_action, $expected_by_channel, $expected_by_app_name ) {
		update_option( 'timezone_string', $timezone );
		foreach ( $events as $event ) {
			$this->insert_log(
				array(
					'hist_time'      => $this->hist_from_local( $event['local'] ),
					'action'         => $event['action'],
					'request_source' => $event['request_source'],
					'user_id'        => $event['user_id'],
				)
			);
		}

		$result = $this->run_summarize( $input, $this->hist_from_local( '2026-03-10 12:00:00' ) );

		$this->assertSame( $expected_total, $result['total'] );
		$this->assertSame( $expected_by_action, $result['by_action'] );
		$this->assertSame( $expected_by_channel, $result['by_channel'] );
		$this->assertSame( $expected_by_app_name, $result['by_app_name'] );
		$this->assertStringNotContainsString( self::TEST_LOG_IP, (string) wp_json_encode( $result ) );
	}

	public function summarize_aggregation_provider() {
		$range = array(
			'date_from' => '2026-03-10',
			'date_to'   => '2026-03-10',
		);
		$noon  = '2026-03-10 12:00:00';

		return array(
			'action counts' => array(
				'UTC',
				$range,
				array(
					array(
						'local'          => $noon,
						'action'         => 'created',
						'request_source' => 'rest',
						'user_id'        => 0,
					),
					array(
						'local'          => $noon,
						'action'         => 'created',
						'request_source' => 'rest',
						'user_id'        => 0,
					),
					array(
						'local'          => $noon,
						'action'         => 'updated',
						'request_source' => 'rest',
						'user_id'        => 0,
					),
				),
				3,
				array(
					'created' => 2,
					'updated' => 1,
				),
				array( 'rest' => 3 ),
				array(),
			),
			'unknown channel' => array(
				'UTC',
				$range,
				array(
					array(
						'local'          => $noon,
						'action'         => 'updated',
						'request_source' => '',
						'user_id'        => 0,
					),
				),
				1,
				array( 'updated' => 1 ),
				array( 'unknown' => 1 ),
				array(),
			),
			'app name totals' => array(
				'UTC',
				$range,
				array(
					array(
						'local'          => $noon,
						'action'         => 'updated',
						'request_source' => 'rest|app:Deploy Bot',
						'user_id'        => 0,
					),
					array(
						'local'          => $noon,
						'action'         => 'updated',
						'request_source' => 'cli|app:Deploy Bot',
						'user_id'        => 0,
					),
				),
				2,
				array( 'updated' => 2 ),
				array(
					'rest' => 1,
					'cli'  => 1,
				),
				array( 'Deploy Bot' => 2 ),
			),
		);
	}

	/** @dataProvider summarize_dst_provider */
	public function test_summarize_dst_hourly_buckets( $date, $event_locals, $expected_total, $expected_bucket_count ) {
		update_option( 'timezone_string', 'Asia/Jerusalem' );
		foreach ( $event_locals as $local_time ) {
			$this->insert_log( array( 'hist_time' => $this->hist_from_local( $local_time ) ) );
		}

		$result = $this->run_summarize(
			array(
				'date_from' => $date,
				'date_to'   => $date,
			),
			$this->hist_from_local( $date . ' 12:00:00' )
		);

		$first_hist     = $this->hist_from_local( $event_locals[0] );
		$bucket_shifted = intdiv( $first_hist, HOUR_IN_SECONDS ) * HOUR_IN_SECONDS;

		$this->assertSame( $expected_total, $result['total'] );
		$this->assertSame(
			array(
				array(
					'bucket' => AAL_Log_Query::hist_time_to_iso8601( $bucket_shifted ),
					'count'  => $expected_bucket_count,
				),
			),
			$result['time_buckets']
		);
	}

	public function summarize_dst_provider() {
		return array(
			'spring forward day' => array(
				'2026-03-27',
				array( '2026-03-27 03:30:00' ),
				1,
				1,
			),
			'fall back merged hour' => array(
				'2026-10-25',
				array( '2026-10-25 01:30:00', '2026-10-25 01:45:00' ),
				2,
				2,
			),
		);
	}

	/** @dataProvider summarize_bucket_granularity_provider */
	public function test_summarize_bucket_granularity( $input, $event_local, $bucket_interval ) {
		update_option( 'timezone_string', 'Asia/Jerusalem' );
		$hist = $this->hist_from_local( $event_local );
		$this->insert_log( array( 'hist_time' => $hist ) );

		$result         = $this->run_summarize( $input, $this->hist_from_local( '2026-03-10 12:00:00' ) );
		$bucket_shifted = intdiv( $hist, $bucket_interval ) * $bucket_interval;

		$this->assertSame(
			array( AAL_Log_Query::hist_time_to_iso8601( $bucket_shifted ) ),
			wp_list_pluck( $result['time_buckets'], 'bucket' )
		);
	}

	public function summarize_bucket_granularity_provider() {
		return array(
			'hourly within 48h' => array(
				array(
					'date_from' => '2026-03-10',
					'date_to'   => '2026-03-10',
				),
				'2026-03-10 16:44:00',
				HOUR_IN_SECONDS,
			),
			'daily beyond 48h' => array(
				array(
					'date_from' => '2026-03-08',
					'date_to'   => '2026-03-10',
				),
				'2026-03-09 14:30:00',
				DAY_IN_SECONDS,
			),
		);
	}

	public function test_summarize_top_users_order_and_limit() {
		update_option( 'timezone_string', 'UTC' );
		$user_ids = array();
		for ( $i = 1; $i <= 11; $i++ ) {
			$user_ids[ $i ] = self::factory()->user->create(
				array(
					'role'         => 'administrator',
					'display_name' => 'Summary User ' . $i,
				)
			);
		}
		$counts = array( 5, 4, 3, 2, 1, 1, 1, 1, 1, 1, 1 );
		foreach ( $counts as $index => $count ) {
			for ( $n = 0; $n < $count; $n++ ) {
				$this->insert_log(
					array(
						'hist_time' => $this->hist_from_local( '2026-03-10 12:00:00' ),
						'user_id'   => $user_ids[ $index + 1 ],
					)
				);
			}
		}

		$result = $this->run_summarize(
			array(
				'date_from' => '2026-03-10',
				'date_to'   => '2026-03-10',
			),
			$this->hist_from_local( '2026-03-10 12:00:00' )
		);

		$this->assertCount( 10, $result['top_users'] );
		$this->assertSame( $user_ids[1], $result['top_users'][0]['user_id'] );
		$this->assertSame( 'Summary User 1', $result['top_users'][0]['name'] );
		$this->assertSame( 5, $result['top_users'][0]['count'] );
		$this->assertSame( $user_ids[10], $result['top_users'][9]['user_id'] );
	}

	public function test_summarize_returns_empty_when_role_filter_denies() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) ) );

		$result = ( new AAL_Log_Query() )->summarize_activity(
			array(
				'from_ts' => 0,
				'to_ts'   => PHP_INT_MAX,
			)
		);

		$this->assertSame( 0, $result['total'] );
		$this->assertSame( array(), $result['by_action'] );
	}

	public function test_summarize_denied_for_subscriber() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = $this->dispatch_summarize_rest();
		$this->assertErrorResponse( 'rest_forbidden', $response, \WP_Http::FORBIDDEN );
	}

	public function test_summarize_empty_input_defaults_to_24h_window() {
		$now_shifted = current_time( 'timestamp' );
		$before      = $this->run_summarize( array(), $now_shifted );
		$this->insert_log( array( 'hist_time' => $now_shifted ) );
		$after       = $this->run_summarize( array(), $now_shifted );

		$this->assertSame( $before['total'] + 1, $after['total'] );
	}

	/** @dataProvider invalid_summary_input_provider */
	public function test_summarize_rejects_invalid_input( $input ) {
		$result = wp_get_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY )->execute( $input );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function invalid_summary_input_provider() {
		return array(
			'from after to' => array(
				array(
					'date_from' => '2026-03-15',
					'date_to'   => '2026-03-01',
				),
			),
			'over 90 days' => array(
				array(
					'date_from' => '2026-01-01',
					'date_to'   => '2026-04-15',
				),
			),
			'period and dates' => array(
				array(
					'period'    => '24h',
					'date_from' => '2026-03-10',
					'date_to'   => '2026-03-10',
				),
			),
			'only date_from' => array(
				array(
					'date_from' => '2026-03-10',
				),
			),
			'bad period' => array(
				array(
					'period' => '365d',
				),
			),
			'invalid calendar date' => array(
				array(
					'date_from' => '2026-02-30',
					'date_to'   => '2026-03-01',
				),
			),
			'bad date format' => array(
				array(
					'date_from' => '10/03/2026',
					'date_to'   => '2026-03-10',
				),
			),
		);
	}

	private function hist_from_local( $local_date_time ) {
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $local_date_time, wp_timezone() );

		return AAL_Log_Query::local_datetime_to_hist_time( $dt );
	}

	private function run_summarize( $input, $now_shifted ) {
		add_filter( 'aal_summarize_activity_hist_now', static function () use ( $now_shifted ) {
			return $now_shifted;
		} );
		$result = wp_get_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY )->execute( $input );
		remove_all_filters( 'aal_summarize_activity_hist_now' );

		return $result;
	}

	private function dispatch_summarize_rest() {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY . '/run' );
		$request->set_query_params(
			array(
				'input' => array(
					'date_from' => '2026-03-10',
					'date_to'   => '2026-03-10',
				),
			)
		);
		rest_get_server()->check_authentication();
		return rest_get_server()->dispatch( $request );
	}

	private function insert_log( $overrides = array() ) {
		global $wpdb;
		$row = array_merge(
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_subtype' => 'post',
				'object_name'    => 'summary-test-' . wp_generate_password( 6, false ),
				'object_id'      => 1,
				'user_id'        => $this->admin_id,
				'user_caps'      => 'administrator',
				'hist_ip'        => self::TEST_LOG_IP,
				'hist_time'      => current_time( 'timestamp' ),
				'request_source' => 'rest|app:Summary Agent',
				'meta'           => '',
			),
			$overrides
		);
		$wpdb->insert( $wpdb->activity_log, $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}
}
