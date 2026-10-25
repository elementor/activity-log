<?php

class AAL_Test_Detect_Suspicious extends WP_Test_REST_TestCase {

	use AAL_Abilities_Test_Helper;

	const TEST_LOG_IP = '203.0.113.10';
	const DAY_RANGE   = array( 'date_from' => '2026-03-10', 'date_to' => '2026-03-10' );
	const FROZEN_NOW  = 1773144000;

	private $admin_id;

	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API requires WP 6.9 or newer.' );
		}
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		update_option( 'activity-log-settings', array_merge( get_option( 'activity-log-settings', array() ), array( 'abilities_agent_exposure' => 'yes' ) ) );
		$this->reset_settings_cache();
		$this->reregister_plugin_abilities();
		update_option( 'timezone_string', 'UTC' );
	}

	/** @dataProvider detect_suspicious_provider */
	public function test_detect_suspicious_rules( $insert_rows, $input_rules, $expected ) {
		foreach ( $insert_rows as $row ) {
			$this->insert_log( $row );
		}
		$input  = array_merge( self::DAY_RANGE, array( 'rules' => $input_rules ) );
		$result = AAL_Main::instance()->abilities->execute_detect_suspicious_events( $input, self::FROZEN_NOW );
		$actual = array_map(
			static function ( $finding ) {
				return array( 'rule' => $finding['rule'], 'count' => $finding['count'] );
			},
			$result['findings']
		);
		$this->assertSame( $expected, $actual );
	}

	public function detect_suspicious_provider() {
		$failed_login_row = array(
			'hist_time'   => self::FROZEN_NOW,
			'action'      => 'failed_login',
			'object_type' => 'Users',
			'object_name' => 'intruder',
		);

		return array(
			'failed_login_burst'   => array(
				array(
					$failed_login_row,
					$failed_login_row,
					$failed_login_row,
					$failed_login_row,
					$failed_login_row,
				),
				array( 'failed_login_burst' ),
				array( array( 'rule' => 'failed_login_burst', 'count' => 5 ) ),
			),
			'login_after_failures' => array(
				array(
					$failed_login_row,
					$failed_login_row,
					$failed_login_row,
					$failed_login_row,
					$failed_login_row,
					array(
						'hist_time'   => self::FROZEN_NOW + 60,
						'action'      => 'logged_in',
						'object_type' => 'Users',
						'object_name' => 'intruder',
					),
				),
				array( 'login_after_failures' ),
				array( array( 'rule' => 'login_after_failures', 'count' => 1 ) ),
			),
			'plugin_activated'     => array(
				array(
					array(
						'hist_time'   => self::FROZEN_NOW,
						'action'      => 'activated',
						'object_type' => 'Plugins',
						'object_name' => 'hello.php',
					),
				),
				array( 'plugin_activated' ),
				array( array( 'rule' => 'plugin_activated', 'count' => 1 ) ),
			),
			'sensitive_option'     => array(
				array(
					array(
						'hist_time'   => self::FROZEN_NOW,
						'action'      => 'updated',
						'object_type' => 'Options',
						'object_name' => 'siteurl',
					),
				),
				array( 'sensitive_option' ),
				array( array( 'rule' => 'sensitive_option', 'count' => 1 ) ),
			),
			'agent_off_hours'      => array(
				array(
					array(
						'hist_time'      => 1773118800,
						'request_source' => 'abilities|app:Night Agent',
					),
				),
				array( 'agent_off_hours' ),
				array( array( 'rule' => 'agent_off_hours', 'count' => 1 ) ),
			),
			'role_promoted_to_admin' => array(
				array(
					array(
						'hist_time'      => self::FROZEN_NOW,
						'action'         => 'updated',
						'object_type'    => 'Users',
						'object_subtype' => 'Role',
						'meta'           => '{"changes":{"roles":["[\\"editor\\",\\"subscriber\\"]","[\\"administrator\\"]"]}}',
					),
				),
				array( 'new_admin' ),
				array( array( 'rule' => 'new_admin', 'count' => 1 ) ),
			),
			'role_promoted_to_admin_multi_role' => array(
				array(
					array(
						'hist_time'      => self::FROZEN_NOW,
						'action'         => 'updated',
						'object_type'    => 'Users',
						'object_subtype' => 'Role',
						'meta'           => '{"changes":{"roles":["[\\"editor\\"]","[\\"administrator\\",\\"editor\\"]"]}}',
					),
				),
				array( 'new_admin' ),
				array( array( 'rule' => 'new_admin', 'count' => 1 ) ),
			),
			'role_demoted_from_admin' => array(
				array(
					array(
						'hist_time'      => self::FROZEN_NOW,
						'action'         => 'updated',
						'object_type'    => 'Users',
						'object_subtype' => 'Role',
						'meta'           => '{"changes":{"roles":["[\\"administrator\\"]","[\\"editor\\"]"]}}',
					),
				),
				array( 'new_admin' ),
				array(),
			),
			'role_already_admin' => array(
				array(
					array(
						'hist_time'      => self::FROZEN_NOW,
						'action'         => 'updated',
						'object_type'    => 'Users',
						'object_subtype' => 'Role',
						'meta'           => '{"changes":{"roles":["[\\"administrator\\",\\"editor\\"]","[\\"administrator\\"]"]}}',
					),
				),
				array( 'new_admin' ),
				array(),
			),
			'role_shop_administrator' => array(
				array(
					array(
						'hist_time'      => self::FROZEN_NOW,
						'action'         => 'updated',
						'object_type'    => 'Users',
						'object_subtype' => 'Role',
						'meta'           => '{"changes":{"roles":["[\\"editor\\"]","[\\"shop_administrator\\"]"]}}',
					),
				),
				array( 'new_admin' ),
				array(),
			),
			'negative'             => array(
				array( array( 'hist_time' => self::FROZEN_NOW, 'action' => 'updated', 'object_type' => 'Posts' ) ),
				array_keys( AAL_Abilities::SUSPICIOUS_RULES ),
				array(),
			),
		);
	}

	public function test_detect_suspicious_new_admin_add_role_administrator() {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$editor_id}" );

		$user = get_user_by( 'id', $editor_id );
		$user->add_role( 'administrator' );
		$this->flush_pending_role_logs();

		$wpdb->update(
			$wpdb->activity_log,
			array( 'hist_time' => self::FROZEN_NOW ),
			array(
				'object_id'      => $editor_id,
				'object_subtype' => 'Role',
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		$result = AAL_Main::instance()->abilities->execute_detect_suspicious_events(
			array_merge( self::DAY_RANGE, array( 'rules' => array( 'new_admin' ) ) ),
			self::FROZEN_NOW
		);

		$this->assertSame( 1, $result['findings'][0]['count'] );
		$this->assertSame( 'new_admin', $result['findings'][0]['rule'] );
	}

	public function test_detect_suspicious_new_admin_uses_registered_user() {
		$new_admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->insert_log(
			array(
				'hist_time'   => self::FROZEN_NOW,
				'action'      => 'registered',
				'object_type' => 'Users',
				'object_id'   => $new_admin_id,
			)
		);
		$result = AAL_Main::instance()->abilities->execute_detect_suspicious_events(
			array_merge( self::DAY_RANGE, array( 'rules' => array( 'new_admin' ) ) ),
			self::FROZEN_NOW
		);
		$this->assertSame( 1, $result['findings'][0]['count'] );
		$this->assertSame( 'new_admin', $result['findings'][0]['rule'] );
	}

	public function test_detect_suspicious_new_admin_groups_registration_and_promotion() {
		$new_admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->insert_log(
			array(
				'hist_time'   => self::FROZEN_NOW,
				'action'      => 'registered',
				'object_type' => 'Users',
				'object_id'   => $new_admin_id,
			)
		);
		$this->insert_log(
			array(
				'hist_time'      => self::FROZEN_NOW,
				'action'         => 'updated',
				'object_type'    => 'Users',
				'object_subtype' => 'Role',
				'meta'           => '{"changes":{"roles":["[\\"editor\\",\\"subscriber\\"]","[\\"administrator\\"]"]}}',
			)
		);
		$result = AAL_Main::instance()->abilities->execute_detect_suspicious_events(
			array_merge( self::DAY_RANGE, array( 'rules' => array( 'new_admin' ) ) ),
			self::FROZEN_NOW
		);
		$this->assertCount( 1, $result['findings'] );
		$this->assertSame( 'new_admin', $result['findings'][0]['rule'] );
		$this->assertSame( 2, $result['findings'][0]['count'] );
	}

	public function test_detect_suspicious_groups_repeated_plugin_activations() {
		$row = array(
			'hist_time'   => self::FROZEN_NOW,
			'action'      => 'activated',
			'object_type' => 'Plugins',
			'object_name' => 'hello.php',
		);
		$this->insert_log( $row );
		$this->insert_log( $row );
		$this->insert_log( $row );
		$result = AAL_Main::instance()->abilities->execute_detect_suspicious_events(
			array_merge( self::DAY_RANGE, array( 'rules' => array( 'plugin_activated' ) ) ),
			self::FROZEN_NOW
		);
		$this->assertCount( 1, $result['findings'] );
		$this->assertSame( 3, $result['findings'][0]['count'] );
	}

	public function test_detect_suspicious_hides_ip() {
		$row = array(
			'hist_time'   => self::FROZEN_NOW,
			'action'      => 'failed_login',
			'object_type' => 'Users',
			'object_name' => 'intruder',
		);
		$this->insert_log( $row );
		$this->insert_log( $row );
		$this->insert_log( $row );
		$this->insert_log( $row );
		$this->insert_log( $row );
		$result = AAL_Main::instance()->abilities->execute_detect_suspicious_events(
			array_merge( self::DAY_RANGE, array( 'rules' => array( 'failed_login_burst' ) ) ),
			self::FROZEN_NOW
		);
		$this->assertCount( 1, $result['findings'] );
		$this->assertSame( 5, $result['findings'][0]['count'] );
		$this->assertStringNotContainsString( self::TEST_LOG_IP, (string) wp_json_encode( $result ) );
	}

	private function flush_pending_role_logs() {
		global $wp_filter;

		if ( empty( $wp_filter['shutdown'] ) ) {
			return;
		}

		$hook = $wp_filter['shutdown'];
		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) && $function[0] instanceof AAL_Hook_Users && 'flush_buffered_role_changes' === $function[1] ) {
					call_user_func( $function );
					return;
				}
			}
		}
	}

	private function insert_log( $overrides = array() ) {
		global $wpdb;
		$row = array_merge(
			array(
				'action' => 'updated', 'object_type' => 'Posts', 'object_subtype' => 'post',
				'object_name' => 'detect-test-' . wp_generate_password( 6, false ), 'object_id' => 1,
				'user_id' => $this->admin_id, 'user_caps' => 'administrator', 'hist_ip' => self::TEST_LOG_IP,
				'hist_time' => self::FROZEN_NOW, 'request_source' => 'rest|app:Detect Agent', 'meta' => '',
			),
			$overrides
		);
		$wpdb->insert( $wpdb->activity_log, $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
	}
}
