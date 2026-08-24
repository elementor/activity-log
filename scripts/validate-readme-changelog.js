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

// Match "= X.Y.Z =" or "= X.Y.Z - YYYY-MM-DD =" (date suffix is optional)
const sectionRegex = new RegExp(
	`= ${ version.replace( /\./g, '\\.' ) }(\\s+-\\s+\\d{4}-\\d{2}-\\d{2})?\\s*=`
);

const headerMatch = changelogBody.match( sectionRegex );

if ( ! headerMatch ) {
	console.error(
		`readme.txt is missing a changelog section for version ${ version }.\n` +
		`Add "= ${ version } =" (optionally with " - YYYY-MM-DD") with release notes under "== Changelog ==" before dispatching the release.`
	);
	process.exit( 1 );
}

const headerIndex = changelogBody.indexOf( headerMatch[ 0 ] );
const afterHeader = changelogBody.slice( headerIndex + headerMatch[ 0 ].length );
const nextSectionMatch = afterHeader.match( /\n= \d+\.\d+\.\d+/ );
const sectionContent = nextSectionMatch
	? afterHeader.slice( 0, nextSectionMatch.index )
	: afterHeader;

const hasBullet = /^\* /m.test( sectionContent );

if ( ! hasBullet ) {
	console.error(
		`readme.txt changelog section for version ${ version } has no release notes.\n` +
		`Add at least one "* ..." line under the section header before dispatching the release.`
	);
	process.exit( 1 );
}

console.log( `Changelog section for version ${ version } is valid.` );
