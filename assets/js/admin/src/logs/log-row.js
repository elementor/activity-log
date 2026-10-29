import { useState } from '@wordpress/element';
import { Button, DropdownMenu, MenuItem } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import ChangesModal from './changes-modal';
import {
	getActionTone,
	getDisplayTime,
	getFullTimestamp,
} from './utils';
import {
	getObjectLinkInEventText,
	partitionRowActions,
} from './event-actions';

export function FilterLink( { filterKey, value, params, onFilter, children, className = '', title = '' } ) {
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
			title={ title || undefined }
			onClick={ ( e ) => {
				e.preventDefault();
				onFilter( filterKey, value );
			} }
		>
			<span className="aal-logs-filter-link__text">{ children }</span>
			{ isActive && (
				<span className="aal-logs-filter-link__clear" aria-hidden="true">✕</span>
			) }
		</a>
	);
}

export function SourceBadge( { type, onClick, children, title = '' } ) {
	const classNames = [
		'aal-logs-source-badge',
		type === 'channel' ? 'aal-logs-source-badge--channel' : 'aal-logs-source-badge--app',
	].join( ' ' );

	if ( onClick ) {
		return (
			<a href="#" className={ classNames } title={ title || undefined } onClick={ onClick }>
				{ children }
			</a>
		);
	}

	return (
		<span className={ classNames } title={ title || undefined }>
			{ children }
		</span>
	);
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

function UserCell( { author, params, onFilter } ) {
	const nameTitle = author.role
		? sprintf(
			/* translators: 1: user display name, 2: role name */
			__( '%1$s (%2$s)', 'aryo-activity-log' ),
			author.name,
			author.role
		)
		: author.name;

	const content = (
		<span className="aal-logs-user">
			{ author.avatar ? (
				<img
					src={ author.avatar }
					alt=""
					width="24"
					height="24"
					className="avatar aal-logs-user__avatar"
				/>
			) : (
				<span className="aal-logs-user__avatar aal-logs-user__avatar--placeholder" aria-hidden="true" />
			) }
			<span className="aal-logs-user__name" title={ nameTitle }>
				{ author.name }
			</span>
		</span>
	);

	if ( author.id > 0 ) {
		return (
			<FilterLink
				filterKey="usershow"
				value={ String( author.id ) }
				params={ params }
				onFilter={ onFilter }
				className="aal-logs-user-link"
				title={ nameTitle }
			>
				{ content }
			</FilterLink>
		);
	}

	return content;
}

function EventText( { eventText, objectName, viewUrl } ) {
	const linkParts = getObjectLinkInEventText( eventText, objectName, viewUrl );

	if ( ! linkParts ) {
		return (
			<span className="aal-logs-event__text" title={ eventText }>
				{ eventText }
			</span>
		);
	}

	const viewLabel = sprintf(
		/* translators: %s: object name in the event description. */
		__( 'View %s', 'aryo-activity-log' ),
		linkParts.name
	);

	return (
		<span className="aal-logs-event__text" title={ eventText }>
			{ linkParts.before }
			<a
				href={ linkParts.url }
				className="aal-logs-event__object-link"
				aria-label={ viewLabel }
			>
				{ linkParts.name }
			</a>
			{ linkParts.after }
		</span>
	);
}

function ChangesButton( { changeCount, omitted, onOpen } ) {
	const changesLabel = sprintf(
		/* translators: %d: number of changed fields. */
		_n( '%d change', '%d changes', changeCount, 'aryo-activity-log' ),
		changeCount
	);
	const visibleLabel = omitted ? `${ changesLabel } +${ omitted }` : changesLabel;
	const ariaLabel = sprintf(
		/* translators: %s: change count phrase, e.g. "3 changes". */
		__( 'View %s', 'aryo-activity-log' ),
		changesLabel
	);

	return (
		<Button
			type="button"
			variant="tertiary"
			size="small"
			className="aal-logs-changes-btn"
			onClick={ onOpen }
			aria-label={ ariaLabel }
		>
			<span className="dashicons dashicons-list-view aal-logs-changes-btn__icon" aria-hidden="true" />
			<span className="aal-logs-changes-btn__label">{ visibleLabel }</span>
		</Button>
	);
}

function RowActionsMenu( { leftovers } ) {
	if ( ! leftovers.length ) {
		return null;
	}

	return (
		<DropdownMenu
			icon="ellipsis"
			label={ __( 'More row actions', 'aryo-activity-log' ) }
			className="aal-logs-row-menu"
			toggleProps={ {
				size: 'small',
				className: 'aal-logs-row-menu__toggle',
			} }
			popoverProps={ {
				placement: 'bottom-end',
			} }
		>
			{ ( { onClose } ) => (
				<>
					{ leftovers.map( ( { key, url, label } ) => (
						<MenuItem
							key={ key }
							href={ url }
							onClick={ onClose }
						>
							{ label }
						</MenuItem>
					) ) }
				</>
			) }
		</DropdownMenu>
	);
}

export default function LogRow( { item, params, onFilter } ) {
	const [ changesOpen, setChangesOpen ] = useState( false );
	const { date, author, source, type, label, description, action, changes, changes_omitted: omitted } = item;
	const changeCount = changes ? Object.keys( changes ).length : 0;
	const displayTime = getDisplayTime( date );
	const fullTimestamp = getFullTimestamp( date );
	const eventText = description.text || '';
	const objectName = label.label || '';
	const { viewUrl, leftovers } = partitionRowActions( description.actions );

	return (
		<>
			<tr className="aal-logs-data-row">
				<td className="aal-logs-col aal-logs-col--time">
					<FilterLink filterKey="dateshow" value={ date.dateshow } params={ params } onFilter={ onFilter } title={ fullTimestamp }>
						<time dateTime={ date.iso8601 } className="aal-logs-time">
							{ displayTime }
						</time>
					</FilterLink>
				</td>
				<td className="aal-logs-col aal-logs-col--user">
					<UserCell author={ author } params={ params } onFilter={ onFilter } />
				</td>
				<td className="aal-logs-col aal-logs-col--action">
					<ActionBadge action={ action } params={ params } onFilter={ onFilter } />
				</td>
				<td className="aal-logs-col aal-logs-col--event">
					<div className="aal-logs-event">
						<EventText eventText={ eventText } objectName={ objectName } viewUrl={ viewUrl } />
						{ changeCount > 0 && (
							<ChangesButton
								changeCount={ changeCount }
								omitted={ omitted }
								onOpen={ () => setChangesOpen( true ) }
							/>
						) }
						<RowActionsMenu leftovers={ leftovers } />
					</div>
				</td>
				<td className="aal-logs-col aal-logs-col--topic">
					<FilterLink filterKey="typeshow" value={ type.value } params={ params } onFilter={ onFilter } title={ type.label }>
						{ type.label }
					</FilterLink>
				</td>
				<td className="aal-logs-col aal-logs-col--context">
					<span className="aal-logs-muted aal-logs-truncate" title={ label.label }>
						{ label.label }
					</span>
				</td>
				<td className="aal-logs-col aal-logs-col--ip">
					{ source.ip ? (
						<FilterLink filterKey="filter_ip" value={ source.ip } params={ params } onFilter={ onFilter } title={ source.ip }>
							{ source.ip }
						</FilterLink>
					) : (
						<span className="aal-logs-muted">—</span>
					) }
				</td>
				<td className="aal-logs-col aal-logs-col--source">
					{ source.channel_label ? (
						<SourceBadge
							type="channel"
							title={ source.app_name ? `${ source.channel_label } · ${ source.app_name }` : source.channel_label }
							onClick={ ( e ) => {
								e.preventDefault();
								onFilter( 'sourceshow', source.channel );
							} }
						>
							{ source.channel_label }
						</SourceBadge>
					) : (
						<span className="aal-logs-muted">—</span>
					) }
				</td>
			</tr>
			{ changesOpen && changeCount > 0 && (
				<ChangesModal
					changes={ changes }
					omitted={ omitted }
					onClose={ () => setChangesOpen( false ) }
				/>
			) }
		</>
	);
}
