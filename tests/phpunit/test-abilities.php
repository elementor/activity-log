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

	private function reset_settings_cache() {
		$settings = AAL_Main::instance()->settings;
		$ref      = new ReflectionProperty( $settings, 'options' );
		$ref->setAccessible( true );
		$ref->setValue( $settings, null );
	}

	private function require_abilities_api() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API requires WP 6.9 or newer.' );
		}
	}

	/**
	 * Re-register plugin abilities so exposure meta reflects current settings.
	 */
	private function reregister_plugin_abilities() {
		$names = array(
			AAL_Abilities::ABILITY_QUERY_LOGS,
			AAL_Abilities::ABILITY_GET_LOG,
			AAL_Abilities::ABILITY_GET_FILTERS,
		);

		foreach ( $names as $name ) {
			if ( wp_has_ability( $name ) ) {
				wp_unregister_ability( $name );
			}
		}

		do_action( 'wp_abilities_api_init' );
	}

	private function enable_agent_exposure() {
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

	/**
	 * @param string      $ability_name Namespaced ability name.
	 * @param array|null  $input        Ability input.
	 * @param string|null $basic_auth   Optional HTTP Basic Authorization header value (without "Basic " prefix).
	 * @return WP_REST_Response
	 */
	private function dispatch_ability_rest( $ability_name, $input = array( 'per_page' => 1 ), $basic_auth = null ) {
		return $this->dispatch_ability_rest_request( $ability_name, true, $input, $basic_auth );
	}

	/**
	 * @param string      $ability_name      Namespaced ability name.
	 * @param bool        $include_input_param Whether to send the `input` query parameter.
	 * @param array|null  $input             Ability input when $include_input_param is true.
	 * @param string|null $basic_auth        Optional HTTP Basic Authorization header value (without "Basic " prefix).
	 * @return WP_REST_Response
	 */
	private function dispatch_ability_rest_request( $ability_name, $include_input_param, $input = null, $basic_auth = null ) {
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}

		$route   = '/wp-abilities/v1/abilities/' . $ability_name . '/run';
		$request = new WP_REST_Request( 'GET', $route );
		$query   = array();
		if ( $include_input_param ) {
			$query['input'] = $input;
		}
		$request->set_query_params( $query );

		if ( null !== $basic_auth ) {
			$request->set_header( 'Authorization', 'Basic ' . $basic_auth );
		}

		$server = rest_get_server();
		$server->check_authentication();

		return $server->dispatch( $request );
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $name    Application password label.
	 * @return string Plaintext application password.
	 */
	private function create_application_password( $user_id, $name = 'AAL PHPUnit' ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$this->markTestSkipped( 'Application Passwords are not available.' );
		}

		$created = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array( 'name' => $name )
		);

		$this->assertIsArray( $created );
		$this->assertArrayHasKey( 0, $created );

		return $created[0];
	}

	private function basic_auth_header_for_user( $user_id, $app_password ) {
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}

		$user = get_userdata( $user_id );
		$this->assertInstanceOf( 'WP_User', $user );

		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['PHP_AUTH_PW']   = $app_password;

		return base64_encode( $user->user_login . ':' . $app_password );
	}

	/**
	 * Clear the cached current user so Application Password authentication can run.
	 */
	private function clear_cached_current_user() {
		global $current_user;
		$current_user = null;
	}

	private function insert_sample_log( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
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
		);

		$row = array_merge( $defaults, $overrides );

		$wpdb->insert(
			$wpdb->activity_log,
			$row,
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function test_abilities_api_unavailable_is_noop() {
		if ( function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API is available in this WordPress version.' );
		}

		$this->assertInstanceOf( 'AAL_Abilities', AAL_Main::instance()->abilities );
	}

	public function test_abilities_are_registered() {
		$this->require_abilities_api();

		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_QUERY_LOGS ) );
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_GET_LOG ) );
		$this->assertTrue( wp_has_ability( AAL_Abilities::ABILITY_GET_FILTERS ) );
		$this->assertTrue( wp_has_ability_category( AAL_Abilities::CATEGORY ) );
	}

	public function test_exposure_meta_off_by_default() {
		$this->require_abilities_api();

		$ability = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS );
		$meta    = $ability->get_meta();

		$this->assertFalse( $meta['show_in_rest'] );
		$this->assertFalse( $meta['public'] );
		$this->assertFalse( $meta['mcp']['public'] );
		$this->assertTrue( $meta['annotations']['readonly'] );
	}

	public function test_unsaved_agent_exposure_uses_code_default() {
		$this->require_abilities_api();

		$this->assertFalse( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertSame( AAL_Abilities::DEFAULT_AGENT_EXPOSURE, AAL_Abilities::get_default_agent_exposure() );
		$this->assertSame( 'no', AAL_Abilities::get_agent_exposure_setting() );
		$this->assertFalse( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_filter_changes_unsaved_agent_exposure_default() {
		$this->require_abilities_api();

		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'yes';
		} );

		$this->assertFalse( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertTrue( AAL_Abilities::is_agent_exposure_enabled() );

		remove_all_filters( 'aal_abilities_agent_exposure_default' );
	}

	public function test_explicit_no_agent_exposure_not_overridden_by_filter() {
		$this->require_abilities_api();

		update_option(
			'activity-log-settings',
			array_merge(
				get_option( 'activity-log-settings', array() ),
				array( 'abilities_agent_exposure' => 'no' )
			)
		);
		$this->reset_settings_cache();

		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'yes';
		} );

		$this->assertTrue( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertFalse( AAL_Abilities::is_agent_exposure_enabled() );

		remove_all_filters( 'aal_abilities_agent_exposure_default' );
	}

	public function test_explicit_yes_agent_exposure_enables_exposure() {
		$this->require_abilities_api();

		update_option(
			'activity-log-settings',
			array_merge(
				get_option( 'activity-log-settings', array() ),
				array( 'abilities_agent_exposure' => 'yes' )
			)
		);
		$this->reset_settings_cache();

		$this->assertTrue( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertTrue( AAL_Abilities::is_agent_exposure_enabled() );
	}

	public function test_invalid_filter_default_falls_back_to_constant() {
		$this->require_abilities_api();

		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'maybe';
		} );

		$this->assertSame( 'no', AAL_Abilities::get_default_agent_exposure() );

		remove_all_filters( 'aal_abilities_agent_exposure_default' );
	}

	public function test_exposure_meta_when_setting_enabled() {
		$this->require_abilities_api();

		update_option(
			'activity-log-settings',
			array_merge(
				get_option( 'activity-log-settings', array() ),
				array( 'abilities_agent_exposure' => 'yes' )
			)
		);
		$this->reset_settings_cache();

		$method = new ReflectionMethod( AAL_Abilities::class, 'get_exposure_meta' );
		$method->setAccessible( true );
		$meta   = $method->invoke( AAL_Main::instance()->abilities );

		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['public'] );
		$this->assertTrue( $meta['mcp']['public'] );
	}

	public function test_query_logs_returns_agent_json_shape() {
		$this->require_abilities_api();

		$log_id = $this->insert_sample_log();

		$ability = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS );
		$result  = $ability->execute(
			array(
				'per_page' => 100,
				'page'     => 1,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message() );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertGreaterThanOrEqual( 1, $result['total'] );

		$found = null;
		foreach ( $result['items'] as $item ) {
			if ( (int) $item['id'] === $log_id ) {
				$found = $item;
				break;
			}
		}

		$this->assertNotNull( $found );
		$this->assertSame( 'rest', $found['channel'] );
		$this->assertSame( 'Test Agent', $found['app_name'] );
		$this->assertArrayNotHasKey( 'request_source', $found );
		$this->assertArrayNotHasKey( 'ip', $found );
		$this->assertNotEmpty( $found['time'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T/', $found['time'] );
	}

	public function test_ability_output_never_includes_ip() {
		$this->require_abilities_api();

		$log_id = $this->insert_sample_log( array( 'hist_ip' => '198.51.100.42' ) );

		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG )->execute( array( 'id' => $log_id ) );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'ip', $result );
	}

	public function test_get_log_by_id() {
		$this->require_abilities_api();

		$log_id = $this->insert_sample_log( array( 'object_name' => 'ability-get-log-target' ) );

		$ability = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG );
		$result  = $ability->execute( array( 'id' => $log_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( $log_id, $result['id'] );
		$this->assertSame( 'ability-get-log-target', $result['object_name'] );
	}

	public function test_get_log_not_found() {
		$this->require_abilities_api();

		$ability = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG );
		$result  = $ability->execute( array( 'id' => 999999999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'aal_log_not_found', $result->get_error_code() );
	}

	public function test_get_filters_returns_expected_keys() {
		$this->require_abilities_api();

		$this->insert_sample_log();

		$ability = wp_get_ability( AAL_Abilities::ABILITY_GET_FILTERS );
		$result  = $ability->execute( array() );

		$this->assertArrayHasKey( 'users', $result );
		$this->assertArrayHasKey( 'roles', $result );
		$this->assertArrayHasKey( 'topics', $result );
		$this->assertArrayHasKey( 'actions', $result );
		$this->assertArrayHasKey( 'sources', $result );
	}

	public function test_query_logs_without_input_direct_execute() {
		$this->require_abilities_api();

		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
	}

	public function test_get_filters_without_input_direct_execute() {
		$this->require_abilities_api();

		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );

		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_FILTERS )->execute();

		$this->assertArrayHasKey( 'users', $result );
		$this->assertArrayHasKey( 'sources', $result );
	}

	public function test_get_log_without_input_still_requires_id() {
		$this->require_abilities_api();

		wp_set_current_user( $this->admin_id );

		$ability = wp_get_ability( AAL_Abilities::ABILITY_GET_LOG );

		$result = $ability->execute();
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		$result = $ability->execute( array() );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_admin_can_execute_query_logs_via_rest_without_input_param() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();
		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch_ability_rest_request( AAL_Abilities::ABILITY_QUERY_LOGS, false );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'items', $response->get_data() );
	}

	public function test_admin_can_execute_get_filters_via_rest_without_input_param() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();
		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch_ability_rest_request( AAL_Abilities::ABILITY_GET_FILTERS, false );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'users', $data );
		$this->assertArrayHasKey( 'sources', $data );
	}

	public function test_anonymous_query_logs_without_input_via_rest_gets_auth_error_not_invalid_input() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();
		wp_set_current_user( 0 );

		$response = $this->dispatch_ability_rest_request( AAL_Abilities::ABILITY_QUERY_LOGS, false );

		$this->assertErrorResponse( 'rest_ability_cannot_execute', $response, rest_authorization_required_code() );
	}

	public function test_anonymous_get_filters_without_input_via_rest_gets_auth_error_not_invalid_input() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();
		wp_set_current_user( 0 );

		$response = $this->dispatch_ability_rest_request( AAL_Abilities::ABILITY_GET_FILTERS, false );

		$this->assertErrorResponse( 'rest_ability_cannot_execute', $response, rest_authorization_required_code() );
	}

	public function test_anonymous_query_logs_without_input_direct_execute_gets_permission_error() {
		$this->require_abilities_api();

		wp_set_current_user( 0 );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_anonymous_get_filters_without_input_direct_execute_gets_permission_error() {
		$this->require_abilities_api();

		wp_set_current_user( 0 );

		$result = wp_get_ability( AAL_Abilities::ABILITY_GET_FILTERS )->execute();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_anonymous_cannot_execute_query_logs_directly() {
		$this->require_abilities_api();

		wp_set_current_user( 0 );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_subscriber_cannot_execute_query_logs() {
		$this->require_abilities_api();

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$ability = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS );
		$result  = $ability->execute( array( 'per_page' => 1 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_contributor_cannot_execute_query_logs() {
		$this->require_abilities_api();

		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		wp_set_current_user( $contributor );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_editor_can_execute_query_logs() {
		$this->require_abilities_api();

		$this->insert_sample_log();

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
	}

	public function test_administrator_can_execute_query_logs() {
		$this->require_abilities_api();

		$this->insert_sample_log();
		wp_set_current_user( $this->admin_id );

		$result = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
	}

	public function test_anonymous_cannot_execute_via_abilities_rest_with_exposure_on() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();
		wp_set_current_user( 0 );

		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );

		$this->assertErrorResponse( 'rest_ability_cannot_execute', $response, rest_authorization_required_code() );
	}

	public function test_subscriber_cannot_execute_via_abilities_rest_with_exposure_on() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );

		$this->assertErrorResponse( 'rest_ability_cannot_execute', $response, rest_authorization_required_code() );
	}

	public function test_editor_can_execute_via_abilities_rest_with_exposure_on() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();
		$this->insert_sample_log();

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$response = $this->dispatch_ability_rest( AAL_Abilities::ABILITY_QUERY_LOGS );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'items', $data );
	}

	public function test_application_password_authorized_user_can_execute_via_rest() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();
		$this->insert_sample_log();

		$password = $this->create_application_password( $this->admin_id );
		wp_logout();
		$this->clear_cached_current_user();

		$auth_header = $this->basic_auth_header_for_user( $this->admin_id, $password );
		$this->assertSame( $this->admin_id, (int) wp_validate_application_password( false ) );

		$response = $this->dispatch_ability_rest(
			AAL_Abilities::ABILITY_QUERY_LOGS,
			array( 'per_page' => 1 ),
			$auth_header
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'items', $response->get_data() );
	}

	public function test_application_password_unauthorized_user_denied_via_rest() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$password = $this->create_application_password( $subscriber );

		wp_logout();
		$this->clear_cached_current_user();

		$response = $this->dispatch_ability_rest(
			AAL_Abilities::ABILITY_QUERY_LOGS,
			array( 'per_page' => 1 ),
			$this->basic_auth_header_for_user( $subscriber, $password )
		);

		$this->assertErrorResponse( 'rest_ability_cannot_execute', $response, rest_authorization_required_code() );
	}

	public function test_exposure_on_does_not_bypass_permission_callback() {
		$this->require_abilities_api();

		$this->enable_agent_exposure();

		$ability = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest' ) );

		wp_set_current_user( 0 );
		$this->assertFalse( $ability->check_permissions( array( 'per_page' => 1 ) ) );

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertFalse( $ability->check_permissions( array( 'per_page' => 1 ) ) );
	}

	public function test_ability_execution_attributes_abilities_channel() {
		$this->require_abilities_api();

		global $wpdb;

		$marker = 'ability-attribution-' . wp_generate_password( 6, false );

		$before_callback = function ( $ability_name ) use ( $marker ) {
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
		};

		add_action( 'wp_before_execute_ability', $before_callback, 10, 1 );

		wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS )->execute( array( 'per_page' => 1 ) );

		remove_action( 'wp_before_execute_ability', $before_callback, 10 );

		$this->clear_ability_stack();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `request_source`, `meta` FROM `{$wpdb->activity_log}` WHERE `object_name` = %s",
				$marker
			)
		);

		$this->assertNotEmpty( $row );
		$this->assertStringContainsString( 'abilities', $row->request_source );

		$meta = json_decode( $row->meta, true );
		$this->assertIsArray( $meta );
		$this->assertSame( AAL_Abilities::ABILITY_QUERY_LOGS, $meta['ability'] );
	}

	public function tear_down() {
		remove_filter( 'wp_is_application_passwords_available', '__return_true' );
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		$this->clear_ability_stack();
		parent::tear_down();
	}

	private function clear_ability_stack() {
		$api  = AAL_Main::instance()->api;
		$prop = new ReflectionProperty( $api, 'ability_stack' );
		$prop->setAccessible( true );
		$prop->setValue( $api, array() );
	}
}
