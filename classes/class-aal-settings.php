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
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
		add_action( 'admin_footer', array( &$this, 'admin_footer' ) );
		add_filter( 'plugin_action_links_' . ACTIVITY_LOG_BASE, array( &$this, 'plugin_action_links' ) );

		add_action( 'wp_ajax_aal_reset_items', array( &$this, 'ajax_aal_reset_items' ) );
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
			__( 'Activity Log Settings', 'aryo-activity-log' ), 	// <title> tag
			__( 'Settings', 'aryo-activity-log' ), 			// menu label
			'manage_options', 								// required cap to view this page
			$this->slug, 			// page slug
			array( &$this, 'display_settings_page' )			// callback
		);

		// register scripts & styles, specific for the settings page
		add_action( "admin_print_scripts-{$this->hook}", array( &$this, 'scripts_n_styles' ) );
		// this callback will initialize the settings for AAL
		// add_action( "load-$this->hook", array( $this, 'register_settings' ) );
	}

	/**
	 * Register scripts & styles
	 *
	 * @since 1.0
	 */
	public function scripts_n_styles() {
		wp_enqueue_style( 'aal-settings', plugins_url( 'assets/css/settings.css', ACTIVITY_LOG__FILE__ ) );
	}

	public function register_settings() {
		// If no options exist, create them.
		if ( ! get_option( $this->slug ) ) {
			update_option( $this->slug, apply_filters( 'aal_default_options', array(
				'logs_lifespan' => '30',
				'logs_failed_login' => 'yes',
				'logs_email' => 'yes',
			) ) );
		}

		register_setting( 'aal-options', $this->slug, array( $this, 'validate_options' ) );
		$section = $this->get_setup_section();

		switch ( $section ) {
			case 'general':
				// First, we register a section. This is necessary since all future options must belong to a
				add_settings_section(
					'general_settings_section',			// ID used to identify this section and with which to register options
					__( 'Display Options', 'aryo-activity-log' ),	// Title to be displayed on the administration page
					array( 'AAL_Settings_Fields', 'general_settings_section_header' ),	// Callback used to render the description of the section
					$this->slug		// Page on which to add this section of options
				);

				add_settings_field(
					'logs_lifespan',
					__( 'Keep logs for', 'aryo-activity-log' ),
					array( 'AAL_Settings_Fields', 'number_field' ),
					$this->slug,
					'general_settings_section',
					array(
						'id'      => 'logs_lifespan',
						'page'    => $this->slug,
						'classes' => array( 'small-text' ),
						'type'    => 'number',
						'sub_desc'    => __( 'days.', 'aryo-activity-log' ),
						'desc'    => __( 'Maximum number of days to keep activity log. Leave blank to keep activity log forever (not recommended).', 'aryo-activity-log' ),
					)
				);

				add_settings_field(
					'logs_failed_login',
					__( 'Keep Failed Login Logs', 'aryo-activity-log' ),
					array( 'AAL_Settings_Fields', 'select_field' ),
					$this->slug,
					'general_settings_section',
					array(
						'id'      => 'logs_failed_login',
						'page'    => $this->slug,
						'type'    => 'select',
						'options' => array(
							'yes' => __( 'Keep', 'aryo-activity-log' ),
							'no' => __( "Don't Keep (Not recommended)", 'aryo-activity-log' ),
						),
					)
				);

				add_settings_field(
					'logs_email',
					__( 'Keep Email Logs', 'aryo-activity-log' ),
					array( 'AAL_Settings_Fields', 'select_field' ),
					$this->slug,
					'general_settings_section',
					array(
						'id'      => 'logs_email',
						'page'    => $this->slug,
						'type'    => 'select',
						'options' => array(
							'yes' => __( 'Keep', 'aryo-activity-log' ),
							'no' => __( "Don't Keep", 'aryo-activity-log' ),
						),
					)
				);

				add_settings_field(
					'log_visitor_ip_source',
					__( 'Visitor IP Detected', 'aryo-activity-log' ),
					array( 'AAL_Settings_Fields', 'select_field' ),
					$this->slug,
					'general_settings_section',
					array(
						'id'      => 'log_visitor_ip_source',
						'page'    => $this->slug,
						'type'    => 'select',
						'options' => array(
							'REMOTE_ADDR' => 'REMOTE_ADDR',
							'HTTP_CF_CONNECTING_IP' => 'HTTP_CF_CONNECTING_IP',
							'HTTP_TRUE_CLIENT_IP' => 'HTTP_TRUE_CLIENT_IP',
							'HTTP_CLIENT_IP' => 'HTTP_CLIENT_IP',
							'HTTP_X_FORWARDED_FOR' => 'HTTP_X_FORWARDED_FOR',
							'HTTP_X_FORWARDED' => 'HTTP_X_FORWARDED',
							'HTTP_X_CLUSTER_CLIENT_IP' => 'HTTP_X_CLUSTER_CLIENT_IP',
							'HTTP_FORWARDED_FOR' => 'HTTP_FORWARDED_FOR',
							'HTTP_FORWARDED' => 'HTTP_FORWARDED',
                            'no-collect-ip' => __( 'Do not collect IP', 'aryo-activity-log' ),
						),
						'desc' => __( 'Select the source of the visitor IP address. For example, if you are using Cloudflare, select <code>HTTP_CF_CONNECTING_IP</code>.', 'aryo-activity-log' )
                                . '<br />'
                                . __( '<strong>Please note:</strong> if you choose "Do not collect IP", the IP column will be hidden in the log.', 'aryo-activity-log' ),
					)
				);

				if ( apply_filters( 'aal_allow_option_erase_logs', true ) ) {
					add_settings_field(
						'raw_delete_log_activities',
						__( 'Delete Log Activities', 'aryo-activity-log' ),
						array( 'AAL_Settings_Fields', 'raw_html' ),
						$this->slug,
						'general_settings_section',
						array(
							'html' => sprintf( __( '<a href="%s" id="%s">Reset Database</a>', 'aryo-activity-log' ), add_query_arg( array(
								'action' => 'aal_reset_items',
								'_nonce' => wp_create_nonce( 'aal_reset_items' ),
							), admin_url( 'admin-ajax.php' ) ), 'aal-delete-log-activities' ),
							'desc' => __( 'Warning: Clicking this will delete all activities from the database.', 'aryo-activity-log' ),
						)
					);
				}
				break;

		}
	}

	/**
	 * Returns the current section within AAL's setting pages
	 *
	 * @return string
	 */
	public function get_setup_section() {
		if ( isset( $_REQUEST['aal_section'] ) )
			return strtolower( $_REQUEST['aal_section'] );

		return 'general';
	}

	/**
	 * Prints section tabs within the settings area
	 */
	private function menu_print_tabs() {
		$current_section = $this->get_setup_section();
		$sections = array(
			'general'       => __( 'General', 'aryo-activity-log' ),
		);

		$sections = apply_filters( 'aal_setup_sections', $sections );

		if ( 1 >= count( $sections ) ) {
			return;
		}

		foreach ( $sections as $section_key => $section_caption ) {
			$active = $current_section === $section_key ? 'nav-tab-active' : '';
			$url = add_query_arg( 'aal_section', $section_key );
			echo '<a class="nav-tab ' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $section_caption ) . '</a>';
		}
	}

	public function validate_options( $input ) {
		$options = $this->options; // CTX,L1504

		// @todo some data validation/sanitization should go here
		$output = apply_filters( 'aal_validate_options', $input, $options );

		// merge with current settings
		$output = array_merge( $options, $output );

		return $output;
	}

	public function display_settings_page() {
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap">

			<h1 class="aal-page-title"><?php esc_html_e( 'Activity Log Settings', 'aryo-activity-log' ); ?></h1>
			<?php settings_errors(); ?>
			<h2 class="nav-tab-wrapper"><?php $this->menu_print_tabs(); ?></h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'aal-options' );
				do_settings_sections( $this->slug );
				submit_button();
				?>
			</form>

		</div><!-- /.wrap -->
		<?php
	}

	public function admin_notices() {
		switch ( filter_input( INPUT_GET, 'message' ) ) {
			case 'data_erased':
				printf( '<div class="updated"><p>%s</p></div>', esc_html__( 'All activities have been successfully deleted.', 'aryo-activity-log' ) );
				break;
		}
	}

	public function admin_footer() {
		// TODO: move to a separate file.
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( '#aal-delete-log-activities' ).on( 'click', function( e ) {
					if ( ! confirm( '<?php echo esc_js( __( 'Attention: We are going to DELETE ALL ACTIVITIES from the database. Are you sure you want to do that?', 'aryo-activity-log' ) ); ?>' ) ) {
						e.preventDefault();
					}
				} );
			} );
		</script>
		<?php
	}

	public function ajax_aal_reset_items() {
		if ( ! check_ajax_referer( 'aal_reset_items', '_nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aryo-activity-log' ) );
		}

		AAL_Main::instance()->api->erase_all_items();

		wp_redirect( add_query_arg( array(
				'page' => 'activity-log-settings',
				'message' => 'data_erased',
		), admin_url( 'admin.php' ) ) );
		die();
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
		// Allow other plugins to get AAL's options.
		if ( isset( $this->options ) && is_array( $this->options ) && ! empty( $this->options ) )
			return $this->options;

		return apply_filters( 'aal_options', get_option( $this->slug, array() ) );
	}

	public function slug() {
		return $this->slug;
	}
}

