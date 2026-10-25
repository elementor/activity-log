<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AAL_Hook_File_Editor extends AAL_Hook_Base {

	/**
	 * Pending file editor context captured before core saves the file.
	 *
	 * @var array|null
	 */
	private $pending = null;

	/**
	 * @param array $args Request arguments (typically $_POST).
	 */
	public function capture_file_hash_before_edit_from_post( $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return;
		}

		$context = $this->resolve_file_editor_context( $args );
		if ( null === $context || ! is_file( $context['real_file'] ) ) {
			return;
		}

		$this->pending = array(
			'object_type'   => $context['object_type'],
			'object_name'   => $context['object_name'],
			'relative_file' => $context['relative_file'],
			'real_file'     => $context['real_file'],
			'hash_before'   => md5_file( $context['real_file'] ),
		);
	}

	public function capture_file_hash_before_edit() {
		if ( empty( $_POST ) ) {
			return;
		}

		$this->capture_file_hash_before_edit_from_post( wp_unslash( $_POST ) );
	}

	public function capture_file_hash_on_editor_load() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( empty( $_POST ) ) {
			return;
		}

		$this->capture_file_hash_before_edit_from_post( wp_unslash( $_POST ) );
	}

	public function log_file_editor_change_on_shutdown() {
		if ( empty( $this->pending ) ) {
			return;
		}

		$pending = $this->pending;

		if ( ! is_file( $pending['real_file'] ) ) {
			return;
		}

		$hash_after = md5_file( $pending['real_file'] );
		if ( $hash_after === $pending['hash_before'] ) {
			return;
		}

		aal_insert_log(
			array(
				'action'         => 'file_updated',
				'object_type'    => $pending['object_type'],
				'object_name'    => $pending['object_name'],
				'object_subtype' => $pending['relative_file'],
				'object_id'      => 0,
			)
		);
	}

	/**
	 * Resolve theme/plugin file editor target the same way core does in wp_edit_theme_plugin_file().
	 *
	 * @param array $args Request arguments.
	 * @return array|null
	 */
	private function resolve_file_editor_context( $args ) {
		if ( empty( $args['file'] ) ) {
			return null;
		}

		if ( 0 !== validate_file( $args['file'] ) ) {
			return null;
		}

		if ( ! isset( $args['newcontent'] ) ) {
			return null;
		}

		$file = $args['file'];

		if ( ! empty( $args['plugin'] ) ) {
			$plugin = $args['plugin'];

			if ( ! current_user_can( 'edit_plugins' ) ) {
				return null;
			}

			if ( ! array_key_exists( $plugin, get_plugins() ) ) {
				return null;
			}

			if ( 0 !== validate_file( $file, get_plugin_files( $plugin ) ) ) {
				return null;
			}

			$real_file = WP_PLUGIN_DIR . '/' . $file;

			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
			$object_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin;

			return array(
				'object_type'   => 'Plugins',
				'object_name'   => $object_name,
				'relative_file' => $file,
				'real_file'     => $real_file,
			);
		}

		if ( ! empty( $args['theme'] ) ) {
			$stylesheet = $args['theme'];

			if ( 0 !== validate_file( $stylesheet ) ) {
				return null;
			}

			if ( ! current_user_can( 'edit_themes' ) ) {
				return null;
			}

			$theme = wp_get_theme( $stylesheet );
			if ( ! $theme->exists() ) {
				return null;
			}

			if ( $theme->errors() && 'theme_no_stylesheet' === $theme->errors()->get_error_code() ) {
				return null;
			}

			$editable_extensions = wp_get_theme_file_editable_extensions( $theme );

			$allowed_files = array();
			foreach ( $editable_extensions as $type ) {
				switch ( $type ) {
					case 'php':
						$allowed_files = array_merge( $allowed_files, $theme->get_files( 'php', -1 ) );
						break;
					case 'css':
						$style_files                = $theme->get_files( 'css', -1 );
						$allowed_files['style.css'] = $style_files['style.css'];
						$allowed_files              = array_merge( $allowed_files, $style_files );
						break;
					default:
						$allowed_files = array_merge( $allowed_files, $theme->get_files( $type, -1 ) );
						break;
				}
			}

			if ( 0 !== validate_file( $file, array_keys( $allowed_files ) ) ) {
				return null;
			}

			$real_file = $theme->get_stylesheet_directory() . '/' . $file;

			return array(
				'object_type'   => 'Themes',
				'object_name'   => $theme->get( 'Name' ),
				'relative_file' => $file,
				'real_file'     => $real_file,
			);
		}

		return null;
	}

	public function __construct() {
		add_action( 'wp_ajax_edit-theme-plugin-file', array( $this, 'capture_file_hash_before_edit' ), 0 );
		add_action( 'load-theme-editor.php', array( $this, 'capture_file_hash_on_editor_load' ) );
		add_action( 'load-plugin-editor.php', array( $this, 'capture_file_hash_on_editor_load' ) );
		add_action( 'shutdown', array( $this, 'log_file_editor_change_on_shutdown' ) );

		parent::__construct();
	}

}
