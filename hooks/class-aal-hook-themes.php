<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Themes extends AAL_Hook_Base {

	private $old_versions = array();

	public function hooks_switch_theme( $new_name, WP_Theme $new_theme, $old_theme = null ) {
		$log_args = array(
			'action'         => 'activated',
			'object_type'    => 'Themes',
			'object_subtype' => $new_theme->get_stylesheet(),
			'object_id'      => 0,
			'object_name'    => $new_name,
		);

		if ( $old_theme instanceof WP_Theme ) {
			$log_args['meta'] = AAL_Changes::diff(
				array( 'theme' => $old_theme->get( 'Name' ) ),
				array( 'theme' => $new_name )
			);
		}

		aal_insert_log( $log_args );
	}

	public function hooks_theme_customizer_modified( WP_Customize_Manager $obj ) {
		$aal_args = array(
			'action'         => 'updated',
			'object_type'    => 'Themes',
			'object_subtype' => $obj->theme()->display( 'Name' ),
			'object_id'      => 0,
			'object_name'    => 'Theme Customizer',
		);

		if ( 'customize_preview_init' === current_filter() )
			$aal_args['action'] = 'accessed';

		aal_insert_log( $aal_args );
	}

	public function hooks_theme_deleted() {
		$backtrace_history = debug_backtrace();

		$delete_theme_call = null;
		foreach ( $backtrace_history as $call ) {
			if ( isset( $call['function'] ) && 'delete_theme' === $call['function'] ) {
				$delete_theme_call = $call;
				break;
			}
		}

		if ( empty( $delete_theme_call ) )
			return;

		$name = $delete_theme_call['args'][0];
		
		aal_insert_log(
			array(
				'action' => 'deleted',
				'object_type' => 'Themes',
				'object_name' => $name,
			)
		);
	}

	/**
	 * @param Theme_Upgrader $upgrader
	 * @param array $extra
	 */
	public function hooks_theme_install_or_update( $upgrader, $extra ) {
		if ( ! isset( $extra['type'] ) || 'theme' !== $extra['type'] )
			return;
		
		if ( 'install' === $extra['action'] ) {
			$slug = $upgrader->theme_info();
			if ( ! $slug )
				return;

			wp_clean_themes_cache();
			$theme   = wp_get_theme( $slug );
			$name    = $theme->name;
			$version = $theme->version;

			aal_insert_log(
				array(
					'action' => 'installed',
					'object_type' => 'Themes',
					'object_name' => $name,
					'object_subtype' => $version,
				)
			);
		}
		
		if ( 'update' === $extra['action'] ) {
			if ( isset( $extra['bulk'] ) && true == $extra['bulk'] )
				$slugs = $extra['themes'];
			else
				$slugs = array( $upgrader->skin->theme );

			foreach ( $slugs as $slug ) {
				$theme      = wp_get_theme( $slug );
				$stylesheet = $theme['Stylesheet Dir'] . '/style.css';
				$theme_data = get_file_data( $stylesheet, array( 'Version' => 'Version' ) );
				
				$name    = $theme['Name'];
				$version = $theme_data['Version'];
				$old     = isset( $this->old_versions[ $slug ] ) ? $this->old_versions[ $slug ] : '';

				$log_args = array(
					'action' => 'updated',
					'object_type' => 'Themes',
					'object_name' => $name,
					'object_subtype' => $version,
				);

				if ( ! empty( $old ) ) {
					$log_args['meta'] = AAL_Changes::diff( array( 'version' => $old ), array( 'version' => $version ) );
				}

				aal_insert_log( $log_args );
			}
		}
	}

	public function capture_old_theme_version( $response, $hook_extra ) {
		if ( ! empty( $hook_extra['theme'] ) ) {
			$this->old_versions[ $hook_extra['theme'] ] = wp_get_theme( $hook_extra['theme'] )->get( 'Version' );
		}

		return $response;
	}

	private function add_log_theme( $action, $theme_slug ) {
		$theme = wp_get_theme( $theme_slug );

		$name = isset( $theme['Name'] ) ? $theme['Name'] : $theme_slug;
		$version = isset( $theme['Version'] ) ? $theme['Version'] : '';

		aal_insert_log(
			array(
				'action' => $action,
				'object_type' => 'Themes',
				'object_name' => $name,
				'object_subtype' => $version,
			)
		);

	}

	public function hooks_auto_update_settings( $option, $value, $old_value ) {
		$enabled_themes = array_diff( $value, $old_value );
		$disabled_themes = array_diff( $old_value, $value );

		foreach ( $disabled_themes as $theme ) {
			$this->add_log_theme( 'auto_update_disabled', $theme );
		}

		foreach ( $enabled_themes as $theme ) {
			$this->add_log_theme( 'auto_update_enabled', $theme );
		}
	}

	public function __construct() {
		add_action( 'switch_theme', array( &$this, 'hooks_switch_theme' ), 10, 3 );
		add_action( 'delete_site_transient_update_themes', array( &$this, 'hooks_theme_deleted' ) );
		add_action( 'upgrader_process_complete', array( &$this, 'hooks_theme_install_or_update' ), 10, 2 );

		add_filter( 'upgrader_pre_install', array( $this, 'capture_old_theme_version' ), 10, 2 );

		// Theme customizer
		add_action( 'customize_save', array( &$this, 'hooks_theme_customizer_modified' ) );
		//add_action( 'customize_preview_init', array( &$this, 'hooks_theme_customizer_modified' ) );

		add_action( 'update_site_option_auto_update_themes', [ $this, 'hooks_auto_update_settings' ], 10, 3 );

		parent::__construct();
	}

}