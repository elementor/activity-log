<?php

class AAL_Test_Theme_Version_Diff extends WP_UnitTestCase {

	public function test_theme_update_stores_version_diff() {
		$theme_slug = 'aal-test-version-theme';
		$theme_dir  = WP_CONTENT_DIR . '/themes/' . $theme_slug;

		wp_mkdir_p( $theme_dir );
		file_put_contents(
			$theme_dir . '/style.css',
			"/*
Theme Name: AAL Test Version Theme
Version: 1.0.0
*/\n"
		);

		$hook = new AAL_Hook_Themes();
		$hook->capture_old_theme_version( true, array( 'theme' => $theme_slug ) );

		file_put_contents(
			$theme_dir . '/style.css',
			"/*
Theme Name: AAL Test Version Theme
Version: 2.0.0
*/\n"
		);

		wp_clean_themes_cache();

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Themes'" );

		$upgrader       = new stdClass();
		$upgrader->skin = new stdClass();
		$upgrader->skin->theme = $theme_slug;

		$hook->hooks_theme_install_or_update(
			$upgrader,
			array(
				'type'   => 'theme',
				'action' => 'update',
			)
		);

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Themes' AND `action` = 'updated'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '1.0.0', '2.0.0' ), $meta['changes']['version'] );

		unlink( $theme_dir . '/style.css' );
		rmdir( $theme_dir );
	}

	public function test_switch_theme_stores_theme_name_diff() {
		$old_slug = 'aal-old-switch-theme';
		$new_slug = 'aal-new-switch-theme';
		$old_dir  = WP_CONTENT_DIR . '/themes/' . $old_slug;
		$new_dir  = WP_CONTENT_DIR . '/themes/' . $new_slug;

		wp_mkdir_p( $old_dir );
		wp_mkdir_p( $new_dir );
		file_put_contents(
			$old_dir . '/style.css',
			"/*
Theme Name: AAL Old Switch Theme
Version: 1.0.0
*/\n"
		);
		file_put_contents(
			$new_dir . '/style.css',
			"/*
Theme Name: AAL New Switch Theme
Version: 1.0.0
*/\n"
		);

		wp_clean_themes_cache();

		$old_theme = wp_get_theme( $old_slug );
		$new_theme = wp_get_theme( $new_slug );
		$new_name  = $new_theme->get( 'Name' );

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'Themes'" );

		$hook = new AAL_Hook_Themes();
		$hook->hooks_switch_theme( $new_name, $new_theme, $old_theme );

		$row = $wpdb->get_row(
			"SELECT meta FROM `{$wpdb->activity_log}`
				WHERE `object_type` = 'Themes' AND `action` = 'activated'
				ORDER BY histid DESC LIMIT 1"
		);

		$this->assertNotEmpty( $row );
		$meta = json_decode( $row->meta, true );
		$this->assertSame(
			array( 'AAL Old Switch Theme', 'AAL New Switch Theme' ),
			$meta['changes']['theme']
		);

		unlink( $old_dir . '/style.css' );
		rmdir( $old_dir );
		unlink( $new_dir . '/style.css' );
		rmdir( $new_dir );
	}
}
