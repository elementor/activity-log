import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function PromotionRow( { promo, colSpan, onDismiss } ) {
	return (
		<tr style={ { background: '#f0f6fc' } }>
			<td colSpan={ colSpan } style={ { padding: '12px 16px' } }>
				<div style={ { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 } }>
					<div>
						<strong>{ promo.title }</strong>{ ' ' }
						{ promo.body }{ ' ' }
						<a href={ promo.cta_url } style={ { fontWeight: 600 } }>
							{ promo.cta_text }
						</a>
					</div>
					<Button
						variant="link"
						onClick={ () => onDismiss( promo.id ) }
						style={ { flexShrink: 0, color: '#757575', textDecoration: 'none' } }
					>
						{ __( 'Dismiss', 'aryo-activity-log' ) }
					</Button>
				</div>
			</td>
		</tr>
	);
}
