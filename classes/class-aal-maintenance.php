<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Maintenance {

	const TARGET_DB_VERSION = '1.1';

	private static $schema_ready_cache = array();

	public static function activate( $network_wide ) {
		global $wpdb;

		if ( function_exists( 'is_multisite') && is_multisite() && $network_wide ) {
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::_create_tables();
				restore_current_blog();
			}
		} else {
			self::_create_tables();
		}

		wp_clear_scheduled_hook( 'aal/maintenance/clear_old_items' );
	}

	public static function uninstall() {
		global $wpdb;

		if ( function_exists( 'is_multisite') && is_multisite() ) {
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs};" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::_remove_tables();
				restore_current_blog();
			}
		} else {
			self::_remove_tables();
		}

		wp_clear_scheduled_hook( 'aal/maintenance/clear_old_items' );
	}

	public static function mu_new_blog_installer( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		if ( is_plugin_active_for_network( ACTIVITY_LOG_BASE ) ) {
			switch_to_blog( $blog_id );
			self::_create_tables();
			restore_current_blog();
		}
	}

	public static function mu_delete_blog( $blog_id, $drop ) {
		switch_to_blog( $blog_id );
		self::_remove_tables();
		restore_current_blog();
	}

	/**
	 * Run pending upgrade steps on admin_init only.
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'activity_log_db_version', '1.0' );

		if ( version_compare( $installed_version, self::TARGET_DB_VERSION, '>=' ) ) {
			return;
		}

		$last_failure = get_transient( 'aal_upgrade_failed' );
		if ( $last_failure ) {
			return;
		}

		$steps = array(
			'1.1' => 'upgrade_to_1_1',
		);

		foreach ( $steps as $version => $method ) {
			if ( version_compare( $installed_version, $version, '>=' ) ) {
				continue;
			}

			$success = call_user_func( array( __CLASS__, $method ) );

			if ( ! $success ) {
				set_transient( 'aal_upgrade_failed', $version, HOUR_IN_SECONDS );
				return;
			}

			update_option( 'activity_log_db_version', $version );
			$installed_version = $version;
		}

		delete_transient( 'aal_upgrade_failed' );
	}

	/**
	 * @return bool
	 */
	private static function upgrade_to_1_1() {
		global $wpdb;

		$table = $wpdb->prefix . 'aryo_activity_log';

		$column_exists = $wpdb->get_results(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'request_source' )
		);

		if ( ! empty( $column_exists ) ) {
			return true;
		}

		$wpdb->query( "ALTER TABLE `{$table}` ADD `request_source` varchar(191) NOT NULL DEFAULT '' AFTER `hist_time`" );

		$verify = $wpdb->get_results(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'request_source' )
		);

		if ( empty( $verify ) ) {
			return false;
		}

		$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `request_source` (`request_source`)" );

		return true;
	}

	/**
	 * Check if the schema is at or above a required version.
	 *
	 * @param string $min_version
	 * @return bool
	 */
	public static function is_schema_ready( $min_version = '1.1' ) {
		if ( isset( self::$schema_ready_cache[ $min_version ] ) ) {
			return self::$schema_ready_cache[ $min_version ];
		}

		$installed = get_option( 'activity_log_db_version', '1.0' );
		$ready = version_compare( $installed, $min_version, '>=' );

		self::$schema_ready_cache[ $min_version ] = $ready;

		return $ready;
	}

	/**
	 * Admin notice when upgrade is stuck.
	 */
	public static function upgrade_notice() {
		$failed_version = get_transient( 'aal_upgrade_failed' );
		if ( ! $failed_version ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'activity-log' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Activity Log: Database upgrade pending. Some features may be unavailable until the upgrade completes. It will retry automatically.', 'aryo-activity-log' )
		);
	}

	protected static function _create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}aryo_activity_log` (
					  `histid` int(11) NOT NULL AUTO_INCREMENT,
					  `user_caps` varchar(70) NOT NULL DEFAULT 'guest',
					  `action` varchar(255) NOT NULL,
					  `object_type` varchar(255) NOT NULL,
					  `object_subtype` varchar(255) NOT NULL DEFAULT '',
					  `object_name` varchar(255) NOT NULL,
					  `object_id` int(11) NOT NULL DEFAULT '0',
					  `user_id` int(11) NOT NULL DEFAULT '0',
					  `hist_ip` varchar(55) NOT NULL DEFAULT '127.0.0.1',
					  `hist_time` int(11) NOT NULL DEFAULT '0',
					  `request_source` varchar(191) NOT NULL DEFAULT '',
					  PRIMARY KEY (`histid`),
						KEY `user_caps` (`user_caps`),
						KEY `action` (`action`),
						KEY `object_type` (`object_type`),
						KEY `object_subtype` (`object_subtype`),
						KEY `object_name` (`object_name`),
						KEY `user_id` (`user_id`),
						KEY `hist_ip` (`hist_ip`),
						KEY `hist_time` (`hist_time`),
						KEY `request_source` (`request_source`)
				) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role instanceof WP_Role && ! $admin_role->has_cap( 'view_all_aryo_activity_log' ) )
			$admin_role->add_cap( 'view_all_aryo_activity_log' );
		
		update_option( 'activity_log_db_version', self::TARGET_DB_VERSION );
	}

	protected static function _remove_tables() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}aryo_activity_log`;" );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role && $admin_role->has_cap( 'view_all_aryo_activity_log' ) )
			$admin_role->remove_cap( 'view_all_aryo_activity_log' );

		delete_option( 'activity_log_db_version' );
	}
}

register_activation_hook( ACTIVITY_LOG_BASE, array( 'AAL_Maintenance', 'activate' ) );
register_uninstall_hook( ACTIVITY_LOG_BASE, array( 'AAL_Maintenance', 'uninstall' ) );

// MU installer for new blog.
add_action( 'wpmu_new_blog', array( 'AAL_Maintenance', 'mu_new_blog_installer' ), 10, 6 );
// MU Uninstall for delete blog.
add_action( 'delete_blog', array( 'AAL_Maintenance', 'mu_delete_blog' ), 10, 2 );

// Runtime upgrade (admin only) + notice.
add_action( 'admin_init', array( 'AAL_Maintenance', 'maybe_upgrade' ) );
add_action( 'admin_notices', array( 'AAL_Maintenance', 'upgrade_notice' ) );
