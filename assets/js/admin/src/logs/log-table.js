import { useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import LogRow from './log-row';
import PromotionRow from './promotion-row';
import Pagination from './pagination';
import { groupItemsByDay } from './utils';

function renderGroupedRows( items, promotions, params, onFilter, onDismiss ) {
	const promoByType = {};
	promotions.forEach( ( p ) => {
		promoByType[ p.id ] = p;
	} );
	const printed = {};

	const dayGroups = groupItemsByDay( items );

	return dayGroups.map( ( group ) => (
		<li key={ group.dayKey } className="aal-logs-day">
			<h2 className="aal-logs-day__header">{ group.header }</h2>
			<ul className="aal-logs-day__list">
				{ group.items.flatMap( ( item ) => {
					const rows = [
						<LogRow key={ item.id } item={ item } params={ params } onFilter={ onFilter } />,
					];
					const objectType = item.type.value.toLowerCase();
					if ( promoByType[ objectType ] && ! printed[ objectType ] ) {
						printed[ objectType ] = true;
						rows.push(
							<PromotionRow
								key={ 'promo-' + objectType }
								promo={ promoByType[ objectType ] }
								onDismiss={ onDismiss }
							/>
						);
					}
					return rows;
				} ) }
			</ul>
		</li>
	) );
}

export default function LogTable( { data, params, csvExportUrl, jsonlExportUrl, hasActiveFilters, onFilterChange, onSortToggle, onPageChange, onDismissPromotion } ) {
	const isEmpty = data.items.length === 0;
	const sortLabel = params.order === 'DESC'
		? __( 'Newest first', 'aryo-activity-log' )
		: __( 'Oldest first', 'aryo-activity-log' );

	const groupedContent = useMemo(
		() => renderGroupedRows( data.items, data.promotions, params, onFilterChange, onDismissPromotion ),
		[ data.items, data.promotions, params, onFilterChange, onDismissPromotion ]
	);

	return (
		<>
			<div className="aal-logs-panel">
				<div className="aal-logs-toolbar">
					<button
						type="button"
						className="aal-logs-toolbar__sort"
						onClick={ onSortToggle }
					>
						{ __( 'Sort by date', 'aryo-activity-log' ) }
						{ params.orderby === 'hist_time' && (
							<span className="aal-logs-toolbar__sort-indicator" aria-hidden="true">
								{ params.order === 'DESC' ? '▼' : '▲' }
							</span>
						) }
						<span className="screen-reader-text">{ sortLabel }</span>
					</button>
				</div>

				{ isEmpty ? (
					<div className="aal-logs-empty">
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
					</div>
				) : (
					<ul className="aal-logs-days">{ groupedContent }</ul>
				) }
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
