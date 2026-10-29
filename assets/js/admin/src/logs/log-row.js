import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import ChangesModal from './changes-modal';
import {
	getActionTone,
	getDisplayTime,
	getFullTimestamp,
} from './utils';

export function FilterLink( { filterKey, value, params, onFilter, children, className = '' } ) {
	const isActive = params[ filterKey ] === value;
	const classNames = [
		'aal-logs-filter-link',
		className,
		isActive ? 'is-active' : '',
	].filter( Boolean ).join( ' ' );

	return (
		<a
			href="#"
			className={ classNames }
			onClick={ ( e ) => {
				e.preventDefault();
				onFilter( filterKey, value );
			} }
		>
			<span>{ children }</span>
			{ isActive && (
				<span className="aal-logs-filter-link__clear" aria-hidden="true">✕</span>
			) }
		</a>
	);
}

export function SourceBadge( { type, onClick, children } ) {
	const classNames = [
		'aal-logs-source-badge',
		type === 'channel' ? 'aal-logs-source-badge--channel' : 'aal-logs-source-badge--app',
	].join( ' ' );

	if ( onClick ) {
		return (
			<a href="#" className={ classNames } onClick={ onClick }>
				{ children }
			</a>
		);
	}

	return <span className={ classNames }>{ children }</span>;
}

function ActionBadge( { action, params, onFilter } ) {
	const tone = getActionTone( action.value );
	const isActive = params.showaction === action.value;
	const classNames = [
		'aal-logs-action-badge',
		`aal-logs-action-badge--${ tone }`,
		isActive ? 'is-active' : '',
	].filter( Boolean ).join( ' ' );

	return (
		<a
			href="#"
			className={ classNames }
			onClick={ ( e ) => {
				e.preventDefault();
				onFilter( 'showaction', action.value );
			} }
		>
			{ action.label }
		</a>
	);
}

function MetaSeparator() {
	return (
		<span className="aal-logs-row__meta-sep aal-logs-row__meta-sep--collapse" aria-hidden="true">
			·
		</span>
	);
}

export default function LogRow( { item, params, onFilter } ) {
	const [ changesOpen, setChangesOpen ] = useState( false );
	const { date, author, source, type, label, description, action, changes, changes_omitted: omitted } = item;
	const changeCount = changes ? Object.keys( changes ).length : 0;
	const displayTime = getDisplayTime( date );
	const fullTimestamp = getFullTimestamp( date );
	const topicLine = label.label
		? `${ type.label }: ${ label.label }`
		: type.label;

	const authorBlock = author.id > 0 ? (
		<FilterLink
			filterKey="usershow"
			value={ String( author.id ) }
			params={ params }
			onFilter={ onFilter }
			className="aal-logs-row__author-link"
		>
			{ author.avatar ? (
				<img
					src={ author.avatar }
					alt=""
					width="32"
					height="32"
					className="avatar aal-logs-row__avatar"
				/>
			) : (
				<span className="aal-logs-row__avatar aal-logs-row__avatar--placeholder" aria-hidden="true" />
			) }
			<span className="aal-logs-row__author-text">
				<span className="aal-logs-row__author-name">{ author.name }</span>
				{ author.role && (
					<span className="aal-logs-row__author-role">{ author.role }</span>
				) }
			</span>
		</FilterLink>
	) : (
		<div className="aal-logs-row__author-link">
			<span className="aal-logs-row__avatar aal-logs-row__avatar--placeholder" aria-hidden="true" />
			<span className="aal-logs-row__author-text">
				<span className="aal-logs-row__author-name">{ author.name }</span>
			</span>
		</div>
	);

	return (
		<li className="aal-logs-row">
			<div className="aal-logs-row__inner">
				<div className="aal-logs-row__author">{ authorBlock }</div>
				<div className="aal-logs-row__body">
					<div className="aal-logs-row__primary">
						<p className="aal-logs-row__description">{ description.text }</p>
						<ActionBadge action={ action } params={ params } onFilter={ onFilter } />
					</div>
					<div className="aal-logs-row__meta">
						<FilterLink filterKey="typeshow" value={ type.value } params={ params } onFilter={ onFilter }>
							{ topicLine }
						</FilterLink>
						{ source.channel_label && (
							<>
								<MetaSeparator />
								<SourceBadge
									type="channel"
									onClick={ ( e ) => {
										e.preventDefault();
										onFilter( 'sourceshow', source.channel );
									} }
								>
									{ source.channel_label }
								</SourceBadge>
							</>
						) }
						{ source.app_name && (
							<>
								<MetaSeparator />
								<span className="aal-logs-row__meta-part aal-logs-row__meta-part--collapse">
									<SourceBadge type="app">{ source.app_name }</SourceBadge>
								</span>
							</>
						) }
						{ source.ip && (
							<>
								<MetaSeparator />
								<FilterLink filterKey="filter_ip" value={ source.ip } params={ params } onFilter={ onFilter }>
									{ source.ip }
								</FilterLink>
							</>
						) }
						<MetaSeparator />
						<FilterLink filterKey="dateshow" value={ date.dateshow } params={ params } onFilter={ onFilter }>
							<time
								className="aal-logs-row__meta-time"
								dateTime={ date.iso8601 }
								title={ fullTimestamp }
							>
								{ displayTime }
							</time>
						</FilterLink>
					</div>
					{ ( changeCount > 0 || Object.keys( description.actions ).length > 0 ) && (
						<div className="aal-logs-row__actions row-actions">
							{ changeCount > 0 && (
								<span className="view">
									<a
										href="#"
										onClick={ ( event ) => {
											event.preventDefault();
											setChangesOpen( true );
										} }
									>
										{ sprintf( /* translators: %d: number of changed fields. */ _n( '%d change', '%d changes', changeCount, 'aryo-activity-log' ), changeCount ) }
										{ omitted ? ` +${ omitted }` : '' }
									</a>
								</span>
							) }
							{ Object.entries( description.actions ).map( ( [ key, url ], i ) => (
								<span key={ key } className={ key }>
									{ ( changeCount > 0 || i > 0 ) && ' | ' }
									<a href={ url }>{ key.charAt( 0 ).toUpperCase() + key.slice( 1 ) }</a>
								</span>
							) ) }
						</div>
					) }
				</div>
			</div>
			{ changesOpen && changeCount > 0 && (
				<ChangesModal
					changes={ changes }
					omitted={ omitted }
					onClose={ () => setChangesOpen( false ) }
				/>
			) }
		</li>
	);
}
