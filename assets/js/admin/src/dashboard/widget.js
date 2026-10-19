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
		paddingBottom: 16,
		borderLeft: '2px solid #dcdcde',
		marginLeft: 6,
	},
	itemLast: { borderLeftColor: 'transparent', paddingBottom: 0 },
	dot: {
		position: 'absolute',
		left: -5,
		top: 4,
		width: 8,
		height: 8,
		borderRadius: '50%',
		background: '#2271b1',
	},
	meta: { color: '#646970', fontSize: 12, marginBottom: 4 },
	title: { fontWeight: 600, marginBottom: 2 },
	description: { margin: 0 },
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

function TimelineItem( { item, isLast } ) {
	const { date, author, source, type, label, description, action } = item;

	return (
		<li style={ { ...styles.item, ...( isLast ? styles.itemLast : {} ) } }>
			<span style={ styles.dot } aria-hidden="true" />
			<div style={ styles.meta }>
				<strong>{ date.relative }</strong>
				{ ' · ' }
				{ date.date }
				{ ' ' }
				{ date.time }
			</div>
			<div style={ styles.title }>
				{ author.name }
				{ ' — ' }
				{ type.label }
				{ ': ' }
				{ label.label }
			</div>
			{ description.text && (
				<p style={ styles.description }>{ description.text }</p>
			) }
			<div style={ styles.meta }>
				{ action.label }
				{ source.channel_label && (
					<>
						{ ' · ' }
						<SourceBadge type="channel">{ source.channel_label }</SourceBadge>
					</>
				) }
				{ source.app_name && (
					<>
						{ ' · ' }
						<SourceBadge type="app">{ source.app_name }</SourceBadge>
					</>
				) }
			</div>
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

	const loadPage = useCallback( ( pageNum, append ) => {
		if ( append ) {
			setLoadingMore( true );
		} else {
			setLoading( true );
		}
		setError( null );

		return fetchLogs( { page: pageNum, per_page: PER_PAGE } )
			.then( ( result ) => {
				setPage( pageNum );
				setHasMore( pageNum < result.pages );
				setItems( ( prev ) =>
					append ? mergeUniqueById( prev, result.items ) : result.items
				);
			} )
			.catch( ( err ) => {
				setError(
					err.message ||
						__( 'Failed to load activity.', 'aryo-activity-log' )
				);
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

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
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
