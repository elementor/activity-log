<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds activity log list data for WP-CLI (no WP-CLI dependency).
 */
class AAL_CLI_List {

	/**
	 * Default output fields (matches admin list columns; IP omitted like the React log table).
	 *
	 * @return string[]
	 */
	public static function get_default_fields() {
		return array(
			'id',
			'date',
			'author',
			'source',
			'type',
			'label',
			'description',
			'action',
		);
	}

	/**
	 * Map WP-CLI associative arguments to AAL_Log_Query::query() arguments.
	 *
	 * @param array<string, mixed> $assoc_args CLI flags.
	 * @return array<string, mixed>
	 */
	public static function build_query_args( $assoc_args ) {
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();

		$per_page = 20;
		if ( isset( $assoc_args['per-page'] ) && '' !== (string) $assoc_args['per-page'] ) {
			$per_page = (int) $assoc_args['per-page'];
		}

		$page = 1;
		if ( isset( $assoc_args['page'] ) && '' !== (string) $assoc_args['page'] ) {
			$page = (int) $assoc_args['page'];
		}

		$query_args = array(
			'orderby'  => 'histid',
			'order'    => 'DESC',
			'page'     => max( 1, $page ),
			'per_page' => min( max( 1, $per_page ), 100 ),
		);

		if ( ! empty( $assoc_args['type'] ) ) {
			$query_args['typeshow'] = $assoc_args['type'];
		}
		if ( ! empty( $assoc_args['action'] ) ) {
			$query_args['showaction'] = $assoc_args['action'];
		}
		if ( ! empty( $assoc_args['ip'] ) ) {
			$query_args['filter_ip'] = $assoc_args['ip'];
		}
		if ( isset( $assoc_args['log-user'] ) && '' !== (string) $assoc_args['log-user'] ) {
			$query_args['usershow'] = (int) $assoc_args['log-user'];
		}
		if ( ! empty( $assoc_args['role'] ) ) {
			$query_args['capshow'] = $assoc_args['role'];
		}
		if ( ! empty( $assoc_args['source'] ) ) {
			$query_args['sourceshow'] = $assoc_args['source'];
		}
		if ( ! empty( $assoc_args['date'] ) ) {
			$query_args['dateshow'] = $assoc_args['date'];
		}
		if ( ! empty( $assoc_args['search'] ) ) {
			$query_args['s'] = $assoc_args['search'];
		}

		if ( 'no-collect-ip' === AAL_Main::instance()->settings->get_option( 'log_visitor_ip_source' ) ) {
			$query_args['exclude_ip'] = true;
		}

		return $query_args;
	}

	/**
	 * @param string[] $fields Field keys.
	 * @return array<string, string> Column key => translated label for export presenter.
	 */
	public static function fields_to_export_columns( $fields ) {
		$labels = array(
			'id'          => __( 'ID', 'aryo-activity-log' ),
			'date'        => __( 'Date', 'aryo-activity-log' ),
			'author'      => __( 'User', 'aryo-activity-log' ),
			'source'      => __( 'Source', 'aryo-activity-log' ),
			'ip'          => __( 'IP', 'aryo-activity-log' ),
			'type'        => __( 'Topic', 'aryo-activity-log' ),
			'label'       => __( 'Context', 'aryo-activity-log' ),
			'description' => __( 'Meta', 'aryo-activity-log' ),
			'action'      => __( 'Action', 'aryo-activity-log' ),
		);

		$columns = array();
		foreach ( $fields as $field ) {
			if ( isset( $labels[ $field ] ) ) {
				$columns[ $field ] = $labels[ $field ];
			}
		}

		return $columns;
	}

	/**
	 * @param object[]             $items  Raw log rows.
	 * @param array<string, mixed> $assoc_args CLI flags (fields).
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_list_rows( $items, $assoc_args = array() ) {
		$fields = self::parse_fields( $assoc_args );
		$columns = self::fields_to_export_columns( $fields );
		$rows    = array();

		foreach ( $items as $item ) {
			$row = AAL_Log_Presenter::to_export_row( $item, $columns );
			$row['id'] = (int) $item->histid;

			$ordered = array();
			foreach ( $fields as $field ) {
				if ( array_key_exists( $field, $row ) ) {
					$ordered[ $field ] = $row[ $field ];
				}
			}
			$rows[] = $ordered;
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $assoc_args CLI flags.
	 * @return string[]
	 */
	public static function parse_fields( $assoc_args ) {
		if ( empty( $assoc_args['fields'] ) ) {
			return self::get_default_fields();
		}

		$fields = array_map( 'trim', explode( ',', (string) $assoc_args['fields'] ) );
		$fields = array_values( array_filter( $fields, 'strlen' ) );

		return ! empty( $fields ) ? $fields : self::get_default_fields();
	}

