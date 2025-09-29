<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AAL_REST_API {

	/**
	 * @var string
	 */
	private $namespace = 'activity-log/v1';

	/**
	 * @var array
	 */
	private $_roles = array();

	/**
	 * @var array
	 */
	private $_caps = array();

	/**
	 * @var array
	 */
	private $_allow_caps = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		$this->init_roles_and_caps();
	}

	/**
	 * Initialize roles and capabilities (copied from list table)
	 */
	private function init_roles_and_caps() {
		$this->_roles = apply_filters(
			'aal_init_roles',
			array(
				// admin
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

					// BC
					'Post',
					'Taxonomy',
					'User',
					'Plugin',
					'Widget',
					'Theme',
					'Menu',
				),
				// editor
				'edit_pages' => array(
					'Posts',
					'Taxonomies',
					'Attachments',
					'Comments',

					// BC
					'Post',
					'Taxonomy',
					'Attachment',
				),
			)
		);

		$default_rules = array(
			'administrator',
			'editor',
			'author',
			'guest',
		);

		global $wp_roles;

		$all_roles = array();
		foreach ( $wp_roles->roles as $key => $wp_role ) {
			$all_roles[] = $key;
		}

		$this->_caps = apply_filters(
			'aal_init_caps',
			array(
				'administrator' => array_unique( array_merge( $default_rules, $all_roles ) ),
				'editor' => array( 'editor', 'author', 'guest' ),
				'author' => array( 'author', 'guest' ),
			)
		);
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/logs', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_logs' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args' => $this->get_collection_params(),
		) );
	}

	/**
	 * Check if user has permission to access activity logs
	 */
	public function check_permission( $request ) {
		// Must be authenticated
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check if user has general view capability
		if ( current_user_can( 'view_all_aryo_activity_log' ) || is_super_admin() ) {
			return true;
		}

		// Check role-based permissions
		$user = get_user_by( 'id', get_current_user_id() );
		if ( ! $user ) {
			return false;
		}

		$user_cap = strtolower( key( $user->caps ) );
		
		foreach ( $this->_caps as $cap => $allowed ) {
			if ( $cap === $user_cap ) {
				return true;
			}
		}

		// Check if user has any of the required role capabilities
		foreach ( $this->_roles as $cap => $types ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get allowed capabilities for current user
	 */
	private function get_allow_caps() {
		if ( empty( $this->_allow_caps ) ) {
			$user = get_user_by( 'id', get_current_user_id() );
			if ( ! $user ) {
				return array();
			}

			$user_cap = strtolower( key( $user->caps ) );
			$allow_caps = array();

			foreach ( $this->_caps as $key => $cap_allow ) {
				if ( $key === $user_cap ) {
					$allow_caps = array_merge( $allow_caps, $cap_allow );
					break;
				}
			}

			// TODO: Find better way to Multisite compatibility.
			if ( is_super_admin() || current_user_can( 'view_all_aryo_activity_log' ) ) {
				$allow_caps = $this->_caps['administrator'];
			}

			$this->_allow_caps = array_unique( $allow_caps );
		}
		return $this->_allow_caps;
	}

	/**
	 * Get WHERE clause for role-based filtering
	 */
	private function get_where_by_role() {
		$allow_modules = array();

		foreach ( $this->_roles as $key => $role ) {
			if ( current_user_can( $key ) || current_user_can( 'view_all_aryo_activity_log' ) ) {
				$allow_modules = array_merge( $allow_modules, $role );
			}
		}

		if ( empty( $allow_modules ) ) {
			return ' AND 1=0'; // No access
		}

		$allow_modules = array_unique( $allow_modules );

		$where = array();
		foreach ( $allow_modules as $type ) {
			$where[] .= '`object_type` = \'' . esc_sql( $type ) . '\'';
		}

		$where_caps = array();
		foreach ( $this->get_allow_caps() as $cap ) {
			$where_caps[] .= '`user_caps` = \'' . esc_sql( $cap ) . '\'';
		}

		if ( empty( $where_caps ) ) {
			return ' AND 1=0'; // No access
		}

		return ' AND (' . implode( ' OR ', $where ) . ') AND (' . implode( ' OR ', $where_caps ) . ')';
	}

	/**
	 * Get activity logs
	 */
	public function get_logs( $request ) {
		global $wpdb;

		$page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20;
		$per_page = min( $per_page, 100 ); // Cap at 100 items

		$where = ' WHERE 1 = 1';

		// Add role-based filtering
		$where .= $this->get_where_by_role();

		// Object type filter
		if ( $request->get_param( 'typeshow' ) ) {
			$where .= $wpdb->prepare( ' AND `object_type` = %s', sanitize_text_field( $request->get_param( 'typeshow' ) ) );
		}

		// Action filter
		if ( $request->get_param( 'showaction' ) && '' !== $request->get_param( 'showaction' ) ) {
			$where .= $wpdb->prepare( ' AND `action` = %s', sanitize_text_field( $request->get_param( 'showaction' ) ) );
		}

		// IP filter
		if ( $request->get_param( 'filter_ip' ) && '' !== $request->get_param( 'filter_ip' ) ) {
			$where .= $wpdb->prepare( ' AND `hist_ip` = %s', sanitize_text_field( $request->get_param( 'filter_ip' ) ) );
		}

		// User filter
		if ( $request->get_param( 'usershow' ) && '' !== $request->get_param( 'usershow' ) ) {
			$where .= $wpdb->prepare( ' AND `user_id` = %d', absint( $request->get_param( 'usershow' ) ) );
		}

		// Capabilities filter
		if ( $request->get_param( 'capshow' ) && '' !== $request->get_param( 'capshow' ) ) {
			$where .= $wpdb->prepare( ' AND `user_caps` = %s', strtolower( sanitize_text_field( $request->get_param( 'capshow' ) ) ) );
		}

		// Date filter
		if ( $request->get_param( 'dateshow' ) ) {
			$date_filter = $this->process_date_filter( $request->get_param( 'dateshow' ) );
			if ( $date_filter ) {
				$where .= $date_filter;
			}
		}

		// Search filter
		if ( $request->get_param( 'search' ) ) {
			$search_term = sanitize_text_field( $request->get_param( 'search' ) );
			$search_esc_like = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where .= $wpdb->prepare( ' AND (`object_name` LIKE %s OR `object_subtype` LIKE %s)', $search_esc_like, $search_esc_like );
		}

		// Get total count
		$total_items = $wpdb->get_var(
			'SELECT COUNT(`histid`) FROM `' . $wpdb->activity_log . '`' . $where
		);

		// Calculate offset
		$offset = ( $page - 1 ) * $per_page;

		// Order parameters
		$orderby = sanitize_sql_orderby( $request->get_param( 'orderby' ) ) ?: 'hist_time';
		$order = strtoupper( $request->get_param( 'order' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
			$order = 'DESC';
		}

		// Get items
		$items = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM `' . $wpdb->activity_log . '`
				' . $where . '
				ORDER BY ' . $orderby . ' ' . $order . '
				LIMIT %d, %d',
			$offset,
			$per_page
		) );

		// Format items for API response
		$formatted_items = array();
		foreach ( $items as $item ) {
			$formatted_items[] = $this->format_log_item( $item );
		}

		// Prepare response
		$total_pages = ceil( $total_items / $per_page );

		$response = array(
			'data' => $formatted_items,
			'total' => (int) $total_items,
			'pages' => (int) $total_pages,
			'current_page' => (int) $page,
			'per_page' => (int) $per_page,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Process date filter parameter
	 */
	private function process_date_filter( $date_param ) {
		global $wpdb;
		
		$current_time = current_time( 'timestamp' );
		$start_time = null;
		$end_time = null;

		if ( in_array( $date_param, array( 'today', 'yesterday', 'week', 'month' ) ) ) {
			// Today
			$start_time = mktime( 0, 0, 0, date( 'm', $current_time ), date( 'd', $current_time ), date( 'Y', $current_time ) );
			$end_time = mktime( 23, 59, 59, date( 'm', $current_time ), date( 'd', $current_time ), date( 'Y', $current_time ) );

			if ( 'yesterday' === $date_param ) {
				$start_time = strtotime( 'yesterday', $start_time );
				$end_time = mktime( 23, 59, 59, date( 'm', $start_time ), date( 'd', $start_time ), date( 'Y', $start_time ) );
			} elseif ( 'week' === $date_param ) {
				$start_time = strtotime( '-1 week', $start_time );
			} elseif ( 'month' === $date_param ) {
				$start_time = strtotime( '-1 month', $start_time );
			}
		} else {
			// Custom date format (DD/MM/YYYY)
			$date_array = explode( '/', $date_param );
			if ( 3 === count( $date_array ) ) {
				$start_time = mktime( 0, 0, 0, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2] );
				$end_time = mktime( 23, 59, 59, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2] );
			}
		}

		if ( ! empty( $start_time ) && ! empty( $end_time ) ) {
			return $wpdb->prepare( ' AND `hist_time` >= %d AND `hist_time` <= %d', $start_time, $end_time );
		}

		return '';
	}

	/**
	 * Format log item for API response
	 */
	private function format_log_item( $item ) {
		$formatted = array(
			'id' => (int) $item->histid,
			'action' => $item->action,
			'object_type' => $item->object_type,
			'object_subtype' => $item->object_subtype,
			'object_name' => $item->object_name,
			'object_id' => (int) $item->object_id,
			'user_id' => (int) $item->user_id,
			'user_caps' => $item->user_caps,
			'hist_ip' => $item->hist_ip,
			'hist_time' => (int) $item->hist_time,
			'formatted_date' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->hist_time ),
			'time_ago' => human_time_diff( $item->hist_time, current_time( 'timestamp' ) ),
		);

		// Add user information if available
		if ( $item->user_id && $item->user_id > 0 ) {
			$user = get_user_by( 'id', $item->user_id );
			if ( $user ) {
				$formatted['user'] = array(
					'id' => $user->ID,
					'display_name' => $user->display_name,
					'user_nicename' => $user->user_nicename,
					'avatar_url' => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
				);
			}
		}

		// Add action links if applicable
		$formatted['action_links'] = $this->get_action_links( $item );

		return $formatted;
	}

	/**
	 * Get action links for log item (view/edit links)
	 */
	private function get_action_links( $item ) {
		$links = array();

		switch ( $item->object_type ) {
			case 'Post':
			case 'Posts':
				if ( $item->object_id ) {
					$view_link = get_permalink( $item->object_id );
					$edit_link = get_edit_post_link( $item->object_id );
					
					if ( $view_link ) {
						$links['view'] = $view_link;
					}
					if ( $edit_link ) {
						$links['edit'] = $edit_link;
					}
				}
				break;

			case 'Taxonomy':
			case 'Taxonomies':
				if ( $item->object_id ) {
					if ( is_taxonomy_viewable( $item->object_subtype ) ) {
						$term_view_link = get_term_link( absint( $item->object_id ), $item->object_subtype );
						if ( ! is_wp_error( $term_view_link ) ) {
							$links['view'] = $term_view_link;
						}
					}

					$term_edit_link = get_edit_term_link( $item->object_id, $item->object_subtype );
					if ( $term_edit_link ) {
						$links['edit'] = $term_edit_link;
					}
				}
				break;

			case 'Comments':
				if ( $item->object_id ) {
					$edit_link = get_edit_comment_link( $item->object_id );
					if ( $edit_link ) {
						$links['edit'] = $edit_link;
					}
				}
				break;

			case 'User':
			case 'Users':
				if ( $item->object_id ) {
					$edit_link = get_edit_user_link( $item->object_id );
					if ( $edit_link ) {
						$links['edit'] = $edit_link;
					}
				}
				break;
		}

		return $links;
	}

	/**
	 * Get collection parameters for the REST endpoint
	 */
	public function get_collection_params() {
		return array(
			'page' => array(
				'description' => 'Current page of the collection.',
				'type' => 'integer',
				'default' => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description' => 'Maximum number of items to be returned in result set.',
				'type' => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
				'sanitize_callback' => 'absint',
			),
			'search' => array(
				'description' => 'Limit results to those matching a string.',
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby' => array(
				'description' => 'Sort collection by attribute.',
				'type' => 'string',
				'default' => 'hist_time',
				'enum' => array( 'hist_time', 'hist_ip' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order' => array(
				'description' => 'Order sort attribute ascending or descending.',
				'type' => 'string',
				'default' => 'desc',
				'enum' => array( 'asc', 'desc' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'dateshow' => array(
				'description' => 'Filter by date range.',
				'type' => 'string',
				'enum' => array( 'today', 'yesterday', 'week', 'month' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'usershow' => array(
				'description' => 'Filter by user ID.',
				'type' => 'integer',
				'sanitize_callback' => 'absint',
			),
			'capshow' => array(
				'description' => 'Filter by user capabilities.',
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'typeshow' => array(
				'description' => 'Filter by object type.',
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'showaction' => array(
				'description' => 'Filter by action.',
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'filter_ip' => array(
				'description' => 'Filter by IP address.',
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
