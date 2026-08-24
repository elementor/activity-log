'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const version = process.argv[ 2 ] || require( path.join( root, 'package.json' ) ).version;
const readme = fs.readFileSync( path.join( root, 'readme.txt' ), 'utf8' );

const changelogMarker = '== Changelog ==';
const changelogIndex = readme.indexOf( changelogMarker );

if ( changelogIndex === -1 ) {
	console.error( 'readme.txt is missing "== Changelog ==" section.' );
	process.exit( 1 );
}

const changelogBody = readme.slice( changelogIndex + changelogMarker.length );

const sectionRegex = new RegExp(
	`= ${ version.replace( /\./g, '\\.' ) }(\\s+-\\s+\\d{4}-\\d{2}-\\d{2})?\\s*=`
);

const headerMatch = changelogBody.match( sectionRegex );

if ( ! headerMatch ) {
	console.error( `Changelog section for version ${ version } not found.` );
	process.exit( 1 );
}

const headerIndex = changelogBody.indexOf( headerMatch[ 0 ] );
const afterHeader = changelogBody.slice( headerIndex + headerMatch[ 0 ].length );
const nextSectionMatch = afterHeader.match( /\n= \d+\.\d+\.\d+/ );
const sectionContent = nextSectionMatch
	? afterHeader.slice( 0, nextSectionMatch.index )
	: afterHeader;

const lines = sectionContent
	.trim()
	.split( '\n' )
	.map( ( l ) => l.trim() )
	.filter( Boolean );

console.log( lines.join( '\n' ) );
