<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAL_Log_Query {

	const AFTER_ID_SAFETY_WINDOW_SECONDS = 5;

	/** Site-local instant to stored hist_time (same space as current_time( 'timestamp' )). */
	public static function local_datetime_to_hist_time( DateTimeImmutable $local_datetime ) {
		return $local_datetime->getTimestamp() + wp_timezone()->getOffset( $local_datetime );
	}

	public static function hist_time_to_unix( $hist_time ) {
		return (int) $hist_time - self::hist_time_offset_seconds( (int) $hist_time );
	}

	/** Stored hist_time (site-shifted) to ISO-8601; gmdate for civil time, explicit offset (no wp_date). */
	public static function hist_time_to_iso8601( $hist_time ) {
		$hist_time = (int) $hist_time;
		$offset    = self::hist_time_offset_seconds( $hist_time );
		$sign      = $offset >= 0 ? '+' : '-';
		$abs       = abs( $offset );

		return gmdate( 'Y-m-d\TH:i:s', $hist_time )
			. sprintf( '%s%02d:%02d', $sign, (int) floor( $abs / HOUR_IN_SECONDS ), (int) floor( ( $abs % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ) );
	}

	protected static function hist_time_offset_seconds( $hist_time ) {
		$timezone  = wp_timezone();
		$unix_hint = $hist_time - $timezone->getOffset( new DateTimeImmutable( 'now', $timezone ) );
		$offsets   = array();
		$from      = $unix_hint - DAY_IN_SECONDS;
		$to        = $unix_hint + DAY_IN_SECONDS;

		if ( $from <= $to && $to <= PHP_INT_MAX ) {
			$transitions = $timezone->getTransitions( (int) $from, (int) $to );
			if ( is_array( $transitions ) ) {
				foreach ( $transitions as $transition ) {
					$offsets[] = (int) $transition['offset'];
				}
			}
		}

		$offsets[] = $timezone->getOffset( new DateTimeImmutable( '@' . $unix_hint ) );
		$offsets   = array_values( array_unique( $offsets ) );
		$chosen    = null;

		foreach ( $offsets as $offset ) {
			$unix = $hist_time - $offset;
			if ( $timezone->getOffset( new DateTimeImmutable( '@' . $unix ) ) !== $offset ) {
				continue;
			}
			if ( null === $chosen || $unix < $chosen['unix'] ) {
				// Fall-back hour: hist_time cannot distinguish DST passes; use the earlier (DST) one.
				$chosen = array(
					'unix'   => $unix,
					'offset' => $offset,
				);
			}
		}

		if ( null !== $chosen ) {
			return $chosen['offset'];
		}

		return $timezone->getOffset( new DateTimeImmutable( '@' . $unix_hint ) );
	}

	protected $roles = array();
	protected $caps = array();
	protected $allow_caps = array();

	/**
	 * When true, skip role/cap visibility restrictions (opt-in for trusted callers such as WP-CLI).
	 *
	 * @var bool
	 */
	protected $unrestricted = false;

	/**
	 * @param array $args {
	 *     @type bool $unrestricted Skip role/cap filters when true.
	 * }
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args( $args, array( 'unrestricted' => false ) );
		$this->unrestricted = ! empty( $args['unrestricted'] );
		$this->init_roles();
		$this->init_caps();
	}

	/**
	 * @return bool
	 */
	protected function is_unrestricted() {
		return $this->unrestricted;
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
		if ( $this->is_unrestricted() ) {
			$all_caps = array();
			foreach ( $this->caps as $cap_allow ) {
				$all_caps = array_merge( $all_caps, $cap_allow );
			}

			return array_values( array_unique( $all_caps ) );
		}

		if ( ! empty( $this->allow_caps ) ) {
			return $this->allow_caps;
		}

		$user = get_user_by( 'id', get_current_user_id() );
		if ( ! $user ) {
			return false;
		}

		$user_role  = ! empty( $user->roles[0] ) ? strtolower( $user->roles[0] ) : '';
		$allow_caps = array();

		foreach ( $this->caps as $key => $cap_allow ) {
			if ( $key === $user_role ) {
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
		if ( $this->is_unrestricted() ) {
			return '';
		}

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
	 * @param string            $role_where      From get_where_by_role().
	 * @param array<int, mixed> $prepare_args    Values for placeholders in $before_role_sql and $after_role_sql.
	 * @return string
	 */
	protected function prepare_summary_sql( $role_where, $before_role_sql, $after_role_sql, $prepare_args ) {
		global $wpdb;

		return $wpdb->prepare( $before_role_sql . ' ' . $role_where . $after_role_sql, $prepare_args );
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
	 * Resolve a dateshow filter value to start/end hist_time timestamps.
	 *
	 * @param string $dateshow Filter value from the activity log UI.
	 * @return array{start: int, end: int}|null Start and end timestamps, or null when invalid.
	 */
	public static function get_date_range( $dateshow ) {
		if ( empty( $dateshow ) ) {
			return null;
		}

		$current_time = current_time( 'timestamp' );
		$start_time   = 0;
		$end_time     = 0;

		if ( in_array( $dateshow, array( 'today', 'yesterday', 'week', 'month' ), true ) ) {
			$start_time = mktime( 0, 0, 0, date( 'm', $current_time ), date( 'd', $current_time ), date( 'Y', $current_time ) );
			$end_time   = mktime( 23, 59, 59, date( 'm', $current_time ), date( 'd', $current_time ), date( 'Y', $current_time ) );

			if ( 'yesterday' === $dateshow ) {
				$start_time = strtotime( 'yesterday', $start_time );
				$end_time   = mktime( 23, 59, 59, date( 'm', $start_time ), date( 'd', $start_time ), date( 'Y', $start_time ) );
			} elseif ( 'week' === $dateshow ) {
				$start_time = strtotime( '-1 week', $start_time );
			} elseif ( 'month' === $dateshow ) {
				$start_time = strtotime( '-30 days', $start_time );
			}
		} else {
			$date_array = explode( '/', $dateshow );
			if ( 3 === count( $date_array ) ) {
				$start_time = mktime( 0, 0, 0, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2] );
				$end_time   = mktime( 23, 59, 59, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2] );
			}
		}

		if ( empty( $start_time ) || empty( $end_time ) ) {
			return null;
		}

		return array(
			'start' => (int) $start_time,
			'end'   => (int) $end_time,
		);
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
	 *     @type bool   $skip_count  When true, skip COUNT and infer has_more from per_page + 1 fetch.
	 *     @type int    $after_id    When set, return rows with histid greater than this (incremental fetch). orderby, order and page are ignored.
	 * }
	 * @return array { items: object[], total: int|null, pages: int|null, has_more: bool, next_after_id?: int } (empty items on access denied).
	 */
	public function query( $args = array() ) {
		global $wpdb;

		$role_where = $this->get_where_by_role();
		if ( false === $role_where ) {
			$denied = array(
				'items'    => array(),
				'total'    => 0,
				'pages'    => 0,
				'has_more' => false,
			);
			if ( is_array( $args ) && array_key_exists( 'after_id', $args ) ) {
				$denied['next_after_id'] = (int) $args['after_id'];
			}
			return $denied;
		}

		$after_id_mode = array_key_exists( 'after_id', $args );

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
			'skip_count' => false,
			'before_id'  => 0,
			'min_id'     => 0,
			'after_id'   => 0,
		) );

		$after_id = (int) $args['after_id'];

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
			$date_range = self::get_date_range( $args['dateshow'] );
			if ( null !== $date_range ) {
				$where .= $wpdb->prepare( ' AND `hist_time` > %d AND `hist_time` < %d', $date_range['start'], $date_range['end'] );
			}
		}

		if ( ! empty( $args['min_id'] ) ) {
			$where .= $wpdb->prepare( ' AND `histid` >= %d', (int) $args['min_id'] );
		}

		if ( ! empty( $args['before_id'] ) ) {
			$where .= $wpdb->prepare( ' AND `histid` < %d', (int) $args['before_id'] );
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

		if ( $after_id_mode ) {
			$where .= $wpdb->prepare( ' AND `histid` > %d', $after_id );

			// Stop before the oldest row that is still inside the safety window (or dated in the future, e.g. after a DST fall-back),
			// so the cursor never moves past a row that a later poll would return.
			$safety_cutoff  = current_time( 'timestamp' ) - self::AFTER_ID_SAFETY_WINDOW_SECONDS;
			$barrier_histid = (int) $wpdb->get_var(
				'SELECT MIN(`histid`) FROM `' . $wpdb->activity_log . '`'
				. $where
				. $wpdb->prepare( ' AND `hist_time` > %d', $safety_cutoff )
				. ' ' . $role_where
			);
			if ( $barrier_histid > 0 ) {
				$where .= $wpdb->prepare( ' AND `histid` < %d', $barrier_histid );
			}
		}

		$allowed_orderby = array( 'hist_time', 'hist_ip', 'histid' );
		$items_orderby   = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'hist_time';
		$items_order     = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		if ( $after_id_mode ) {
			$items_orderby = 'histid';
			$items_order   = 'ASC';
			$offset        = 0;
		}

		$skip_count = ! empty( $args['skip_count'] ) || $after_id_mode;

		$total_items = null;
		$pages       = null;

		if ( ! $skip_count ) {
			$total_items = (int) $wpdb->get_var(
				'SELECT COUNT(`histid`) FROM `' . $wpdb->activity_log . '`'
				. $where . ' ' . $role_where
			);
			$pages = (int) ceil( $total_items / $per_page );
		}

		$limit_count = $skip_count ? $per_page + 1 : $per_page;

		$items = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM `' . $wpdb->activity_log . '`'
			. $where . ' ' . $role_where
			. ' ORDER BY ' . $items_orderby . ' ' . $items_order
			. ' LIMIT %d, %d;',
			$offset,
			$limit_count
		) );

		if ( $skip_count ) {
			$has_more = $items && count( $items ) > $per_page;
			if ( $has_more ) {
				$items = array_slice( $items, 0, $per_page );
			}
		} else {
			$has_more = $page < $pages;
		}

		$result = array(
			'items'    => $items ? $items : array(),
			'total'    => $total_items,
			'pages'    => $pages,
			'has_more' => $has_more,
		);

		if ( $after_id_mode ) {
			$next_after_id = $after_id;
			if ( ! empty( $result['items'] ) ) {
				$last_item     = end( $result['items'] );
				$next_after_id = (int) $last_item->histid;
			}
			$result['next_after_id'] = $next_after_id;
		}

		return $result;
	}

	/**
	 * Yield batches of log items using before_id cursor pagination (histid DESC).
	 *
	 * @param array $args       Query filters (merged with per-batch pagination).
	 * @param int   $batch_size Items per batch.
	 * @return Generator<int, object[]>
	 */
	public function iterate_items( array $args, $batch_size ) {
		$batch_size = max( 1, (int) $batch_size );
		$before_id  = 0;

		while ( true ) {
			$batch_args = array_merge(
				$args,
				array(
					'orderby'    => 'histid',
					'order'      => 'DESC',
					'skip_count' => true,
					'page'       => 1,
					'per_page'   => $batch_size,
				)
			);

			if ( $before_id > 0 ) {
				$batch_args['before_id'] = $before_id;
			}

			$result = $this->query( $batch_args );
			if ( empty( $result['items'] ) ) {
				break;
			}

			yield $result['items'];

			$last_item = end( $result['items'] );
			$before_id = (int) $last_item->histid;

			if ( empty( $result['has_more'] ) ) {
				break;
			}
		}
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

		global $wp_roles;

		$allow_caps = $this->get_allow_caps();
		$roles      = array();
		if ( false !== $allow_caps ) {
			foreach ( $allow_caps as $cap ) {
				$label = isset( $wp_roles->role_names[ $cap ] ) ? $wp_roles->role_names[ $cap ] : ucwords( $cap );
				$roles[] = array(
					'value' => $cap,
					'label' => $label,
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

		if ( ! $has_period ) {
			$input['period'] = '24h';
		}

		$period  = $input['period'];
		$now     = null !== $now ? (int) $now : (int) current_time( 'timestamp' );
		$periods = array(
			'24h' => DAY_IN_SECONDS,
			'7d'  => 7 * DAY_IN_SECONDS,
			'30d' => 30 * DAY_IN_SECONDS,
		);

		if ( 'today' === $period ) {
			$from_ts = self::iso_calendar_date_to_hist_time( gmdate( 'Y-m-d', $now ), false );
			if ( is_wp_error( $from_ts ) ) {
				return $from_ts;
			}
			$to_ts = $now;
		} elseif ( isset( $periods[ $period ] ) ) {
			$from_ts = $now - $periods[ $period ];
			$to_ts   = $now;
		} else {
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
		$parts = explode( '-', $date );
		$year  = (int) $parts[0];
		$month = (int) $parts[1];
		$day   = (int) $parts[2];

		if ( ! checkdate( $month, $day, $year ) ) {
			return new WP_Error(
				'ability_invalid_input',
				__( 'Invalid date_from or date_to value.', 'aryo-activity-log' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		$dt = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			sprintf( '%s %s', $date, $end_of_day ? '23:59:59' : '00:00:00' ),
			wp_timezone()
		);

		return self::local_datetime_to_hist_time( $dt );
	}

	public function summarize_activity( $args ) {
		global $wpdb;

		$from_ts    = (int) $args['from_ts'];
		$to_ts      = (int) $args['to_ts'];
		$role_where = $this->get_where_by_role();

		if ( false === $role_where ) {
			return $this->build_activity_summary( $from_ts, $to_ts );
		}

		$count_sql = $this->prepare_summary_sql(
			$role_where,
			'SELECT COUNT(`histid`) FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d',
			'',
			array( $wpdb->activity_log, $from_ts, $to_ts )
		);
		$total     = (int) $wpdb->get_var( $count_sql );

		$by_action      = $this->summarize_group_counts( $role_where, 'action', $from_ts, $to_ts );
		$by_object_type = $this->summarize_group_counts( $role_where, 'object_type', $from_ts, $to_ts );

		$user_sql  = $this->prepare_summary_sql(
			$role_where,
			'SELECT `user_id`, COUNT(`histid`) AS `cnt` FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d',
			' GROUP BY `user_id` ORDER BY `cnt` DESC, `user_id` ASC LIMIT 10',
			array( $wpdb->activity_log, $from_ts, $to_ts )
		);
		$user_rows = $wpdb->get_results( $user_sql );

		$top_users = array();
		if ( $user_rows ) {
			foreach ( $user_rows as $row ) {
				$user_id = (int) $row->user_id;
				$name    = __( 'N/A', 'aryo-activity-log' );
				if ( $user_id > 0 ) {
					$user = get_user_by( 'id', $user_id );
					if ( $user ) {
						$name = $user->user_nicename;
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
			$source_sql  = $this->prepare_summary_sql(
				$role_where,
				'SELECT `request_source`, COUNT(`histid`) AS `cnt` FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d',
				' GROUP BY `request_source` ORDER BY `request_source` ASC',
				array( $wpdb->activity_log, $from_ts, $to_ts )
			);
			$source_rows = $wpdb->get_results( $source_sql );
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
		$interval      = $hourly ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
		$bucket_sql    = $this->prepare_summary_sql(
			$role_where,
			'SELECT FLOOR(`hist_time` / %d) * %d AS `bucket_start`, COUNT(`histid`) AS `cnt` FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d',
			' GROUP BY `bucket_start` ORDER BY `bucket_start` ASC',
			array( $interval, $interval, $wpdb->activity_log, $from_ts, $to_ts )
		);
		$bucket_rows   = $wpdb->get_results( $bucket_sql );

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

		return $this->build_activity_summary(
			$from_ts,
			$to_ts,
			array(
				'total'          => $total,
				'by_action'      => $by_action,
				'by_object_type' => $by_object_type,
				'top_users'      => $top_users,
				'by_channel'     => $by_channel,
				'by_app_name'    => $by_app_name,
				'time_buckets'   => $time_buckets,
			)
		);
	}

	protected function summarize_group_counts( $role_where, $column, $from_ts, $to_ts ) {
		global $wpdb;

		$sql  = $this->prepare_summary_sql(
			$role_where,
			'SELECT %i, COUNT(`histid`) AS `cnt` FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d',
			' GROUP BY %i ORDER BY %i ASC',
			array( $column, $wpdb->activity_log, $from_ts, $to_ts, $column, $column )
		);
		$rows = $wpdb->get_results( $sql );

		$counts = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$counts[ (string) $row->$column ] = (int) $row->cnt;
			}
		}

		return $counts;
	}

	protected function build_activity_summary( $from_ts, $to_ts, $values = array() ) {
		return array_merge(
			array(
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
			),
			$values
		);
	}

	/**
	 * @param int                  $from_ts
	 * @param int                  $to_ts
	 * @param array<int, string>   $rules
	 * @return array<string, mixed>
	 */
	public function detect_suspicious_events( $from_ts, $to_ts, $rules ) {
		global $wpdb;

		$from_ts    = (int) $from_ts;
		$to_ts      = (int) $to_ts;
		$rules      = array_values( array_intersect( $rules, array_keys( AAL_Abilities::SUSPICIOUS_RULES ) ) );
		$range      = array(
			'from' => self::hist_time_to_iso8601( $from_ts ),
			'to'   => self::hist_time_to_iso8601( $to_ts ),
		);
		$findings   = array();
		$truncated  = false;
		$role_where = $this->get_where_by_role();
		$groups     = array();

		if ( false === $role_where || empty( $rules ) ) {
			return array( 'range' => $range, 'findings' => $findings, 'truncated' => $truncated );
		}

		$want_burst = in_array( 'failed_login_burst', $rules, true );
		$want_after = in_array( 'login_after_failures', $rules, true );
		$row_rules  = array_intersect( $rules, array( 'new_admin', 'plugin_activated', 'sensitive_option', 'agent_off_hours' ) );
		$burst_rows = array();
		$first_fail = array();

		if ( $want_burst || $want_after ) {
			$burst_sql  = $this->prepare_summary_sql(
				$role_where,
				'SELECT `hist_ip`, COUNT(`histid`) AS `cnt`, MIN(`hist_time`) AS `min_time`, MAX(`hist_time`) AS `max_time`, GROUP_CONCAT(`histid` ORDER BY `histid` DESC) AS `ids`, MAX(`object_name`) AS `actor` FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d AND `action` = %s',
				' GROUP BY `hist_ip` HAVING `cnt` >= %d ORDER BY `cnt` DESC LIMIT 50',
				array( $wpdb->activity_log, $from_ts, $to_ts, 'failed_login', AAL_Abilities::FAILED_LOGIN_THRESHOLD )
			);
			$burst_rows = $wpdb->get_results( $burst_sql );
		}

		if ( $want_burst && $burst_rows ) {
			foreach ( $burst_rows as $row ) {
				$key = 'failed_login_burst:' . $row->hist_ip;
				$this->merge_suspicious_group(
					$groups,
					$key,
					'failed_login_burst',
					(string) $row->actor,
					(int) $row->min_time,
					(int) $row->max_time,
					(int) $row->cnt,
					(string) $row->ids
				);
			}
		}

		if ( $want_after && $burst_rows ) {
			$ips = wp_list_pluck( $burst_rows, 'hist_ip' );
			foreach ( $burst_rows as $row ) {
				$first_fail[ $row->hist_ip ] = (int) $row->min_time;
			}
			$placeholders = implode( ', ', array_fill( 0, count( $ips ), '%s' ) );
			$login_sql    = $this->prepare_summary_sql(
				$role_where,
				'SELECT `histid`, `user_id`, `action`, `object_type`, `object_name`, `object_id`, `hist_time`, `request_source`, `hist_ip` FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d AND `action` = %s AND `hist_ip` IN (' . $placeholders . ')',
				' ORDER BY `hist_time` DESC LIMIT 50',
				array_merge( array( $wpdb->activity_log, $from_ts, $to_ts, 'logged_in' ), $ips )
			);
			$login_rows   = $wpdb->get_results( $login_sql );
			if ( $login_rows ) {
				foreach ( $login_rows as $login ) {
					$ip = $login->hist_ip;
					if ( ! isset( $first_fail[ $ip ] ) || (int) $login->hist_time <= $first_fail[ $ip ] ) {
						continue;
					}
					$key = 'login_after_failures:' . $ip;
					$this->merge_suspicious_group(
						$groups,
						$key,
						'login_after_failures',
						(string) $login->object_name,
						(int) $login->hist_time,
						(int) $login->hist_time,
						1,
						(string) $login->histid
					);
				}
			}
		}

		if ( ! empty( $row_rules ) ) {
			$parts       = array();
			$parts_args  = array();
			if ( in_array( 'new_admin', $row_rules, true ) ) {
				$parts[]      = "(`object_type` = 'Users' AND `action` = 'registered')";
				$parts[]      = "(`object_type` = 'Users' AND `object_subtype` = 'Role' AND `action` = 'updated' AND `meta` LIKE %s)";
				$parts_args[] = '%' . $wpdb->esc_like( 'administrator' ) . '%';
			}
			if ( in_array( 'plugin_activated', $row_rules, true ) ) {
				$parts[] = "(`object_type` = 'Plugins' AND `action` = 'activated')";
			}
			if ( in_array( 'sensitive_option', $row_rules, true ) ) {
				$option_placeholders = implode( ', ', array_fill( 0, count( AAL_Abilities::SENSITIVE_OPTIONS ), '%s' ) );
				$parts[]             = "(`object_type` = 'Options' AND `action` = 'updated' AND `object_name` IN ($option_placeholders))";
				$parts_args          = array_merge( $parts_args, AAL_Abilities::SENSITIVE_OPTIONS );
			}
			if ( in_array( 'agent_off_hours', $row_rules, true ) ) {
				$parts[] = "(`request_source` LIKE 'abilities%' OR `request_source` LIKE '%|app:%' OR `request_source` LIKE 'app:%')";
			}

			$row_sql   = $this->prepare_summary_sql(
				$role_where,
				'SELECT `histid`, `user_id`, `action`, `object_type`, `object_subtype`, `object_name`, `object_id`, `hist_time`, `request_source`, IF(`object_subtype` = \'Role\', `meta`, NULL) AS `meta` FROM %i WHERE `hist_time` >= %d AND `hist_time` <= %d AND (' . implode( ' OR ', $parts ) . ')',
				' ORDER BY `hist_time` DESC LIMIT %d',
				array_merge( array( $wpdb->activity_log, $from_ts, $to_ts ), $parts_args, array( AAL_Abilities::SUSPICIOUS_ROW_LIMIT ) )
			);
			$row_rows  = $wpdb->get_results( $row_sql );
			$truncated = $row_rows && count( $row_rows ) >= AAL_Abilities::SUSPICIOUS_ROW_LIMIT;
			$admin_ids = array();
			if ( in_array( 'new_admin', $row_rules, true ) && $row_rows ) {
				$candidate_ids = array();
				foreach ( $row_rows as $row ) {
					if ( 'Users' === $row->object_type && 'registered' === $row->action ) {
						$candidate_ids[] = (int) $row->object_id;
					}
				}
				if ( ! empty( $candidate_ids ) ) {
					$admin_ids = array_map(
						'intval',
						get_users(
							array(
								'include' => array_values( array_unique( $candidate_ids ) ),
								'role'    => 'administrator',
								'fields'  => 'ID',
							)
						)
					);
				}
			}

			if ( $row_rows ) {
				foreach ( $row_rows as $row ) {
					$rule = $this->classify_row( $row, $admin_ids );
					if ( null === $rule || ! in_array( $rule, $row_rules, true ) ) {
						continue;
					}
					$actor = $this->suspicious_actor_from_row( $row );
					$key   = $rule . ':' . $actor;
					$this->merge_suspicious_group(
						$groups,
						$key,
						$rule,
						$actor,
						(int) $row->hist_time,
						(int) $row->hist_time,
						1,
						(string) $row->histid
					);
				}
			}
		}

		foreach ( $groups as $group ) {
			$findings[] = $this->build_suspicious_finding( $group );
		}

		return array( 'range' => $range, 'findings' => $findings, 'truncated' => $truncated );
	}

	/**
	 * @param object          $row
	 * @param array<int, int> $admin_ids
	 * @return string|null
	 */
	private function classify_row( $row, $admin_ids ) {
		if ( 'Users' === $row->object_type && 'registered' === $row->action && in_array( (int) $row->object_id, $admin_ids, true ) ) {
			return 'new_admin';
		}
		if ( 'Users' === $row->object_type && 'Role' === $row->object_subtype && 'updated' === $row->action ) {
			$decoded = json_decode( (string) $row->meta, true );
			$pair    = ( is_array( $decoded ) && isset( $decoded['changes']['roles'] ) && is_array( $decoded['changes']['roles'] ) ) ? $decoded['changes']['roles'] : array();
			$old     = isset( $pair[0] ) ? json_decode( (string) $pair[0], true ) : array();
			$new     = isset( $pair[1] ) ? json_decode( (string) $pair[1], true ) : array();
			if ( is_array( $new ) && in_array( 'administrator', $new, true ) && ( ! is_array( $old ) || ! in_array( 'administrator', $old, true ) ) ) {
				return 'new_admin';
			}
		}
		if ( 'Plugins' === $row->object_type && 'activated' === $row->action ) {
			return 'plugin_activated';
		}
		if ( 'Options' === $row->object_type && 'updated' === $row->action && in_array( $row->object_name, AAL_Abilities::SENSITIVE_OPTIONS, true ) ) {
			return 'sensitive_option';
		}
		if ( $this->is_agent_request_source( $row->request_source ) && $this->is_off_hours_hist_time( (int) $row->hist_time ) ) {
			return 'agent_off_hours';
		}

		return null;
	}

	/**
	 * @param array<string, array<string, mixed>> $groups
	 * @param string                              $key
	 * @param string                              $rule
	 * @param string                              $actor
	 * @param int                                 $hist_time
	 * @param int                                 $hist_time_end
	 * @param int                                 $count_delta
	 * @param string                              $ids_csv
	 */
	private function merge_suspicious_group( &$groups, $key, $rule, $actor, $hist_time, $hist_time_end, $count_delta, $ids_csv ) {
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array(
				'rule'       => $rule,
				'severity'   => AAL_Abilities::SUSPICIOUS_RULES[ $rule ],
				'first_time' => $hist_time,
				'last_time'  => $hist_time_end,
				'actor'      => $actor,
				'count'      => 0,
				'ids_csv'    => '',
			);
		}

		$groups[ $key ]['first_time'] = min( $groups[ $key ]['first_time'], $hist_time );
		$groups[ $key ]['last_time']  = max( $groups[ $key ]['last_time'], $hist_time_end );
		$groups[ $key ]['count']      += $count_delta;
		if ( '' !== $ids_csv ) {
			if ( '' !== $groups[ $key ]['ids_csv'] ) {
				$groups[ $key ]['ids_csv'] .= ',';
			}
			$groups[ $key ]['ids_csv'] .= $ids_csv;
		}
	}

	private function is_agent_request_source( $request_source ) {
		return 0 === strpos( (string) $request_source, 'abilities' )
			|| false !== strpos( (string) $request_source, '|app:' )
			|| 0 === strpos( (string) $request_source, 'app:' );
	}

	private function is_off_hours_hist_time( $hist_time ) {
		$hour  = (int) wp_date( 'G', self::hist_time_to_unix( $hist_time ) );
		$start = AAL_Abilities::WORK_HOURS[0];
		$end   = AAL_Abilities::WORK_HOURS[1];

		return $hour < $start || $hour >= $end;
	}

	/**
	 * @param object $row
	 * @return string
	 */
	private function suspicious_actor_from_row( $row ) {
		$user_id = (int) $row->user_id;
		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				return $user->user_nicename;
			}
		}

		if ( AAL_Maintenance::is_schema_ready( '1.1' ) ) {
			$parsed = AAL_API::parse_request_source( $row->request_source );
			if ( '' !== $parsed['app_name'] ) {
				return $parsed['app_name'];
			}
		}

		return __( 'N/A', 'aryo-activity-log' );
	}

	/**
	 * @param array<string, mixed> $group
	 * @return array<string, mixed>
	 */
	private function build_suspicious_finding( $group ) {
		$ids = array_slice( array_map( 'intval', array_filter( explode( ',', (string) $group['ids_csv'] ) ) ), 0, 5 );

		return array(
			'rule'       => $group['rule'],
			'severity'   => $group['severity'],
			'first_time' => self::hist_time_to_iso8601( (int) $group['first_time'] ),
			'last_time'  => self::hist_time_to_iso8601( (int) $group['last_time'] ),
			'actor'      => $group['actor'],
			'count'      => (int) $group['count'],
			'log_ids'    => $ids,
		);
	}
}
