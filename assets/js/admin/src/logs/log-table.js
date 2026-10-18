import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import LogRow from './log-row';
import PromotionRow from './promotion-row';
import Pagination from './pagination';

const styles = {
	noItems: { textAlign: 'center', padding: 20, color: '#646970' },
};

function renderRowsWithPromotions( items, promotions, params, onFilter, onDismiss ) {
	const promoByType = {};
	promotions.forEach( ( p ) => {
		promoByType[ p.id ] = p;
	} );

	const printed = {};
	const rows = [];

	items.forEach( ( item ) => {
		rows.push(
			<LogRow key={ item.id } item={ item } params={ params } onFilter={ onFilter } />
		);

		const objectType = item.type.value.toLowerCase();
		if ( promoByType[ objectType ] && ! printed[ objectType ] ) {
			printed[ objectType ] = true;
			rows.push(
				<PromotionRow
					key={ 'promo-' + objectType }
					promo={ promoByType[ objectType ] }
					colSpan={ 7 }
					onDismiss={ onDismiss }
				/>
			);
		}
	} );

	return rows;
}

export default function LogTable( { data, params, exportUrl, hasActiveFilters, onFilterChange, onSortToggle, onPageChange, onDismissPromotion } ) {
	return (
		<>
			<table className="wp-list-table widefat fixed striped" style={ { marginTop: 8 } }>
				<thead>
					<tr>
						<th
							style={ { cursor: 'pointer', userSelect: 'none' } }
							onClick={ onSortToggle }
						>
							{ __( 'Date', 'aryo-activity-log' ) }
							{ params.orderby === 'hist_time' && (
								<span style={ { fontSize: 10 } }>
									{ params.order === 'DESC' ? ' ▼' : ' ▲' }
								</span>
							) }
						</th>
						<th>{ __( 'User', 'aryo-activity-log' ) }</th>
						<th>{ __( 'Source', 'aryo-activity-log' ) }</th>
						<th>{ __( 'Topic', 'aryo-activity-log' ) }</th>
						<th>{ __( 'Context', 'aryo-activity-log' ) }</th>
						<th>{ __( 'Meta', 'aryo-activity-log' ) }</th>
						<th>{ __( 'Action', 'aryo-activity-log' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ data.items.length === 0 ? (
						<tr>
							<td colSpan="7" style={ styles.noItems }>
								{ __( 'No activity log entries found.', 'aryo-activity-log' ) }
							</td>
						</tr>
					) : (
						renderRowsWithPromotions( data.items, data.promotions, params, onFilterChange, onDismissPromotion )
					) }
				</tbody>
			</table>

			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 12 } }>
				<div>
					{ exportUrl && (
						<Button
							variant="primary"
							href={ exportUrl }
							style={ { whiteSpace: 'nowrap' } }
						>
							{ hasActiveFilters
								? __( 'Export filtered records as CSV', 'aryo-activity-log' )
								: __( 'Export as CSV', 'aryo-activity-log' )
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
