<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Widgets extends AAL_Hook_Base {

	public function hooks_widget_update_callback( $instance, $new_instance, $old_instance, WP_Widget $widget ) {
		$aal_args = array(
			'action'         => 'updated',
			'object_type'    => 'Widget',
			'object_subtype' => 'sidebar_unknown',
			'object_id'      => 0,
			'object_name'    => $widget->id_base,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; WordPress core verifies the widgets nonce.
		if ( empty( $_REQUEST['sidebar'] ) ) {
			return $instance;
		}

		if ( false !== $instance ) {
			$diff = AAL_Changes::diff( (array) $old_instance, (array) $instance );
			if ( ! empty( $diff ) ) {
				$aal_args['meta'] = $diff;
			}
		}

		aal_insert_log( $aal_args );

		// We are need return the instance, for complete the filter.
		return $instance;
	}

	public function hooks_widget_delete() {
		// A reference: http://grinninggecko.com/hooking-into-widget-delete-action-in-wordpress/
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only logging; WordPress core verifies the widgets nonce.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'post' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) && ! empty( $_REQUEST['widget-id'] ) ) {
			if ( isset( $_REQUEST['delete_widget'] ) && 1 === (int) $_REQUEST['delete_widget'] ) {
				aal_insert_log( array(
					'action'         => 'deleted',
					'object_type'    => 'Widget',
					'object_subtype' => isset( $_REQUEST['sidebar'] ) ? strtolower( sanitize_text_field( wp_unslash( $_REQUEST['sidebar'] ) ) ) : '',
					'object_id'      => 0,
					'object_name'    => isset( $_REQUEST['id_base'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id_base'] ) ) : '',
				) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
	
	public function __construct() {
		add_filter( 'widget_update_callback', array( &$this, 'hooks_widget_update_callback' ), 9999, 4 );
		add_filter( 'sidebar_admin_setup', array( &$this, 'hooks_widget_delete' ) ); // Widget delete.
		
		parent::__construct();
	}

}
