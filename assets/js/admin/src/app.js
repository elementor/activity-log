import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	Button,
	SelectControl,
	SearchControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs, fetchFilters, dismissPromotion, buildExportUrl } from './api';

function useDebounce( value, delay ) {
	const [ debounced, setDebounced ] = useState( value );
	const timerRef = useRef();

	useEffect( () => {
		timerRef.current = setTimeout( () => setDebounced( value ), delay );
		return () => clearTimeout( timerRef.current );
	}, [ value, delay ] );

	return debounced;
}

const config = window.aalAdmin || {};

const styles = {
	app: { marginTop: 16 },
	filtersRow: { display: 'flex', alignItems: 'flex-end', gap: 8, marginBottom: 0 },
	selectControl: { minWidth: 0 },
	searchControl: { width: 200 },
	loading: { textAlign: 'center', padding: '40px 0' },
	pagination: { display: 'flex', alignItems: 'center', gap: 8, marginTop: 12, justifyContent: 'flex-end' },
	paginationInfo: { fontSize: 13, color: '#50575e' },
	badgeBase: { display: 'inline-block', padding: '1px 6px', borderRadius: 3, fontSize: 12, lineHeight: '18px', textDecoration: 'none' },
	badgeChannel: { background: '#e7f0f7', color: '#1e4d78' },
	badgeApp: { background: '#f0e7f7', color: '#4d1e78' },
	noItems: { textAlign: 'center', padding: 20, color: '#646970' },
	promotionNotice: { margin: '8px 0' },
	activeFilter: { fontWeight: 600, background: '#e5f5fa', borderRadius: 3, padding: '1px 4px', display: 'inline-flex', alignItems: 'center', gap: 2 },
};

function getInitialState() {
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

function syncUrl( params ) {
	const url = new URL( window.location.href );
	const filterKeys = [
		'dateshow', 'capshow', 'usershow', 'typeshow',
		'showaction', 'sourceshow', 'filter_ip', 's',
	];

	filterKeys.forEach( ( key ) => {
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

function Pagination( { page, pages, total, onPageChange } ) {
	return (
		<div style={ styles.pagination }>
			<span style={ styles.paginationInfo }>
				{ total } { __( 'items', 'aryo-activity-log' ) }
				{ pages > 1 && (
					<>{ ' — ' }{ __( 'Page', 'aryo-activity-log' ) } { page } / { pages }</>
				) }
			</span>
			{ pages > 1 && (
				<>
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => onPageChange( 1 ) }
						size="small"
					>
						«
					</Button>
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => onPageChange( page - 1 ) }
						size="small"
					>
						‹
					</Button>
					<Button
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => onPageChange( page + 1 ) }
						size="small"
					>
						›
					</Button>
					<Button
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => onPageChange( pages ) }
						size="small"
					>
						»
					</Button>
				</>
			) }
		</div>
	);
}

function PromotionRow( { promo, colSpan, onDismiss } ) {
	return (
		<tr style={ { background: '#f0f6fc' } }>
			<td colSpan={ colSpan } style={ { padding: '12px 16px' } }>
				<div style={ { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 } }>
					<div>
						<strong>{ promo.title }</strong>{ ' ' }
						{ promo.body }{ ' ' }
						<a href={ promo.cta_url } style={ { fontWeight: 600 } }>
							{ promo.cta_text }
						</a>
					</div>
					<Button
						variant="link"
						onClick={ () => onDismiss( promo.id ) }
						style={ { flexShrink: 0, color: '#757575', textDecoration: 'none' } }
					>
						{ __( 'Dismiss', 'aryo-activity-log' ) }
					</Button>
				</div>
			</td>
		</tr>
	);
}

function SourceBadge( { type, onClick, children } ) {
	const badgeStyle = {
		...styles.badgeBase,
		...( type === 'channel' ? styles.badgeChannel : styles.badgeApp ),
	};

	if ( onClick ) {
		return (
			<a href="#" style={ badgeStyle } onClick={ onClick }>
				{ children }
			</a>
		);
	}

	return <span style={ badgeStyle }>{ children }</span>;
}

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

function FilterLink( { filterKey, value, params, onFilter, children } ) {
	const isActive = params[ filterKey ] === value;

	return (
		<a
			href="#"
			style={ isActive ? { ...styles.activeFilter, display: 'inline-flex', alignItems: 'center', gap: 3 } : undefined }
			onClick={ ( e ) => {
				e.preventDefault();
				onFilter( filterKey, value );
			} }
		>
			<span>{ children }</span>{ isActive && <span style={ { fontSize: 11 } }>✕</span> }
		</a>
	);
}

