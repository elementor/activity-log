<?php

class AAL_Test_Changes extends WP_UnitTestCase {

	public function test_equal_values_are_skipped() {
		$result = AAL_Changes::diff(
			array( 'blogname' => 'Same' ),
			array( 'blogname' => 'Same' )
		);
		$this->assertArrayNotHasKey( 'changes', $result );
	}

	public function test_scalar_change() {
		$result = AAL_Changes::diff(
			array( 'blogname' => 'Old' ),
			array( 'blogname' => 'New' )
		);
		$this->assertSame( array( 'Old', 'New' ), $result['changes']['blogname'] );
	}

	public function test_one_level_assoc_array_diff() {
		$result = AAL_Changes::diff(
			array( 'settings' => array( 'a' => '1', 'b' => '2' ) ),
			array( 'settings' => array( 'a' => '1', 'b' => '3', 'c' => '4' ) )
		);
		$this->assertArrayNotHasKey( 'settings[a]', $result['changes'] );
		$this->assertSame( array( '2', '3' ), $result['changes']['settings[b]'] );
		$this->assertSame( array( null, '4' ), $result['changes']['settings[c]'] );
	}

	public function test_list_array_encoded_whole() {
		$result = AAL_Changes::diff(
			array( 'ping_sites' => array( 'http://a.com', 'http://b.com' ) ),
			array( 'ping_sites' => array( 'http://a.com', 'http://c.com' ) )
		);
		$this->assertArrayHasKey( 'ping_sites', $result['changes'] );
		$this->assertSame(
			array( 'http://a.com', 'http://b.com' ),
			json_decode( $result['changes']['ping_sites'][0], true )
		);
		$this->assertSame(
			array( 'http://a.com', 'http://c.com' ),
			json_decode( $result['changes']['ping_sites'][1], true )
		);
	}

	/**
	 * @dataProvider secret_field_provider
	 */
	public function test_secret_fields_are_redacted( $field, $should_redact ) {
		$result = AAL_Changes::diff(
			array( $field => 'old-secret' ),
			array( $field => 'new-secret' )
		);
		if ( $should_redact ) {
			$this->assertSame( array( '[redacted]', '[redacted]' ), $result['changes'][ $field ] );
		} else {
			$this->assertSame( array( 'old-secret', 'new-secret' ), $result['changes'][ $field ] );
		}
	}

	public function secret_field_provider() {
		return array(
			array( 'mailserver_pass', true ),
			array( 'api_token', true ),
			array( 'private_key', true ),
			array( 'my_api_key', true ),
			array( 'public_key', false ),
			array( 'site_key', false ),
			array( 'publishable_key', false ),
			array( 'blogname', false ),
		);
	}

	public function test_nested_secret_key_is_redacted() {
		$result = AAL_Changes::diff(
			array( 'settings' => array( 'api_key' => 'old', 'name' => 'A' ) ),
			array( 'settings' => array( 'api_key' => 'new', 'name' => 'B' ) )
		);
		$this->assertSame( array( '[redacted]', '[redacted]' ), $result['changes']['settings[api_key]'] );
		$this->assertSame( array( 'A', 'B' ), $result['changes']['settings[name]'] );
	}

	public function test_secret_parent_redacts_children() {
		$result = AAL_Changes::diff(
			array( 'user_tokens' => array( 'abc' => 'old-tok' ) ),
			array( 'user_tokens' => array( 'abc' => 'new-tok' ) )
		);
		$this->assertSame( array( '[redacted]', '[redacted]' ), $result['changes']['user_tokens[abc]'] );
	}

	public function test_empty_secret_value_not_redacted() {
		$result = AAL_Changes::diff(
			array( 'mailserver_pass' => 'secret' ),
			array( 'mailserver_pass' => '' )
		);
		$this->assertSame( array( '[redacted]', '' ), $result['changes']['mailserver_pass'] );
	}

	public function test_value_truncated_at_200_chars() {
		$long = str_repeat( 'א', 250 );
		$result = AAL_Changes::diff(
			array( 'blogname' => 'short' ),
			array( 'blogname' => $long )
		);
		$new = $result['changes']['blogname'][1];
		$this->assertSame( 201, mb_strlen( $new ) );
		$this->assertStringEndsWith( '…', $new );
	}

	public function test_max_fields_cap_sets_changes_omitted() {
		$old = array();
		$new = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$old[ 'field_' . $i ] = 'old' . $i;
			$new[ 'field_' . $i ] = 'new' . $i;
		}
		$result = AAL_Changes::diff( $old, $new );
		$this->assertCount( 20, $result['changes'] );
		$this->assertSame( 5, $result['changes_omitted'] );
	}

	public function test_hebrew_meta_stored_as_valid_json_under_65535_bytes() {
		global $wpdb;

		$hebrew = str_repeat( 'ש', 200 );
		$changes = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$changes[ 'field_' . $i ] = array( $hebrew, $hebrew );
		}

		aal_insert_log( array(
			'action'      => 'updated',
			'object_type' => 'Options',
			'object_name' => 'hebrew_test',
			'meta'        => array( 'changes' => $changes ),
		) );

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}` WHERE `object_name` = 'hebrew_test' ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$this->assertLessThan( 65535, strlen( $row->meta ) );
		$decoded = json_decode( $row->meta, true );
		$this->assertIsArray( $decoded );
		$this->assertCount( 20, $decoded['changes'] );
		$this->assertSame( $hebrew, $decoded['changes']['field_0'][0] );
	}

	public function test_bool_and_null_normalization() {
		$result = AAL_Changes::diff(
			array( 'flag' => true, 'gone' => 'x' ),
			array( 'flag' => false, 'gone' => null )
		);
		$this->assertSame( array( 'true', 'false' ), $result['changes']['flag'] );
		$this->assertSame( array( 'x', null ), $result['changes']['gone'] );
	}
}
