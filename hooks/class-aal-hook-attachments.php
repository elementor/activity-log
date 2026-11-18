<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Attachments extends AAL_Hook_Base {

	protected function _add_log_attachment( $action, $attachment_id ) {
		$post = get_post( $attachment_id );

		aal_insert_log( array(
			'action'         => $action,
			'object_type'    => 'Attachments',
			'object_subtype' => $post->post_type,
			'object_id'      => $attachment_id,
			'object_name'    => get_post_field( 'post_title', $post->ID ),
		) );
	}

	public function hooks_delete_attachment( $attachment_id ) {
		$this->_add_log_attachment( 'deleted', $attachment_id );
	}

	public function hooks_edit_attachment( $attachment_id ) {
		$this->_add_log_attachment( 'updated', $attachment_id );
	}

	public function hooks_add_attachment( $attachment_id ) {
		$this->_add_log_attachment( 'uploaded', $attachment_id );
	}
	
	public function __construct() {
		add_action( 'add_attachment', array( &$this, 'hooks_add_attachment' ) );
		add_action( 'edit_attachment', array( &$this, 'hooks_edit_attachment' ) );
		add_action( 'delete_attachment', array( &$this, 'hooks_delete_attachment' ) );

		parent::__construct();
	}

}
