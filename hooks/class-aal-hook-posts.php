<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Posts extends AAL_Hook_Base {

	private $before = array();

	protected function _draft_or_post_title( $post = 0 ) {
		$title = esc_html( get_the_title( $post ) );

		if ( empty( $title ) )
			$title = __( '(no title)', 'aryo-activity-log' );

		return $title;
	}

	public function hooks_pre_post_update( $post_id, $data ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! empty( $_GET['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$post = get_post( $post_id );
		if ( $post ) {
			$this->before[ $post_id ] = $post;
		}
	}

	public function hooks_transition_post_status( $new_status, $old_status, $post ) {
		if ( 'auto-draft' === $old_status && ( 'auto-draft' !== $new_status && 'inherit' !== $new_status ) ) {
			// page created
			$action = 'created';
		} elseif ( 'auto-draft' === $new_status || ( 'new' === $old_status && 'inherit' === $new_status ) ) {
			// nvm.. ignore it.
			return;
		} elseif ( 'trash' === $new_status ) {
			// page was deleted.
			$action = 'trashed';
		} elseif ( 'trash' === $old_status ) {
			$action = 'restored';
		} else {
			// page updated. I guess.
			$action = 'updated';
		}

		if ( wp_is_post_revision( $post->ID ) )
			return;

		// Skip for menu items.
		if ( 'nav_menu_item' === get_post_type( $post->ID ) )
			return;

		$args = array(
			'action'         => $action,
			'object_type'    => 'Posts',
			'object_subtype' => $post->post_type,
			'object_id'      => $post->ID,
			'object_name'    => $this->_draft_or_post_title( $post->ID ),
		);

		$diff = $this->build_post_changes( $post );
		if ( ! empty( $diff ) ) {
			$args['meta'] = $diff;
		}

		aal_insert_log( $args );
	}

	private function build_post_changes( $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return array();
		}

		if ( ! empty( $_GET['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return array();
		}

		if ( empty( $this->before[ $post->ID ] ) ) {
			return array();
		}

		$before = $this->before[ $post->ID ];
		unset( $this->before[ $post->ID ] );

		$fields = array( 'post_title', 'post_status', 'post_name', 'post_author', 'post_date', 'post_password' );
		$old    = array();
		$new    = array();

		foreach ( $fields as $field ) {
			if ( 'post_date' === $field && '0000-00-00 00:00:00' === $before->post_date_gmt ) {
				continue;
			}

			$old[ $field ] = $before->$field;
			$new[ $field ] = $post->$field;
		}

		$diff = AAL_Changes::diff( $old, $new );

		if ( $before->post_content !== $post->post_content ) {
			if ( empty( $diff['changes'] ) ) {
				$diff['changes'] = array();
			}
			$diff['changes']['post_content'] = array( null, null );
		}

		return $diff;
	}

	public function hooks_delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) )
			return;

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ) ) )
			return;

		// Skip for menu items.
		if ( 'nav_menu_item' === get_post_type( $post->ID ) )
			return;

		aal_insert_log(
			array(
				'action' => 'deleted',
				'object_type' => 'Posts',
				'object_subtype' => $post->post_type,
				'object_id' => $post->ID,
				'object_name' => $this->_draft_or_post_title( $post->ID ),
			)
		);
	}

	public function __construct() {
		add_action( 'pre_post_update', array( &$this, 'hooks_pre_post_update' ), 10, 2 );
		add_action( 'transition_post_status', array( &$this, 'hooks_transition_post_status' ), 10, 3 );
		add_action( 'delete_post', array( &$this, 'hooks_delete_post' ) );

		parent::__construct();
	}
}
