<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class HS_Maintenance {
	
	public function activated() {
		/** @var $wpdb wpdb */
		global $wpdb;

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}history_timeline` (
					  `histid` int(11) NOT NULL AUTO_INCREMENT,
					  `userCaps` varchar(70) NOT NULL DEFAULT 'guest',
					  `action` varchar(255) NOT NULL,
					  `object_type` varchar(255) NOT NULL,
					  `object_subtype` varchar(255) NOT NULL DEFAULT '',
					  `object_name` varchar(255) NOT NULL,
					  `object_id` int(11) NOT NULL DEFAULT '0',
					  `user_id` int(11) NOT NULL DEFAULT '0',
					  `histIP` varchar(55) NOT NULL DEFAULT '127.0.0.1',
					  `histTime` int(11) NOT NULL DEFAULT '0',
					  PRIMARY KEY (`histid`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;"
		);
	}
	
	public function __construct() {
		add_action( 'activate_' . plugin_basename( __FILE__ ), array( &$this, 'activated' ) );
	}
}
new HS_Maintenance();
