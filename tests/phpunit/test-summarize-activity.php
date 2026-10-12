<?php

class AAL_Test_Summarize_Activity extends WP_Test_REST_TestCase {

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

	/** @dataProvider summary_counts_provider */
	public function test_summarize_counts_match_inserted_logs( $rows, $expected_total, $expected_by_action ) {
		$range = $this->fixed_summary_date_range();
		foreach ( $rows as $row ) {
			$this->insert_log( $row );
		}

		$result = $this->run_summarize( $range );

		$this->assertSame( $expected_total, $result['total'] );
		$this->assertSame( $expected_by_action, $result['by_action'] );
		$this->assertArrayNotHasKey( 'ip', $result );
	}

	public function summary_counts_provider() {
		$in_range = array( 'hist_time' => $this->site_timestamp_for( '2026-03-10 12:00:00' ) );

		return array(
			'single action' => array(
				array(
					array_merge( $in_range, array( 'action' => 'created' ) ),
					array_merge( $in_range, array( 'action' => 'created' ) ),
					array_merge( $in_range, array( 'action' => 'updated' ) ),
				),
				3,
				array( 'created' => 2, 'updated' => 1 ),
			),
		);
	}

	public function test_summarize_excludes_rows_outside_date_range() {
		$range = $this->fixed_summary_date_range();
		$this->insert_log( array( 'hist_time' => $this->site_timestamp_for( '2026-03-10 10:00:00' ) ) );
		$this->insert_log( array( 'hist_time' => $this->site_timestamp_for( '2026-03-01 10:00:00' ) ) );

		$result = $this->run_summarize( $range );

		$this->assertSame( 1, $result['total'] );
	}

	public function test_summarize_respects_role_visibility() {
		$range = $this->fixed_summary_date_range();
		$this->insert_log(
			array(
				'hist_time'   => $this->site_timestamp_for( '2026-03-10 11:00:00' ),
				'object_type' => 'Posts',
				'user_caps'   => 'editor',
			)
		);
		$this->insert_log(
			array(
				'hist_time'   => $this->site_timestamp_for( '2026-03-10 12:00:00' ),
				'object_type' => 'Users',
				'user_caps'   => 'editor',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$result = $this->run_summarize( $range );

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( array( 'Posts' => 1 ), $result['by_object_type'] );
	}

	public function test_summarize_denied_for_subscriber() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = $this->dispatch_summarize_rest();
		$this->assertErrorResponse( 'rest_forbidden', $response, \WP_Http::FORBIDDEN );
	}

	public function test_summarize_empty_input_defaults_to_24h_window() {
		$before = wp_get_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY )->execute();
		$this->insert_log( array( 'hist_time' => current_time( 'timestamp' ) ) );
		$after = wp_get_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY )->execute();

