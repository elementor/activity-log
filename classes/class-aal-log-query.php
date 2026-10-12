<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAL_Log_Query {

	/** Site-local instant to stored hist_time (same space as current_time( 'timestamp' )). */
	public static function local_datetime_to_hist_time( DateTimeImmutable $local_datetime ) {
		return $local_datetime->getTimestamp() + wp_timezone()->getOffset( $local_datetime );
	}

	public static function hist_time_to_unix( $hist_time ) {
		return self::hist_time_to_local_datetime( (int) $hist_time )->getTimestamp();
	}

	public static function hist_time_to_iso8601( $hist_time ) {
		return wp_date( 'c', self::hist_time_to_unix( (int) $hist_time ) );
	}

	/**
	 * Map stored hist_time to a site-timezone DateTimeImmutable (handles DST ambiguity).
	 */
	protected static function hist_time_to_local_datetime( $hist_time ) {
		$timezone = wp_timezone();
		$unix     = $hist_time - $timezone->getOffset( new DateTimeImmutable( 'now', $timezone ) );
		$local    = ( new DateTimeImmutable( '@' . $unix ) )->setTimezone( $timezone );

		if ( $local->getTimestamp() + $timezone->getOffset( $local ) !== $hist_time ) {
			$unix  = $hist_time - $timezone->getOffset( $local );
			$local = ( new DateTimeImmutable( '@' . $unix ) )->setTimezone( $timezone );
		}

		return $local;
	}

	protected $roles = array();
	protected $caps = array();
	protected $allow_caps = array();

	public function __construct() {
		$this->init_roles();
		$this->init_caps();
	}

	protected function init_roles() {
		$this->roles = apply_filters(
			'aal_init_roles',
			array(
				'manage_options' => array(
					'Core',
					'Export',
					'Posts',
					'Taxonomies',
					'Users',
					'Emails',
					'Options',
					'Attachments',
					'Plugins',
					'Widgets',
					'Themes',
					'Menus',
					'Comments',
					'Post',
					'Taxonomy',
					'User',
					'Plugin',
					'Widget',
					'Theme',
					'Menu',
				),
				'edit_pages' => array(
					'Posts',
					'Taxonomies',
					'Attachments',
					'Comments',
					'Post',
					'Taxonomy',
					'Attachment',
				),
			)
		);
	}

	protected function init_caps() {
		$default_rules = array(
			'administrator',
			'editor',
			'author',
			'guest',
		);

		global $wp_roles;

		$all_roles = array();
		if ( isset( $wp_roles->roles ) ) {
			foreach ( $wp_roles->roles as $key => $wp_role ) {
				$all_roles[] = $key;
			}
		}

		$this->caps = apply_filters(
			'aal_init_caps',
			array(
				'administrator' => array_unique( array_merge( $default_rules, $all_roles ) ),
				'editor'        => array( 'editor', 'author', 'guest' ),
				'author'        => array( 'author', 'guest' ),
			)
		);
	}

	/**
	 * @return array|false Allowed caps for the current user, or false if denied.
	 */
	public function get_allow_caps() {
		if ( ! empty( $this->allow_caps ) ) {
			return $this->allow_caps;
		}

		$user = get_user_by( 'id', get_current_user_id() );
		if ( ! $user ) {
			return false;
		}

		$user_cap   = strtolower( key( $user->caps ) );
		$allow_caps = array();

		foreach ( $this->caps as $key => $cap_allow ) {
			if ( $key === $user_cap ) {
				$allow_caps = array_merge( $allow_caps, $cap_allow );
				break;
			}
		}

		if ( is_super_admin() || current_user_can( 'view_all_aryo_activity_log' ) ) {
			$allow_caps = $this->caps['administrator'];
		}

		if ( empty( $allow_caps ) ) {
			return false;
		}

		$this->allow_caps = array_unique( $allow_caps );

		return $this->allow_caps;
	}

	/**
	 * @return string|false SQL fragment, or false if denied.
	 */
	public function get_where_by_role() {
		$allow_modules = array();

		foreach ( $this->roles as $key => $role ) {
			if ( current_user_can( $key ) || current_user_can( 'view_all_aryo_activity_log' ) ) {
				$allow_modules = array_merge( $allow_modules, $role );
			}
		}

		if ( empty( $allow_modules ) ) {
			return false;
		}

		$allow_modules = array_unique( $allow_modules );

		$where = array();
		foreach ( $allow_modules as $type ) {
			$where[] = '`object_type` = \'' . esc_sql( $type ) . '\'';
		}

		$allow_caps = $this->get_allow_caps();
		if ( false === $allow_caps ) {
			return false;
		}

		$where_caps = array();
		foreach ( $allow_caps as $cap ) {
			$where_caps[] = '`user_caps` = \'' . esc_sql( $cap ) . '\'';
		}

		return 'AND (' . implode( ' OR ', $where ) . ') AND (' . implode( ' OR ', $where_caps ) . ')';
	}

	/**
	 * Fetch one log row by ID, respecting role/cap visibility rules.
	 *
	 * @param int $histid Log entry ID.
	 * @return object|null
	 */
	public function get_item( $histid ) {
		global $wpdb;

		$role_where = $this->get_where_by_role();
		if ( false === $role_where ) {
			return null;
		}

		$item = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->activity_log . '` WHERE `histid` = %d ' . $role_where,
				(int) $histid
			)
		);

		return $item ? $item : null;
	}

	/**
	 * Query activity log rows.
	 *
	 * @param array $args {
	 *     @type string $typeshow    Filter by object_type.
	 *     @type string $showaction  Filter by action.
	 *     @type string $filter_ip   Filter by IP.
	 *     @type int    $usershow    Filter by user_id.
	 *     @type string $capshow     Filter by user_caps.
	 *     @type string $sourceshow  Filter by request_source channel.
	 *     @type string $dateshow    Date filter: today|yesterday|week (last 7 days)|month (last 30 days)|dd/mm/yyyy.
	 *     @type string $s           Search term.
	 *     @type string $orderby     Column to sort by (hist_time|hist_ip). Default hist_time.
	 *     @type string $order       ASC or DESC. Default DESC.
	 *     @type int    $page        1-indexed page number.
	 *     @type int    $per_page    Items per page (max 100).
	 *     @type bool   $exclude_ip  When true, omit hist_ip from the search columns.
	 * }
	 * @return array { items: object[], total: int, pages: int } or empty on access denied.
	 */
	public function query( $args = array() ) {
		global $wpdb;

		$role_where = $this->get_where_by_role();
		if ( false === $role_where ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 0,
			);
		}

		$args = wp_parse_args( $args, array(
			'typeshow'   => '',
			'showaction' => '',
			'filter_ip'  => '',
			'usershow'   => '',
			'capshow'    => '',
			'sourceshow' => '',
			'dateshow'   => '',
			's'          => '',
			'orderby'    => 'hist_time',
			'order'      => 'DESC',
			'page'       => 1,
			'per_page'   => 50,
			'exclude_ip' => false,
		) );

		$where = ' WHERE 1 = 1';

		if ( ! empty( $args['typeshow'] ) ) {
			$where .= $wpdb->prepare( ' AND `object_type` = %s', sanitize_text_field( $args['typeshow'] ) );
		}

		if ( '' !== $args['showaction'] && ! empty( $args['showaction'] ) ) {
			$where .= $wpdb->prepare( ' AND `action` = %s', sanitize_text_field( $args['showaction'] ) );
		}

		if ( '' !== $args['filter_ip'] && ! empty( $args['filter_ip'] ) ) {
			$where .= $wpdb->prepare( ' AND `hist_ip` = %s', sanitize_text_field( $args['filter_ip'] ) );
		}

		if ( '' !== $args['usershow'] && '' !== (string) $args['usershow'] ) {
			$where .= $wpdb->prepare( ' AND `user_id` = %d', intval( $args['usershow'] ) );
		}

		if ( ! empty( $args['capshow'] ) ) {
			$where .= $wpdb->prepare( ' AND `user_caps` = %s', strtolower( sanitize_text_field( $args['capshow'] ) ) );
		}

		if ( AAL_Maintenance::is_schema_ready( '1.1' ) && ! empty( $args['sourceshow'] ) ) {
			$source_filter = sanitize_key( $args['sourceshow'] );
			if ( 'app_password' === $source_filter ) {
				$where .= " AND (`request_source` LIKE '%|app:%' OR `request_source` LIKE 'app:%')";
			} else {
				$where .= $wpdb->prepare(
					" AND (`request_source` = %s OR `request_source` LIKE %s)",
					$source_filter,
					$wpdb->esc_like( $source_filter ) . '|%'
				);
			}
		}

		if ( ! empty( $args['dateshow'] ) ) {
			$current_time = current_time( 'timestamp' );
			$start_time   = 0;
			$end_time     = 0;

			if ( in_array( $args['dateshow'], array( 'today', 'yesterday', 'week', 'month' ), true ) ) {
				$start_time = mktime( 0, 0, 0, date( 'm', $current_time ), date( 'd', $current_time ), date( 'Y', $current_time ) );
				$end_time   = mktime( 23, 59, 59, date( 'm', $current_time ), date( 'd', $current_time ), date( 'Y', $current_time ) );

				if ( 'yesterday' === $args['dateshow'] ) {
					$start_time = strtotime( 'yesterday', $start_time );
					$end_time   = mktime( 23, 59, 59, date( 'm', $start_time ), date( 'd', $start_time ), date( 'Y', $start_time ) );
				} elseif ( 'week' === $args['dateshow'] ) {
					$start_time = strtotime( '-1 week', $start_time );
				} elseif ( 'month' === $args['dateshow'] ) {
					$start_time = strtotime( '-30 days', $start_time );
				}
			} else {
				$date_array = explode( '/', $args['dateshow'] );
				if ( 3 === count( $date_array ) ) {
					$start_time = mktime( 0, 0, 0, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2] );
					$end_time   = mktime( 23, 59, 59, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2] );
				}
			}

			if ( ! empty( $start_time ) && ! empty( $end_time ) ) {
				$where .= $wpdb->prepare( ' AND `hist_time` > %d AND `hist_time` < %d', $start_time, $end_time );
			}
		}

		if ( ! empty( $args['s'] ) ) {
			$search_like = '%' . $wpdb->esc_like( $args['s'] ) . '%';
			$columns     = array( 'object_name', 'object_subtype' );
			if ( empty( $args['exclude_ip'] ) ) {
				$columns[] = 'hist_ip';
			}
			$parts = array();
			foreach ( $columns as $column ) {
				$parts[] = '`' . $column . '` LIKE %s';
			}
			$where .= $wpdb->prepare(
				' AND (' . implode( ' OR ', $parts ) . ')',
				array_fill( 0, count( $columns ), $search_like )
			);
		}

		$allowed_orderby = array( 'hist_time', 'hist_ip' );
		$items_orderby   = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'hist_time';
		$items_order     = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$total_items = (int) $wpdb->get_var(
			'SELECT COUNT(`histid`) FROM `' . $wpdb->activity_log . '`'
			. $where . ' ' . $role_where
		);

		$items = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM `' . $wpdb->activity_log . '`'
			. $where . ' ' . $role_where
			. ' ORDER BY ' . $items_orderby . ' ' . $items_order
			. ' LIMIT %d, %d;',
			$offset,
			$per_page
		) );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total_items,
			'pages' => (int) ceil( $total_items / $per_page ),
		);
	}

	/**
	 * Get distinct filter options for dropdowns.
	 *
	 * @return array { users, roles, topics, actions, sources }
	 */
	public function get_filter_options() {
		global $wpdb;

		$role_where = $this->get_where_by_role();
		if ( false === $role_where ) {
			return array(
				'users'   => array(),
				'roles'   => array(),
				'topics'  => array(),
				'actions' => array(),
				'sources' => array(),
			);
		}

		$user_rows = $wpdb->get_results(
			'SELECT DISTINCT `user_id` FROM `' . $wpdb->activity_log . '`'
			. ' WHERE 1 = 1 ' . $role_where
			. ' GROUP BY `user_id` ORDER BY `user_id` LIMIT 100;'
		);

		$users = array();
		if ( $user_rows ) {
			foreach ( $user_rows as $_user ) {
				if ( 0 === (int) $_user->user_id ) {
					$users[] = array(
						'id'   => 0,
						'name' => __( 'N/A', 'aryo-activity-log' ),
					);
					continue;
				}
				$user = get_user_by( 'id', $_user->user_id );
				if ( $user ) {
					$users[] = array(
						'id'   => $user->ID,
						'name' => $user->user_nicename,
					);
				}
			}
		}

		$allow_caps = $this->get_allow_caps();
		$roles      = array();
		if ( false !== $allow_caps ) {
			foreach ( $allow_caps as $cap ) {
				$roles[] = array(
					'value' => $cap,
					'label' => ucwords( $cap ),
				);
			}
		}

		$topics_raw = $wpdb->get_col(
			'SELECT DISTINCT `object_type` FROM `' . $wpdb->activity_log . '`'
			. ' WHERE 1 = 1 ' . $role_where
			. ' GROUP BY `object_type` ORDER BY `object_type`;'
		);

		$actions_raw = $wpdb->get_results(
			'SELECT DISTINCT `action` FROM `' . $wpdb->activity_log . '`'
			. ' WHERE 1 = 1 ' . $role_where
			. ' GROUP BY `action` ORDER BY `action`;'
		);

		$actions = array();
		if ( $actions_raw ) {
			foreach ( $actions_raw as $action ) {
				$actions[] = array(
					'value' => $action->action,
					'label' => ucwords( str_replace( '_', ' ', $action->action ) ),
				);
			}
		}

		$sources = array();
		if ( AAL_Maintenance::is_schema_ready( '1.1' ) ) {
			$channel_labels = AAL_API::get_channel_labels();
			$sources        = array(
				array( 'value' => 'abilities', 'label' => $channel_labels['abilities'] ),
				array( 'value' => 'rest', 'label' => $channel_labels['rest'] ),
				array( 'value' => 'xmlrpc', 'label' => $channel_labels['xmlrpc'] ),
				array( 'value' => 'cli', 'label' => $channel_labels['cli'] ),
				array( 'value' => 'cron', 'label' => $channel_labels['cron'] ),
				array( 'value' => 'app_password', 'label' => __( 'App Password', 'aryo-activity-log' ) ),
			);
		}

		return array(
			'users'   => $users,
			'roles'   => $roles,
			'topics'  => $topics_raw ? $topics_raw : array(),
			'actions' => $actions,
			'sources' => $sources,
		);
	}

	/**
	 * @param array<string, mixed> $input Ability input (period or date_from/date_to).
	 * @param int|null             $now   Optional hist_time for period windows (tests).
	 * @return array<string, mixed>|WP_Error
	 */
	public function summarize_activity_from_input( $input, $now = null ) {
		$range = self::resolve_summarize_activity_range( $input, $now );
		if ( is_wp_error( $range ) ) {
			return $range;
		}

		return $this->summarize_activity( $range );
	}

	/**
	 * @param array<string, mixed> $input
	 * @param int|null             $now
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

			$from_ts = self::iso_calendar_date_to_hist_time( $input['date_from'], false );
			$to_ts   = self::iso_calendar_date_to_hist_time( $input['date_to'], true );
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
				$from_ts = self::local_datetime_to_hist_time( self::hist_time_to_local_datetime( $now )->setTime( 0, 0, 0 ) );
				$to_ts   = $now;
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
	 * @param mixed $date Y-m-d calendar date in the site timezone.
	 * @return int|WP_Error
	 */
	protected static function iso_calendar_date_to_hist_time( $date, $end_of_day ) {
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

		$dt = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			sprintf(
				'%04d-%02d-%02d %s',
				$year,
				$month,
				$day,
				$end_of_day ? '23:59:59' : '00:00:00'
			),
			wp_timezone()
		);
		if ( false === $dt ) {
			return new WP_Error(
				'ability_invalid_input',
				__( 'Invalid date_from or date_to value.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		return self::local_datetime_to_hist_time( $dt );
	}

	public function summarize_activity( $args ) {
		global $wpdb;

		$from_ts = (int) $args['from_ts'];
		$to_ts   = (int) $args['to_ts'];
		$empty   = $this->empty_activity_summary( $from_ts, $to_ts );

		$role_where = $this->get_where_by_role();
		if ( false === $role_where ) {
			return $empty;
		}

		$time_where = $wpdb->prepare(
			' WHERE `hist_time` >= %d AND `hist_time` <= %d',
			$from_ts,
			$to_ts
		);

		$total = (int) $wpdb->get_var(
			'SELECT COUNT(`histid`) FROM `' . $wpdb->activity_log . '`'
			. $time_where . ' ' . $role_where
		);

		$by_action = $this->summarize_group_counts(
			'action',
			$time_where,
			$role_where
		);

		$by_object_type = $this->summarize_group_counts(
			'object_type',
			$time_where,
			$role_where
		);

		$user_rows = $wpdb->get_results(
			'SELECT `user_id`, COUNT(`histid`) AS `cnt` FROM `' . $wpdb->activity_log . '`'
			. $time_where . ' ' . $role_where
			. ' GROUP BY `user_id` ORDER BY `cnt` DESC, `user_id` ASC LIMIT 10'
		);

		$top_users = array();
		if ( $user_rows ) {
			foreach ( $user_rows as $row ) {
				$user_id = (int) $row->user_id;
				$name    = __( 'N/A', 'aryo-activity-log' );
				if ( $user_id > 0 ) {
					$user = get_user_by( 'id', $user_id );
					if ( $user ) {
						$name = $user->display_name;
					}
				}
				$top_users[] = array(
					'user_id' => $user_id,
					'name'    => $name,
					'count'   => (int) $row->cnt,
				);
			}
		}

		$by_channel  = array();
		$by_app_name = array();
		if ( AAL_Maintenance::is_schema_ready( '1.1' ) ) {
			$source_rows = $wpdb->get_results(
				'SELECT `request_source`, COUNT(`histid`) AS `cnt` FROM `' . $wpdb->activity_log . '`'
				. $time_where . ' ' . $role_where
				. ' GROUP BY `request_source` ORDER BY `request_source` ASC'
			);
			if ( $source_rows ) {
				foreach ( $source_rows as $row ) {
					$parsed  = AAL_API::parse_request_source( $row->request_source );
					$channel = $parsed['channel'];
					if ( '' === $channel ) {
						$channel = 'unknown';
					}
					if ( ! isset( $by_channel[ $channel ] ) ) {
						$by_channel[ $channel ] = 0;
					}
					$by_channel[ $channel ] += (int) $row->cnt;

					$app_name = $parsed['app_name'];
					if ( '' !== $app_name ) {
						if ( ! isset( $by_app_name[ $app_name ] ) ) {
							$by_app_name[ $app_name ] = 0;
						}
						$by_app_name[ $app_name ] += (int) $row->cnt;
					}
				}
			}
		}

		$range_seconds = max( 0, $to_ts - $from_ts );
		$hourly        = $range_seconds <= ( 48 * HOUR_IN_SECONDS );
		$bucket_expr   = $this->get_summary_time_bucket_sql( $hourly );
		$bucket_rows   = $wpdb->get_results(
			'SELECT ' . $bucket_expr . ' AS `bucket_start`, COUNT(`histid`) AS `cnt` FROM `' . $wpdb->activity_log . '`'
			. $time_where . ' ' . $role_where
			. ' GROUP BY `bucket_start` ORDER BY `bucket_start` ASC'
		);

		$time_buckets = array();
		if ( $bucket_rows ) {
			foreach ( $bucket_rows as $row ) {
				$time_buckets[] = array(
					'bucket' => self::hist_time_to_iso8601( (int) $row->bucket_start ),
					'count'  => (int) $row->cnt,
				);
			}
		}

		ksort( $by_channel );
		ksort( $by_app_name );

		return array(
			'range'          => array(
				'from' => self::hist_time_to_iso8601( $from_ts ),
				'to'   => self::hist_time_to_iso8601( $to_ts ),
			),
			'total'          => $total,
			'by_action'      => $by_action,
			'by_object_type' => $by_object_type,
			'top_users'      => $top_users,
			'by_channel'     => $by_channel,
			'by_app_name'    => $by_app_name,
			'time_buckets'   => $time_buckets,
		);
	}

	protected function summarize_group_counts( $column, $time_where, $role_where ) {
		global $wpdb;

		$allowed = array( 'action', 'object_type' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			'SELECT `' . $column . '`, COUNT(`histid`) AS `cnt` FROM `' . $wpdb->activity_log . '`'
			. $time_where . ' ' . $role_where
			. ' GROUP BY `' . $column . '` ORDER BY `' . $column . '` ASC'
		);

		$counts = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$counts[ (string) $row->$column ] = (int) $row->cnt;
			}
		}

		return $counts;
	}

	/**
	 * SQL expression for hour or day buckets on stored hist_time values.
	 *
	 * @param bool $hourly True for hourly buckets, false for daily.
	 * @return string
	 */
	protected function get_summary_time_bucket_sql( $hourly ) {
		// Site-shifted hist_time cannot split the repeated local hour when DST ends;
		// both passes are counted in one hourly bucket (limit of current_time storage).
		$interval = $hourly ? HOUR_IN_SECONDS : DAY_IN_SECONDS;

		return sprintf( 'FLOOR(`hist_time` / %d) * %d', $interval, $interval );
	}

	protected function empty_activity_summary( $from_ts, $to_ts ) {
		return array(
			'range'          => array(
				'from' => self::hist_time_to_iso8601( $from_ts ),
				'to'   => self::hist_time_to_iso8601( $to_ts ),
			),
			'total'          => 0,
			'by_action'      => array(),
			'by_object_type' => array(),
			'top_users'      => array(),
			'by_channel'     => array(),
			'by_app_name'    => array(),
			'time_buckets'   => array(),
		);
	}
}
