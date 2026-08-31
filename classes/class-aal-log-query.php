<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAL_Log_Query {

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
	 * Query activity log rows.
	 *
	 * @param array $args {
	 *     @type string $typeshow    Filter by object_type.
	 *     @type string $showaction  Filter by action.
	 *     @type string $filter_ip   Filter by IP.
	 *     @type int    $usershow    Filter by user_id.
	 *     @type string $capshow     Filter by user_caps.
	 *     @type string $sourceshow  Filter by request_source channel.
	 *     @type string $dateshow    Date filter: today|yesterday|week|month|dd/mm/yyyy.
	 *     @type string $s           Search term.
	 *     @type string $orderby     Column to sort by (hist_time|hist_ip). Default hist_time.
	 *     @type string $order       ASC or DESC. Default DESC.
	 *     @type int    $page        1-indexed page number.
	 *     @type int    $per_page    Items per page (max 100).
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
					$start_time = strtotime( '-1 month', $start_time );
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
			$where .= $wpdb->prepare(
				' AND (`object_name` LIKE %s OR `object_subtype` LIKE %s OR `hist_ip` LIKE %s)',
				$search_like,
				$search_like,
				$search_like
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
}
