import { SourceBadge } from '../logs/log-row';

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

export default function TimelineItem( { item, isExpanded, onToggle } ) {
	const { date, author, source, type, label, description, action } = item;
	const detailsId = `aal-dashboard-log-${ item.id }`;
	const topic =
		type.label + ( label.label ? `: ${ label.label }` : '' );
	const fullText =
		topic +
		( description.text ? ` · ${ description.text }` : '' ) +
		` ${ author.name }`;
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
				<span className="aal-dashboard-row__text" title={ fullText }>
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
