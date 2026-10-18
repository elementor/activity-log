import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function EraseLogsRow( { erasing, onErase } ) {
	return (
		<tr>
			<th scope="row">
				{ __( 'Delete Log Activities', 'aryo-activity-log' ) }
			</th>
			<td>
				<Button
					variant="secondary"
					isDestructive
					onClick={ onErase }
					isBusy={ erasing }
					disabled={ erasing }
				>
					{ __( 'Reset Database', 'aryo-activity-log' ) }
				</Button>
				<p className="description">
					{ __( 'Warning: Clicking this will delete all activities from the database.', 'aryo-activity-log' ) }
				</p>
			</td>
		</tr>
	);
}
