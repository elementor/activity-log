import { createRoot } from '@wordpress/element';
import DashboardWidget from './dashboard/widget';

const container = document.getElementById( 'aal-dashboard-widget-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <DashboardWidget /> );
}
