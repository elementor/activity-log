<?php

class AAL_Test_Plugin_Log_Exact_File extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		file_put_contents(
			WP_PLUGIN_DIR . '/aal-test-single.php',
			"<?php\n/**\n * Plugin Name: AAL Test Single\n * Version: 1.2.3\n */\n"
		);

		wp_mkdir_p( WP_PLUGIN_DIR . '/aal-test-multi' );
		file_put_contents(
			WP_PLUGIN_DIR . '/aal-test-multi/a.php',
			"<?php\n/**\n * Plugin Name: AAL Multi A\n * Version: 1.0.0\n */\n"
		);
		file_put_contents(
			WP_PLUGIN_DIR . '/aal-test-multi/b.php',
			"<?php\n/**\n * Plugin Name: AAL Multi B\n * Version: 2.0.0\n */\n"
		);
	}

	public function tearDown(): void {
		$files = array(
			WP_PLUGIN_DIR . '/aal-test-single.php',
			WP_PLUGIN_DIR . '/aal-test-multi/a.php',
			WP_PLUGIN_DIR . '/aal-test-multi/b.php',
		);

		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		if ( is_dir( WP_PLUGIN_DIR . '/aal-test-multi' ) ) {
			rmdir( WP_PLUGIN_DIR . '/aal-test-multi' );
		}

		parent::tearDown();
	}

	private function get_latest_log_row() {
		global $wpdb;

		return $wpdb->get_row( "SELECT * FROM `{$wpdb->activity_log}` ORDER BY histid DESC LIMIT 1" );
	}

	public function test_single_file_plugin() {
		( new AAL_Hook_Plugins() )->hooks_activated_plugin( 'aal-test-single.php' );

		$row = $this->get_latest_log_row();

		$this->assertSame( 'AAL Test Single', $row->object_name );
		$this->assertSame( '1.2.3', $row->object_subtype );
	}

	public function test_multi_plugin_directory_uses_exact_file() {
		( new AAL_Hook_Plugins() )->hooks_activated_plugin( 'aal-test-multi/b.php' );

		$row = $this->get_latest_log_row();

		$this->assertSame( 'AAL Multi B', $row->object_name );
		$this->assertSame( '2.0.0', $row->object_subtype );
	}

	public function test_missing_file_falls_back_to_file_name() {
		( new AAL_Hook_Plugins() )->hooks_delete_plugin( 'aal-missing/missing.php' );

		$row = $this->get_latest_log_row();

		$this->assertSame( 'aal-missing/missing.php', $row->object_name );
		$this->assertSame( '', $row->object_subtype );
	}
}