export default function App() {
	const [ params, setParams ] = useState( getInitialState );
	const [ searchInput, setSearchInput ] = useState( params.s );
	const debouncedSearch = useDebounce( searchInput, 500 );
	const [ data, setData ] = useState( { items: [], total: 0, pages: 0, promotions: [] } );
	const [ filterOptions, setFilterOptions ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const trimmed = debouncedSearch.trim();
		setParams( ( prev ) => {
			if ( prev.s === trimmed ) {
				return prev;
			}
			return { ...prev, s: trimmed, page: 1 };
		} );
	}, [ debouncedSearch ] );

	const loadData = useCallback( ( queryParams ) => {
		setLoading( true );
		setError( null );

		fetchLogs( queryParams )
			.then( ( result ) => {
				setData( result );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Failed to load logs.', 'aryo-activity-log' ) );
				setLoading( false );
			} );
	}, [] );

	useEffect( () => {
		fetchFilters()
			.then( setFilterOptions )
			.catch( () => {} );
	}, [] );

	useEffect( () => {
		loadData( params );
		syncUrl( params );
	}, [ params, loadData ] );

	const updateFilter = ( key, value ) => {
		setParams( ( prev ) => ( {
			...prev,
			[ key ]: prev[ key ] === value ? '' : value,
			page: 1,
		} ) );
	};

	const resetFilters = () => {
		setSearchInput( '' );
		setParams( ( prev ) => ( {
			...prev,
			dateshow: '',
			capshow: '',
			usershow: '',
			typeshow: '',
			showaction: '',
			sourceshow: '',
			filter_ip: '',
			s: '',
			page: 1,
		} ) );
	};

	const hasActiveFilters = [ 'dateshow', 'capshow', 'usershow', 'typeshow', 'showaction', 'sourceshow', 'filter_ip', 's' ]
		.some( ( key ) => params[ key ] !== '' );

	const handleDismissPromotion = ( id ) => {
		dismissPromotion( id );
		setData( ( prev ) => ( {
			...prev,
			promotions: prev.promotions.filter( ( p ) => p.id !== id ),
		} ) );
	};

	const dateOptions = [
		{ label: __( 'All Time', 'aryo-activity-log' ), value: '' },
		{ label: __( 'Today', 'aryo-activity-log' ), value: 'today' },
		{ label: __( 'Yesterday', 'aryo-activity-log' ), value: 'yesterday' },
		{ label: __( 'Week', 'aryo-activity-log' ), value: 'week' },
		{ label: __( 'Month', 'aryo-activity-log' ), value: 'month' },
	];

	const exportUrl = buildExportUrl( params );

	return (
		<div style={ styles.app }>
			<div style={ styles.filtersRow }>
				<div style={ { display: 'flex', flexWrap: 'wrap', gap: 4, alignItems: 'flex-end', flex: 1 } }>
				<SelectControl
					value={ params.dateshow }
					options={ dateOptions }
					onChange={ ( v ) => updateFilter( 'dateshow', v ) }
					__nextHasNoMarginBottom
					style={ styles.selectControl }
				/>

				{ filterOptions && filterOptions.roles.length > 0 && (
					<SelectControl
						value={ params.capshow }
						options={ [
							{ label: __( 'All Roles', 'aryo-activity-log' ), value: '' },
							...filterOptions.roles.map( ( r ) => ( { label: r.label, value: r.value } ) ),
						] }
						onChange={ ( v ) => updateFilter( 'capshow', v ) }
						__nextHasNoMarginBottom
						style={ styles.selectControl }
					/>
				) }

				{ filterOptions && filterOptions.users.length > 0 && (
					<SelectControl
						value={ params.usershow }
						options={ [
							{ label: __( 'All Users', 'aryo-activity-log' ), value: '' },
							...filterOptions.users.map( ( u ) => ( { label: u.name, value: String( u.id ) } ) ),
						] }
						onChange={ ( v ) => updateFilter( 'usershow', v ) }
						__nextHasNoMarginBottom
						style={ styles.selectControl }
					/>
				) }

				{ filterOptions && filterOptions.topics.length > 0 && (
					<SelectControl
						value={ params.typeshow }
						options={ [
							{ label: __( 'All Topics', 'aryo-activity-log' ), value: '' },
							...filterOptions.topics.map( ( t ) => ( { label: t, value: t } ) ),
						] }
						onChange={ ( v ) => updateFilter( 'typeshow', v ) }
						__nextHasNoMarginBottom
						style={ styles.selectControl }
					/>
				) }

				{ filterOptions && filterOptions.actions.length > 0 && (
					<SelectControl
						value={ params.showaction }
						options={ [
							{ label: __( 'All Actions', 'aryo-activity-log' ), value: '' },
							...filterOptions.actions.map( ( a ) => ( { label: a.label, value: a.value } ) ),
						] }
						onChange={ ( v ) => updateFilter( 'showaction', v ) }
						__nextHasNoMarginBottom
						style={ styles.selectControl }
					/>
				) }

				{ filterOptions && filterOptions.sources.length > 0 && (
					<SelectControl
						value={ params.sourceshow }
						options={ [
							{ label: __( 'All Sources', 'aryo-activity-log' ), value: '' },
							...filterOptions.sources.map( ( s ) => ( { label: s.label, value: s.value } ) ),
						] }
						onChange={ ( v ) => updateFilter( 'sourceshow', v ) }
						__nextHasNoMarginBottom
						style={ styles.selectControl }
					/>
				) }

				{ hasActiveFilters && (
					<a
						href="#"
						onClick={ ( e ) => { e.preventDefault(); resetFilters(); } }
						style={ { display: 'inline-flex', alignItems: 'center', gap: 2, textDecoration: 'none', marginInlineStart: 5, height: 36 } }
					>
						<span className="dashicons dashicons-dismiss" style={ { fontSize: 15, width: 15, height: 15 } }></span>
						{ __( 'Reset Filters', 'aryo-activity-log' ) }
					</a>
				) }
				</div>

				<SearchControl
					value={ searchInput }
					onChange={ setSearchInput }
					placeholder={ __( 'Search…', 'aryo-activity-log' ) }
					__nextHasNoMarginBottom
					style={ styles.searchControl }
				/>
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ loading ? (
				<div style={ styles.loading }>
					<Spinner />
				</div>
			) : (
				<>
					<table className="wp-list-table widefat fixed striped" style={ { marginTop: 8 } }>
						<thead>
							<tr>
								<th
									style={ { cursor: 'pointer', userSelect: 'none' } }
									onClick={ () => setParams( ( prev ) => ( {
										...prev,
										orderby: 'hist_time',
										order: prev.order === 'DESC' ? 'ASC' : 'DESC',
										page: 1,
									} ) ) }
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
								renderRowsWithPromotions( data.items, data.promotions, params, updateFilter, handleDismissPromotion )
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
							onPageChange={ ( p ) => setParams( ( prev ) => ( { ...prev, page: p } ) ) }
						/>
					</div>
				</>
			) }
		</div>
	);
}

