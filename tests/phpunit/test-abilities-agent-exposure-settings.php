<?php

/**
 * Settings REST behavior for abilities_agent_exposure (saved vs unsaved).
 */
class AAL_Test_Abilities_Agent_Exposure_Settings extends WP_Test_REST_TestCase {

	private $admin;

	public function set_up() {
		parent::set_up();

		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );

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

	private function get_settings_field_value() {
		$request  = new WP_REST_Request( 'GET', '/activity-log/v1/settings' );
		$request->set_header( 'X-AAL-Settings-Nonce', wp_create_nonce( 'aal_settings' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		return $data['fields']['abilities_agent_exposure']['value'];
	}

	public function test_settings_rest_reflects_unsaved_default() {
		add_filter( 'aal_abilities_agent_exposure_default', static function () {
			return 'yes';
		} );

		$this->assertFalse( AAL_Abilities::has_saved_agent_exposure_setting() );
		$this->assertSame( 'yes', $this->get_settings_field_value() );

		remove_all_filters( 'aal_abilities_agent_exposure_default' );
	}

	public function test_settings_rest_reflects_explicit_no() {
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
		$this->assertSame( 'no', $this->get_settings_field_value() );

		remove_all_filters( 'aal_abilities_agent_exposure_default' );
	}
}
