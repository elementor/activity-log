<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAL_Log_Presenter {

	/**
	 * Present a single DB row for JSON output.
	 *
	 * @param object $item       Raw DB row.
	 * @param bool   $collect_ip Whether to show IP addresses.
	 * @return array
	 */
	public static function to_json( $item, $collect_ip = true ) {
		$data = array(
			'id'             => (int) $item->histid,
			'date'           => self::format_date( $item ),
			'author'         => self::format_author( $item ),
			'source'         => self::format_source( $item, $collect_ip ),
			'type'           => self::format_type( $item ),
			'label'          => self::format_label( $item ),
			'description'    => self::format_description( $item ),
			'action'         => self::format_action( $item ),
		);

		return $data;
	}

	/**
	 * Present a single DB row for export.
	 *
	 * @param object $item       Raw DB row.
	 * @param array  $columns    Column keys.
	 * @return array
	 */
	public static function to_export_row( $item, $columns ) {
		$row = array();

		foreach ( array_keys( $columns ) as $column ) {
			switch ( $column ) {
				case 'date':
					$row[ $column ] = date_i18n( get_option( 'date_format' ), $item->hist_time )
						. ' '
						. date_i18n( get_option( 'time_format' ), $item->hist_time );
					break;

				case 'author':
					$user = get_userdata( $item->user_id );
					$row[ $column ] = isset( $user->display_name ) ? $user->display_name : 'unknown';
					break;

				case 'source':
					if ( AAL_Maintenance::is_schema_ready( '1.1' ) && ! empty( $item->request_source ) ) {
						$row[ $column ] = self::format_source_label_plain( $item->request_source );
					} else {
						$row[ $column ] = '';
					}
					break;

				case 'ip':
					$row[ $column ] = $item->hist_ip;
					break;

				case 'type':
					$row[ $column ] = $item->object_type;
					break;

				case 'label':
					$row[ $column ] = $item->object_subtype;
					break;

				case 'action':
					$row[ $column ] = self::get_action_label( $item->action );
					break;

				case 'description':
					$row[ $column ] = $item->object_name;
					break;
			}
		}

		return $row;
	}

	public static function get_action_label( $action ) {
		return ucwords( str_replace( '_', ' ', __( $action, 'aryo-activity-log' ) ) );
	}

	private static function format_date( $item ) {
		$relative = sprintf(
			/* translators: %s: human time diff */
			__( '%s ago', 'aryo-activity-log' ),
			human_time_diff( $item->hist_time, current_time( 'timestamp' ) )
		);

		return array(
			'relative'  => $relative,
			'date'      => date_i18n( get_option( 'date_format' ), $item->hist_time ),
			'time'      => date_i18n( get_option( 'time_format' ), $item->hist_time ),
			'timestamp' => (int) $item->hist_time,
			'dateshow'  => date( 'd/m/Y', $item->hist_time ),
		);
	}

	private static function format_author( $item ) {
		global $wp_roles;

		if ( ! empty( $item->user_id ) && 0 !== (int) $item->user_id ) {
			$user = get_user_by( 'id', $item->user_id );
			if ( $user instanceof WP_User && 0 !== $user->ID ) {
				$stored_slug = isset( $item->user_caps ) ? $item->user_caps : '';
				$role_name   = __( 'Unknown', 'aryo-activity-log' );

				if ( ! empty( $stored_slug ) && isset( $wp_roles->role_names[ $stored_slug ] ) ) {
					$role_name = $wp_roles->role_names[ $stored_slug ];
				} elseif ( isset( $user->roles[0] ) && isset( $wp_roles->role_names[ $user->roles[0] ] ) ) {
					$role_name = $wp_roles->role_names[ $user->roles[0] ];
				}

				return array(
					'id'     => $user->ID,
					'name'   => $user->display_name,
					'avatar' => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
					'role'   => $role_name,
				);
			}
		}

		return array(
			'id'     => 0,
			'name'   => __( 'N/A', 'aryo-activity-log' ),
			'avatar' => '',
			'role'   => '',
		);
	}

	private static function format_source( $item, $collect_ip ) {
		$data = array(
			'channel'       => '',
			'channel_label' => '',
			'app_name'      => '',
			'ip'            => '',
		);

		$raw = isset( $item->request_source ) ? $item->request_source : '';
		if ( '' !== $raw ) {
			$parsed         = AAL_API::parse_request_source( $raw );
			$channel_labels = AAL_API::get_channel_labels();

			if ( ! empty( $parsed['channel'] ) && isset( $channel_labels[ $parsed['channel'] ] ) ) {
				$data['channel']       = $parsed['channel'];
				$data['channel_label'] = $channel_labels[ $parsed['channel'] ];
			}

			$data['app_name'] = $parsed['app_name'];
		}

		if ( $collect_ip && ! empty( $item->hist_ip ) ) {
			$data['ip'] = $item->hist_ip;
		}

		return $data;
	}

	private static function format_type( $item ) {
		$label = __( $item->object_type, 'aryo-activity-log' );

		$rendered = apply_filters( 'aal_table_list_column_type', $label, $item );

		return array(
			'value'    => $item->object_type,
			'label'    => $label,
			'rendered' => $rendered,
		);
	}

	private static function format_label( $item ) {
		$label = '';
		if ( ! empty( $item->object_subtype ) ) {
			$pt    = get_post_type_object( $item->object_subtype );
			$label = ! empty( $pt->label ) ? $pt->label : $item->object_subtype;
		}

		$rendered = apply_filters( 'aal_table_list_column_label', $label, $item );

		return array(
			'value'    => $item->object_subtype,
			'label'    => $label,
			'rendered' => $rendered,
		);
	}

	private static function format_description( $item ) {
		$text    = esc_html( $item->object_name );
		$actions = array();

		switch ( $item->object_type ) {
			case 'Post':
			case 'Posts':
				$actions['view'] = get_permalink( $item->object_id );
				$actions['edit'] = get_edit_post_link( $item->object_id, 'raw' );
				break;

			case 'Taxonomy':
			case 'Taxonomies':
				if ( ! empty( $item->object_id ) ) {
					if ( is_taxonomy_viewable( $item->object_subtype ) ) {
						$term_link = get_term_link( absint( $item->object_id ), $item->object_subtype );
						if ( ! is_wp_error( $term_link ) ) {
							$actions['view'] = $term_link;
						}
					}
					$term_edit = get_edit_term_link( $item->object_id, $item->object_subtype );
					if ( ! empty( $term_edit ) ) {
						$actions['edit'] = $term_edit;
					}
				}
				break;

			case 'Comments':
				if ( ! empty( $item->object_id ) && get_comment( $item->object_id ) ) {
					$actions['edit'] = get_edit_comment_link( $item->object_id );
				}
				$text = esc_html( "{$item->object_name} #{$item->object_id}" );
				break;

			case 'User':
			case 'Users':
				$user_edit = get_edit_user_link( $item->object_id );
				if ( ! empty( $user_edit ) ) {
					$actions['edit'] = $user_edit;
				}
				if ( ! empty( $item->object_name ) ) {
					$text = __( 'Username:', 'aryo-activity-log' ) . ' ' . esc_html( $item->object_name );
				}
				break;

			case 'Export':
				if ( 'all' === $item->object_name ) {
					$text = __( 'All', 'aryo-activity-log' );
				} else {
					$pt   = get_post_type_object( $item->object_name );
					$text = ! empty( $pt->label ) ? $pt->label : $item->object_name;
				}
				break;

			case 'Options':
			case 'Core':
				$text = __( $item->object_name, 'aryo-activity-log' );
				break;
		}

		$rendered = apply_filters( 'aal_table_list_column_description', $text, $item );

		return array(
			'text'     => $text,
			'actions'  => $actions,
			'rendered' => $rendered,
		);
	}

	private static function format_action( $item ) {
		$label = self::get_action_label( $item->action );

		$rendered = apply_filters( 'aal_table_list_column_default', $label, $item, 'action' );

		return array(
			'value'    => $item->action,
			'label'    => $label,
			'rendered' => $rendered,
		);
	}

	private static function format_source_label_plain( $raw ) {
		$parsed         = AAL_API::parse_request_source( $raw );
		$parts          = array();
		$channel_labels = AAL_API::get_channel_labels();

		if ( ! empty( $parsed['channel'] ) && isset( $channel_labels[ $parsed['channel'] ] ) ) {
			$parts[] = $channel_labels[ $parsed['channel'] ];
		}

		if ( ! empty( $parsed['app_name'] ) ) {
			$parts[] = 'App Password: ' . $parsed['app_name'];
		} elseif ( false !== strpos( $raw, 'app:' ) ) {
			$parts[] = 'App Password';
		}

		return implode( '; ', $parts );
	}
}
