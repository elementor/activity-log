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
	const ABILITY_GET_FILTERS      = 'activity-log/get-filters';
	const ABILITY_SUMMARIZE_ACTIVITY = 'activity-log/summarize-activity';

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

		wp_register_ability(
			self::ABILITY_SUMMARIZE_ACTIVITY,
			array(
				'label'               => __( 'Summarize activity', 'aryo-activity-log' ),
				'description'         => __( 'Returns aggregated activity statistics for a time range (counts by action, type, user, channel, and time buckets).', 'aryo-activity-log' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::get_summarize_activity_input_schema(),
				'output_schema'       => self::get_summarize_activity_output_schema(),
				'execute_callback'    => array( $this, 'execute_summarize_activity' ),
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
	 * @param array<string, mixed> $input
	 * @param int|null             $now    Optional stored hist_time for period windows (tests).
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_summarize_activity( $input, $now = null ) {
		$range = self::resolve_summarize_activity_range( $input, $now );
		if ( is_wp_error( $range ) ) {
			return $range;
		}

		$query = new AAL_Log_Query();

		return $query->summarize_activity( $range );
	}

	/**
	 * @param array<string, mixed> $input
	 * @param int|null $now Stored hist_time for "now" (defaults to current_time( 'timestamp' )).
	 * @return array{from_ts: int, to_ts: int}|WP_Error
	 */
	public static function resolve_summarize_activity_range( $input, $now = null ) {
		$input = is_array( $input ) ? $input : array();

		$has_period = array_key_exists( 'period', $input );
		$has_from   = array_key_exists( 'date_from', $input );
		$has_to     = array_key_exists( 'date_to', $input );
		$has_dates  = $has_from || $has_to;

		if ( $has_dates && $has_period ) {
			return new WP_Error(
				'ability_invalid_input',
				__( 'Provide either period or date_from/date_to, not both.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		if ( $has_dates ) {
			if ( ! $has_from || ! $has_to ) {
				return new WP_Error(
					'ability_invalid_input',
					__( 'date_from and date_to must both be set.', 'aryo-activity-log' ),
					array( 'status' => \WP_Http::BAD_REQUEST )
				);
			}

			$from_ts = self::parse_summary_iso_date_start( $input['date_from'] );
			$to_ts   = self::parse_summary_iso_date_end( $input['date_to'] );
			if ( is_wp_error( $from_ts ) ) {
				return $from_ts;
			}
			if ( is_wp_error( $to_ts ) ) {
				return $to_ts;
			}

			if ( $from_ts > $to_ts ) {
				return new WP_Error(
					'ability_invalid_input',
					__( 'date_from must not be after date_to.', 'aryo-activity-log' ),
					array( 'status' => \WP_Http::BAD_REQUEST )
				);
			}

			$range_days = (int) floor( ( $to_ts - $from_ts ) / DAY_IN_SECONDS ) + 1;
			if ( $range_days > 90 ) {
				return new WP_Error(
					'ability_invalid_input',
					__( 'The date range may not exceed 90 days.', 'aryo-activity-log' ),
					array( 'status' => \WP_Http::BAD_REQUEST )
				);
			}

			return array(
				'from_ts' => $from_ts,
				'to_ts'   => $to_ts,
			);
		}

		$period = $has_period ? $input['period'] : '24h';
		$now    = null !== $now ? (int) $now : (int) current_time( 'timestamp' );

		switch ( $period ) {
			case 'today':
				$local_now   = ( new DateTimeImmutable( '@' . AAL_Log_Query::hist_time_to_unix( $now ) ) )->setTimezone( wp_timezone() );
				$midnight_dt = $local_now->setTime( 0, 0, 0 );
				$from_ts     = AAL_Log_Query::local_datetime_to_hist_time( $midnight_dt );
				$to_ts       = $now;
				break;
			case '24h':
				$from_ts = $now - DAY_IN_SECONDS;
				$to_ts   = $now;
				break;
			case '7d':
				$from_ts = $now - ( 7 * DAY_IN_SECONDS );
				$to_ts   = $now;
				break;
			case '30d':
				$from_ts = $now - ( 30 * DAY_IN_SECONDS );
				$to_ts   = $now;
				break;
			default:
				return new WP_Error(
					'ability_invalid_input',
					__( 'Invalid period value.', 'aryo-activity-log' ),
					array( 'status' => \WP_Http::BAD_REQUEST )
				);
		}

		return array(
			'from_ts' => (int) $from_ts,
			'to_ts'   => (int) $to_ts,
		);
	}

	/**
	 * @param mixed $date ISO-8601 calendar date (Y-m-d).
	 * @return int|WP_Error Start of day timestamp in site timezone.
	 */
	private static function parse_summary_iso_date_start( $date ) {
		$parts = self::parse_summary_iso_date_parts( $date );
		if ( is_wp_error( $parts ) ) {
			return $parts;
		}

		$date_time = sprintf( '%04d-%02d-%02d 00:00:00', $parts['year'], $parts['month'], $parts['day'] );
		$dt        = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_time, wp_timezone() );
		if ( false === $dt ) {
			return new WP_Error(
				'ability_invalid_input',
				__( 'Invalid date_from or date_to value.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		return AAL_Log_Query::local_datetime_to_hist_time( $dt );
	}

	/**
	 * @param mixed $date ISO-8601 calendar date (Y-m-d).
	 * @return int|WP_Error End of day timestamp in site timezone.
	 */
	private static function parse_summary_iso_date_end( $date ) {
		$parts = self::parse_summary_iso_date_parts( $date );
		if ( is_wp_error( $parts ) ) {
			return $parts;
		}

		$date_time = sprintf( '%04d-%02d-%02d 23:59:59', $parts['year'], $parts['month'], $parts['day'] );
		$dt        = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_time, wp_timezone() );
		if ( false === $dt ) {
			return new WP_Error(
				'ability_invalid_input',
				__( 'Invalid date_from or date_to value.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		return AAL_Log_Query::local_datetime_to_hist_time( $dt );
	}

	/**
	 * @param mixed $date ISO-8601 calendar date (Y-m-d).
	 * @return array{year: int, month: int, day: int}|WP_Error
	 */
	private static function parse_summary_iso_date_parts( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
			return new WP_Error(
				'ability_invalid_input',
				__( 'Invalid date_from or date_to format.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		$year  = (int) $matches[1];
		$month = (int) $matches[2];
		$day   = (int) $matches[3];

		if ( ! checkdate( $month, $day, $year ) ) {
			return new WP_Error(
				'ability_invalid_input',
				__( 'Invalid date_from or date_to value.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		return array(
			'year'  => $year,
			'month' => $month,
			'day'   => $day,
		);
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
	public static function get_summarize_activity_input_schema() {
		return array(
			'type'                 => 'object',
			'default'              => array(),
			'properties'           => array(
				'period'    => array(
					'type'        => 'string',
					'description' => __( 'Relative time window (default 24h when input is empty).', 'aryo-activity-log' ),
					'enum'        => array( 'today', '24h', '7d', '30d' ),
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => __( 'Range start date (ISO-8601 Y-m-d, site timezone).', 'aryo-activity-log' ),
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
				'date_to'   => array(
					'type'        => 'string',
					'description' => __( 'Range end date (ISO-8601 Y-m-d, site timezone). Max 90 days from date_from.', 'aryo-activity-log' ),
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_summarize_activity_output_schema() {
		$count_map = array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'integer' ),
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'range'          => array(
					'type'       => 'object',
					'properties' => array(
						'from' => array( 'type' => 'string' ),
						'to'   => array( 'type' => 'string' ),
					),
					'required'   => array( 'from', 'to' ),
				),
				'total'          => array( 'type' => 'integer' ),
				'by_action'      => $count_map,
				'by_object_type' => $count_map,
				'top_users'      => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'user_id' => array( 'type' => 'integer' ),
							'name'    => array( 'type' => 'string' ),
							'count'   => array( 'type' => 'integer' ),
						),
						'required'   => array( 'user_id', 'name', 'count' ),
					),
				),
				'by_channel'     => $count_map,
				'by_app_name'    => $count_map,
				'time_buckets'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'bucket' => array( 'type' => 'string' ),
							'count'  => array( 'type' => 'integer' ),
						),
						'required'   => array( 'bucket', 'count' ),
					),
				),
			),
			'required'   => array(
				'range',
				'total',
				'by_action',
				'by_object_type',
				'top_users',
				'by_channel',
				'by_app_name',
				'time_buckets',
			),
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
