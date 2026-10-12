<?php

class AAL_Test_Abilities extends WP_UnitTestCase {

	private $admin_id;

	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		update_option(
			'activity-log-settings',
			array(
				'logs_lifespan'            => '30',
				'logs_failed_login'        => 'yes',
				'logs_email'               => 'yes',
				'log_visitor_ip_source'    => 'REMOTE_ADDR',
				'abilities_agent_exposure' => 'no',
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
		$this->assertNotEmpty( $found['time'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T/', $found['time'] );
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

	public function test_subscriber_cannot_execute_query_logs() {
		$this->require_abilities_api();

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$ability = wp_get_ability( AAL_Abilities::ABILITY_QUERY_LOGS );
		$result  = $ability->execute( array( 'per_page' => 1 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
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
