<?php

class AAL_Test_Hook_File_Editor extends WP_UnitTestCase {

	private $plugin_file = 'aal-file-editor-test/aal-file-editor-test.php';

	private $plugin_path;

	public function set_up() {
		parent::set_up();

		$this->plugin_path = WP_PLUGIN_DIR . '/' . $this->plugin_file;
		wp_mkdir_p( dirname( $this->plugin_path ) );
		file_put_contents(
			$this->plugin_path,
			"<?php\n/**\n * Plugin Name: AAL File Editor Test\n * Version: 1.0.0\n */\n"
		);

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}

	public function tear_down() {
		if ( file_exists( $this->plugin_path ) ) {
			unlink( $this->plugin_path );
		}
		$dir = dirname( $this->plugin_path );
		if ( is_dir( $dir ) ) {
			rmdir( $dir );
		}

		parent::tear_down();
	}

	public function test_changed_plugin_file_logs_relative_path() {
		global $wpdb;

		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Plugins' AND `action` = 'file_updated'" );

		$hook = new AAL_Hook_File_Editor();
		$hook->capture_file_hash_before_edit_from_post(
			array(
				'plugin'     => $this->plugin_file,
				'file'       => $this->plugin_file,
				'newcontent' => "<?php\n// changed\n",
			)
		);

		file_put_contents( $this->plugin_path, "<?php\n// changed\n" );
		$hook->log_file_editor_change_on_shutdown();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '`
					WHERE `action` = %s
						AND `object_type` = %s
						AND `object_name` = %s
						AND `object_subtype` = %s
					ORDER BY `histid` DESC
					LIMIT 1',
				'file_updated',
				'Plugins',
				'AAL File Editor Test',
				$this->plugin_file
			)
		);

		$this->assertNotEmpty( $row );
	}

	public function test_unchanged_plugin_file_logs_nothing() {
		global $wpdb;

		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Plugins' AND `action` = 'file_updated'" );

		$original = file_get_contents( $this->plugin_path );

		$hook = new AAL_Hook_File_Editor();
		$hook->capture_file_hash_before_edit_from_post(
			array(
				'plugin'     => $this->plugin_file,
				'file'       => $this->plugin_file,
				'newcontent' => "<?php\n// broken\n",
			)
		);

		file_put_contents( $this->plugin_path, "<?php\n// broken\n" );
		file_put_contents( $this->plugin_path, $original );
		$hook->log_file_editor_change_on_shutdown();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->activity_log . '`
					WHERE `action` = %s AND `object_type` = %s',
				'file_updated',
				'Plugins'
			)
		);

		$this->assertSame( 0, $count );
	}
}
