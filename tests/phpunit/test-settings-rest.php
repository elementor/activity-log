<?php

class AAL_Test_Settings_REST extends WP_UnitTestCase {

	private $admin;
	private $editor;
	private $nonce;

	public function set_up() {
		parent::set_up();

		$this->admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( $this->admin );
		$this->nonce = wp_create_nonce( 'aal_settings' );

		update_option( 'activity-log-settings', array(
			'logs_lifespan'        => '30',
			'logs_failed_login'    => 'yes',
			'logs_email'           => 'yes',
			'log_visitor_ip_source' => 'REMOTE_ADDR',
		) );

		$this->reset_settings_cache();
	}

	private function reset_settings_cache() {
		$settings = AAL_Main::instance()->settings;
		$ref = new ReflectionProperty( $settings, 'options' );
		$ref->setAccessible( true );
		$ref->setValue( $settings, null );
	}

	private function do_request( $method, $route, $body = array(), $user = null, $nonce = null ) {
		if ( null !== $user ) {
			wp_set_current_user( $user );
		}

		$request = new WP_REST_Request( $method, '/activity-log/v1/' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-AAL-Settings-Nonce', null !== $nonce ? $nonce : $this->nonce );

		if ( ! empty( $body ) ) {
			$request->set_body( wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	// --- Permission tests ---

	public function test_editor_cannot_get_settings() {
		wp_set_current_user( $this->editor );
		$nonce = wp_create_nonce( 'aal_settings' );

		$response = $this->do_request( 'GET', 'settings', array(), $this->editor, $nonce );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_editor_cannot_update_settings() {
		wp_set_current_user( $this->editor );
		$nonce = wp_create_nonce( 'aal_settings' );

		$response = $this->do_request( 'PUT', 'settings', array( 'logs_lifespan' => '60' ), $this->editor, $nonce );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_editor_cannot_erase_logs() {
		wp_set_current_user( $this->editor );
		$nonce = wp_create_nonce( 'aal_settings' );

		$response = $this->do_request( 'POST', 'logs/erase', array(), $this->editor, $nonce );
		$this->assertSame( 403, $response->get_status() );
	}

	// --- Nonce tests ---

	public function test_missing_nonce_forbidden() {
		$response = $this->do_request( 'GET', 'settings', array(), $this->admin, '' );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_invalid_nonce_forbidden() {
		$response = $this->do_request( 'GET', 'settings', array(), $this->admin, 'bad-nonce' );
		$this->assertSame( 403, $response->get_status() );
	}

	// --- GET settings ---

	public function test_get_settings_returns_fields_and_erase() {
		$response = $this->do_request( 'GET', 'settings' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'fields', $data );
		$this->assertArrayHasKey( 'canEraseLogs', $data );
		$this->assertArrayHasKey( 'logs_lifespan', $data['fields'] );
		$this->assertArrayHasKey( 'logs_failed_login', $data['fields'] );
		$this->assertArrayHasKey( 'logs_email', $data['fields'] );
		$this->assertArrayHasKey( 'log_visitor_ip_source', $data['fields'] );
	}

	// --- PUT settings (sanitization) ---

	public function test_update_valid_settings() {
		$response = $this->do_request( 'PUT', 'settings', array(
			'logs_lifespan'     => '60',
			'logs_failed_login' => 'no',
			'logs_email'        => 'no',
			'log_visitor_ip_source' => 'HTTP_CF_CONNECTING_IP',
		) );

		$this->assertSame( 200, $response->get_status() );

		$this->reset_settings_cache();
		$saved = get_option( 'activity-log-settings' );
		$this->assertSame( '60', $saved['logs_lifespan'] );
		$this->assertSame( 'no', $saved['logs_failed_login'] );
		$this->assertSame( 'no', $saved['logs_email'] );
		$this->assertSame( 'HTTP_CF_CONNECTING_IP', $saved['log_visitor_ip_source'] );
	}

	public function test_update_empty_lifespan_keeps_forever() {
		$response = $this->do_request( 'PUT', 'settings', array(
			'logs_lifespan' => '',
		) );

		$this->assertSame( 200, $response->get_status() );

		$this->reset_settings_cache();
		$saved = get_option( 'activity-log-settings' );
		$this->assertSame( '', $saved['logs_lifespan'] );
	}

	public function test_update_invalid_ip_source_rejected() {
		$response = $this->do_request( 'PUT', 'settings', array(
			'log_visitor_ip_source' => 'INVALID_HEADER',
		) );

		$this->assertSame( 400, $response->get_status() );

		$this->reset_settings_cache();
		$saved = get_option( 'activity-log-settings' );
		$this->assertSame( 'REMOTE_ADDR', $saved['log_visitor_ip_source'] );
	}

	public function test_update_invalid_yesno_rejected() {
		$response = $this->do_request( 'PUT', 'settings', array(
			'logs_failed_login' => 'maybe',
		) );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_update_ignores_unknown_keys() {
		$response = $this->do_request( 'PUT', 'settings', array(
			'logs_lifespan' => '45',
			'evil_key'      => 'injected',
		) );

		$this->assertSame( 200, $response->get_status() );

		$this->reset_settings_cache();
		$saved = get_option( 'activity-log-settings' );
		$this->assertSame( '45', $saved['logs_lifespan'] );
		$this->assertArrayNotHasKey( 'evil_key', $saved );
	}

	public function test_update_merges_with_existing() {
		$response = $this->do_request( 'PUT', 'settings', array(
			'logs_lifespan' => '90',
		) );

		$this->assertSame( 200, $response->get_status() );

		$this->reset_settings_cache();
		$saved = get_option( 'activity-log-settings' );
		$this->assertSame( '90', $saved['logs_lifespan'] );
		$this->assertSame( 'yes', $saved['logs_failed_login'] );
		$this->assertSame( 'REMOTE_ADDR', $saved['log_visitor_ip_source'] );
	}

	public function test_update_negative_lifespan_rejected() {
		$response = $this->do_request( 'PUT', 'settings', array(
			'logs_lifespan' => '-5',
		) );

		$this->assertSame( 400, $response->get_status() );
	}

	// --- Erase ---

	public function test_erase_logs_success() {
		$response = $this->do_request( 'POST', 'logs/erase' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	public function test_erase_blocked_by_filter() {
		add_filter( 'aal_allow_option_erase_logs', '__return_false' );

		$response = $this->do_request( 'POST', 'logs/erase' );
		$this->assertSame( 403, $response->get_status() );

		remove_filter( 'aal_allow_option_erase_logs', '__return_false' );
	}
}
