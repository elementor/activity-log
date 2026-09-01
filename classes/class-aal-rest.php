<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAL_REST {

	const NAMESPACE_V1 = 'activity-log/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE_V1, '/logs', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_logs' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => $this->get_logs_args(),
		) );

		register_rest_route( self::NAMESPACE_V1, '/logs/filters', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_filters' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( self::NAMESPACE_V1, '/promotions/(?P<id>[a-z_]+)/dismiss', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'dismiss_promotion' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
				),
			),
		) );
	}

	public function check_permissions() {
		if ( current_user_can( 'view_all_aryo_activity_log' ) ) {
			return true;
		}

		$cap = apply_filters( 'aal_menu_page_capability', 'edit_pages' );
		if ( current_user_can( $cap ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to view activity logs.', 'aryo-activity-log' ),
			array( 'status' => \WP_Http::FORBIDDEN )
		);
	}

	private function get_logs_args() {
		return array(
			'page'       => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'   => array(
				'default'           => 50,
				'sanitize_callback' => 'absint',
			),
			'orderby'    => array(
				'default'           => 'hist_time',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order'      => array(
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'typeshow'   => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'showaction' => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'filter_ip'  => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'usershow'   => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'capshow'    => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'sourceshow' => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'dateshow'   => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			's'          => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	public function get_logs( WP_REST_Request $request ) {
		$query = new AAL_Log_Query();

		$per_page = min( (int) $request->get_param( 'per_page' ), 100 );

		$result = $query->query( array(
			'typeshow'   => $request->get_param( 'typeshow' ),
			'showaction' => $request->get_param( 'showaction' ),
			'filter_ip'  => $request->get_param( 'filter_ip' ),
			'usershow'   => $request->get_param( 'usershow' ),
			'capshow'    => $request->get_param( 'capshow' ),
			'sourceshow' => $request->get_param( 'sourceshow' ),
			'dateshow'   => $request->get_param( 'dateshow' ),
			's'          => $request->get_param( 's' ),
			'orderby'    => $request->get_param( 'orderby' ),
			'order'      => $request->get_param( 'order' ),
			'page'       => $request->get_param( 'page' ),
			'per_page'   => $per_page,
		) );

		$collect_ip = 'no-collect-ip' !== AAL_Main::instance()->settings->get_option( 'log_visitor_ip_source' );

		$items = array();
		$seen_types = array();
		foreach ( $result['items'] as $item ) {
			$items[] = AAL_Log_Presenter::to_json( $item, $collect_ip );
			$seen_types[ strtolower( $item->object_type ) ] = true;
		}

		$promotions = $this->get_promotions( $seen_types );

		$response = new WP_REST_Response( array(
			'items'      => $items,
			'total'      => $result['total'],
			'pages'      => $result['pages'],
			'promotions' => $promotions,
		) );

		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', $result['pages'] );

		return $response;
	}

	public function get_filters( WP_REST_Request $request ) {
		$query = new AAL_Log_Query();

		return rest_ensure_response( $query->get_filter_options() );
	}

	private static $allowed_promotions = array( 'emails', 'attachments' );

	public function dismiss_promotion( WP_REST_Request $request ) {
		$promotion_id = $request->get_param( 'id' );

		if ( ! in_array( $promotion_id, self::$allowed_promotions, true ) ) {
			return new WP_Error(
				'rest_invalid_promotion',
				__( 'Invalid promotion ID.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		update_user_meta( get_current_user_id(), "_aal_promotion_{$promotion_id}_notice_viewed", 'true' );

		return rest_ensure_response( array( 'success' => true ) );
	}

	private function get_promotions( $seen_types ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return array();
		}

		$promotions = array();

		$promotion_map = array(
			'emails'      => array(
				'plugin_file' => 'site-mailer/site-mailer.php',
				'plugin_slug' => 'site-mailer',
				'title'       => __( 'Ensure your emails avoid the spam folder!', 'aryo-activity-log' ),
				'body'        => __( 'Use Site Mailer for improved email deliverability, detailed email logs, and an easy setup.', 'aryo-activity-log' ),
			),
			'attachments' => array(
				'plugin_file' => 'image-optimization/image-optimization.php',
				'plugin_slug' => 'image-optimization',
				'title'       => __( 'Optimize Your Images for a Faster Website!', 'aryo-activity-log' ),
				'body'        => __( 'Reduce image sizes without losing quality and improve your site speed.', 'aryo-activity-log' ),
			),
		);

		foreach ( $promotion_map as $type => $info ) {
			if ( ! isset( $seen_types[ $type ] ) ) {
				continue;
			}

			$is_viewed = get_user_meta( get_current_user_id(), "_aal_promotion_{$type}_notice_viewed", true );
			if ( ! empty( $is_viewed ) ) {
				continue;
			}

			if ( is_plugin_active( $info['plugin_file'] ) ) {
				continue;
			}

			$installed = $this->is_plugin_installed( $info['plugin_file'] );

			if ( $installed ) {
				$cta_url = add_query_arg(
					array(
						'action'        => 'activate',
						'plugin'        => $info['plugin_file'],
						'plugin_status' => 'all',
						'paged'         => '1',
						'_wpnonce'      => wp_create_nonce( 'activate-plugin_' . $info['plugin_file'] ),
					),
					admin_url( 'plugins.php' )
				);
			} else {
				$cta_url = add_query_arg(
					array(
						'action'   => 'install-plugin',
						'plugin'   => $info['plugin_slug'],
						'_wpnonce' => wp_create_nonce( 'install-plugin_' . $info['plugin_slug'] ),
					),
					self_admin_url( 'update.php' )
				);
			}

			$promotions[] = array(
				'id'        => $type,
				'title'     => $info['title'],
				'body'      => $info['body'],
				'installed' => $installed,
				'cta_url'   => $cta_url,
				'cta_text'  => $installed
					? __( 'Activate Plugin', 'aryo-activity-log' )
					: __( 'Install Plugin', 'aryo-activity-log' ),
			);
		}

		return $promotions;
	}

	private function is_plugin_installed( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();

		return isset( $plugins[ $plugin_file ] );
	}
}
