import './widget.scss';
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs } from '../api';
import TimelineItem from './timeline-item';
import { groupItemsByDay, mergeUniqueById } from './utils';

const config = window.aalDashboard || {};
const PER_PAGE = config.perPage || 10;

export default function DashboardWidget() {
	const [ items, setItems ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ hasMore, setHasMore ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ loadingMore, setLoadingMore ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ loadMoreError, setLoadMoreError ] = useState( null );
	const [ expandedIds, setExpandedIds ] = useState( () => new Set() );

	const dayGroups = useMemo( () => groupItemsByDay( items ), [ items ] );

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
	const showFooter = ( hasMore && ! error ) || viewAllUrl;

	return (
		<div className="aal-dashboard-widget">
			{ ( error || loadMoreError ) && (
				<Notice status="error" isDismissible={ false }>
					{ error || loadMoreError }
				</Notice>
			) }

			{ loading && ! items.length && (
				<div className="aal-dashboard-loading">
					<Spinner />
				</div>
			) }

			{ ! loading && ! error && ! items.length && (
				<p className="aal-dashboard-empty">
					{ __( 'No activity logged yet.', 'aryo-activity-log' ) }
				</p>
			) }

			{ items.length > 0 && (
				<div className="aal-dashboard-timeline">
					<span
						className="aal-dashboard-timeline__line"
						aria-hidden="true"
					/>
					<ul className="aal-dashboard-days">
						{ dayGroups.map( ( group ) => (
							<li
								key={ group.dayKey }
								className="aal-dashboard-day"
							>
								<p className="aal-dashboard-day__header">
									{ group.header }
								</p>
								<ul className="aal-dashboard-day__list">
									{ group.items.map( ( item ) => (
										<TimelineItem
											key={ item.id }
											item={ item }
											isExpanded={ expandedIds.has(
												item.id
											) }
											onToggle={ toggleExpanded }
										/>
									) ) }
								</ul>
							</li>
						) ) }
					</ul>
				</div>
			) }

			{ showFooter && (
				<div className="aal-dashboard-footer">
					{ hasMore && ! error && (
						<Button
							variant="secondary"
							className="aal-dashboard-load-more"
							onClick={ () => loadPage( page + 1, true ) }
							isBusy={ loadingMore }
							disabled={ loadingMore }
						>
							{ __( 'Load more', 'aryo-activity-log' ) }
						</Button>
					) }
					{ viewAllUrl && (
						<div className="aal-dashboard-footer__view-all-wrap">
							<a
								className="aal-dashboard-footer__view-all"
								href={ viewAllUrl }
							>
								{ __( 'View all', 'aryo-activity-log' ) } →
							</a>
						</div>
					) }
				</div>
			) }
		</div>
	);
}
