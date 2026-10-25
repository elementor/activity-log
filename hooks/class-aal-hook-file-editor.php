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
		if ( empty( $args['file'] ) || ! isset( $args['newcontent'] ) || 0 !== validate_file( $args['file'] ) ) {
			return null;
		}

		if ( ! empty( $args['plugin'] ) ) {
			if ( ! current_user_can( 'edit_plugins' ) || 0 !== validate_file( $args['plugin'] ) || ! is_file( WP_PLUGIN_DIR . '/' . $args['plugin'] ) ) {
				return null;
			}

			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $args['plugin'], false, false );
			$object_type = 'Plugins';
			$object_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $args['plugin'];
			$base_dir    = WP_PLUGIN_DIR;
		} elseif ( ! empty( $args['theme'] ) ) {
			if ( ! current_user_can( 'edit_themes' ) || 0 !== validate_file( $args['theme'] ) ) {
				return null;
			}

			$theme = wp_get_theme( $args['theme'] );
			if ( ! $theme->exists() ) {
				return null;
			}

			$object_type = 'Themes';
			$object_name = $theme->get( 'Name' );
			$base_dir    = $theme->get_stylesheet_directory();
		} else {
			return null;
		}

		$real_base = realpath( $base_dir );
		$real_file = realpath( $base_dir . '/' . $args['file'] );

		// Only files inside the plugins folder or the theme folder may be hashed.
		if ( false === $real_base || false === $real_file || 0 !== strpos( $real_file, trailingslashit( $real_base ) ) ) {
			return null;
		}

		return array(
			'object_type'   => $object_type,
			'object_name'   => $object_name,
			'relative_file' => $args['file'],
			'real_file'     => $real_file,
		);
	}

	public function __construct() {
		add_action( 'wp_ajax_edit-theme-plugin-file', array( $this, 'capture_file_hash_before_edit' ), 0 );
		add_action( 'load-theme-editor.php', array( $this, 'capture_file_hash_before_edit' ) );
		add_action( 'load-plugin-editor.php', array( $this, 'capture_file_hash_before_edit' ) );
		add_action( 'shutdown', array( $this, 'log_file_editor_change_on_shutdown' ) );

		parent::__construct();
	}

}
