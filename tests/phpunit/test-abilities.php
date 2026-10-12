<?php

class AAL_Test_Abilities extends WP_Test_REST_TestCase {

	private $admin_id;

	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
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
		$this->require_abilities_api();

		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_QUERY_LOGS ) );
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_GET_LOG ) );
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_GET_FILTERS ) );
		$this->assertTrue( wp_has_ability_category( AAL_Abilities::CATEGORY ) );

		$meta = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->get_meta();
		$this->assertTrue( $meta['annotations']['readonly'] );
	}

	/**
	 * @dataProvider agent_exposure_resolution_provider
	 */
	public function test_agent_exposure_resolution( $setup, $expected_enabled, $expected_saved ) {
		$this->require_abilities_api();
		$setup( $this );

		$this->assertSame( $expected_saved, AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertSame( $expected_enabled, AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function agent_exposure_resolution_provider() {
		return array(
			'unsaved uses code default' => array(
				static function () {},
				false,
				false,
			),
			'filter changes unsaved default' => array(
				static function () {
					add_filter( 'aal_abilities_agent_exposure_default', static function () {
						return 'yes';
					} );
				},
				true,
				false,
			),
			'explicit no wins over filter' => array(
				static function ( $test ) {
					$test->save_setting( 'abilities_agent_exposure', 'no' );
					add_filter( 'aal_abilities_agent_exposure_default', static function () {
						return 'yes';
					} );
				},
				false,
				true,
			),
			'explicit yes' => array(
				static function ( $test ) {
					$test->save_setting( 'abilities_agent_exposure', 'yes' );
				},
				true,
				true,
			),
			'invalid filter falls back to constant' => array(
				function ( $test ) {
					add_filter( 'aal_abilities_agent_exposure_default', static function () {
						return 'maybe';
					} );
					$test->assertSame( 'no', AAL_Abilities::get_default_agent_exposure() );
				},
				false,
				false,
			),
		);
	}

	/**
	 * @dataProvider exposure_meta_provider
	 */
	public function test_exposure_meta_matches_setting( $exposure_yes, $expect_public ) {
		$this->require_abilities_api();

		if ( $exposure_yes ) {
			$this->enable_agent_exposure();
		}

		$method = new ReflectionMethod( AAL_Abilities::class, 'get_exposure_meta' );
		$method->setAccessible( true );
		$meta   = $method->invoke( AAL_Main::instance()->abilities );

		$this->assertSame( $expect_public, $meta['show_in_rest'] );
		$this->assertSame( $expect_public, $meta['public'] );
		$this->assertSame( $expect_public, $meta['mcp']['public'] );
	}

	public function exposure_meta_provider() {
		return array(
			'off by default' => array( false, false ),
			'on when enabled' => array( true, true ),
		);
	}

	public function test_agent_exposure_disabled_denies_execution() {
		$this->require_abilities_api();

		$this->save_setting( 'abilities_agent_exposure', 'no' );
		$this->reregister_plugin_abilities();
		wp_set_current_user( $this->admin_id );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * @dataProvider direct_permission_provider
	 */
	public function test_query_logs_direct_permission_matrix( $prepare_user, $expect_success ) {
		$this->require_abilities_api();
		$this->enable_agent_exposure();
		$this->insert_sample_log();

		$prepare_user( $this );

		$ability = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS );

		if ( $expect_success ) {
			$result = $ability->execute( array( 'per_page' => 1 ) );
			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'items', $result );
		} else {
			$this->assertNotTrue( $ability->check_permissions( array( 'per_page' => 1 ) ) );
		}
	}

	public function direct_permission_provider() {
		return array(
			'anonymous' => array(
				static function () {
					wp_set_current_user( 0 );
				},
				false,
			),
			'subscriber' => array(
				static function ( $test ) {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
				},
				false,
			),
			'contributor' => array(
				static function () {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
				},
				false,
			),
			'editor' => array(
				static function () {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
				},
				true,
			),
			'administrator' => array(
				static function ( $test ) {
					wp_set_current_user( $test->admin_id );
				},
				true,
			),
			'view_all cap' => array(
				static function () {
					$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
					$user    = new WP_User( $user_id );
					$user->add_cap( 'view_all_aryo_activity_log' );
					wp_set_current_user( $user_id );
				},
				true,
			),
			'aal_menu_page_capability filter' => array(
				static function () {
					add_filter( 'aal_menu_page_capability', static function () {
						return 'read';
					} );
					$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
					$user    = new WP_User( $user_id );
					$user->add_cap( 'read' );
					$user->add_cap( 'view_all_aryo_activity_log' );
					wp_set_current_user( $user_id );
				},
				true,
			),
		);
	}

	/**
	 * @dataProvider rest_permission_provider
	 */
	public function test_query_logs_rest_permission_matrix( $prepare_user, $error_code, $status ) {
		$this->require_abilities_api();
		$this->enable_agent_exposure();
		$this->insert_sample_log();

		$prepare_user( $this );

		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );

		if ( null === $error_code ) {
			$this->assertSame( 200, $response->get_status() );
			$this->assertArrayHasKey( 'items', $response->get_data() );
		} else {
			$this->assertErrorResponse( $error_code, $response, $status );
		}
	}

	public function rest_permission_provider() {
		return array(
			'anonymous' => array(
				static function () {
					wp_set_current_user( 0 );
				},
				'rest_forbidden',
				rest_authorization_required_code(),
			),
			'subscriber' => array(
				static function () {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
				},
				'rest_forbidden',
				\WP_Http::FORBIDDEN,
			),
			'editor' => array(
				static function () {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
				},
				null,
				null,
			),
		);
	}

	/**
	 * @dataProvider application_password_rest_provider
	 */
	public function test_application_password_rest_access( $role, $expect_success ) {
		$this->require_abilities_api();
		$this->enable_agent_exposure();
		$this->insert_sample_log();

		$user_id  = self::factory()->user->create( array( 'role' => $role ) );
		$password = $this->create_application_password( $user_id );

		wp_logout();
		$this->clear_cached_current_user();

		$response = $this->dispatch_ability_rest(
			AAL_Abilities::ABILITY_QUERY_LOGS,
			array( 'per_page' => 1 ),
			$this->basic_auth_header_for_user( $user_id, $password )
		);

		if ( $expect_success ) {
			$this->assertSame( 200, $response->get_status() );
		} else {
			$this->assertErrorResponse( 'rest_forbidden', $response, \WP_Http::FORBIDDEN );
		}
	}

	public function application_password_rest_provider() {
		return array(
			'administrator allowed' => array( 'administrator', true ),
			'subscriber denied'     => array( 'subscriber', false ),
		);
	}

	public function test_query_logs_agent_json_shape_excludes_ip() {
		$this->require_abilities_api();
		$this->enable_agent_exposure();

		$log_id = $this->insert_sample_log( array( 'hist_ip' => '198.51.100.42' ) );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute(
			array(
				'per_page' => 100,
				'page'     => 1,
			)
		);

		$this->assertIsArray( $result );
		$item = null;
		foreach ( $result['items'] as $row ) {
			if ( (int) $row['id'] === $log_id ) {
				$item = $row;
				break;
			}
		}

		$this->assertNotNull( $item );
		$this->assertSame( 'rest', $item['channel'] );
		$this->assertSame( 'Test Agent', $item['app_name'] );
		$this->assertArrayNotHasKey( 'ip', $item );
		$this->assertArrayNotHasKey( 'request_source', $item );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T/', $item['time'] );
	}

	public function test_get_log_filters_and_missing_id_validation() {
		$this->require_abilities_api();
		$this->enable_agent_exposure();
		$log_id  = $this->insert_sample_log( array( 'object_name' => 'ability-get-log-target' ) );
		$get_log = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG );
		$by_id   = $get_log->execute( array( 'id' => $log_id ) );
		$this->assertSame( 'ability-get-log-target', $by_id['object_name'] );
		$this->assertArrayNotHasKey( 'ip', $by_id );

		$filters = wp_get_ability( AAL_Abilities::ABILITY_GET_FILTERS )->execute();
		foreach ( array( 'users', 'roles', 'topics', 'actions', 'sources' ) as $key ) {
			$this->assertArrayHasKey( $key, $filters );
		}

		$missing_id = $get_log->execute();
		$this->assertWPError( $missing_id );
		$this->assertSame( 'ability_invalid_input', $missing_id->get_error_code() );
	}

	/**
	 * @dataProvider omitted_input_provider
	 */
	public function test_abilities_accept_omitted_input( $ability, $via_rest ) {
		$this->require_abilities_api();
		$this->enable_agent_exposure();
		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );

		if ( $via_rest ) {
			$response = $this->dispatch_ability_rest_request( $ability, false );
			$this->assertSame( 200, $response->get_status() );
			$this->assertIsArray( $response->get_data() );
		} else {
			$result = wp_get_ability( $ability )->execute();
			$this->assertIsArray( $result );
		}
	}

	public function omitted_input_provider() {
		return array(
			'query-logs direct'    => array( AAL_Abilities::ABILITY_QUERY_LOGS, false ),
			'get-filters direct'   => array( AAL_Abilities::ABILITY_GET_FILTERS, false ),
			'query-logs rest'      => array( AAL_Abilities::ABILITY_QUERY_LOGS, true ),
		);
	}

	/**
	 * @dataProvider ip_inference_blocked_provider
	 */
	public function test_ip_addresses_are_not_inferable_via_query_input( $input ) {
		$this->require_abilities_api();
		$this->enable_agent_exposure();

		$this->insert_sample_log(
			array(
				'object_name' => 'ip-marker-' . wp_generate_password( 4, false ),
				'hist_ip'     => '198.51.100.77',
			)
		);

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( $input );

		if ( isset( $input['s'] ) ) {
			$this->assertIsArray( $result );
			$this->assertSame( 0, $result['total'] );
		} else {
			$this->assertWPError( $result );
			$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
		}
	}

	public function ip_inference_blocked_provider() {
		return array(
			'search prefix does not match ip' => array(
				array(
					's'        => '198.51.100',
					'per_page' => 50,
				),
			),
			'filter_ip rejected' => array(
				array(
					'filter_ip' => '198.51.100.77',
					'per_page'  => 1,
				),
			),
			'orderby hist_ip rejected' => array(
				array(
					'orderby'  => 'hist_ip',
					'per_page' => 1,
				),
			),
		);
	}

	public function test_meta_output_is_allowlisted() {
		$this->require_abilities_api();
		$this->enable_agent_exposure();

		$log_id = $this->insert_sample_log(
			array(
				'meta' => array(
					'ability' => AAL_Abilities::ABILITY_GET_LOG,
					'secret'  => 'must-not-leak',
				),
			)
		);

		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );

		$this->assertSame( AAL_Abilities::ABILITY_GET_LOG, $result['meta']['ability'] );
		$this->assertArrayNotHasKey( 'secret', $result['meta'] );
	}

	/**
	 * @dataProvider invalid_query_input_provider
	 */
	public function test_query_logs_schema_rejects_invalid_input( $input ) {
		$this->require_abilities_api();
		$this->enable_agent_exposure();

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

	public function test_get_log_not_found_and_role_visibility() {
		$this->require_abilities_api();
		$this->enable_agent_exposure();

		$get_log = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG );

		$missing = $get_log->execute( array( 'id' => 999999999 ) );
		$this->assertWPError( $missing );
		$this->assertSame( 'aal_log_not_found', $missing->get_error_code() );

		$hidden_id = $this->insert_sample_log(
			array(
				'object_type' => 'Users',
				'user_caps'   => 'administrator',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$denied = $get_log->execute( array( 'id' => $hidden_id ) );
		$this->assertWPError( $denied );
		$this->assertSame( 'aal_log_not_found', $denied->get_error_code() );
	}

	public function test_ability_execution_records_abilities_channel() {
		$this->require_abilities_api();
		$this->enable_agent_exposure();

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
			},
			10,
			1
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
		$meta = json_decode( $row->meta, true );
		$this->assertSame( AAL_Abilities::ABILITY_QUERY_LOGS, $meta['ability'] );
	}

	/**
	 * @dataProvider settings_rest_exposure_provider
	 */
	public function test_settings_rest_reflects_agent_exposure( $setup, $expected_value ) {
		$this->require_abilities_api();
		$setup( $this );

		$request  = new WP_REST_Request( 'GET', '/activity-log/v1/settings' );
		$request->set_header( 'X-AAL-Settings-Nonce', wp_create_nonce( 'aal_settings' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			$expected_value,
			$response->get_data()['fields']['abilities_agent_exposure']['value']
		);
	}

	public function settings_rest_exposure_provider() {
		return array(
			'unsaved default via filter' => array(
				static function () {
					add_filter( 'aal_abilities_agent_exposure_default', static function () {
						return 'yes';
					} );
				},
				'yes',
			),
			'explicit no overrides filter' => array(
				static function ( $test ) {
					$test->save_setting( 'abilities_agent_exposure', 'no' );
					add_filter( 'aal_abilities_agent_exposure_default', static function () {
						return 'yes';
					} );
				},
				'no',
			),
		);
	}

	private function require_abilities_api() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API requires WP 6.9 or newer.' );
		}
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
			array_merge(
				get_option( 'activity-log-settings', array() ),
				array( $key => $value )
			)
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

	private function enable_agent_exposure() {
		$this->save_setting( 'abilities_agent_exposure', 'yes' );
		$this->reregister_plugin_abilities();
	}

	private function dispatch_ability_rest( $ability_name, $input = array( 'per_page' => 1 ), $basic_auth = null ) {
		return $this->dispatch_ability_rest_request( $ability_name, true, $input, $basic_auth );
	}

	private function dispatch_ability_rest_request( $ability_name, $include_input_param, $input = null, $basic_auth = null ) {
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . $ability_name . '/run' );
		if ( $include_input_param ) {
			$request->set_query_params( array( 'input' => $input ) );
		}

		if ( null !== $basic_auth ) {
			$request->set_header( 'Authorization', 'Basic ' . $basic_auth );
		}

		$server = rest_get_server();
		$server->check_authentication();

		return $server->dispatch( $request );
	}

	private function create_application_password( $user_id, $name = 'AAL PHPUnit' ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$this->markTestSkipped( 'Application Passwords are not available.' );
		}

		$created = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $name ) );
		$this->assertIsArray( $created );

		return $created[0];
	}

	private function basic_auth_header_for_user( $user_id, $app_password ) {
		$user                     = get_userdata( $user_id );
		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['PHP_AUTH_PW']   = $app_password;

		return base64_encode( $user->user_login . ':' . $app_password );
	}

	private function clear_cached_current_user() {
		global $current_user;
		$current_user = null;
	}

	private function insert_sample_log( $overrides = array() ) {
		global $wpdb;

		$meta = null;
		if ( array_key_exists( 'meta', $overrides ) ) {
			$meta = $overrides['meta'];
			unset( $overrides['meta'] );
		}

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
			),
			$overrides
		);

		$wpdb->insert(
			$wpdb->activity_log,
			$row,
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		$insert_id = (int) $wpdb->insert_id;

		if ( null !== $meta && $insert_id > 0 ) {
			$wpdb->update(
				$wpdb->activity_log,
				array( 'meta' => wp_json_encode( $meta ) ),
				array( 'histid' => $insert_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return $insert_id;
	}

	private function clear_ability_stack() {
		$api  = AAL_Main::instance()->api;
		$prop = new ReflectionProperty( $api, 'ability_stack' );
		$prop->setAccessible( true );
		$prop->setValue( $api, array() );
	}
}
