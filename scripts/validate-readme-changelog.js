'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );

const version = process.argv[ 2 ] || require( path.join( root, 'package.json' ) ).version;
const today = new Intl.DateTimeFormat( 'en-CA', { timeZone: 'Asia/Jerusalem' } ).format( new Date() );
const expectedHeader = `= ${ version } - ${ today } =`;

/**
 * @param {string} fileName
 * @param {string} filePath
 * @returns {{ date: string, lines: string[] }}
 */
function extractVersionBlock( fileName, filePath ) {
	const content = fs.readFileSync( filePath, 'utf8' );
	const changelogMarker = '== Changelog ==';
	const changelogIndex = content.indexOf( changelogMarker );

	if ( changelogIndex === -1 ) {
		console.error( `${ fileName } is missing "== Changelog ==" section.` );
		process.exit( 1 );
	}

	const changelogBody = content.slice( changelogIndex + changelogMarker.length );
	const escapedVersion = version.replace( /\./g, '\\.' );
	const headerRegex = new RegExp( `^= ${ escapedVersion } - (\\d{4}-\\d{2}-\\d{2}) =`, 'm' );
	const headerMatch = changelogBody.match( headerRegex );

	if ( ! headerMatch ) {
		console.error( `Expected "${ expectedHeader }" in ${ fileName }` );
		process.exit( 1 );
	}

	const matchedHeader = headerMatch[ 0 ];
	const date = headerMatch[ 1 ];

	if ( matchedHeader !== expectedHeader ) {
		console.error( `Expected "${ expectedHeader }" in ${ fileName }` );
		process.exit( 1 );
	}

	const headerIndex = changelogBody.indexOf( matchedHeader );
	const afterHeader = changelogBody.slice( headerIndex + matchedHeader.length );
	const nextSectionMatch = afterHeader.match( /\n= \d+\.\d+\.\d+/ );
	const sectionContent = nextSectionMatch
		? afterHeader.slice( 0, nextSectionMatch.index )
		: afterHeader;

	const lines = sectionContent
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line.length > 0 );

	return { date, lines };
}

function linesOnlyIn( haystack, other ) {
	const otherSet = new Set( other );
	return haystack.filter( ( line ) => ! otherSet.has( line ) );
}

const readmePath = path.join( root, 'readme.txt' );
const changelogPath = path.join( root, 'changelog.txt' );

const readmeBlock = extractVersionBlock( 'readme.txt', readmePath );
const changelogBlock = extractVersionBlock( 'changelog.txt', changelogPath );

for ( const [ fileName, block ] of [
	[ 'readme.txt', readmeBlock ],
	[ 'changelog.txt', changelogBlock ],
] ) {
	if ( ! block.lines.some( ( line ) => line.startsWith( '* ' ) ) ) {
		console.error(
			`${ fileName } changelog section for version ${ version } has no release notes.\n` +
			`Add at least one "* ..." line under the section header before dispatching the release.`
		);
		process.exit( 1 );
	}
}

const readmeKey = readmeBlock.lines.join( '\n' );
const changelogKey = changelogBlock.lines.join( '\n' );

if ( readmeKey !== changelogKey ) {
	const onlyInReadme = linesOnlyIn( readmeBlock.lines, changelogBlock.lines );
	const onlyInChangelog = linesOnlyIn( changelogBlock.lines, readmeBlock.lines );

	console.error(
		`readme.txt and changelog.txt release notes for version ${ version } do not match.`
	);
	if ( onlyInReadme.length ) {
		console.error( 'Only in readme.txt:' );
		onlyInReadme.forEach( ( line ) => console.error( `  ${ line }` ) );
	}
	if ( onlyInChangelog.length ) {
		console.error( 'Only in changelog.txt:' );
		onlyInChangelog.forEach( ( line ) => console.error( `  ${ line }` ) );
	}
	process.exit( 1 );
}

console.log( `Changelog section for version ${ version } is valid.` );
