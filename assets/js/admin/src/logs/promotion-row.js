import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function PromotionRow( { promo, onDismiss } ) {
	return (
		<li className="aal-logs-promo">
			<div className="aal-logs-promo__inner">
				<div className="aal-logs-promo__text">
					<strong>{ promo.title }</strong>{ ' ' }
					{ promo.body }{ ' ' }
					<a href={ promo.cta_url }>
						{ promo.cta_text }
					</a>
				</div>
				<Button
					className="aal-logs-promo__dismiss"
					variant="link"
					onClick={ () => onDismiss( promo.id ) }
				>
					{ __( 'Dismiss', 'aryo-activity-log' ) }
				</Button>
			</div>
		</li>
	);
}
