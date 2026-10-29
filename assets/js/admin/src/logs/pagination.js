import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

export default function Pagination( { page, pages, total, onPageChange } ) {
	return (
		<div className="aal-logs-pagination">
			<span className="aal-logs-pagination__info">
				{ sprintf( /* translators: %d: number of log items. */ _n( '%d item', '%d items', total, 'aryo-activity-log' ), total ) }
				{ pages > 1 && (
					<>{ ' — ' }{ __( 'Page', 'aryo-activity-log' ) } { page } / { pages }</>
				) }
			</span>
			{ pages > 1 && (
				<div className="aal-logs-pagination__nav">
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => onPageChange( 1 ) }
						size="small"
						aria-label={ __( 'First page', 'aryo-activity-log' ) }
					>
						«
					</Button>
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => onPageChange( page - 1 ) }
						size="small"
						aria-label={ __( 'Previous page', 'aryo-activity-log' ) }
					>
						‹
					</Button>
					<Button
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => onPageChange( page + 1 ) }
						size="small"
						aria-label={ __( 'Next page', 'aryo-activity-log' ) }
					>
						›
					</Button>
					<Button
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => onPageChange( pages ) }
						size="small"
						aria-label={ __( 'Last page', 'aryo-activity-log' ) }
					>
						»
					</Button>
				</div>
			) }
		</div>
	);
}
