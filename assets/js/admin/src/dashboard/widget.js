import './widget.scss';
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs } from '../api';
import { SourceBadge } from '../logs/log-row';

const config = window.aalDashboard || {};
const PER_PAGE = config.perPage || 10;

function mergeUniqueById( existing, incoming ) {
	const seen = new Set( existing.map( ( item ) => item.id ) );
	const added = incoming.filter( ( item ) => ! seen.has( item.id ) );
	return existing.concat( added );
}

function isSameLocalDay( a, b ) {
	return (
		a.getFullYear() === b.getFullYear() &&
		a.getMonth() === b.getMonth() &&
		a.getDate() === b.getDate()
	);
}

function getDayHeaderLabel( iso8601 ) {
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

function groupItemsByDay( items ) {
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

function AuthorAvatar( { url } ) {
	if ( url ) {
		return (
			<img
				src={ url }
				alt=""
				width="20"
				height="20"
				className="avatar aal-dashboard-item__avatar"
			/>
		);
	}

	return (
		<span
			className="aal-dashboard-item__avatar"
			aria-hidden="true"
		/>
	);
}

function TimelineItem( { item, isExpanded, onToggle } ) {
	const { date, author, source, type, label, description, action } = item;
	const detailsId = `aal-dashboard-log-${ item.id }`;
	const topic =
		type.label + ( label.label ? `: ${ label.label }` : '' );
	const rowClassName =
		'aal-dashboard-row' + ( isExpanded ? ' is-expanded' : '' );

	return (
		<li className="aal-dashboard-item">
			<AuthorAvatar url={ author.avatar } />
			<button
				type="button"
				className={ rowClassName }
				aria-expanded={ isExpanded }
				aria-controls={ detailsId }
				onClick={ () => onToggle( item.id ) }
			>
				<span className="aal-dashboard-row__text">
					<span className="aal-dashboard-row__topic">{ topic }</span>
					{ description.text && (
						<span className="aal-dashboard-row__desc">
							{ ` · ${ description.text }` }
						</span>
					) }
					<span className="aal-dashboard-row__author">
						{ ` ${ author.name }` }
					</span>
				</span>
				<span className="aal-dashboard-row__time">{ date.relative }</span>
				<span
					className="aal-dashboard-row__chevron"
					aria-hidden="true"
				>
					▼
				</span>
			</button>
			{ isExpanded && (
				<div id={ detailsId } className="aal-dashboard-details">
					<span className="aal-dashboard-pill">{ action.label }</span>
					<div>
						{ date.date }
						{ ' ' }
						{ date.time }
					</div>
					{ ( source.channel_label || source.app_name ) && (
						<div>
							{ source.channel_label && (
								<SourceBadge type="channel">
									{ source.channel_label }
								</SourceBadge>
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