function LogRow( { item, params, onFilter } ) {
	const { date, author, source, type, label, description, action } = item;

	return (
		<tr>
			<td>
				<strong>{ date.relative }</strong>
				<br />
				<FilterLink filterKey="dateshow" value={ date.dateshow } params={ params } onFilter={ onFilter }>
					{ date.date }
				</FilterLink>
				<br />
				{ date.time }
			</td>
			<td>
				{ author.id > 0 ? (
					<FilterLink filterKey="usershow" value={ String( author.id ) } params={ params } onFilter={ onFilter }>
						{ author.avatar && (
							<img
								src={ author.avatar }
								alt=""
								width="40"
								height="40"
								className="avatar"
								style={ { verticalAlign: 'middle', marginRight: 4, borderRadius: '50%' } }
							/>
						) }
						<span style={ { verticalAlign: 'middle' } }>{ author.name }</span>
					</FilterLink>
				) : (
					<span>{ author.name }</span>
				) }
				{ author.role && <br /> }
				{ author.role && <small>{ author.role }</small> }
			</td>
			<td>
				{ source.channel_label && (
					<>
						<SourceBadge
							type="channel"
							onClick={ ( e ) => {
								e.preventDefault();
								onFilter( 'sourceshow', source.channel );
							} }
						>
							{ source.channel_label }
						</SourceBadge>
						<br />
					</>
				) }
				{ source.app_name && (
					<>
						<SourceBadge type="app">{ source.app_name }</SourceBadge>
						<br />
					</>
				) }
				{ source.ip && (
					<FilterLink filterKey="filter_ip" value={ source.ip } params={ params } onFilter={ onFilter }>
						{ source.ip }
					</FilterLink>
				) }
			</td>
			<td>
				<FilterLink filterKey="typeshow" value={ type.value } params={ params } onFilter={ onFilter }>
					{ type.label }
				</FilterLink>
			</td>
			<td>{ label.label }</td>
			<td>
				{ description.text }
				{ Object.keys( description.actions ).length > 0 && (
					<div className="row-actions">
						{ Object.entries( description.actions ).map( ( [ key, url ], i ) => (
							<span key={ key } className={ key }>
								{ i > 0 && ' | ' }
								<a href={ url }>{ key.charAt( 0 ).toUpperCase() + key.slice( 1 ) }</a>
							</span>
						) ) }
					</div>
				) }
			</td>
			<td>
				<FilterLink filterKey="showaction" value={ action.value } params={ params } onFilter={ onFilter }>
					{ action.label }
				</FilterLink>
			</td>
		</tr>
	);
}
