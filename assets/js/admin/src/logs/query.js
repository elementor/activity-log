export const FILTER_KEYS = [
	'dateshow', 'capshow', 'usershow', 'typeshow',
	'showaction', 'sourceshow', 'filter_ip', 's',
];

const config = window.aalAdmin || {};

export function getInitialState() {
	const q = config.initialQuery || {};
	const urlParams = new URLSearchParams( window.location.search );
	return {
		page: parseInt( urlParams.get( 'paged' ), 10 ) || 1,
		per_page: config.perPage || 50,
		orderby: 'hist_time',
		order: 'DESC',
		dateshow: q.dateshow || '',
		capshow: q.capshow || '',
		usershow: q.usershow || '',
		typeshow: q.typeshow || '',
		showaction: q.showaction || '',
		sourceshow: q.sourceshow || '',
		filter_ip: q.filter_ip || '',
		s: q.s || '',
	};
}

export function syncUrl( params ) {
	const url = new URL( window.location.href );

	FILTER_KEYS.forEach( ( key ) => {
		if ( params[ key ] ) {
			url.searchParams.set( key, params[ key ] );
		} else {
			url.searchParams.delete( key );
		}
	} );

	if ( params.page > 1 ) {
		url.searchParams.set( 'paged', params.page );
	} else {
		url.searchParams.delete( 'paged' );
	}

	window.history.replaceState( null, '', url.toString() );
}