		$this->assertSame( $before['total'] + 1, $after['total'] );
	}

	/** @dataProvider invalid_summary_range_provider */
	public function test_summarize_rejects_invalid_range( $input ) {
		$result = wp_get_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY )->execute( $input );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/** @dataProvider summarize_shifted_range_provider */
	public function test_summarize_range_respects_shifted_hist_time( $input, $local_event_time, $expected_from_local, $expected_to_local ) {
		$this->use_asia_jerusalem_timezone();
		$before    = $this->run_summarize( $input );
		$hist_time = 'now' === $local_event_time ? current_time( 'timestamp' ) : $this->stored_hist_time_for_local( $local_event_time );
		$this->insert_log( array( 'hist_time' => $hist_time ) );
		$result    = $this->run_summarize( $input );

		$this->assertSame( $before['total'] + 1, $result['total'] );
		$this->assertSame( $this->expected_range_iso( $expected_from_local ), $result['range']['from'] );
		$this->assertSame( $this->expected_range_iso( $expected_to_local ), $result['range']['to'] );
	}

	public function summarize_shifted_range_provider() {
		$date = '2026-03-10';

		return array(
			'rolling 24h ends at now' => array(
				array( 'period' => '24h' ),
				'now',
				'rolling_from',
				'now',
			),
			'today starts at local midnight' => array(
				array( 'period' => 'today' ),
				'now',
				'today_midnight',
				'now',
			),
			'explicit date range bounds' => array(
				array(
					'date_from' => $date,
					'date_to'   => $date,
				),
				'2026-03-10 12:30:00',
				$date . ' 00:00:00',
				$date . ' 23:59:59',
			),
		);
	}

	/** @dataProvider summarize_shifted_bucket_provider */
	public function test_summarize_bucket_labels_use_shifted_hist_time( $input, $local_event_time, $expected_bucket_local ) {
		$this->use_asia_jerusalem_timezone();
		$this->insert_log( array( 'hist_time' => $this->stored_hist_time_for_local( $local_event_time ) ) );

		$result = $this->run_summarize( $input );

		$this->assertSame(
			array( $this->expected_range_iso( $expected_bucket_local ) ),
			wp_list_pluck( $result['time_buckets'], 'bucket' )
		);
	}

	public function summarize_shifted_bucket_provider() {
		return array(
			'hourly bucket local hour' => array(
				array(
					'date_from' => '2026-03-10',
					'date_to'   => '2026-03-10',
				),
				'2026-03-10 16:44:00',
				'2026-03-10 16:00:00',
			),
			'daily bucket local midnight' => array(
				array(
					'date_from' => '2026-03-08',
					'date_to'   => '2026-03-10',
				),
				'2026-03-09 14:30:00',
				'2026-03-09 00:00:00',
			),
		);
	}

	public function test_summarize_excludes_shifted_hist_time_outside_bounds() {
		$this->use_asia_jerusalem_timezone();
		$this->insert_log( array( 'hist_time' => $this->stored_hist_time_for_local( '2026-03-10 10:00:00' ) ) );
		$this->insert_log( array( 'hist_time' => $this->stored_hist_time_for_local( '2026-03-09 23:59:58' ) ) );

		$result = $this->run_summarize(
			array(
				'date_from' => '2026-03-10',
				'date_to'   => '2026-03-10',
			)
		);

		$this->assertSame( 1, $result['total'] );
	}

	/** @dataProvider unknown_channel_provider */
	public function test_summarize_maps_empty_channel_to_unknown( $request_source, $expected_channel ) {
		$range = $this->fixed_summary_date_range();
		$this->insert_log(
			array(
				'hist_time'      => $this->site_timestamp_for( '2026-03-10 12:00:00' ),
				'request_source' => $request_source,
			)
		);

		$result = $this->run_summarize( $range );

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( array( $expected_channel => 1 ), $result['by_channel'] );
	}

	public function unknown_channel_provider() {
		return array(
			'empty request source' => array( '', 'unknown' ),
			'rest channel'         => array( 'rest', 'rest' ),
		);
	}

	public function invalid_summary_range_provider() {
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
		);
	}

	private function site_timestamp_for( $local_date_time ) {
		return $this->stored_hist_time_for_local( $local_date_time );
	}

	private function stored_hist_time_for_local( $local_date_time ) {
		return AAL_Log_Query::local_datetime_to_hist_time( $this->local_datetime( $local_date_time ) );
	}

	private function local_datetime( $local_date_time ) {
		return DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $local_date_time, wp_timezone() );
	}

	private function use_asia_jerusalem_timezone() {
		update_option( 'timezone_string', 'Asia/Jerusalem' );
	}

	private function expected_range_iso( $local_marker ) {
		if ( 'now' === $local_marker ) {
			return wp_date( 'c', time() );
		}
		if ( 'rolling_from' === $local_marker ) {
			return AAL_Log_Query::hist_time_to_iso8601( current_time( 'timestamp' ) - DAY_IN_SECONDS );
		}
		if ( 'today_midnight' === $local_marker ) {
			$midnight = ( new DateTimeImmutable( 'today', wp_timezone() ) )->setTime( 0, 0, 0 );

			return wp_date( 'c', $midnight->getTimestamp() );
		}

		return wp_date( 'c', $this->local_datetime( $local_marker )->getTimestamp() );
	}

	private function fixed_summary_date_range() {
		return array(
			'date_from' => '2026-03-10',
			'date_to'   => '2026-03-10',
		);
	}

	private function run_summarize( $input ) {
		return wp_get_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY )->execute( $input );
	}

	private function dispatch_summarize_rest() {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY . '/run' );
		$request->set_query_params(
			array(
				'input' => $this->fixed_summary_date_range(),
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
				'hist_ip'        => '203.0.113.10',
				'hist_time'      => time(),
				'request_source' => 'rest|app:Summary Agent',
				'meta'           => '',
			),
			$overrides
		);
		$wpdb->insert( $wpdb->activity_log, $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	private function reset_settings_cache() {
		$settings = AAL_Main::instance()->settings;
		$ref      = new ReflectionProperty( $settings, 'options' );
		$ref->setAccessible( true );
		$ref->setValue( $settings, null );
	}

	private function reregister_plugin_abilities() {
		foreach (
			array(
				AAL_Abilities::ABILITY_QUERY_LOGS,
				AAL_Abilities::ABILITY_GET_LOG,
				AAL_Abilities::ABILITY_GET_FILTERS,
				AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY,
			) as $name
		) {
			if ( wp_has_ability( $name ) ) {
				wp_unregister_ability( $name );
			}
		}
		do_action( 'wp_abilities_api_init' );
	}
}
