import { Modal } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

function formatChangeValue( value, other ) {
	if ( null === value && null === other ) {
		return __( 'changed', 'aryo-activity-log' );
	}
	if ( null === value ) {
		return '—';
	}
	if ( typeof value === 'string' && value.startsWith( '[' ) ) {
		try {
			const parsed = JSON.parse( value );
			if ( Array.isArray( parsed ) && parsed.every( ( item ) => typeof item === 'string' || typeof item === 'number' ) ) {
				return parsed.length ? parsed.join( ', ' ) : '—';
			}
		} catch ( e ) {
			// Fall through to String( value ).
		}
	}
	return String( value );
}

export default function ChangesModal( { changes, omitted, onClose } ) {
	return (
		<Modal
			className="aal-logs-changes-modal"
			title={ __( 'Changes', 'aryo-activity-log' ) }
			onRequestClose={ onClose }
			size="large"
		>
			{ omitted > 0 && (
				<p className="aal-logs-changes-notice">
					{ sprintf( /* translators: %d: number of fields that were not stored. */ _n( '%d more field was not stored.', '%d more fields were not stored.', omitted, 'aryo-activity-log' ), omitted ) }
				</p>
			) }
			<ul className="aal-logs-changes-list">
				{ Object.entries( changes ).map( ( [ field, pair ] ) => {
					const before = formatChangeValue( pair[ 0 ], pair[ 1 ] );
					const after = formatChangeValue( pair[ 1 ], pair[ 0 ] );
					return (
						<li key={ field } className="aal-logs-change">
							<p className="aal-logs-change__field">{ field }</p>
							<div className="aal-logs-change__values">
								<div>
									<span className="aal-logs-change__label">{ __( 'Before', 'aryo-activity-log' ) }</span>
									<p className="aal-logs-change__value aal-logs-change__value--before">{ before }</p>
								</div>
								<div>
									<span className="aal-logs-change__label">{ __( 'After', 'aryo-activity-log' ) }</span>
									<p className="aal-logs-change__value aal-logs-change__value--after">{ after }</p>
								</div>
							</div>
						</li>
					);
				} ) }
			</ul>
		</Modal>
	);
}
