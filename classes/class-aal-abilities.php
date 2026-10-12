<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers read-only WordPress Abilities for AI agents (WP 6.9+).
 *
 * Agent exposure must be enabled in settings for abilities to run, including direct
 * {@see WP_Ability::execute()} calls. REST/MCP visibility uses the same flag.
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
	 * @return string 'yes' or 'no'
	 */
	public static function get_agent_exposure_setting() {
		$options = AAL_Main::instance()->settings->get_options();

		if ( is_array( $options ) && array_key_exists( 'abilities_agent_exposure', $options ) ) {
			return 'yes' === $options['abilities_agent_exposure'] ? 'yes' : 'no';
		}

		return 'no';
	}

	/**
	 * Whether abilities are exposed to REST / MCP clients.
	 *
	 * @return bool
	 */
	public static function is_agent_exposure_enabled() {
		return 'yes' === self::get_agent_exposure_setting();
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
				'input_schema'        => self::get_get_filters_input_schema(),
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
		if ( ! self::is_agent_exposure_enabled() ) {
			return false;
		}

		return $this->rest->check_permissions();
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_query_logs( $input ) {
		$query  = new AAL_Log_Query();
		$result = $query->query(
			array_merge(
				(array) $input,
				array(
					'orderby'    => 'hist_time',
					'exclude_ip' => true,
				)
			)
		);

		$items = array();
		foreach ( $result['items'] as $item ) {
			$items[] = AAL_Log_Presenter::to_ability_json( $item );
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
		$query = new AAL_Log_Query();
		$item  = $query->get_item( (int) $input['id'] );

		if ( ! $item ) {
			return new WP_Error(
				'aal_log_not_found',
				__( 'Activity log entry not found.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::NOT_FOUND )
			);
		}

		return AAL_Log_Presenter::to_ability_json( $item );
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
	public static function get_get_filters_input_schema() {
		return array(
			'type'                  => 'object',
			'default'               => array(),
			'properties'            => array(),
			'additionalProperties'  => false,
		);
	}

	public static function get_query_logs_source_filter_values() {
		return array_merge( array_keys( AAL_API::get_channel_labels() ), array( 'app_password' ) );
	}

	public static function get_query_logs_input_schema() {
		return array(
			'type'       => 'object',
			'default'    => array(),
			'properties' => array(
				'page'       => array(
					'type'        => 'integer',
					'description' => __( 'Page number (1-based).', 'aryo-activity-log' ),
					'default'     => 1,
					'minimum'     => 1,
					'maximum'     => 100000,
				),
				'per_page'   => array(
					'type'        => 'integer',
					'description' => __( 'Number of entries per page (max 100).', 'aryo-activity-log' ),
					'default'     => 50,
					'minimum'     => 1,
					'maximum'     => 100,
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
					'maxLength'   => 191,
				),
				'showaction' => array(
					'type'        => 'string',
					'description' => __( 'Filter by action.', 'aryo-activity-log' ),
					'maxLength'   => 191,
				),
				'usershow'   => array(
					'type'        => 'integer',
					'description' => __( 'Filter by user ID.', 'aryo-activity-log' ),
					'minimum'     => 1,
				),
				'capshow'    => array(
					'type'        => 'string',
					'description' => __( 'Filter by user role/cap slug stored in user_caps.', 'aryo-activity-log' ),
					'maxLength'   => 191,
				),
				'sourceshow' => array(
					'type'        => 'string',
					'description' => __( 'Filter by request channel.', 'aryo-activity-log' ),
					'enum'        => self::get_query_logs_source_filter_values(),
				),
				'dateshow'   => array(
					'description' => __( 'Date filter: today, yesterday, week, month, or dd/mm/yyyy.', 'aryo-activity-log' ),
					'oneOf'       => array(
						array(
							'type' => 'string',
							'enum' => array( 'today', 'yesterday', 'week', 'month' ),
						),
						array(
							'type'      => 'string',
							'pattern'   => '^\d{2}/\d{2}/\d{4}$',
							'maxLength' => 10,
						),
					),
				),
				's'          => array(
					'type'        => 'string',
					'description' => __( 'Search object name or subtype.', 'aryo-activity-log' ),
					'maxLength'   => 500,
				),
			),
			'additionalProperties' => false,
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
			'required'             => array( 'id' ),
			'additionalProperties' => false,
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
