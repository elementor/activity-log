import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import ChangesModal from './changes-modal';

const styles = {
	badgeBase: { display: 'inline-block', padding: '1px 6px', borderRadius: 3, fontSize: 'inherit', lineHeight: '14px', textDecoration: 'none', border: 'none', cursor: 'pointer', fontFamily: 'inherit' },
	badgeChannel: { background: '#e7f0f7', color: '#1e4d78' },
	badgeApp: { background: '#f0e7f7', color: '#4d1e78' },
	activeFilter: { fontWeight: 600, background: '#e5f5fa', borderRadius: 3, padding: '1px 4px', display: 'inline-flex', alignItems: 'center', gap: 2 },
};

export function FilterLink( { filterKey, value, params, onFilter, children } ) {
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

export function SourceBadge( { type, onClick, children } ) {
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

export default function LogRow( { item, params, onFilter } ) {
	const [ changesOpen, setChangesOpen ] = useState( false );
	const { date, author, source, type, label, description, action, changes, changes_omitted: omitted } = item;
	const changeCount = changes ? Object.keys( changes ).length : 0;

	return (
		<>
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
									style={ { verticalAlign: 'middle', marginInlineEnd: 4, borderRadius: '50%' } }
								/>
							) }
							<span style={ { verticalAlign: 'middle' } }>{ author.name }</span>
						</FilterLink>
					) : (
						<span>{ author.name }</span>
					) }
					{ author.role && <br /> }
					{ author.role && <small>{ author.role }</small> }
					{ source.app_name && (
						<>
							<br />
							<small><SourceBadge type="app">{ source.app_name }</SourceBadge></small>
						</>
					) }
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
					{ changeCount > 0 && (
						<div className="row-actions" style={ { position: 'static', marginBlockStart: 4 } }>
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
						</div>
					) }
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
