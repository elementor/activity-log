<?php

class AAL_Test_Abilities extends WP_Test_REST_TestCase {

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
		remove_all_filters( 'aal_abilities_agent_exposure_default' );
		remove_all_filters( 'aal_menu_page_capability' );
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
		$this->assertTrue( wp_has_ability_category( AAL_Abilities::CATEGORY ) );
		$this->assertTrue( wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->get_meta()['annotations']['readonly'] );
	}

	public function test_unsaved_agent_exposure_uses_code_default() {
		$this->restore_unsaved_settings_option();
		$this->assertFalse( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertFalse( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_filter_changes_unsaved_agent_exposure_default() {
		$this->restore_unsaved_settings_option();
		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'yes';
		} );
		$this->assertTrue( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_explicit_no_agent_exposure_wins_over_filter() {
		$this->save_setting( 'abilities_agent_exposure', 'no' );
		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'yes';
		} );
		$this->assertTrue( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertFalse( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_explicit_yes_agent_exposure_is_saved() {
		$this->save_setting( 'abilities_agent_exposure', 'yes' );
		$this->assertTrue( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertTrue( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_invalid_agent_exposure_filter_falls_back_to_constant() {
		$this->restore_unsaved_settings_option();
		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'maybe';
		} );
		$this->assertSame( 'no', AAL_Abilities::get_default_agent_exposure() );
	}

	public function test_registered_ability_exposure_meta_is_public_when_enabled() {
		$meta = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->get_meta();
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['public'] );
		$this->assertTrue( $meta['mcp']['public'] );
	}

	public function test_registered_ability_exposure_meta_is_private_when_disabled() {
		$this->save_setting( 'abilities_agent_exposure', 'no' );
		$this->reregister_plugin_abilities();
		$meta = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->get_meta();
		$this->assertFalse( $meta['show_in_rest'] );
		$this->assertFalse( $meta['public'] );
		$this->assertFalse( $meta['mcp']['public'] );
	}

	public function test_agent_exposure_disabled_denies_execution() {
		$this->save_setting( 'abilities_agent_exposure', 'no' );
		$this->reregister_plugin_abilities();
		wp_set_current_user( $this->admin_id );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/** @dataProvider denied_roles_provider */
	public function test_query_logs_direct_denied_for_role( $role ) {
		$this->insert_sample_log();
		$this->act_as_role( $role );
		$ability = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS );
		$this->assertNotTrue( $ability->check_permissions( array( 'per_page' => 1 ) ) );
	}

	public function denied_roles_provider() {
		return array(
			'anonymous'   => array( '' ),
			'subscriber'  => array( 'subscriber' ),
			'contributor' => array( 'contributor' ),
		);
	}

	/** @dataProvider allowed_roles_provider */
	public function test_query_logs_direct_allowed_for_role( $role ) {
		$this->insert_sample_log();
		$this->act_as_role( $role );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );
		$this->assertArrayHasKey( 'items', $result );
	}

	public function allowed_roles_provider() {
		return array(
			'editor'        => array( 'editor' ),
			'administrator' => array( 'administrator' ),
		);
	}

	public function test_view_all_cap_allows_direct_query() {
		$this->insert_sample_log();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		( new WP_User( $user_id ) )->add_cap( 'view_all_aryo_activity_log' );
		wp_set_current_user( $user_id );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );
		$this->assertArrayHasKey( 'items', $result );
	}

	public function test_menu_page_capability_filter_allows_direct_query() {
		$this->insert_sample_log();
		add_filter( 'aal_menu_page_capability', static function () {
			return 'read';
		} );
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = new WP_User( $user_id );
		$user->add_cap( 'read' );
		$user->add_cap( 'view_all_aryo_activity_log' );
		wp_set_current_user( $user_id );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );
		$this->assertArrayHasKey( 'items', $result );
	}

	public function test_query_logs_rest_denied_for_anonymous() {
		$this->insert_sample_log();
		wp_set_current_user( 0 );
		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );
		$this->assertErrorResponse( 'rest_forbidden', $response, rest_authorization_required_code() );
	}

	public function test_query_logs_rest_denied_for_subscriber() {
		$this->insert_sample_log();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );
		$this->assertErrorResponse( 'rest_forbidden', $response, \WP_Http::FORBIDDEN );
	}

	public function test_query_logs_rest_allowed_for_editor() {
		$this->insert_sample_log();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'items', $response->get_data() );
	}

	public function test_application_password_rest_allowed_for_administrator() {
		$this->insert_sample_log();
		$user_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$password = $this->create_application_password( $user_id );
		wp_logout();
		$this->clear_cached_current_user();
		$auth = $this->basic_auth_header_for_user( $user_id, $password );
		$this->assertSame( $user_id, (int) wp_validate_application_password( null ) );
		$response = $this->dispatch_ability_rest(
			AAL_Abilities::ABILITY_QUERY_LOGS,
			array( 'per_page' => 1 ),
			$auth
		);
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_application_password_rest_denied_for_subscriber() {
		$this->insert_sample_log();
		$user_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$password = $this->create_application_password( $user_id );
		wp_logout();
		$this->clear_cached_current_user();
		$response = $this->dispatch_ability_rest(
			AAL_Abilities::ABILITY_QUERY_LOGS,
			array( 'per_page' => 1 ),
			$this->basic_auth_header_for_user( $user_id, $password )
		);
		$this->assertErrorResponse( 'rest_forbidden', $response, \WP_Http::FORBIDDEN );
	}

	public function test_query_logs_agent_json_shape_excludes_ip() {
		$log_id = $this->insert_sample_log( array( 'hist_ip' => '198.51.100.42' ) );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 100, 'page' => 1 ) );
		$item   = $this->find_log_item( $result['items'], $log_id );
		$this->assertSame( 'rest', $item['channel'] );
		$this->assertSame( 'Test Agent', $item['app_name'] );
		$this->assertArrayNotHasKey( 'ip', $item );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T/', $item['time'] );
	}

	public function test_get_log_returns_entry_without_ip() {
		$log_id = $this->insert_sample_log( array( 'object_name' => 'ability-get-log-target' ) );
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );
		$this->assertSame( 'ability-get-log-target', $result['object_name'] );
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

	public function test_query_logs_direct_without_input() {
		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );
		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute();
		$this->assertArrayHasKey( 'items', $result );
	}

	public function test_get_filters_direct_without_input() {
		$this->insert_sample_log();
		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_FILTERS )->execute();
		$this->assertArrayHasKey( 'sources', $result );
	}

	public function test_query_logs_rest_without_input() {
		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch_ability_rest_request( AAL_Abilities::ABILITY_QUERY_LOGS, false );
		$this->assertSame( 200, $response->get_status() );
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

	public function test_ability_execution_records_abilities_channel() {
		global $wpdb;
		$marker = 'ability-attribution-' . wp_generate_password( 6, false );
		add_action(
			'wp_before_execute_ability',
			static function ( $ability_name ) use ( $marker ) {
				if ( AAL_Abilities::ABILITY_QUERY_LOGS !== $ability_name ) {
					return;
				}
				aal_insert_log(
					array(
						'action'      => 'updated',
						'object_type' => 'Posts',
						'object_name' => $marker,
						'object_id'   => 1,
					)
				);
			}
		);
		wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );
		remove_all_actions( 'wp_before_execute_ability' );
		$this->clear_ability_stack();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `request_source`, `meta` FROM `{$wpdb->activity_log}` WHERE `object_name` = %s",
				$marker
			)
		);
		$this->assertStringContainsString( 'abilities', $row->request_source );
		$this->assertSame( AAL_Abilities::ABILITY_QUERY_LOGS, json_decode( $row->meta, true )['ability'] );
	}

	public function test_settings_rest_reflects_unsaved_default_via_filter() {
		$this->restore_unsaved_settings_option();
		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'yes';
		} );
		$this->assertSame( 'yes', $this->get_settings_exposure_value() );
	}

	public function test_settings_rest_reflects_explicit_no_over_filter() {
		$this->save_setting( 'abilities_agent_exposure', 'no' );
		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'yes';
		} );
		$this->assertSame( 'no', $this->get_settings_exposure_value() );
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

	private function get_settings_exposure_value() {
		$request = new WP_REST_Request( 'GET', '/activity-log/v1/settings' );
		$request->set_header( 'X-AAL-Settings-Nonce', wp_create_nonce( 'aal_settings' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data()['fields']['abilities_agent_exposure']['value'];
	}

	private function restore_unsaved_settings_option() {
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

	private function reset_settings_cache() {
		$settings = AAL_Main::instance()->settings;
		$ref      = new ReflectionProperty( $settings, 'options' );
		$ref->setAccessible( true );
		$ref->setValue( $settings, null );
	}

	private function save_setting( $key, $value ) {
		update_option(
			'activity-log-settings',
			array_merge( get_option( 'activity-log-settings', array() ), array( $key => $value ) )
		);
		$this->reset_settings_cache();
	}

	private function reregister_plugin_abilities() {
		foreach (
			array(
				AAL_Abilities::ABILITY_QUERY_LOGS,
				AAL_Abilities::ABILITY_GET_LOG,
				AAL_Abilities::ABILITY_GET_FILTERS,
			) as $name
		) {
			if ( wp_has_ability( $name ) ) {
				wp_unregister_ability( $name );
			}
		}
		do_action( 'wp_abilities_api_init' );
	}

	private function dispatch_ability_rest( $ability_name, $input = array( 'per_page' => 1 ), $basic_auth = null ) {
		return $this->dispatch_ability_rest_request( $ability_name, true, $input, $basic_auth );
	}

	private function dispatch_ability_rest_request( $ability_name, $include_input, $input = null, $basic_auth = null ) {
		$this->mark_rest_request();
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . $ability_name . '/run' );
		if ( $include_input ) {
			$request->set_query_params( array( 'input' => $input ) );
		}
		if ( null !== $basic_auth ) {
			$request->set_header( 'Authorization', 'Basic ' . $basic_auth );
		}
		rest_get_server()->check_authentication();
		return rest_get_server()->dispatch( $request );
	}

	private function create_application_password( $user_id ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$this->markTestSkipped( 'Application Passwords are not available.' );
		}
		$created = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => 'AAL PHPUnit' ) );
		$this->assertIsArray( $created );
		return $created[0];
	}

	private function basic_auth_header_for_user( $user_id, $app_password ) {
		$this->mark_rest_request();
		$user                     = get_userdata( $user_id );
		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['PHP_AUTH_PW']   = $app_password;
		return base64_encode( $user->user_login . ':' . $app_password );
	}

	private function mark_rest_request() {
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}
	}

	private function clear_cached_current_user() {
		global $current_user;
		$current_user = null;
	}

	private function insert_sample_log( $overrides = array() ) {
		global $wpdb;
		$row     = array_merge(
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
		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' );
		$wpdb->insert( $wpdb->activity_log, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	private function clear_ability_stack() {
		$api  = AAL_Main::instance()->api;
		$prop = new ReflectionProperty( $api, 'ability_stack' );
		$prop->setAccessible( true );
		$prop->setValue( $api, array() );
	}
}
