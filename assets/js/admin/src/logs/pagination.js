import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

const styles = {
	pagination: { display: 'flex', alignItems: 'center', gap: 8, marginTop: 12, justifyContent: 'flex-end' },
	paginationInfo: { fontSize: 13, color: '#50575e' },
};

export default function Pagination( { page, pages, total, onPageChange } ) {
	return (
		<div style={ styles.pagination }>
			<span style={ styles.paginationInfo }>
				{ sprintf( /* translators: %d: number of log items. */ _n( '%d item', '%d items', total, 'aryo-activity-log' ), total ) }
				{ pages > 1 && (
					<>{ ' — ' }{ __( 'Page', 'aryo-activity-log' ) } { page } / { pages }</>
				) }
			</span>
			{ pages > 1 && (
				<>
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => onPageChange( 1 ) }
						size="small"
					>
						«
					</Button>
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => onPageChange( page - 1 ) }
						size="small"
					>
						‹
					</Button>
					<Button
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => onPageChange( page + 1 ) }
						size="small"
					>
						›
					</Button>
					<Button
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => onPageChange( pages ) }
						size="small"
					>
						»
					</Button>
				</>
			) }
		</div>
	);
}
