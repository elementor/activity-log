import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs } from '../api';
import { SourceBadge } from '../logs/log-row';

const config = window.aalDashboard || {};
const PER_PAGE = config.perPage || 10;

const styles = {
	timeline: { listStyle: 'none', margin: 0, padding: 0 },
	item: {
		position: 'relative',
		paddingLeft: 20,
		paddingBottom: 8,
		borderLeft: '2px solid #dcdcde',
		marginLeft: 6,
	},
	itemLast: { borderLeftColor: 'transparent', paddingBottom: 0 },
	dot: {
		position: 'absolute',
		left: -5,
		top: 10,
		width: 8,
		height: 8,
		borderRadius: '50%',
		background: '#2271b1',
	},
	rowButton: {
		display: 'flex',
		alignItems: 'center',
		gap: 6,
		width: '100%',
		margin: 0,
		padding: '4px 0',
		border: 'none',
		background: 'transparent',
		cursor: 'pointer',
		textAlign: 'left',
		font: 'inherit',
		color: 'inherit',
	},
	rowText: {
		flex: 1,
		minWidth: 0,
		whiteSpace: 'nowrap',
		overflow: 'hidden',
		textOverflow: 'ellipsis',
	},
	timeMuted: { color: '#646970', fontWeight: 400 },
	chevron: {
		flexShrink: 0,
		fontSize: 10,
		color: '#646970',
		transition: 'transform 0.15s ease',
	},
	chevronExpanded: { transform: 'rotate(180deg)' },
	details: {
		margin: '0 0 4px 0',
		paddingLeft: 4,
		fontSize: 12,
		color: '#646970',
	},
	footer: { marginTop: 12, display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' },
	viewAll: { marginBottom: 12, display: 'inline-block' },
	loading: { textAlign: 'center', padding: '24px 0' },
	empty: { color: '#646970', margin: 0 },
};

function mergeUniqueById( existing, incoming ) {
	const seen = new Set( existing.map( ( item ) => item.id ) );
	const added = incoming.filter( ( item ) => ! seen.has( item.id ) );
	return existing.concat( added );
}

function TimelineItem( { item, isLast, isExpanded, onToggle } ) {
	const { date, author, source, type, label, description, action } = item;
	const detailsId = `aal-dashboard-log-${ item.id }`;

	return (
		<li style={ { ...styles.item, ...( isLast ? styles.itemLast : {} ) } }>
			<span style={ styles.dot } aria-hidden="true" />
			<button
				type="button"
				className="aal-dashboard-widget-row"
				style={ styles.rowButton }
				aria-expanded={ isExpanded }
				aria-controls={ detailsId }
				onClick={ () => onToggle( item.id ) }
			>
				<span style={ styles.rowText }>
					<span style={ styles.timeMuted }>{ date.relative }</span>
					{ ' ' }
					<strong>{ author.name }</strong>
					{ ' ' }
					{ type.label }
					{ label.label ? `: ${ label.label }` : '' }
					{ description.text ? ` · ${ description.text }` : '' }
				</span>
				<span
					style={ {
						...styles.chevron,
						...( isExpanded ? styles.chevronExpanded : {} ),
					} }
					aria-hidden="true"
				>
					▼
				</span>
			</button>
			{ isExpanded && (
				<div id={ detailsId } style={ styles.details }>
					<div>
						{ date.date }
						{ ' ' }
						{ date.time }
					</div>
					<div>{ action.label }</div>
					{ ( source.channel_label || source.app_name ) && (
						<div>
							{ source.channel_label && (
								<SourceBadge type="channel">{ source.channel_label }</SourceBadge>
							) }
							{ source.channel_label && source.app_name && ' ' }
							{ source.app_name && (
								<SourceBadge type="app">{ source.app_name }</SourceBadge>
							) }
						</div>
					) }
				</div>
			) }
		</li>
	);
}

export default function DashboardWidget() {
	const [ items, setItems ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ hasMore, setHasMore ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ loadingMore, setLoadingMore ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ loadMoreError, setLoadMoreError ] = useState( null );
	const [ expandedIds, setExpandedIds ] = useState( () => new Set() );

	const toggleExpanded = ( id ) => {
		setExpandedIds( ( prev ) => {
			const next = new Set( prev );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );
	};

	const loadPage = useCallback( ( pageNum, append ) => {
		if ( append ) {
			setLoadingMore( true );
			setLoadMoreError( null );
		} else {
			setLoading( true );
			setError( null );
			setLoadMoreError( null );
		}

		return fetchLogs( { page: pageNum, per_page: PER_PAGE } )
			.then( ( result ) => {
				setPage( pageNum );
				setHasMore( pageNum < result.pages );
				setItems( ( prev ) =>
					append ? mergeUniqueById( prev, result.items ) : result.items
				);
				setLoadMoreError( null );
			} )
			.catch( ( err ) => {
				const message =
					err.message ||
					__( 'Failed to load activity.', 'aryo-activity-log' );
				if ( append ) {
					setLoadMoreError( message );
				} else {
					setError( message );
				}
			} )
			.finally( () => {
				setLoading( false );
				setLoadingMore( false );
			} );
	}, [] );

	useEffect( () => {
		loadPage( 1, false );
	}, [ loadPage ] );

	const viewAllUrl = config.viewAllUrl || '';

	return (
		<div className="aal-dashboard-widget">
			{ viewAllUrl && (
				<a className="aal-dashboard-widget-view-all" href={ viewAllUrl } style={ styles.viewAll }>
					{ __( 'View all', 'aryo-activity-log' ) }
				</a>
			) }

			{ ( error || loadMoreError ) && (
				<Notice status="error" isDismissible={ false }>
					{ error || loadMoreError }
				</Notice>
			) }

			{ loading && ! items.length && (
				<div style={ styles.loading }>
					<Spinner />
				</div>
			) }

			{ ! loading && ! error && ! items.length && (
				<p style={ styles.empty }>
					{ __( 'No activity logged yet.', 'aryo-activity-log' ) }
				</p>
			) }

			{ items.length > 0 && (
				<ul style={ styles.timeline }>
					{ items.map( ( item, index ) => (
						<TimelineItem
							key={ item.id }
							item={ item }
							isLast={ index === items.length - 1 && ! hasMore }
							isExpanded={ expandedIds.has( item.id ) }
							onToggle={ toggleExpanded }
						/>
					) ) }
				</ul>
			) }

			{ hasMore && ! error && (
				<div style={ styles.footer }>
					<Button
						variant="secondary"
						onClick={ () => loadPage( page + 1, true ) }
						isBusy={ loadingMore }
						disabled={ loadingMore }
					>
						{ __( 'Load more', 'aryo-activity-log' ) }
					</Button>
				</div>
			) }
		</div>
	);
}
