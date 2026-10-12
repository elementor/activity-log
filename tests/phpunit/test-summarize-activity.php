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

	/** @dataProvider site_timezone_date_range_provider */
	public function test_resolve_summary_date_range_in_site_timezone( $timezone_string, $date, $from_iso, $to_iso ) {
		update_option( 'timezone_string', $timezone_string );
		$range = AAL_Abilities::resolve_summarize_activity_range(
			array(
				'date_from' => $date,
				'date_to'   => $date,
			)
		);
		$this->assertSame( $from_iso, wp_date( 'c', $range['from_ts'] ) );
		$this->assertSame( $to_iso, wp_date( 'c', $range['to_ts'] ) );
	}

	public function site_timezone_date_range_provider() {
		return array(
			'asia kolkata' => array(
				'Asia/Kolkata',
				'2026-03-10',
				'2026-03-10T00:00:00+05:30',
				'2026-03-10T23:59:59+05:30',
			),
		);
	}

	public function test_resolve_summary_today_starts_at_site_midnight() {
		update_option( 'timezone_string', 'Asia/Kolkata' );
		$range    = AAL_Abilities::resolve_summarize_activity_range( array( 'period' => 'today' ) );
		$expected = ( new DateTimeImmutable( 'today', wp_timezone() ) )->setTime( 0, 0, 0 )->getTimestamp();
		$this->assertSame( $expected, $range['from_ts'] );
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
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $local_date_time, wp_timezone() );

		return (int) $dt->getTimestamp();
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
