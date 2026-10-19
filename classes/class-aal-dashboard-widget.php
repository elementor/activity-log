<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAL_Dashboard_Widget {

	const WIDGET_ID = 'aal_recent_activity';

	public function __construct() {
		if ( is_network_admin() ) {
			return;
		}

		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Whether the current user may view activity logs (same rules as the REST API).
	 *
	 * @return bool
	 */
	public function user_can_view_logs() {
		return true === AAL_Main::instance()->rest->check_permissions();
	}

	public function register_widget() {
		if ( ! $this->user_can_view_logs() ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'Recent Activity', 'aryo-activity-log' ),
			array( $this, 'render_widget' )
		);
	}

	public function render_widget() {
		echo '<div id="aal-dashboard-widget-root"></div>';
	}

	public function enqueue_scripts( $hook ) {
		if ( 'index.php' !== $hook || ! $this->user_can_view_logs() ) {
			return;
		}

		$build_dir  = plugin_dir_path( ACTIVITY_LOG__FILE__ ) . 'assets/js/admin/build/';
		$asset_file = $build_dir . 'dashboard-index.asset.php';

		if ( ! file_exists( $asset_file ) || ! file_exists( $build_dir . 'dashboard-index.js' ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'aal-dashboard-widget',
			plugins_url( 'assets/js/admin/build/dashboard-index.js', ACTIVITY_LOG__FILE__ ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		$bootstrap = array(
			'perPage'    => 10,
			'viewAllUrl' => admin_url( 'admin.php?page=activity-log-page' ),
		);

		wp_add_inline_script(
			'aal-dashboard-widget',
			'window.aalDashboard = ' . wp_json_encode( $bootstrap ) . ';',
			'before'
		);

		wp_set_script_translations( 'aal-dashboard-widget', 'aryo-activity-log' );
	}
}
