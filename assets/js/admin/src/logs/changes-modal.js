import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
					{ omitted }{ ' ' }
					{ __( 'more fields were not stored.', 'aryo-activity-log' ) }
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
