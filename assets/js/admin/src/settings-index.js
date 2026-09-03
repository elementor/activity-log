import { createRoot } from '@wordpress/element';
import Settings from './settings';

const container = document.getElementById( 'aal-settings-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <Settings /> );
}
