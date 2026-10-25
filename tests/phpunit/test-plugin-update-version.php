<?php

class AAL_Test_Plugin_Update_Version extends WP_UnitTestCase {

	public function test_plugin_update_stores_version_diff() {
		$plugin_file = 'aal-test-version/aal-test-version.php';
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		wp_mkdir_p( dirname( $plugin_path ) );
		file_put_contents(
			$plugin_path,
			"<?php\n/**\n * Plugin Name: AAL Test Version\n * Version: 1.0.0\n */\n"
		);

		$hook = new AAL_Hook_Plugins();
		$hook->capture_old_plugin_version( true, array( 'plugin' => $plugin_file ) );

		file_put_contents(
			$plugin_path,
			"<?php\n/**\n * Plugin Name: AAL Test Version\n * Version: 2.0.0\n */\n"
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Plugins'" );

		$upgrader       = new stdClass();
		$upgrader->skin = new stdClass();
		$upgrader->skin->plugin = $plugin_file;

		$hook->hooks_plugin_install_or_update(
			$upgrader,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => $plugin_file,
			)
		);

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Plugins' AND `action` = 'updated'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '1.0.0', '2.0.0' ), $meta['changes']['version'] );

		unlink( $plugin_path );
		rmdir( dirname( $plugin_path ) );
	}
}
