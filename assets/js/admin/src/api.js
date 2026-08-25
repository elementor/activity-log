import apiFetch from '@wordpress/api-fetch';

const config = window.aalAdmin || {};

export function fetchLogs( params = {} ) {
	const query = new URLSearchParams();

	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== '' && value !== undefined && value !== null ) {
			query.set( key, value );
		}
	} );

	const qs = query.toString();
	const path = `activity-log/v1/logs${ qs ? '?' + qs : '' }`;

	return apiFetch( { path } );
}

export function fetchFilters() {
	return apiFetch( { path: 'activity-log/v1/logs/filters' } );
}

export function dismissPromotion( id ) {
	return apiFetch( {
		path: `activity-log/v1/promotions/${ id }/dismiss`,
		method: 'POST',
	} );
}

const EXPORT_FILTER_KEYS = [
	'dateshow', 'capshow', 'usershow', 'typeshow',
	'showaction', 'sourceshow', 'filter_ip', 's',
];

export function buildExportUrl( filters ) {
	const base = config.exportUrl || '';
	if ( ! base ) {
		return '';
	}

	const url = new URL( base, window.location.origin );

	EXPORT_FILTER_KEYS.forEach( ( key ) => {
		if ( filters[ key ] ) {
			url.searchParams.set( key, filters[ key ] );
		}
	} );

	url.searchParams.set( 'aal-record-actions-submit', '1' );
	url.searchParams.set( 'aal-record-action', 'csv' );
	url.searchParams.set( 'aal_actions_nonce', config.exportNonce || '' );

	return url.toString();
}
