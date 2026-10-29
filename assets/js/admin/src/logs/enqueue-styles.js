/**
 * Loads the webpack-extracted stylesheet for the admin app when PHP does not enqueue it.
 */
export default function enqueueLogsStylesheet() {
	if ( typeof document === 'undefined' ) {
		return;
	}

	const script = document.querySelector( 'script[src*="build/index.js"]' );
	if ( ! script || ! script.src ) {
		return;
	}

	const href = script.src.replace( /index\.js(\?.*)?$/, 'index.css$1' );
	const links = document.querySelectorAll( 'link[rel="stylesheet"]' );
	for ( let i = 0; i < links.length; i++ ) {
		if ( links[ i ].href === href ) {
			return;
		}
	}

	const link = document.createElement( 'link' );
	link.rel = 'stylesheet';
	link.href = href;
	document.head.appendChild( link );
}

enqueueLogsStylesheet();