	/**
	 * Whether the CLI request should bypass capability checks on log queries.
	 *
	 * @return bool True when running under WP-CLI with no current user (full log access).
	 */
	public static function is_unrestricted() {
		return defined( 'WP_CLI' ) && WP_CLI && 0 === get_current_user_id();
	}

	/**
	 * Fetch log rows and total for list output.
	 *
	 * @param array<string, mixed> $assoc_args CLI flags.
	 * @return array{ rows: array<int, array<string, mixed>>, total: int }
	 */
	public static function fetch_list( $assoc_args ) {
		$unrestricted = self::is_unrestricted();
		$query        = new AAL_Log_Query( array( 'unrestricted' => $unrestricted ) );
		$result       = $query->query( self::build_query_args( $assoc_args ) );

		return array(
			'rows'  => self::build_list_rows( $result['items'], $assoc_args ),
			'total' => isset( $result['total'] ) ? (int) $result['total'] : count( $result['items'] ),
		);
	}
}

/**
 * Builds activity log stats data for WP-CLI (no WP-CLI dependency).
 */
class AAL_CLI_Stats {

	const DEFAULT_PERIOD = '24h';

	const COUNT_ROW_FIELD_COUNT = 'count';

	const OBJECT_TYPE_FIELD = 'object_type';

	const ACTION_FIELD = 'action';

	const TOP_USER_FIELD_USER_ID = 'user_id';

	const TOP_USER_FIELD_NAME = 'name';

	const TOP_USER_FIELD_COUNT = 'count';

	/**
	 * Map WP-CLI associative arguments to summarize-activity input.
	 *
	 * @param array<string, mixed> $assoc_args CLI flags.
	 * @return array<string, mixed>
	 */
	public static function build_summarize_input( $assoc_args ) {
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$input      = array();

		if ( ! empty( $assoc_args['period'] ) ) {
			$input['period'] = $assoc_args['period'];
		}
		if ( ! empty( $assoc_args['date-from'] ) ) {
			$input['date_from'] = $assoc_args['date-from'];
		}
		if ( ! empty( $assoc_args['date-to'] ) ) {
			$input['date_to'] = $assoc_args['date-to'];
		}

		if ( empty( $input ) ) {
			$input['period'] = self::DEFAULT_PERIOD;
		}

		return $input;
	}

	/**
	 * @param array<string, int> $counts     Label => count map from summarize output.
	 * @param string             $label_field Row key for the label column.
	 * @return array<int, array<string, int|string>>
	 */
	public static function build_label_count_rows( $counts, $label_field ) {
		$rows = array();
		foreach ( $counts as $label => $count ) {
			$rows[] = array(
				$label_field              => (string) $label,
				self::COUNT_ROW_FIELD_COUNT => (int) $count,
			);
		}

		return $rows;
	}

