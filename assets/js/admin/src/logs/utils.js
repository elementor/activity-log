import { __ } from '@wordpress/i18n';

const WEEK_SECONDS = 7 * 24 * 60 * 60;

/**
 * Day header label (Today / Yesterday / localized date).
 *
 * @param {Object} date Log date object from the API.
 * @return {string}
 */
export function getDayHeaderLabel( date ) {
	const parts = date.dateshow.split( '/' );
	if ( parts.length === 3 ) {
		const day = parseInt( parts[ 0 ], 10 );
		const month = parseInt( parts[ 1 ], 10 ) - 1;
		const year = parseInt( parts[ 2 ], 10 );
		const itemStart = new Date( year, month, day );
		const now = new Date();
		const todayStart = new Date( now.getFullYear(), now.getMonth(), now.getDate() );
		const yesterdayStart = new Date( todayStart );
		yesterdayStart.setDate( yesterdayStart.getDate() - 1 );

		if ( itemStart.getTime() === todayStart.getTime() ) {
			return __( 'Today', 'aryo-activity-log' );
		}
		if ( itemStart.getTime() === yesterdayStart.getTime() ) {
			return __( 'Yesterday', 'aryo-activity-log' );
		}
	}

	return date.date;
}

/**
 * @param {Array} items Log items.
 * @return {Array}
 */
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

/**
 * Relative time for recent entries; absolute date after one week.
 *
 * @param {Object} date Log date object from the API.
 * @return {string}
 */
export function getDisplayTime( date ) {
	const now = Math.floor( Date.now() / 1000 );
	const age = now - date.timestamp;

	if ( age >= 0 && age < WEEK_SECONDS ) {
		return date.relative;
	}

	return date.date;
}

/**
 * @param {Object} date Log date object from the API.
 * @return {string}
 */
export function getFullTimestamp( date ) {
	return `${ date.date } ${ date.time }`;
}

/**
 * Maps action slugs to badge tone classes.
 *
 * @param {string} actionValue Action slug from the API.
 * @return {string}
 */
export function getActionTone( actionValue ) {
	const value = ( actionValue || '' ).toLowerCase();

	if ( value === 'created' || value === 'activated' || value === 'approved' ) {
		return 'created';
	}
	if (
		value === 'updated' ||
		value === 'modified' ||
		value === 'restored' ||
		value === 'untrashed'
	) {
		return 'updated';
	}
	if (
		value === 'deleted' ||
		value === 'trashed' ||
		value === 'denied' ||
		value === 'failed'
	) {
		return 'deleted';
	}

	return 'other';
}
