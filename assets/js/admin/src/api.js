import apiFetch from '@wordpress/api-fetch';
import { FILTER_KEYS } from './filter-keys';

const config = window.aalAdmin || {};

export const EXPORT_FORMAT_CSV = 'csv';
export const EXPORT_FORMAT_JSONL = 'jsonl';

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

export function buildExportUrl( filters, format = EXPORT_FORMAT_CSV ) {
	const base = config.exportUrl || '';
	if ( ! base ) {
		return '';
	}

	const url = new URL( base, window.location.origin );

	FILTER_KEYS.forEach( ( key ) => {
		if ( filters[ key ] ) {
			url.searchParams.set( key, filters[ key ] );
		}
	} );

	url.searchParams.set( 'aal-record-actions-submit', '1' );
	url.searchParams.set( 'aal-record-action', format );
	url.searchParams.set( 'aal_actions_nonce', config.exportNonce || '' );

	return url.toString();
}

export function fetchSettings() {
	return apiFetch( {
		path: 'activity-log/v1/settings',
		headers: { 'X-AAL-Settings-Nonce': config.settingsNonce || '' },
	} );
}

export function saveSettings( data ) {
	return apiFetch( {
		path: 'activity-log/v1/settings',
		method: 'PUT',
		data,
		headers: { 'X-AAL-Settings-Nonce': config.settingsNonce || '' },
	} );
}

export function eraseLogs() {
	return apiFetch( {
		path: 'activity-log/v1/logs/erase',
		method: 'POST',
		headers: { 'X-AAL-Settings-Nonce': config.settingsNonce || '' },
	} );
}
