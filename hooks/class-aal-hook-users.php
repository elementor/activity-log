<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Users extends AAL_Hook_Base {

	private $skip_profile_update = false;

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
		if ( $this->skip_profile_update ) {
			$this->skip_profile_update = false;
			return;
		}

		$user = get_user_by( 'id', $user_id );

		$args = array(
			'action'         => 'updated',
			'object_type'    => 'Users',
			'object_subtype' => 'Profile',
			'object_id'      => $user->ID,
			'object_name'    => $user->user_nicename,
		);

		if ( $old_user_data instanceof WP_User ) {
			$fields = array( 'user_email', 'display_name', 'user_url', 'user_nicename' );
			$old    = array();
			$new    = array();

			foreach ( $fields as $field ) {
				$old[ $field ] = $old_user_data->$field;
				$new[ $field ] = $user->$field;
			}

			$diff = AAL_Changes::diff( $old, $new );
			if ( ! empty( $diff ) ) {
				$args['meta'] = $diff;
			}
		}

		aal_insert_log( $args );
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

	public function maybe_skip_preferences_update( $response, $handler, $request ) {
		$this->skip_profile_update = false;

		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return $response;
		}

		$method = $request->get_method();
		if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return $response;
		}

		if ( ! preg_match( '#^/wp/v2/users/(me|\d+)$#', $request->get_route() ) ) {
			return $response;
		}

		$params = array_merge( (array) $request->get_json_params(), (array) $request->get_body_params() );

		if ( array( 'meta' ) !== array_keys( $params ) ) {
			return $response;
		}

		$meta = $params['meta'];
		if ( ! is_array( $meta ) || array( 'persisted_preferences' ) !== array_keys( $meta ) ) {
			return $response;
		}

		$this->skip_profile_update = true;

		return $response;
	}

	public function reset_skip_profile_update( $response ) {
		$this->skip_profile_update = false;

		return $response;
	}

	public function __construct() {
		add_action( 'wp_login', array( &$this, 'hooks_wp_login' ), 10, 2 );
		add_action( 'clear_auth_cookie', array( &$this, 'hooks_clear_auth_cookie' ) );
		add_action( 'delete_user', array( &$this, 'hooks_delete_user' ) );
		add_action( 'user_register', array( &$this, 'hooks_user_register' ) );
		add_action( 'profile_update', array( &$this, 'hooks_profile_update' ), 10, 2 );
		add_action( 'set_user_role', array( &$this, 'hooks_set_user_role' ), 10, 3 );
		add_filter( 'wp_login_failed', array( &$this, 'hooks_wrong_password' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'maybe_skip_preferences_update' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'reset_skip_profile_update' ) );

		parent::__construct();
	}

}