	/**
	 * @param array<int, array<string, mixed>> $top_users Summarize top_users payload.
	 * @return array<int, array<string, int|string>>
	 */
	public static function build_top_users_rows( $top_users ) {
		$rows = array();
		foreach ( $top_users as $user ) {
			$rows[] = array(
				self::TOP_USER_FIELD_USER_ID => (int) $user['user_id'],
				self::TOP_USER_FIELD_NAME    => (string) $user['name'],
				self::TOP_USER_FIELD_COUNT   => (int) $user['count'],
			);
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $assoc_args CLI flags.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function fetch_stats( $assoc_args ) {
		$unrestricted = AAL_CLI_List::is_unrestricted();
		$query        = new AAL_Log_Query( array( 'unrestricted' => $unrestricted ) );

		return $query->summarize_activity_from_input( self::build_summarize_input( $assoc_args ) );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Activity Log WP-CLI commands.
	 */
	class AAL_CLI_Command extends WP_CLI_Command {

		/**
		 * List activity log entries.
		 *
		 * ## OPTIONS
		 *
		 * [--type=<type>]
		 * : Filter by object type (topic).
		 *
		 * [--action=<action>]
		 * : Filter by action slug.
		 *
		 * [--ip=<ip>]
		 * : Filter by IP address.
		 *
		 * [--log-user=<id>]
		 * : Filter by WordPress user ID (not the global WP-CLI --user flag).
		 *
		 * [--role=<role>]
		 * : Filter by user role slug stored in the log (user_caps).
		 *
		 * [--source=<source>]
		 * : Filter by request source channel.
		 *
		 * [--date=<date>]
		 * : Date filter: today, yesterday, week, month, or dd/mm/yyyy.
		 *
		 * [--search=<term>]
		 * : Search object name, subtype, and IP (when collected).
		 *
		 * [--per-page=<n>]
		 * : Number of log entries per page.
		 * ---
		 * default: 20
		 * ---
		 *
		 * [--page=<n>]
		 * : Page number.
		 * ---
		 * default: 1
		 * ---
		 *
		 * [--fields=<fields>]
		 * : Comma-separated list of columns to show.
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 *   - yaml
		 *   - count
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp activity-log list
		 *     wp activity-log list --type=Posts --action=updated --per-page=50
		 *     wp activity-log list --format=count --date=today
		 *
		 * @subcommand list
		 *
		 * @param array<int, string>   $args       Positional arguments.
		 * @param array<string, mixed> $assoc_args Associative arguments.
		 */
		public function list_entries( $args, $assoc_args ) {
			$data = AAL_CLI_List::fetch_list( $assoc_args );

			$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

			if ( 'count' === $format ) {
				WP_CLI::log( (string) $data['total'] );
				return;
			}

			$fields = AAL_CLI_List::parse_fields( $assoc_args );
			\WP_CLI\Utils\format_items( $format, $data['rows'], $fields );
		}

		/**
		 * Show aggregated activity log statistics.
		 *
		 * ## OPTIONS
		 *
		 * [--period=<period>]
		 * : Relative time window.
		 * ---
		 * default: 24h
		 * options:
		 *   - 24h
		 *   - 7d
		 *   - 30d
		 *   - today
		 * ---
		 *
		 * [--date-from=<Y-m-d>]
		 * : Range start date in the site timezone (use with --date-to).
		 *
		 * [--date-to=<Y-m-d>]
		 * : Range end date in the site timezone (use with --date-from).
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - json
		 *   - yaml
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp activity-log stats
		 *     wp activity-log stats --period=7d
		 *     wp activity-log stats --date-from=2026-03-01 --date-to=2026-03-07 --format=json
		 *
		 * @subcommand stats
		 *
		 * @param array<int, string>   $args       Positional arguments.
		 * @param array<string, mixed> $assoc_args Associative arguments.
		 */
		public function stats( $args, $assoc_args ) {
			$summary = AAL_CLI_Stats::fetch_stats( $assoc_args );
			if ( is_wp_error( $summary ) ) {
				WP_CLI::error( $summary->get_error_message() );
			}

			$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

			if ( 'table' !== $format ) {
				WP_CLI::print_value( $summary, array( 'format' => $format ) );
				return;
			}

			WP_CLI::log(
				sprintf(
					'Range: %s - %s',
					$summary['range']['from'],
					$summary['range']['to']
				)
			);
			WP_CLI::log( sprintf( 'Total: %d', (int) $summary['total'] ) );

			$this->print_stats_count_table(
				'By object type',
				AAL_CLI_Stats::build_label_count_rows( $summary['by_object_type'], AAL_CLI_Stats::OBJECT_TYPE_FIELD ),
				array( AAL_CLI_Stats::OBJECT_TYPE_FIELD, AAL_CLI_Stats::COUNT_ROW_FIELD_COUNT )
			);
			$this->print_stats_count_table(
				'By action',
				AAL_CLI_Stats::build_label_count_rows( $summary['by_action'], AAL_CLI_Stats::ACTION_FIELD ),
				array( AAL_CLI_Stats::ACTION_FIELD, AAL_CLI_Stats::COUNT_ROW_FIELD_COUNT )
			);
			$this->print_stats_count_table(
				'Top users',
				AAL_CLI_Stats::build_top_users_rows( $summary['top_users'] ),
				array(
					AAL_CLI_Stats::TOP_USER_FIELD_USER_ID,
					AAL_CLI_Stats::TOP_USER_FIELD_NAME,
					AAL_CLI_Stats::TOP_USER_FIELD_COUNT,
				)
			);
		}

		/**
		 * @param string               $title  Section heading.
		 * @param array<int, mixed>    $rows   Table rows.
		 * @param array<int, string>   $fields Column keys.
		 */
		private function print_stats_count_table( $title, $rows, $fields ) {
			if ( empty( $rows ) ) {
				return;
			}

			WP_CLI::log( '' );
			WP_CLI::log( $title );
			\WP_CLI\Utils\format_items( 'table', $rows, $fields );
		}
	}
}
