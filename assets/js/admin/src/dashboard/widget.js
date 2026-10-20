import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs } from '../api';
import { SourceBadge } from '../logs/log-row';

const config = window.aalDashboard || {};
const PER_PAGE = config.perPage || 10;
const THEME_COLOR = 'var(--wp-admin-theme-color, #2271b1)';

const styles = {
	widget: { position: 'relative' },
	timeline: {
		position: 'relative',
		margin: 0,
		padding: 0,
		listStyle: 'none',
	},
	timelineLine: {
		position: 'absolute',
		left: 9,
		top: 0,
		bottom: 0,
		width: 1,
		background: '#e0e0e0',
		pointerEvents: 'none',
	},
	dayGroups: { margin: 0, padding: 0, listStyle: 'none' },
	dayHeader: {
		fontSize: 11,
		fontWeight: 600,
		textTransform: 'uppercase',
		letterSpacing: '0.04em',
		color: '#757575',
		margin: '14px 0 6px',
		padding: 0,
	},
	dayHeaderFirst: { marginTop: 0 },
	dayList: { listStyle: 'none', margin: 0, padding: 0 },
	item: {
		position: 'relative',
		paddingLeft: 28,
		marginBottom: 2,
	},
	avatar: {
		position: 'absolute',
		left: 0,
		top: 8,
		width: 20,
		height: 20,
		borderRadius: '50%',
		flexShrink: 0,
		zIndex: 1,
		background: '#dcdcde',
		objectFit: 'cover',
	},
	rowText: {
		flex: 1,
		minWidth: 0,
		whiteSpace: 'nowrap',
		overflow: 'hidden',
		textOverflow: 'ellipsis',
	},
	topic: { color: '#1e1e1e', fontWeight: 400 },
	descMuted: { color: '#757575' },
	authorMuted: { color: '#757575', fontSize: 12 },
	timeRight: {
		flexShrink: 0,
		fontSize: 12,
		color: '#757575',
		marginLeft: 8,
	},
	details: {
		margin: '4px 0 8px 0',
		paddingLeft: 28,
		fontSize: 12,
		color: '#757575',
		display: 'flex',
		flexDirection: 'column',
		gap: 6,
	},
	footer: {
		marginTop: 12,
		display: 'flex',
		flexDirection: 'column',
		gap: 8,
	},
	viewAllWrap: { textAlign: 'right' },
	viewAllLink: {
		color: THEME_COLOR,
		textDecoration: 'none',
		fontSize: 13,
	},
	loading: { textAlign: 'center', padding: '24px 0' },
	empty: { color: '#646970', margin: 0 },
};

const widgetStyles = `
.aal-dashboard-widget-row {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	margin: 0;
	padding: 6px 8px;
	border: none;
	background: transparent;
	cursor: pointer;
	text-align: left;
	font: inherit;
	color: inherit;
	border-radius: 4px;
}
.aal-dashboard-widget-row:hover,
.aal-dashboard-widget-row:focus-visible {
	background: #f6f7f7;
	outline: none;
}
.aal-dashboard-widget-chevron {
	flex-shrink: 0;
	font-size: 10px;
	color: #757575;
	transition: transform 0.15s ease;
	visibility: hidden;
}
.aal-dashboard-widget-row:hover .aal-dashboard-widget-chevron,
.aal-dashboard-widget-row:focus-visible .aal-dashboard-widget-chevron,
.aal-dashboard-widget-row[aria-expanded="true"] .aal-dashboard-widget-chevron {
	visibility: visible;
}
.aal-dashboard-widget-row[aria-expanded="true"] .aal-dashboard-widget-chevron {
	transform: rotate(180deg);
}
.aal-dashboard-widget-action-pill {
	display: inline-block;
	background: #f0f6fc;
	color: ${ THEME_COLOR };
	border-radius: 10px;
	font-size: 11px;
	padding: 2px 8px;
	line-height: 1.4;
	align-self: flex-start;
}
@supports (background: color-mix(in srgb, red, blue)) {
	.aal-dashboard-widget-action-pill {
		background: color-mix(in srgb, var(--wp-admin-theme-color, #2271b1) 12%, white);
	}
}
.aal-dashboard-widget-load-more {
	width: 100%;
	justify-content: center;
}
`;

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
				className="avatar"
				style={ styles.avatar }
			/>
		);
	}

	return <span style={ styles.avatar } aria-hidden="true" />;
}

function TimelineItem( { item, isExpanded, onToggle } ) {
	const { date, author, source, type, label, description, action } = item;
	const detailsId = `aal-dashboard-log-${ item.id }`;
	const topic =
		type.label + ( label.label ? `: ${ label.label }` : '' );

	return (
		<li style={ styles.item }>
			<AuthorAvatar url={ author.avatar } />
			<button
				type="button"
				className="aal-dashboard-widget-row"
				aria-expanded={ isExpanded }
				aria-controls={ detailsId }
				onClick={ () => onToggle( item.id ) }
			>
				<span style={ styles.rowText }>
					<span style={ styles.topic }>{ topic }</span>
					{ description.text && (
						<span style={ styles.descMuted }>
							{ ` · ${ description.text }` }
						</span>
					) }
					<span style={ styles.authorMuted }>
						{ ` ${ author.name }` }
					</span>
				</span>
				<span style={ styles.timeRight }>{ date.relative }</span>
				<span className="aal-dashboard-widget-chevron" aria-hidden="true">
					▼
				</span>
			</button>
			{ isExpanded && (
				<div id={ detailsId } style={ styles.details }>
					<span className="aal-dashboard-widget-action-pill">
						{ action.label }
					</span>
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
		<div className="aal-dashboard-widget" style={ styles.widget }>
			<style>{ widgetStyles }</style>

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
				<div style={ styles.timeline }>
					<span style={ styles.timelineLine } aria-hidden="true" />
					<ul style={ styles.dayGroups }>
						{ dayGroups.map( ( group, groupIndex ) => (
							<li key={ group.dayKey }>
								<p
									style={ {
										...styles.dayHeader,
										...( groupIndex === 0
											? styles.dayHeaderFirst
											: {} ),
									} }
								>
									{ group.header }
								</p>
								<ul style={ styles.dayList }>
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
				<div style={ styles.footer }>
					{ hasMore && ! error && (
						<Button
							variant="secondary"
							className="aal-dashboard-widget-load-more"
							onClick={ () => loadPage( page + 1, true ) }
							isBusy={ loadingMore }
							disabled={ loadingMore }
						>
							{ __( 'Load more', 'aryo-activity-log' ) }
						</Button>
					) }
					{ viewAllUrl && (
						<div style={ styles.viewAllWrap }>
							<a
								className="aal-dashboard-widget-view-all"
								href={ viewAllUrl }
								style={ styles.viewAllLink }
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
