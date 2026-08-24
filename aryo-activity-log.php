<?php
/*
Plugin Name: Activity Log
Plugin URI: https://activitylog.io/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
Description: This top rated Activity Log plugin helps you monitor & log all changes and actions on your WordPress site, so you can remain secure and organized.
Author: Activity Log Team
Author URI: https://activitylog.io/?utm_source=wp-plugins&utm_campaign=author-uri&utm_medium=wp-dash
Version: 2.13.0
Text Domain: aryo-activity-log
License: GPLv2 or later

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'ACTIVITY_LOG__FILE__', __FILE__ );
define( 'ACTIVITY_LOG_BASE', plugin_basename( ACTIVITY_LOG__FILE__ ) );

include( 'classes/class-aal-maintenance.php' );
include( 'classes/class-aal-activity-log-list-table.php' );
include( 'classes/class-aal-admin-ui.php' );
include( 'classes/class-aal-settings.php' );
include( 'classes/class-aal-api.php' );
include( 'classes/class-aal-hooks.php' );
include( 'classes/class-aal-export.php' );
include( 'classes/class-aal-privacy.php' );
include( 'classes/abstract-class-aal-exporter.php' );

// Integrations
include( 'classes/class-aal-integration-woocommerce.php' );

// Probably we should put this in a separate file
final class AAL_Main {

	/**
	 * @var AAL_Main The one true AAL_Main
	 * @since 2.0.5
	 */
	private static $_instance = null;

	/**
	 * @var AAL_Admin_Ui
	 * @since 1.0.0
	 */
	public $ui;

	/**
	 * @var AAL_Hooks
	 * @since 1.0.1
	 */
	public $hooks;

	/**
	 * @var AAL_Settings
	 * @since 1.0.0
	 */
	public $settings;

	/**
	 * @var AAL_API
	 * @since 2.0.5
	 */
	public $api;

	/**
	 * Construct
	 */
	protected function __construct() {
		global $wpdb;

		$this->ui            = new AAL_Admin_Ui();
		$this->hooks         = new AAL_Hooks();
		$this->settings      = new AAL_Settings();
		$this->api           = new AAL_API();

		new AAL_Export();
		new AAL_Privacy();

		// set up our DB name
		$wpdb->activity_log = $wpdb->prefix . 'aryo_activity_log';
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 2.0.7
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %s: Class name. */
					__( 'Cloning instances of the singleton "%s" class is forbidden.', 'aryo-activity-log' ),
					get_class( $this )
				)
			),
			'2.0.7'
		);
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 2.0.7
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %s: Class name. */
					__( 'Unserializing instances of the singleton "%s" class is forbidden.', 'aryo-activity-log' ),
					get_class( $this )
				)
			),
			'2.0.7'
		);
	}

	/**
	 * @return AAL_Main
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new AAL_Main();
		return self::$_instance;
	}
}

AAL_Main::instance();
