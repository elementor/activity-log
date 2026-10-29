import './logs.scss';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs, fetchFilters, dismissPromotion, buildExportUrl, EXPORT_FORMAT_CSV, EXPORT_FORMAT_JSONL } from '../api';
import useDebounce from '../hooks/use-debounce';
import { getInitialState, syncUrl, FILTER_KEYS } from './query';
import FiltersBar from './filters-bar';
import LogTable from './log-table';

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
		setParams( ( prev ) => {
			const cleared = { ...prev, page: 1 };
			FILTER_KEYS.forEach( ( key ) => {
				cleared[ key ] = '';
			} );
			return cleared;
		} );
	};

	const hasActiveFilters = FILTER_KEYS.some( ( key ) => params[ key ] !== '' );

	const handleDismissPromotion = ( id ) => {
		dismissPromotion( id );
		setData( ( prev ) => ( {
			...prev,
			promotions: prev.promotions.filter( ( p ) => p.id !== id ),
		} ) );
	};

	const handleSortToggle = () => {
		setParams( ( prev ) => ( {
			...prev,
			orderby: 'hist_time',
			order: prev.order === 'DESC' ? 'ASC' : 'DESC',
			page: 1,
		} ) );
	};

	const csvExportUrl = buildExportUrl( params, EXPORT_FORMAT_CSV );
	const jsonlExportUrl = buildExportUrl( params, EXPORT_FORMAT_JSONL );

	return (
		<div className="aal-logs-app">
			<FiltersBar
				params={ params }
				filterOptions={ filterOptions }
				searchInput={ searchInput }
				hasActiveFilters={ hasActiveFilters }
				onFilterChange={ updateFilter }
				onSearchChange={ setSearchInput }
				onReset={ resetFilters }
			/>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ loading ? (
				<div className="aal-logs-loading">
					<Spinner />
				</div>
			) : (
				<LogTable
					data={ data }
					params={ params }
					csvExportUrl={ csvExportUrl }
					jsonlExportUrl={ jsonlExportUrl }
					hasActiveFilters={ hasActiveFilters }
					onFilterChange={ updateFilter }
					onSortToggle={ handleSortToggle }
					onPageChange={ ( p ) => setParams( ( prev ) => ( { ...prev, page: p } ) ) }
					onDismissPromotion={ handleDismissPromotion }
				/>
			) }
		</div>
	);
}
