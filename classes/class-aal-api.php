<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AAL_API {

	private $app_password_authenticated = false;
	private $app_password_name = '';
	private $ability_stack = array();

	public function __construct() {
		add_action( 'admin_init', [ $this, 'maybe_add_schedule_delete_old_items' ] );
		add_action( 'aal/maintenance/clear_old_items', [ $this, 'delete_old_items' ] );

		add_action( 'application_password_did_authenticate', [ $this, 'on_app_password_auth' ], 10, 2 );

		// WP Abilities hooks (WP 6.9+). Safe to register on older WP — they simply never fire.
		add_action( 'wp_before_execute_ability', [ $this, 'on_ability_start' ], 10, 2 );
		add_action( 'wp_after_execute_ability', [ $this, 'on_ability_end' ], 10, 3 );
	}

	public function on_app_password_auth( $user, $item ) {
		$this->app_password_authenticated = true;
		$this->app_password_name = isset( $item['name'] ) ? $item['name'] : '';
	}

	public function on_ability_start( $ability_name, $input ) {
		$this->ability_stack[] = $ability_name;
	}

	public function on_ability_end( $ability_name, $input, $result ) {
		array_pop( $this->ability_stack );
	}

	public function maybe_add_schedule_delete_old_items() {
		if ( ! wp_next_scheduled( 'aal/maintenance/clear_old_items' ) ) {
			wp_schedule_event( time(), 'daily', 'aal/maintenance/clear_old_items' );
		}
	}

	public function delete_old_items() {
		global $wpdb;

		$logs_lifespan = absint( AAL_Main::instance()->settings->get_option( 'logs_lifespan' ) );
		if ( empty( $logs_lifespan ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . $wpdb->activity_log . '`
					WHERE `hist_time` < %d',
				strtotime( '-' . $logs_lifespan . ' days', current_time( 'timestamp' ) )
			)
		);
	}

	/**
	 * Get real address
	 *
	 * @since 2.1.4
	 * @return string real address IP
	 */
	protected function _get_ip_address() {
		$header_key = AAL_Main::instance()->settings->get_option( 'log_visitor_ip_source' );
		
		if ( empty( $header_key ) ) {
			$header_key = 'REMOTE_ADDR';
		}
		
		if ( 'no-collect-ip' === $header_key ) {
			return '';
		}
		
		$visitor_ip_address = '';
		if ( ! empty( $_SERVER[ $header_key ] ) ) {
			$visitor_ip_address = $_SERVER[ $header_key ];
		}

		$remote_address = apply_filters( 'aal_get_ip_address', $visitor_ip_address );
		
		if ( ! empty( $remote_address ) && filter_var( $remote_address, FILTER_VALIDATE_IP ) ) {
			return $remote_address;
		}
		
		return '127.0.0.1';
	}

	/**
	 * @since 2.0.0
	 * @return void
	 */
	public function erase_all_items() {
		global $wpdb;

		$wpdb->query( 'TRUNCATE `' . $wpdb->activity_log . '`' );
	}

	/**
	 * @since 1.0.0
	 *
	 * @param array $args
	 * @return void
	 */
	public function insert( $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'action'         => '',
				'object_type'    => '',
				'object_subtype' => '',
				'object_name'    => '',
				'object_id'      => '',
				'hist_ip'        => $this->_get_ip_address(),
				'hist_time'      => current_time( 'timestamp' ),
			)
		);

		$args = $this->setup_userdata( $args );

		$should_skip_insert = apply_filters( 'aal_skip_insert_log', false, $args );

		if ( $should_skip_insert ) {
			return;
		}

		// Make sure for non duplicate.
		$check_duplicate = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT `histid` FROM `' . $wpdb->activity_log . '`
					WHERE `user_caps` = %s
						AND `action` = %s
						AND `object_type` = %s
						AND `object_subtype` = %s
						AND `object_name` = %s
						AND `user_id` = %s
						AND `hist_ip` = %s
						AND `hist_time` = %s
				;',
				$args['user_caps'],
				$args['action'],
				$args['object_type'],
				$args['object_subtype'],
				$args['object_name'],
				$args['user_id'],
				$args['hist_ip'],
				$args['hist_time']
			)
		);

		if ( $check_duplicate ) {
			return;
		}

		$row = array(
			'action'         => $args['action'],
			'object_type'    => $args['object_type'],
			'object_subtype' => $args['object_subtype'],
			'object_name'    => $args['object_name'],
			'object_id'      => $args['object_id'],
			'user_id'        => $args['user_id'],
			'user_caps'      => $args['user_caps'],
			'hist_ip'        => $args['hist_ip'],
			'hist_time'      => $args['hist_time'],
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d' );

		if ( AAL_Maintenance::is_schema_ready( '1.1' ) ) {
			$request_source = $this->resolve_request_source( $args );
			$row['request_source'] = $request_source;
			$formats[] = '%s';
			$args['request_source'] = $request_source;

			$meta = $this->build_meta();
			if ( null !== $meta ) {
				$row['meta'] = wp_json_encode( $meta );
				$formats[] = '%s';
				$args['meta'] = $meta;
			}
		}

		$wpdb->insert( $wpdb->activity_log, $row, $formats );

		do_action( 'aal_insert_log', $args );
	}

	private function setup_userdata( $args ) {
		$user = false;

		if ( function_exists( 'get_user_by' ) ) {
			$user = get_user_by( 'id', get_current_user_id() );
		}

		if ( $user ) {
			$args['user_caps'] = strtolower( key( $user->caps ) );
			if ( empty( $args['user_id'] ) ) {
				$args['user_id'] = $user->ID;
			}
		} else {
			$args['user_caps'] = 'guest';
			if ( empty( $args['user_id'] ) ) {
				$args['user_id'] = 0;
			}
		}

		// TODO: Find better way to Multisite compatibility.
		// Fallback for multisite with bbPress
		if ( empty( $args['user_caps'] ) || 'bbp_participant' === $args['user_caps'] ) {
			$args['user_caps'] = 'administrator';
		}

		return $args;
	}

	/**
	 * Build the encoded request_source string: {channel}|app:{name}
	 *
	 * @param array $args
	 * @return string
	 */
	private function resolve_request_source( $args ) {
		if ( ! empty( $args['request_source'] ) ) {
			return sanitize_text_field( $args['request_source'] );
		}

		$channel = $this->resolve_channel();
		$app_part = $this->resolve_app_password_part();

		if ( '' !== $channel && '' !== $app_part ) {
			$value = $channel . '|' . $app_part;
		} elseif ( '' !== $app_part ) {
			$value = '|' . $app_part;
		} else {
			$value = $channel;
		}

		if ( strlen( $value ) > 191 ) {
			$value = substr( $value, 0, 191 );
		}

		return $value;
	}

	private function resolve_channel() {
		if ( ! empty( $this->ability_stack ) ) {
			return 'abilities';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return 'xmlrpc';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'cron';
		}

		return '';
	}

	private function resolve_app_password_part() {
		$name = '';

		if ( $this->app_password_authenticated ) {
			$name = $this->app_password_name;
		} elseif ( function_exists( 'rest_get_authenticated_app_password' ) ) {
			$uuid = rest_get_authenticated_app_password();
			if ( $uuid ) {
				$this->app_password_authenticated = true;
				$user_id = get_current_user_id();
				if ( $user_id && class_exists( 'WP_Application_Passwords' ) ) {
					$password = WP_Application_Passwords::get_user_application_password( $user_id, $uuid );
					$name = isset( $password['name'] ) ? $password['name'] : '';
				}
			}
		}

		if ( ! $this->app_password_authenticated ) {
			return '';
		}

		$name = sanitize_text_field( $name );

		if ( '' === $name ) {
			return 'app:';
		}

		return 'app:' . $name;
	}

	/**
	 * @return array|null Meta array or null if nothing to store.
	 */
	private function build_meta() {
		$meta = array();

		if ( ! empty( $this->ability_stack ) ) {
			$meta['ability'] = end( $this->ability_stack );
		}

		return empty( $meta ) ? null : $meta;
	}

	/**
	 * @return array
	 */
	public static function get_channel_labels() {
		return array(
			'abilities' => __( 'WP Abilities', 'aryo-activity-log' ),
			'rest'      => __( 'REST API', 'aryo-activity-log' ),
			'xmlrpc'    => __( 'XML-RPC', 'aryo-activity-log' ),
			'cli'       => __( 'WP-CLI', 'aryo-activity-log' ),
			'cron'      => __( 'WP-Cron', 'aryo-activity-log' ),
		);
	}

	/**
	 * Parse a stored request_source value into channel and app name.
	 *
	 * @param string $raw
	 * @return array { channel: string, app_name: string }
	 */
	public static function parse_request_source( $raw ) {
		$channel = '';
		$app_name = '';

		if ( '' === $raw || null === $raw ) {
			return compact( 'channel', 'app_name' );
		}

		$app_marker = '|app:';
		$app_pos = strpos( $raw, $app_marker );

		if ( false !== $app_pos ) {
			$channel = substr( $raw, 0, $app_pos );
			$app_name = substr( $raw, $app_pos + strlen( $app_marker ) );
		} elseif ( 0 === strpos( $raw, 'app:' ) ) {
			$app_name = substr( $raw, 4 );
		} else {
			$channel = $raw;
		}

		return compact( 'channel', 'app_name' );
	}
}

/**
 * @since 1.0.0
 *
 * @see AAL_API::insert
 *
 * @param array $args
 * @return void
 */
function aal_insert_log( $args = array() ) {
	AAL_Main::instance()->api->insert( $args );
}