// TODO: Need rewrite this class to useful tool.
final class AAL_Settings_Fields {

	public static function general_settings_section_header() {
		?>
		<p><?php esc_html_e( 'These are some basic settings for Activity Log.', 'aryo-activity-log' ); ?></p>
		<?php
	}

	public static function raw_html( $args ) {
		if ( empty( $args['html'] ) )
			return;

		echo wp_kses_post( $args['html'] );
		if ( ! empty( $args['desc'] ) ) : ?>
			<p class="description"><?php echo wp_kses_post( $args['desc'] ); ?></p>
		<?php endif;
	}

	public static function text_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );

		$args = wp_parse_args( $args, array(
			'classes' => array(),
		) );
		if ( empty( $args['id'] ) || empty( $args['page'] ) )
			return;

		?>
		<input type="text" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="<?php echo esc_attr( implode( ' ', $args['classes'] ) ); ?>" />
		<?php if ( ! empty( $desc ) ) : ?>
		<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif;
	}

	public static function textarea_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );

		$args = wp_parse_args( $args, array(
			'classes' => array(),
			'rows'    => 5,
			'cols'    => 50,
		) );

		if ( empty( $args['id'] ) || empty( $args['page'] ) )
			return;

		?>
		<textarea id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( implode( ' ', $args['classes'] ) ); ?>" rows="<?php echo absint( $args['rows'] ); ?>" cols="<?php echo absint( $args['cols'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>

		<?php if ( ! empty( $desc ) ) : ?>
			<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif;
	}

	public static function number_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );

		$args = wp_parse_args( $args, array(
			'classes' => array(),
			'min' => '1',
			'step' => '1',
			'desc' => '',
		) );
		if ( empty( $args['id'] ) || empty( $args['page'] ) )
			return;

		?>
		<input type="number" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="<?php echo esc_attr( implode( ' ', $args['classes'] ) ); ?>" min="<?php echo esc_attr( $args['min'] ); ?>" step="<?php echo esc_attr( $args['step'] ); ?>" />
		<?php if ( ! empty( $args['sub_desc'] ) ) echo wp_kses_post( $args['sub_desc'] ); ?>
		<?php if ( ! empty( $args['desc'] ) ) : ?>
			<p class="description"><?php echo wp_kses_post( $args['desc'] ); ?></p>
		<?php endif;
	}

	public static function select_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );

		if ( empty( $options ) || empty( $id ) || empty( $page ) )
			return;

		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php printf( '%s[%s]', esc_attr( $page ), esc_attr( $id ) ); ?>">
			<?php foreach ( $options as $name => $label ) : ?>
			<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $name, (string) $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $desc ) ) : ?>
		<p class="description"><?php echo wp_kses_post( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function yesno_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );

		?>
		<label class="tix-yes-no description"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value, true ); ?>> <?php esc_html_e( 'Yes', 'aryo-activity-log' ); ?></label>
		<label class="tix-yes-no description"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="0" <?php checked( $value, false ); ?>> <?php esc_html_e( 'No', 'aryo-activity-log' ); ?></label>

		<?php if ( isset( $args['description'] ) ) : ?>
		<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	private static function _set_name_and_value( &$args ) {
		if ( ! isset( $args['name'] ) ) {
			$args['name'] = sprintf( '%s[%s]', esc_attr( $args['page'] ), esc_attr( $args['id'] ) );
		}

		if ( ! isset( $args['value'] ) ) {
			$args['value'] = AAL_Main::instance()->settings->get_option( $args['id'] );
		}
	}
}
