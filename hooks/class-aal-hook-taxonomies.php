<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Taxonomies extends AAL_Hook_Base {

	private $before = array();

	private function term_fields( $term ) {
		return array(
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
		);
	}

	public function hooks_edit_terms( $term_id, $taxonomy ) {
		if ( 'nav_menu' === $taxonomy ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$this->before[ $term_id ] = $this->term_fields( $term );
		}
	}

	public function hooks_created_edited_deleted_term( $term_id, $tt_id, $taxonomy, $deleted_term = null ) {
		// Make sure do not action nav menu taxonomy.
		if ( 'nav_menu' === $taxonomy )
			return;

		if ( 'delete_term' === current_filter() )
			$term = $deleted_term;
		else
			$term = get_term( $term_id, $taxonomy );

		if ( $term && ! is_wp_error( $term ) ) {
			if ( 'edited_term' === current_filter() ) {
				$action = 'updated';
			} elseif ( 'delete_term' === current_filter() ) {
				$action  = 'deleted';
				$term_id = '';
			} else {
				$action = 'created';
			}

			$args = array(
				'action'         => $action,
				'object_type'    => 'Taxonomies',
				'object_subtype' => $taxonomy,
				'object_id'      => $term_id,
				'object_name'    => $term->name,
			);

			if ( 'updated' === $action && ! empty( $this->before[ $term_id ] ) ) {
				$old = $this->before[ $term_id ];
				unset( $this->before[ $term_id ] );

				$new  = $this->term_fields( $term );
				$diff = AAL_Changes::diff( $old, $new );
				if ( ! empty( $diff ) ) {
					$args['meta'] = $diff;
				}
			}

			aal_insert_log( $args );
		}
	}
	
	public function __construct() {
		add_action( 'edit_terms', array( &$this, 'hooks_edit_terms' ), 10, 2 );
		add_action( 'created_term', array( &$this, 'hooks_created_edited_deleted_term' ), 10, 3 );
		add_action( 'edited_term', array( &$this, 'hooks_created_edited_deleted_term' ), 10, 3 );
		add_action( 'delete_term', array( &$this, 'hooks_created_edited_deleted_term' ), 10, 4 );

		parent::__construct();
	}

}
