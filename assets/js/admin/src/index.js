import { createRoot } from '@wordpress/element';
import App from './app';

const container = document.getElementById( 'aal-admin-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
