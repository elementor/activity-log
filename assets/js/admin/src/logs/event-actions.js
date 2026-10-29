/**
 * Splits row action URLs into a primary View link and leftover menu items.
 *
 * Edit is omitted when its URL matches View (common on the front end).
 *
 * @param {Object} actions Action slug → URL map from the API.
 * @return {{ viewUrl: string, leftovers: Array<{ key: string, url: string, label: string }> }}
 */
export function partitionRowActions( actions ) {
	const viewUrl = actions && actions.view ? actions.view : '';
	const leftovers = [];

	if ( ! actions ) {
		return { viewUrl, leftovers };
	}

	Object.entries( actions ).forEach( ( [ key, url ] ) => {
		if ( ! url ) {
			return;
		}
		if ( key === 'view' ) {
			return;
		}
		if ( key === 'edit' && url === viewUrl ) {
			return;
		}
		leftovers.push( {
			key,
			url,
			label: key.charAt( 0 ).toUpperCase() + key.slice( 1 ),
		} );
	} );

	return { viewUrl, leftovers };
}

/**
 * When View exists, find the object label inside the event text for inline linking.
 *
 * @param {string} eventText   Event column text.
 * @param {string} objectName  Context / object label.
 * @param {string} viewUrl     View action URL.
 * @return {null|{ before: string, name: string, after: string, url: string }}
 */
export function getObjectLinkInEventText( eventText, objectName, viewUrl ) {
	if ( ! viewUrl || ! eventText ) {
		return null;
	}

	if ( objectName ) {
		const index = eventText.indexOf( objectName );
		if ( index !== -1 ) {
			return {
				before: eventText.slice( 0, index ),
				name: objectName,
				after: eventText.slice( index + objectName.length ),
				url: viewUrl,
			};
		}
	}

	return null;
}
