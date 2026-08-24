'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const { version } = require( path.join( root, 'package.json' ) );

const pluginFile = path.join( root, 'aryo-activity-log.php' );
let plugin = fs.readFileSync( pluginFile, 'utf8' );

plugin = plugin.replace(
	/^(Version:\s*)\d+\.\d+\.\d+/m,
	`$1${ version }`
);
fs.writeFileSync( pluginFile, plugin );

const readmeFile = path.join( root, 'readme.txt' );
let readme = fs.readFileSync( readmeFile, 'utf8' );

readme = readme.replace(
	/^Stable tag: \d+\.\d+\.\d+/m,
	`Stable tag: ${ version }`
);
fs.writeFileSync( readmeFile, readme );

console.log( `Synced version ${ version } to aryo-activity-log.php and readme.txt` );
