import { __ } from '@wordpress/i18n';

export function mergeUniqueById( existing, incoming ) {
	const seen = new Set( existing.map( ( item ) => item.id ) );
	const added = incoming.filter( ( item ) => ! seen.has( item.id ) );
	return existing.concat( added );
}

export function getDayHeaderLabel( date ) {
	const dashboard = window.aalDashboard || {};

	if ( date.dateshow === dashboard.today ) {
		return __( 'Today', 'aryo-activity-log' );
	}
	if ( date.dateshow === dashboard.yesterday ) {
		return __( 'Yesterday', 'aryo-activity-log' );
	}
	return date.date;
}

export function groupItemsByDay( items ) {
	const groups = [];
	let currentKey = null;

	items.forEach( ( item ) => {
		const dayKey = item.date.dateshow;

		if ( dayKey !== currentKey ) {
			groups.push( {
				dayKey,
				header: getDayHeaderLabel( item.date ),
				items: [],
			} );
			currentKey = dayKey;
		}

		groups[ groups.length - 1 ].items.push( item );
	} );

	return groups;
}
