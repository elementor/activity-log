<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_Users extends AAL_Hook_Base {

	private $old_meta = array();

	private $new_meta = array();

	/**
	 * @var array<int, array{original: array<int, string>}>
	 */
	private $role_change_buffers = array();

	private function get_user_object_name( $user ) {
		return $user instanceof WP_User ? $user->user_nicename : 'Unknown user';
	}

	public function hooks_user_register( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		aal_insert_log( array(
			'action' => 'registered',
			'object_type' => 'Users',
			'object_subtype' => 'Profile',
			'object_id' => (int) $user_id,
			'object_name' => $this->get_user_object_name( $user ),
		) );
	}
	public function hooks_delete_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		aal_insert_log( array(
			'action' => 'deleted',
			'object_type' => 'Users',
			'object_subtype' => 'Profile',
			'object_id' => (int) $user_id,
			'object_name' => $this->get_user_object_name( $user ),
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
			'object_id'      => (int) $user_id,
			'object_name'    => $this->get_user_object_name( $user ),
		);

		if ( $old_user_data instanceof WP_User && $user instanceof WP_User ) {
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

	private function is_role_change_from_set_role() {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $call ) {
			if ( isset( $call['function'], $call['class'] ) && 'set_role' === $call['function'] && 'WP_User' === $call['class'] ) {
				return true;
			}
		}

		return false;
	}

	private function buffer_role_change( $user_id, $role, $event ) {
		if ( $this->is_role_change_from_set_role() ) {
			return;
		}

		if ( isset( $this->role_change_buffers[ $user_id ] ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			$this->role_change_buffers[ $user_id ] = array(
				'original' => array(),
			);
			return;
		}

		$current = array_values( (array) $user->roles );
		sort( $current );

		if ( 'add' === $event ) {
			$original = array_values( array_diff( $current, array( $role ) ) );
		} else {
			$original = array_values( array_merge( $current, array( $role ) ) );
			sort( $original );
		}

		$this->role_change_buffers[ $user_id ] = array(
			'original' => $original,
		);
	}

	public function hooks_add_user_role( $user_id, $role ) {
		$this->buffer_role_change( $user_id, $role, 'add' );
	}

	public function hooks_remove_user_role( $user_id, $role ) {
		$this->buffer_role_change( $user_id, $role, 'remove' );
	}

	public function flush_buffered_role_changes() {
		foreach ( $this->role_change_buffers as $user_id => $buffer ) {
			$old  = $buffer['original'];
			$user = get_user_by( 'id', $user_id );

			if ( ! $user instanceof WP_User ) {
				$new = array();
				if ( $old === $new ) {
					continue;
				}

				aal_insert_log(
					array(
						'action'         => 'updated',
						'object_type'    => 'Users',
						'object_subtype' => 'Role',
						'object_id'      => (int) $user_id,
						'object_name'    => $this->get_user_object_name( $user ),
						'meta'           => AAL_Changes::diff( array( 'roles' => $old ), array( 'roles' => $new ) ),
					)
				);
				continue;
			}

			$new = array_values( (array) $user->roles );
			sort( $new );

			if ( $old === $new ) {
				continue;
			}

			aal_insert_log(
				array(
					'action'         => 'updated',
					'object_type'    => 'Users',
					'object_subtype' => 'Role',
					'object_id'      => $user->ID,
					'object_name'    => $this->get_user_object_name( $user ),
					'meta'           => AAL_Changes::diff( array( 'roles' => $old ), array( 'roles' => $new ) ),
				)
			);
		}

		$this->role_change_buffers = array();
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
		add_action( 'add_user_role', array( &$this, 'hooks_add_user_role' ), 10, 2 );
		add_action( 'remove_user_role', array( &$this, 'hooks_remove_user_role' ), 10, 2 );
		add_action( 'shutdown', array( &$this, 'flush_buffered_role_changes' ) );
		add_action( 'set_user_role', array( &$this, 'hooks_set_user_role' ), 10, 3 );
		add_filter( 'wp_login_failed', array( &$this, 'hooks_wrong_password' ) );
		add_filter( 'insert_user_meta', array( $this, 'capture_user_meta_changes' ), 10, 4 );

		parent::__construct();
	}

}
