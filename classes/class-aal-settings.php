<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Settings {
	private $hook;
	public $slug = 'activity-log-settings';
	protected $options;

	public function __construct() {
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_menu', array( &$this, 'action_admin_menu' ), 30 );
		add_action( 'admin_init', array( &$this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . ACTIVITY_LOG_BASE, array( &$this, 'plugin_action_links' ) );
	}

	public function init() {
		$this->options = $this->get_options();
	}

	public function plugin_action_links( $links ) {
		$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=activity-log-page' ), __( 'Activity Log', 'aryo-activity-log' ) );
		array_unshift( $links, $settings_link );

		$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=activity-log-settings' ), __( 'Settings', 'aryo-activity-log' ) );
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Register the settings page
	 *
	 * @since 1.0
	 */
	public function action_admin_menu() {
		$this->hook = add_submenu_page(
			'activity-log-page',
			__( 'Activity Log Settings', 'aryo-activity-log' ),
			__( 'Settings', 'aryo-activity-log' ),
			'manage_options',
			$this->slug,
			array( &$this, 'display_settings_page' )
		);

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_settings_scripts' ) );
	}

	public function enqueue_settings_scripts( $hook ) {
		if ( ! isset( $this->hook ) || $hook !== $this->hook ) {
			return;
		}

		$build_dir  = plugin_dir_path( ACTIVITY_LOG__FILE__ ) . 'assets/js/admin/build/';
		$asset_file = $build_dir . 'settings-index.asset.php';

		if ( ! file_exists( $asset_file ) || ! file_exists( $build_dir . 'settings-index.js' ) ) {
			wp_admin_notice(
				esc_html__( 'Activity Log: admin assets are not built. Run `npm run build` in the plugin directory.', 'aryo-activity-log' ),
				array( 'type' => 'error' )
			);

			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'aal-settings-app',
			plugins_url( 'assets/js/admin/build/settings-index.js', ACTIVITY_LOG__FILE__ ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		$bootstrap = array(
			'restBase'      => rest_url( AAL_REST::NAMESPACE_V1 ),
			'settingsNonce' => wp_create_nonce( 'aal_settings' ),
		);

		wp_add_inline_script(
			'aal-settings-app',
			'window.aalAdmin = ' . wp_json_encode( $bootstrap ) . ';',
			'before'
		);

		wp_set_script_translations( 'aal-settings-app', 'aryo-activity-log' );
	}

	public function register_settings() {
		if ( ! get_option( $this->slug ) ) {
			update_option( $this->slug, apply_filters( 'aal_default_options', array(
				'logs_lifespan' => '30',
				'logs_failed_login' => 'yes',
				'logs_email' => 'yes',
			) ) );
		}
	}

	public function validate_options( $input ) {
		$options = $this->options;

		$output = apply_filters( 'aal_validate_options', $input, $options );

		$output = array_merge( $options, $output );

		return $output;
	}

	public function display_settings_page() {
		?>
		<div class="wrap">
			<h1 class="aal-page-title"><?php esc_html_e( 'Activity Log Settings', 'aryo-activity-log' ); ?></h1>
			<div id="aal-settings-root"></div>
		</div>
		<?php
	}

	public function get_option( $key = '' ) {
		$settings = $this->get_options();
		return ! empty( $settings[ $key ] ) ? $settings[ $key ] : false;
	}

	/**
	 * Returns all options
	 *
	 * @since 2.0.7
	 * @return array
	 */
	public function get_options() {
		if ( isset( $this->options ) && is_array( $this->options ) && ! empty( $this->options ) )
			return $this->options;

		return apply_filters( 'aal_options', get_option( $this->slug, array() ) );
	}

	public function slug() {
		return $this->slug;
	}
}
