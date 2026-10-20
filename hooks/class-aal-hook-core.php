<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Core extends AAL_Hook_Base {

	public function core_updated_successfully( $new_version ) {
		global $pagenow;

		// Auto updated
		if ( 'update-core.php' !== $pagenow ) {
			$object_name = 'WordPress Auto Updated';
		} else {
			$object_name = 'WordPress Updated';
		}

		$log_args = array(
			'action'      => 'updated',
			'object_type' => 'Core',
			'object_id'   => 0,
			'object_name' => $object_name,
		);

		$diff = AAL_Changes::diff(
			array( 'version' => $GLOBALS['wp_version'] ),
			array( 'version' => $new_version )
		);
		if ( ! empty( $diff ) ) {
			$log_args['meta'] = $diff;
		}

		aal_insert_log( $log_args );
	}

	public function hooks_auto_major_update_settings( $option, $value, $old_value ) {
		if ( 'enabled' === $value ) {
			$action = 'auto_update_enabled';
		} else {
			$action = 'auto_update_disabled';
		}

		aal_insert_log(
			array(
				'action' => $action,
				'object_type' => 'Core',
				'object_name' => 'All New Versions'
			)
		);
	}

	public function __construct() {
		add_action( '_core_updated_successfully', array( &$this, 'core_updated_successfully' ) );
		add_action( 'update_site_option_auto_update_core_major', [ $this, 'hooks_auto_major_update_settings' ], 10, 3 );

		parent::__construct();
	}
	
}