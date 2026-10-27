import { Modal } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

const styles = {
	changesCell: { maxInlineSize: 280, overflowWrap: 'anywhere', whiteSpace: 'pre-wrap' },
};

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
			title={ __( 'Changes', 'aryo-activity-log' ) }
			onRequestClose={ onClose }
			size="large"
		>
			{ omitted > 0 && (
				<p>
					{ sprintf( /* translators: %d: number of fields that were not stored. */ _n( '%d more field was not stored.', '%d more fields were not stored.', omitted, 'aryo-activity-log' ), omitted ) }
				</p>
			) }
			<table className="widefat striped" style={ { margin: 0 } }>
				<thead>
					<tr>
						<th>{ __( 'Field', 'aryo-activity-log' ) }</th>
						<th>{ __( 'Before', 'aryo-activity-log' ) }</th>
						<th>{ __( 'After', 'aryo-activity-log' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ Object.entries( changes ).map( ( [ field, pair ] ) => {
						const before = formatChangeValue( pair[ 0 ], pair[ 1 ] );
						const after = formatChangeValue( pair[ 1 ], pair[ 0 ] );
						return (
							<tr key={ field }>
								<td>{ field }</td>
								<td style={ styles.changesCell }>{ before }</td>
								<td style={ styles.changesCell }>{ after }</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</Modal>
	);
}
