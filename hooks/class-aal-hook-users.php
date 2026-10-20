<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Users extends AAL_Hook_Base {

	private $old_meta = array();

	private $new_meta = array();

	public function hooks_user_register( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		aal_insert_log( array(
			'action' => 'registered',
			'object_type' => 'Users',
			'object_subtype' => 'Profile',
			'object_id' => $user->ID,
			'object_name' => $user->user_nicename,
		) );
	}
	public function hooks_delete_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		aal_insert_log( array(
			'action' => 'deleted',
			'object_type' => 'Users',
			'object_subtype' => 'Profile',
			'object_id' => $user->ID,
			'object_name' => $user->user_nicename,
		) );
	}

	public function hooks_wp_login( $user_login, $user ) {
		aal_insert_log( array(
			'action' => 'logged_in',
			'object_type' => 'Users',
			'object_subtype' => 'Session',
			'user_id' => $user->ID,
			'object_id' => $user->ID,
			'object_name' => $user->user_nicename,
		) );
	}

	public function hooks_clear_auth_cookie() {
		$user = wp_get_current_user();

		if ( empty( $user ) || ! $user->exists() ) {
			return;
		}

		aal_insert_log( array(
			'action' => 'logged_out',
			'object_type' => 'Users',
			'object_subtype' => 'Session',
			'user_id' => $user->ID,
			'object_id' => $user->ID,
			'object_name' => $user->user_nicename,
		) );
	}

	public function hooks_profile_update( $user_id, $old_user_data = null ) {
		$user = get_user_by( 'id', $user_id );

		$args = array(
			'action'         => 'updated',
			'object_type'    => 'Users',
			'object_subtype' => 'Profile',
			'object_id'      => $user->ID,
			'object_name'    => $user->user_nicename,
		);

		if ( $old_user_data instanceof WP_User ) {
			$fields = array( 'user_email', 'display_name', 'user_url', 'user_nicename', 'user_pass' );
			$old    = array();
			$new    = array();

			foreach ( $fields as $field ) {
				$old[ $field ] = $old_user_data->$field;
				$new[ $field ] = $user->$field;
			}

			$diff = AAL_Changes::diff( $old + $this->old_meta, $new + $this->new_meta );
			$this->old_meta = array();
			$this->new_meta = array();

			if ( empty( $diff ) ) {
				return;
			}

			$args['meta'] = $diff;
		} else {
			$this->old_meta = array();
			$this->new_meta = array();
		}

		aal_insert_log( $args );
	}

	public function capture_user_meta_changes( $meta, $user, $update, $userdata = array() ) {
		$this->old_meta = array();
		$this->new_meta = array();

		if ( ! $update ) {
			return $meta;
		}

		$new = $meta;
		foreach ( array_keys( wp_get_user_contact_methods( $user ) ) as $key ) {
			if ( isset( $userdata[ $key ] ) ) {
				$new[ $key ] = $userdata[ $key ];
			}
		}

		$old = array();
		foreach ( array_keys( $new ) as $key ) {
			$old[ $key ] = get_user_meta( $user->ID, $key, true );
		}

		$this->old_meta = $old;
		$this->new_meta = $new;

		return $meta;
	}

	public function hooks_wrong_password( $username ) {
		if ( 'no' === AAL_Main::instance()->settings->get_option( 'logs_failed_login' ) ) {
			return;
		}

		aal_insert_log( array(
			'action' => 'failed_login',
			'object_type' => 'Users',
			'object_subtype' => 'Session',
			'user_id' => 0,
			'object_id' => 0,
			'object_name' => $username,
		) );
	}

	public function hooks_set_user_role( $user_id, $role, $old_roles ) {
		if ( empty( $old_roles ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$old = array_values( (array) $old_roles );
		sort( $old );

		$new = ( is_string( $role ) && '' !== $role ) ? array( $role ) : array();

		if ( $old === $new ) {
			return;
		}

		aal_insert_log( array(
			'action'         => 'updated',
			'object_type'    => 'Users',
			'object_subtype' => 'Role',
			'object_id'      => $user->ID,
			'object_name'    => $user->user_nicename,
			'meta'           => AAL_Changes::diff( array( 'roles' => $old ), array( 'roles' => $new ) ),
		) );
	}

	public function __construct() {
		add_action( 'wp_login', array( &$this, 'hooks_wp_login' ), 10, 2 );
		add_action( 'clear_auth_cookie', array( &$this, 'hooks_clear_auth_cookie' ) );
		add_action( 'delete_user', array( &$this, 'hooks_delete_user' ) );
		add_action( 'user_register', array( &$this, 'hooks_user_register' ) );
		add_action( 'profile_update', array( &$this, 'hooks_profile_update' ), 10, 2 );
		add_action( 'set_user_role', array( &$this, 'hooks_set_user_role' ), 10, 3 );
		add_filter( 'wp_login_failed', array( &$this, 'hooks_wrong_password' ) );
		add_filter( 'insert_user_meta', array( $this, 'capture_user_meta_changes' ), 10, 4 );

		parent::__construct();
	}

}
