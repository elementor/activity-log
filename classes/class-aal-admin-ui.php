<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Admin_Ui {

	protected $_screens = array();

	public function create_admin_menu() {
		$menu_capability = current_user_can( 'view_all_aryo_activity_log' ) ? 'view_all_aryo_activity_log' : apply_filters( 'aal_menu_page_capability', 'edit_pages' );

		$this->_screens['main'] = add_menu_page( _x( 'Activity Log', 'Page and Menu Title', 'aryo-activity-log' ), _x( 'Activity Log', 'Page and Menu Title', 'aryo-activity-log' ), $menu_capability, 'activity-log-page', array( &$this, 'activity_log_page_func' ), '', '2.1' );

		add_action( 'load-' . $this->_screens['main'], array( &$this, 'on_admin_page_load' ) );
	}

	public function on_admin_page_load() {
		do_action( 'aal_admin_page_load', null );
	}

	public function activity_log_page_func() {
		?>
		<div class="wrap">
			<h1 class="aal-page-title"><?php echo esc_html_x( 'Activity Log', 'Page and Menu Title', 'aryo-activity-log' ); ?></h1>
			<div id="aal-admin-root"></div>
		</div>
		<?php
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( ! isset( $this->_screens['main'] ) || $hook !== $this->_screens['main'] ) {
			return;
		}

		$build_dir = plugin_dir_path( ACTIVITY_LOG__FILE__ ) . 'assets/js/admin/build/';
		$asset_file = $build_dir . 'index.asset.php';

		if ( ! file_exists( $asset_file ) || ! file_exists( $build_dir . 'index.js' ) ) {
			wp_admin_notice(
				esc_html__( 'Activity Log: admin assets are not built. Run `npm run build` in the plugin directory.', 'aryo-activity-log' ),
				array( 'type' => 'error' )
			);

			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'aal-admin-app',
			plugins_url( 'assets/js/admin/build/index.js', ACTIVITY_LOG__FILE__ ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		$collect_ip = 'no-collect-ip' !== AAL_Main::instance()->settings->get_option( 'log_visitor_ip_source' );

		$export_url = menu_page_url( 'activity-log-page', false );

		$bootstrap = array(
			'restBase'          => rest_url( AAL_REST::NAMESPACE_V1 ),
			'perPage'           => 50,
			'collectIp'         => $collect_ip,
			'canInstallPlugins' => current_user_can( 'install_plugins' ),
			'exportUrl'         => $export_url,
			'exportNonce'       => wp_create_nonce( 'aal_actions_nonce' ),
			'initialQuery'      => $this->get_initial_query(),
		);

		wp_add_inline_script(
			'aal-admin-app',
			'window.aalAdmin = ' . wp_json_encode( $bootstrap ) . ';',
			'before'
		);

		wp_set_script_translations( 'aal-admin-app', 'aryo-activity-log' );
	}

	private function get_initial_query() {
		$params = array(
			'dateshow', 'capshow', 'usershow', 'typeshow',
			'showaction', 'sourceshow', 'filter_ip', 's',
		);

		$query = array();
		foreach ( $params as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$query[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		return $query;
	}

	public function print_admin_head_styles() {
		echo '<style>
			#adminmenu #toplevel_page_activity-log-page div.wp-menu-image:before { content: "\f321"; }
			h1.aal-page-title:before { content: "\f321"; font: 400 25px/1 dashicons !important; speak: none; color: #030303; display: inline-block; padding-inline-end: .2em; vertical-align: -18%; }
		</style>';
	}

	public function __construct() {
		add_action( 'admin_menu', array( &$this, 'create_admin_menu' ), 20 );
		add_action( 'admin_head', array( &$this, 'print_admin_head_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_admin_scripts' ) );

		add_action( 'wp_ajax_aal_promotion_dismiss', [ $this, 'ajax_aal_promotion_dismiss' ] );
		add_action( 'wp_ajax_aal_promotion_campaign', [ $this, 'ajax_aal_promotion_campaign' ] );
	}

	public function ajax_aal_promotion_dismiss() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aal_promotion' ) ) {
			wp_send_json_error();
		}

		if ( empty( $_POST['promotion_id'] ) ) {
			wp_send_json_error();
		}

		$promotion_id = sanitize_key( $_POST['promotion_id'] );

		update_user_meta( get_current_user_id(), "_aal_promotion_{$promotion_id}_notice_viewed", 'true'  );

		wp_send_json_success();
	}

	public function ajax_aal_promotion_campaign() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aal_promotion' ) ) {
			wp_send_json_error();
		}

		if ( empty( $_POST['promotion_id'] ) ) {
			wp_send_json_error();
		}

		if ( 'emails' === $_POST['promotion_id'] ) {
			$campaign_data = [
				'source' => 'sm-aal-install',
				'campaign' => 'sm-plg',
				'medium' => 'wp-dash',
			];

			set_transient( 'elementor_site_mailer_campaign', $campaign_data, 30 * DAY_IN_SECONDS );
		}

		if ( 'media' === $_POST['promotion_id'] ) {
			$campaign_data = [
				'source' => 'io-aal-install',
				'campaign' => 'io-plg',
				'medium' => 'wp-dash',
			];

			set_transient( 'elementor_image_optimization_campaign', $campaign_data, 30 * DAY_IN_SECONDS );
		}

		wp_send_json_success();
	}
}
