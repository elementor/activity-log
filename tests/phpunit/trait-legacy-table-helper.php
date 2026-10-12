<?php

trait AAL_Legacy_Table_Helper {

	private function reset_table( $legacy ) {
		global $wpdb;

		wp_cache_flush();
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->activity_log}`" );
		delete_option( 'activity_log_db_version' );
		delete_option( 'aal_manual_db_upgrade' );
		delete_transient( 'aal_upgrade_failed' );

		if ( $legacy ) {
			$wpdb->query(
				"CREATE TABLE `{$wpdb->activity_log}` (
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
					PRIMARY KEY (`histid`)
				) {$wpdb->get_charset_collate()}"
			);
		}
	}

	private function create_tables() {
		$method = new ReflectionMethod( 'AAL_Maintenance', '_create_tables' );
		$method->setAccessible( true );
		$method->invoke( null );
	}

	private function restore_table() {
		$this->reset_table( false );
		$this->create_tables();
	}

	private function has_new_columns() {
		global $wpdb;

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$wpdb->activity_log}`" );

		return empty( array_diff( array( 'request_source', 'meta' ), $columns ) );
	}
}
