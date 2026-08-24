'use strict';

const path = require( 'path' );

const bump = process.argv[ 2 ];

if ( ! [ 'patch', 'minor', 'major' ].includes( bump ) ) {
	console.error( 'Usage: node next-version.js <patch|minor|major>' );
	process.exit( 1 );
}

const { version } = require( path.resolve( __dirname, '..', 'package.json' ) );
const parts = version.split( '.' ).map( Number );

switch ( bump ) {
	case 'major':
		parts[ 0 ]++;
		parts[ 1 ] = 0;
		parts[ 2 ] = 0;
		break;
	case 'minor':
		parts[ 1 ]++;
		parts[ 2 ] = 0;
		break;
	case 'patch':
		parts[ 2 ]++;
		break;
}

console.log( parts.join( '.' ) );
