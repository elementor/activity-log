<?php

class AAL_Test_Abilities extends WP_Test_REST_TestCase {

	use AAL_Abilities_Test_Helper;

	private $admin_id;

	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			if ( 'test_abilities_api_unavailable_is_noop' !== $this->getName() ) {
				$this->markTestSkipped( 'WordPress Abilities API requires WP 6.9 or newer.' );
			}
			return;
		}

		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		update_option(
			'activity-log-settings',
			array(
				'logs_lifespan'         => '30',
				'logs_failed_login'     => 'yes',
				'logs_email'            => 'yes',
				'log_visitor_ip_source' => 'REMOTE_ADDR',
			)
		);
		$this->reset_settings_cache();
		$this->save_setting( 'abilities_agent_exposure', 'yes' );
		$this->reregister_plugin_abilities();
	}

	public function tear_down() {
		remove_filter( 'wp_is_application_passwords_available', '__return_true' );
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		$this->clear_ability_stack();
		parent::tear_down();
	}

	public function test_abilities_api_unavailable_is_noop() {
		if ( function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API is available in this WordPress version.' );
		}
		$this->assertInstanceOf( 'AAL_Abilities', AAL_Main::instance()->abilities );
	}

	public function test_abilities_are_registered_with_readonly_meta() {
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_QUERY_LOGS ) );
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_GET_LOG ) );
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_GET_FILTERS ) );
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_SUMMARIZE_ACTIVITY ) );
		$this->assertTrue( wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->get_meta()['annotations']['readonly'] );
	}

	public function test_agent_exposure_defaults_to_no_when_unset() {
		$this->reset_settings_without_exposure();
		$this->assertFalse( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_saved_agent_exposure_yes_is_enabled() {
		$this->save_setting( 'abilities_agent_exposure', 'yes' );
		$this->assertTrue( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_registered_ability_exposure_meta_is_public_when_enabled() {
		$meta = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->get_meta();
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['mcp']['public'] );
	}

	public function test_registered_ability_exposure_meta_is_private_when_disabled() {
		$this->save_setting( 'abilities_agent_exposure', 'no' );
		$this->reregister_plugin_abilities();
		$meta = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->get_meta();
		$this->assertFalse( $meta['show_in_rest'] );
	}

	public function test_agent_exposure_disabled_denies_execution() {
		$this->save_setting( 'abilities_agent_exposure', 'no' );
		$this->reregister_plugin_abilities();
		wp_set_current_user( $this->admin_id );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/** @dataProvider rest_allowed_roles_provider */
	public function test_query_logs_rest_allowed_for_role( $role ) {
		$this->insert_sample_log();
		$this->act_as_role( $role );
		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'items', $response->get_data() );
	}

	public function rest_allowed_roles_provider() {
		return array(
			'editor' => array( 'editor' ),
		);
	}

	/** @dataProvider rest_denied_roles_provider */
	public function test_query_logs_rest_denied_for_role( $role, $error_code, $status ) {
		$this->insert_sample_log();
		$this->act_as_role( $role );
		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );
		$this->assertErrorResponse( $error_code, $response, $status );
	}

	public function rest_denied_roles_provider() {
		return array(
			'anonymous'  => array( '', 'rest_forbidden', rest_authorization_required_code() ),
			'subscriber' => array( 'subscriber', 'rest_forbidden', \WP_Http::FORBIDDEN ),
		);
	}

	public function test_view_cap_allows_query_logs() {
		$this->insert_sample_log();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = new WP_User( $user_id );
		$user->add_cap( 'view_all_aryo_activity_log' );
		wp_set_current_user( $user_id );
		$this->assertSame(
			200,
			$this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS )->get_status()
		);
	}

	public function test_view_cap_denies_settings() {
		$this->insert_sample_log();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = new WP_User( $user_id );
		$user->add_cap( 'view_all_aryo_activity_log' );
		wp_set_current_user( $user_id );
		$this->assertSame(
			403,
			rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/activity-log/v1/settings' ) )->get_status()
		);
	}

	public function test_query_logs_agent_json_shape_excludes_ip() {
		$log_id = $this->insert_sample_log( array( 'hist_ip' => '198.51.100.42' ) );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 100 ) );
		$item   = $this->find_log_item( $result['items'], $log_id );
		$this->assertSame( 'rest', $item['channel'] );
		$this->assertArrayNotHasKey( 'ip', $item );
	}

	public function test_get_log_returns_entry_without_ip() {
		$log_id = $this->insert_sample_log();
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );
		$this->assertSame( $log_id, $result['id'] );
		$this->assertArrayNotHasKey( 'ip', $result );
	}

	public function test_get_filters_returns_expected_keys() {
		$this->insert_sample_log();
		$filters = wp_get_ability( AAL_Abilities::ABILITY_GET_FILTERS )->execute();
		$this->assertEquals( array( 'users', 'roles', 'topics', 'actions', 'sources' ), array_keys( $filters ) );
	}

	public function test_get_log_requires_id_in_input() {
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute();
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_query_logs_after_id_incremental() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->activity_log}" );
		$hist_time = current_time( 'timestamp' ) - 60;
		$id1       = $this->insert_sample_log( array( 'hist_time' => $hist_time ) );
		$id2       = $this->insert_sample_log( array( 'hist_time' => $hist_time - 1 ) );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute(
			array(
				'after_id' => $id1,
				'per_page' => 10,
			)
		);

		$this->assertNull( $result['total'] );
		$this->assertNull( $result['pages'] );
		$this->assertFalse( $result['has_more'] );
		$this->assertSame( $id2, $result['next_after_id'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( $id2, $result['items'][0]['id'] );

		$rest = $this->dispatch_ability_rest(
			AAL_Abilities::ABILITY_QUERY_LOGS,
			array(
				'after_id' => $id1,
				'per_page' => 10,
			)
		);
		$rest_data = $rest->get_data();
		$this->assertSame( $id2, $rest_data['next_after_id'] );
		$this->assertFalse( $rest_data['has_more'] );
	}

	public function test_query_logs_without_input_direct_and_rest() {
		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );
		$this->assertArrayHasKey( 'items', wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute() );
		$this->assertSame( 200, $this->dispatch_ability_rest_request( AAL_Abilities::ABILITY_QUERY_LOGS, false )->get_status() );
	}

	public function test_query_search_does_not_match_ip() {
		$this->insert_sample_log( array( 'hist_ip' => '198.51.100.77' ) );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 's' => '198.51.100', 'per_page' => 50 ) );
		$this->assertSame( 0, $result['total'] );
	}

	public function test_query_rejects_filter_ip_input() {
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'filter_ip' => '198.51.100.77', 'per_page' => 1 ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_query_rejects_orderby_hist_ip() {
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'orderby' => 'hist_ip', 'per_page' => 1 ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_meta_output_is_allowlisted() {
		$log_id = $this->insert_sample_log(
			array(
				'meta' => wp_json_encode(
					array(
						'ability' => AAL_Abilities::ABILITY_GET_LOG,
						'secret'  => 'must-not-leak',
					)
				),
			)
		);
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );
		$this->assertSame( AAL_Abilities::ABILITY_GET_LOG, $result['meta']['ability'] );
		$this->assertArrayNotHasKey( 'secret', $result['meta'] );
	}

	public function test_meta_includes_changes_allowlist() {
		$log_id = $this->insert_sample_log(
			array(
				'meta' => wp_json_encode(
					array(
						'changes'         => array( 'blogname' => array( 'Old', 'New' ) ),
						'changes_omitted' => 1,
						'secret'          => 'must-not-leak',
					)
				),
			)
		);
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );
		$this->assertSame( array( 'Old', 'New' ), $result['meta']['changes']['blogname'] );
		$this->assertSame( 1, $result['meta']['changes_omitted'] );
		$this->assertArrayNotHasKey( 'secret', $result['meta'] );
	}

	public function test_meta_omits_changes_for_old_rows() {
		$log_id = $this->insert_sample_log( array( 'meta' => '' ) );
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );
		$this->assertArrayNotHasKey( 'meta', $result );
	}

	/** @dataProvider invalid_query_input_provider */
	public function test_query_logs_schema_rejects_invalid_input( $input ) {
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( $input );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function invalid_query_input_provider() {
		return array(
			'per_page above max' => array( array( 'per_page' => 200 ) ),
			'page zero'          => array( array( 'page' => 0, 'per_page' => 1 ) ),
			'usershow not int'   => array( array( 'usershow' => 'not-a-user-id', 'per_page' => 1 ) ),
		);
	}

	public function test_get_log_not_found() {
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => 999999999 ) );
		$this->assertWPError( $result );
		$this->assertSame( 'aal_log_not_found', $result->get_error_code() );
	}

	public function test_get_log_denied_outside_role_visibility() {
		$log_id = $this->insert_sample_log( array( 'object_type' => 'Users', 'user_caps' => 'administrator' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );
		$this->assertWPError( $result );
		$this->assertSame( 'aal_log_not_found', $result->get_error_code() );
	}

	private function act_as_role( $role ) {
		if ( '' === $role ) {
			wp_set_current_user( 0 );
			return;
		}
		if ( 'administrator' === $role ) {
			wp_set_current_user( $this->admin_id );
			return;
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function find_log_item( $items, $log_id ) {
		foreach ( $items as $row ) {
			if ( (int) $row['id'] === $log_id ) {
				return $row;
			}
		}
		$this->fail( 'Log item not found in ability response.' );
	}

	private function reset_settings_without_exposure() {
		update_option(
			'activity-log-settings',
			array(
				'logs_lifespan'         => '30',
				'logs_failed_login'     => 'yes',
				'logs_email'            => 'yes',
				'log_visitor_ip_source' => 'REMOTE_ADDR',
			)
		);
		$this->reset_settings_cache();
		$this->reregister_plugin_abilities();
	}

	private function save_setting( $key, $value ) {
		update_option(
			'activity-log-settings',
			array_merge( get_option( 'activity-log-settings', array() ), array( $key => $value ) )
		);
		$this->reset_settings_cache();
	}

	private function dispatch_ability_rest( $ability_name, $input = array( 'per_page' => 1 ) ) {
		return $this->dispatch_ability_rest_request( $ability_name, true, $input );
	}

	private function dispatch_ability_rest_request( $ability_name, $include_input, $input = null ) {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . $ability_name . '/run' );
		if ( $include_input ) {
			$request->set_query_params( array( 'input' => $input ) );
		}
		rest_get_server()->check_authentication();
		return rest_get_server()->dispatch( $request );
	}

	private function insert_sample_log( $overrides = array() ) {
		global $wpdb;
		$row = array_merge(
			array(
				'action'         => 'updated',
				'object_type'    => 'Posts',
				'object_subtype' => 'post',
				'object_name'    => 'ability-test-' . wp_generate_password( 8, false ),
				'object_id'      => 1,
				'user_id'        => $this->admin_id,
				'user_caps'      => 'administrator',
				'hist_ip'        => '203.0.113.10',
				'hist_time'      => time(),
				'request_source' => 'rest|app:Test Agent',
				'meta'           => '',
			),
			$overrides
		);
		$wpdb->insert( $wpdb->activity_log, $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	private function clear_ability_stack() {
		$api  = AAL_Main::instance()->api;
		$prop = new ReflectionProperty( $api, 'ability_stack' );
		$prop->setAccessible( true );
		$prop->setValue( $api, array() );
	}
}
