<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers read-only WordPress Abilities for AI agents (WP 6.9+).
 */
class AAL_Abilities {

	const CATEGORY = 'activity-log';

	const ABILITY_QUERY_LOGS  = 'activity-log/query-logs';
	const ABILITY_GET_LOG     = 'activity-log/get-log';
	const ABILITY_GET_FILTERS = 'activity-log/get-filters';

	/**
	 * @var AAL_REST
	 */
	private $rest;

	public function __construct( AAL_REST $rest ) {
		$this->rest = $rest;

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Whether abilities are exposed to REST / MCP clients (opt-in).
	 *
	 * @return bool
	 */
	public static function is_agent_exposure_enabled() {
		return 'yes' === AAL_Main::instance()->settings->get_option( 'abilities_agent_exposure' );
	}

	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Activity Log', 'aryo-activity-log' ),
				'description' => __( 'Read-only access to site activity log entries for auditing and monitoring.', 'aryo-activity-log' ),
			)
		);
	}

	public function register_abilities() {
		$exposure_meta = $this->get_exposure_meta();

		wp_register_ability(
			self::ABILITY_QUERY_LOGS,
			array(
				'label'               => __( 'Query activity logs', 'aryo-activity-log' ),
				'description'         => __( 'Returns paginated activity log entries with the same filters as the Activity Log admin list.', 'aryo-activity-log' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::get_query_logs_input_schema(),
				'output_schema'       => self::get_query_logs_output_schema(),
				'execute_callback'    => array( $this, 'execute_query_logs' ),
				'permission_callback' => array( $this, 'check_view_permission' ),
				'meta'                => $exposure_meta,
			)
		);

		wp_register_ability(
			self::ABILITY_GET_LOG,
			array(
				'label'               => __( 'Get activity log entry', 'aryo-activity-log' ),
				'description'         => __( 'Returns a single activity log entry by ID.', 'aryo-activity-log' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::get_get_log_input_schema(),
				'output_schema'       => self::get_log_entry_output_schema(),
				'execute_callback'    => array( $this, 'execute_get_log' ),
				'permission_callback' => array( $this, 'check_view_permission' ),
				'meta'                => $exposure_meta,
			)
		);

		wp_register_ability(
			self::ABILITY_GET_FILTERS,
			array(
				'label'               => __( 'Get activity log filters', 'aryo-activity-log' ),
				'description'         => __( 'Returns available filter values (users, roles, topics, actions, sources) for the current user.', 'aryo-activity-log' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type' => 'object',
					'properties' => array(),
				),
				'output_schema'       => self::get_filters_output_schema(),
				'execute_callback'    => array( $this, 'execute_get_filters' ),
				'permission_callback' => array( $this, 'check_view_permission' ),
				'meta'                => $exposure_meta,
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_exposure_meta() {
		$public = self::is_agent_exposure_enabled();

		return array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'show_in_rest' => $public,
			'public'       => $public,
			'mcp'          => array(
				'public' => $public,
			),
		);
	}

	/**
	 * @param mixed $input Ability input (unused).
	 * @return bool|WP_Error
	 */
	public function check_view_permission( $input = null ) {
		$allowed = $this->rest->check_permissions();

		if ( is_wp_error( $allowed ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_query_logs( $input ) {
		$query = new AAL_Log_Query();

		$per_page = isset( $input['per_page'] ) ? min( (int) $input['per_page'], 100 ) : 50;

		$result = $query->query(
			array(
				'typeshow'   => isset( $input['typeshow'] ) ? $input['typeshow'] : '',
				'showaction' => isset( $input['showaction'] ) ? $input['showaction'] : '',
				'filter_ip'  => isset( $input['filter_ip'] ) ? $input['filter_ip'] : '',
				'usershow'   => isset( $input['usershow'] ) ? $input['usershow'] : '',
				'capshow'    => isset( $input['capshow'] ) ? $input['capshow'] : '',
				'sourceshow' => isset( $input['sourceshow'] ) ? $input['sourceshow'] : '',
				'dateshow'   => isset( $input['dateshow'] ) ? $input['dateshow'] : '',
				's'          => isset( $input['s'] ) ? $input['s'] : '',
				'orderby'    => isset( $input['orderby'] ) ? $input['orderby'] : 'hist_time',
				'order'      => isset( $input['order'] ) ? $input['order'] : 'DESC',
				'page'       => isset( $input['page'] ) ? $input['page'] : 1,
				'per_page'   => $per_page,
			)
		);

		$collect_ip = 'no-collect-ip' !== AAL_Main::instance()->settings->get_option( 'log_visitor_ip_source' );

		$items = array();
		foreach ( $result['items'] as $item ) {
			$items[] = AAL_Log_Presenter::to_ability_json( $item, $collect_ip );
		}

		return array(
			'items' => $items,
			'total' => (int) $result['total'],
			'pages' => (int) $result['pages'],
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_get_log( $input ) {
		if ( empty( $input['id'] ) ) {
			return new WP_Error(
				'aal_missing_log_id',
				__( 'Log entry ID is required.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		$query = new AAL_Log_Query();
		$item  = $query->get_item( (int) $input['id'] );

		if ( ! $item ) {
			return new WP_Error(
				'aal_log_not_found',
				__( 'Activity log entry not found.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::NOT_FOUND )
			);
		}

		$collect_ip = 'no-collect-ip' !== AAL_Main::instance()->settings->get_option( 'log_visitor_ip_source' );

		return AAL_Log_Presenter::to_ability_json( $item, $collect_ip );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute_get_filters( $input ) {
		$query = new AAL_Log_Query();

		return $query->get_filter_options();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_query_logs_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'page'       => array(
					'type'        => 'integer',
					'description' => __( 'Page number (1-based).', 'aryo-activity-log' ),
					'default'     => 1,
					'minimum'     => 1,
				),
				'per_page'   => array(
					'type'        => 'integer',
					'description' => __( 'Number of entries per page (max 100).', 'aryo-activity-log' ),
					'default'     => 50,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'orderby'    => array(
					'type'        => 'string',
					'description' => __( 'Column to sort by.', 'aryo-activity-log' ),
					'default'     => 'hist_time',
					'enum'        => array( 'hist_time', 'hist_ip' ),
				),
				'order'      => array(
					'type'        => 'string',
					'description' => __( 'Sort direction.', 'aryo-activity-log' ),
					'default'     => 'DESC',
					'enum'        => array( 'ASC', 'DESC' ),
				),
				'typeshow'   => array(
					'type'        => 'string',
					'description' => __( 'Filter by object_type.', 'aryo-activity-log' ),
				),
				'showaction' => array(
					'type'        => 'string',
					'description' => __( 'Filter by action.', 'aryo-activity-log' ),
				),
				'filter_ip'  => array(
					'type'        => 'string',
					'description' => __( 'Filter by IP address.', 'aryo-activity-log' ),
				),
				'usershow'   => array(
					'type'        => 'string',
					'description' => __( 'Filter by user ID.', 'aryo-activity-log' ),
				),
				'capshow'    => array(
					'type'        => 'string',
					'description' => __( 'Filter by user role/cap slug stored in user_caps.', 'aryo-activity-log' ),
				),
				'sourceshow' => array(
					'type'        => 'string',
					'description' => __( 'Filter by request channel (e.g. rest, abilities, app_password).', 'aryo-activity-log' ),
				),
				'dateshow'   => array(
					'type'        => 'string',
					'description' => __( 'Date filter: today, yesterday, week, month, or dd/mm/yyyy.', 'aryo-activity-log' ),
				),
				's'          => array(
					'type'        => 'string',
					'description' => __( 'Search object name, subtype, or IP.', 'aryo-activity-log' ),
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_get_log_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => __( 'Activity log entry ID (histid).', 'aryo-activity-log' ),
					'minimum'     => 1,
				),
			),
			'required'   => array( 'id' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_log_entry_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'time'            => array(
					'type'        => 'string',
					'description' => __( 'Event time in ISO-8601 format (site timezone).', 'aryo-activity-log' ),
				),
				'timestamp'       => array( 'type' => 'integer' ),
				'user_id'         => array( 'type' => 'integer' ),
				'user_caps'       => array( 'type' => 'string' ),
				'action'          => array( 'type' => 'string' ),
				'object_type'     => array( 'type' => 'string' ),
				'object_subtype'  => array( 'type' => 'string' ),
				'object_name'     => array( 'type' => 'string' ),
				'object_id'       => array( 'type' => 'integer' ),
				'channel'         => array(
					'type'        => 'string',
					'description' => __( 'Request channel (rest, abilities, cli, etc.).', 'aryo-activity-log' ),
				),
				'app_name'        => array(
					'type'        => 'string',
					'description' => __( 'Application Password name when the request used an app password.', 'aryo-activity-log' ),
				),
				'ip'              => array(
					'type'        => 'string',
					'description' => __( 'Visitor IP when collection is enabled.', 'aryo-activity-log' ),
				),
				'meta'            => array(
					'type'        => 'object',
					'description' => __( 'Optional structured metadata (e.g. ability name).', 'aryo-activity-log' ),
				),
			),
			'required'   => array(
				'id',
				'time',
				'timestamp',
				'user_id',
				'user_caps',
				'action',
				'object_type',
				'object_subtype',
				'object_name',
				'object_id',
				'channel',
				'app_name',
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_query_logs_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'items' => array(
					'type'  => 'array',
					'items' => self::get_log_entry_output_schema(),
				),
				'total' => array( 'type' => 'integer' ),
				'pages' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'items', 'total', 'pages' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_filters_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'users'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'   => array( 'type' => 'integer' ),
							'name' => array( 'type' => 'string' ),
						),
					),
				),
				'roles'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'value' => array( 'type' => 'string' ),
							'label' => array( 'type' => 'string' ),
						),
					),
				),
				'topics'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'actions' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'value' => array( 'type' => 'string' ),
							'label' => array( 'type' => 'string' ),
						),
					),
				),
				'sources' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'value' => array( 'type' => 'string' ),
							'label' => array( 'type' => 'string' ),
						),
					),
				),
			),
			'required'   => array( 'users', 'roles', 'topics', 'actions', 'sources' ),
		);
	}
}
