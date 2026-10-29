import { useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import LogRow from './log-row';
import PromotionRow from './promotion-row';
import Pagination from './pagination';
import { groupItemsByDay } from './utils';

export const LOG_TABLE_COLUMN_COUNT = 8;

function renderGroupedTableBody( items, promotions, params, onFilter, onDismiss ) {
	const promoByType = {};
	promotions.forEach( ( p ) => {
		promoByType[ p.id ] = p;
	} );
	const printed = {};
	const dayGroups = groupItemsByDay( items );
	const nodes = [];

	dayGroups.forEach( ( group ) => {
		nodes.push(
			<tr key={ 'day-' + group.dayKey } className="aal-logs-day-separator">
				<td colSpan={ LOG_TABLE_COLUMN_COUNT }>{ group.header }</td>
			</tr>
		);

		group.items.forEach( ( item ) => {
			nodes.push(
				<LogRow key={ item.id } item={ item } params={ params } onFilter={ onFilter } />
			);

			const objectType = item.type.value.toLowerCase();
			if ( promoByType[ objectType ] && ! printed[ objectType ] ) {
				printed[ objectType ] = true;
				nodes.push(
					<PromotionRow
						key={ 'promo-' + objectType }
						promo={ promoByType[ objectType ] }
						colSpan={ LOG_TABLE_COLUMN_COUNT }
						onDismiss={ onDismiss }
					/>
				);
			}
		} );
	} );

	return nodes;
}

export default function LogTable( { data, params, csvExportUrl, jsonlExportUrl, hasActiveFilters, onFilterChange, onSortToggle, onPageChange, onDismissPromotion } ) {
	const isEmpty = data.items.length === 0;
	const sortLabel = params.order === 'DESC'
		? __( 'Newest first', 'aryo-activity-log' )
		: __( 'Oldest first', 'aryo-activity-log' );

	const bodyRows = useMemo(
		() => renderGroupedTableBody( data.items, data.promotions, params, onFilterChange, onDismissPromotion ),
		[ data.items, data.promotions, params, onFilterChange, onDismissPromotion ]
	);

	return (
		<>
			<div className="aal-logs-panel">
				<table className="wp-list-table widefat fixed striped aal-logs-table">
					<colgroup>
						<col className="aal-logs-col aal-logs-col--time" />
						<col className="aal-logs-col aal-logs-col--user" />
						<col className="aal-logs-col aal-logs-col--action" />
						<col className="aal-logs-col aal-logs-col--event" />
						<col className="aal-logs-col aal-logs-col--topic" />
						<col className="aal-logs-col aal-logs-col--context" />
						<col className="aal-logs-col aal-logs-col--ip" />
						<col className="aal-logs-col aal-logs-col--source" />
					</colgroup>
					<thead>
						<tr>
							<th
								scope="col"
								className="aal-logs-col aal-logs-col--time aal-logs-table__sort"
								onClick={ onSortToggle }
							>
								{ __( 'Time', 'aryo-activity-log' ) }
								{ params.orderby === 'hist_time' && (
									<span className="aal-logs-table__sort-indicator" aria-hidden="true">
										{ params.order === 'DESC' ? ' ▼' : ' ▲' }
									</span>
								) }
								<span className="screen-reader-text">{ sortLabel }</span>
							</th>
							<th scope="col" className="aal-logs-col aal-logs-col--user">{ __( 'User', 'aryo-activity-log' ) }</th>
							<th scope="col" className="aal-logs-col aal-logs-col--action">{ __( 'Action', 'aryo-activity-log' ) }</th>
							<th scope="col" className="aal-logs-col aal-logs-col--event">{ __( 'Event', 'aryo-activity-log' ) }</th>
							<th scope="col" className="aal-logs-col aal-logs-col--topic">{ __( 'Topic', 'aryo-activity-log' ) }</th>
							<th scope="col" className="aal-logs-col aal-logs-col--context">{ __( 'Context', 'aryo-activity-log' ) }</th>
							<th scope="col" className="aal-logs-col aal-logs-col--ip">{ __( 'IP', 'aryo-activity-log' ) }</th>
							<th scope="col" className="aal-logs-col aal-logs-col--source">{ __( 'Source', 'aryo-activity-log' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ isEmpty ? (
							<tr>
								<td colSpan={ LOG_TABLE_COLUMN_COUNT } className="aal-logs-empty">
									<span className="dashicons dashicons-backup aal-logs-empty__icon" aria-hidden="true"></span>
									<p className="aal-logs-empty__title">
										{ __( 'No activity log entries found.', 'aryo-activity-log' ) }
									</p>
									<p className="aal-logs-empty__hint">
										{ hasActiveFilters
											? __( 'Try adjusting your filters or search terms.', 'aryo-activity-log' )
											: __( 'Activity from your site will appear here.', 'aryo-activity-log' )
										}
									</p>
								</td>
							</tr>
						) : (
							bodyRows
						) }
					</tbody>
				</table>
			</div>

			<div className="aal-logs-footer">
				<div className="aal-logs-footer__exports">
					{ csvExportUrl && (
						<Button variant="primary" href={ csvExportUrl }>
							{ hasActiveFilters
								? __( 'Export filtered records as CSV', 'aryo-activity-log' )
								: __( 'Export as CSV', 'aryo-activity-log' )
							}
						</Button>
					) }
					{ jsonlExportUrl && (
						<Button variant="secondary" href={ jsonlExportUrl }>
							{ hasActiveFilters
								? __( 'Export filtered records as JSON Lines', 'aryo-activity-log' )
								: __( 'Export as JSON Lines', 'aryo-activity-log' )
							}
						</Button>
					) }
				</div>
				<Pagination
					page={ params.page }
					pages={ data.pages }
					total={ data.total }
					onPageChange={ onPageChange }
				/>
			</div>
		</>
	);
}
