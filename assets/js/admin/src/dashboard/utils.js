import { __ } from '@wordpress/i18n';

export function mergeUniqueById( existing, incoming ) {
	const seen = new Set( existing.map( ( item ) => item.id ) );
	const added = incoming.filter( ( item ) => ! seen.has( item.id ) );
	return existing.concat( added );
}

export function isSameLocalDay( a, b ) {
	return (
		a.getFullYear() === b.getFullYear() &&
		a.getMonth() === b.getMonth() &&
		a.getDate() === b.getDate()
	);
}

export function getDayHeaderLabel( iso8601 ) {
	const date = new Date( iso8601 );
	const today = new Date();
	const yesterday = new Date();
	yesterday.setDate( today.getDate() - 1 );

	if ( isSameLocalDay( date, today ) ) {
		return __( 'Today', 'aryo-activity-log' );
	}
	if ( isSameLocalDay( date, yesterday ) ) {
		return __( 'Yesterday', 'aryo-activity-log' );
	}
	return date.toLocaleDateString( undefined, {
		month: 'short',
		day: 'numeric',
	} );
}

export function groupItemsByDay( items ) {
	const groups = [];
	let currentKey = null;

	items.forEach( ( item ) => {
		const dayDate = new Date( item.date.iso8601 );
		const dayKey = `${ dayDate.getFullYear() }-${ dayDate.getMonth() }-${ dayDate.getDate() }`;

		if ( dayKey !== currentKey ) {
			groups.push( {
				dayKey,
				header: getDayHeaderLabel( item.date.iso8601 ),
				items: [],
			} );
			currentKey = dayKey;
		}

		groups[ groups.length - 1 ].items.push( item );
	} );

	return groups;
}
