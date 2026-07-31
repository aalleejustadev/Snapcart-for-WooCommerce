#!/usr/bin/env node
/**
 * Set the plugin version everywhere it appears.
 *
 * The version lives in six places that must agree: WordPress reads the plugin
 * header, the update checker reads the readme's stable tag, the block editor
 * reads the block metadata, and cache-busting reads the PHP constant. Editing
 * them by hand reliably misses one, so this is the only supported way to change
 * the version.
 *
 * Usage:
 *   node tools/version.mjs            # bump the patch segment: 2.0.0 -> 2.0.1
 *   node tools/version.mjs patch      # same
 *   node tools/version.mjs minor      # 2.0.4 -> 2.1.0
 *   node tools/version.mjs major      # 2.1.3 -> 3.0.0
 *   node tools/version.mjs 2.4.0      # set explicitly
 *   node tools/version.mjs --check    # report without changing anything
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const PLUGIN_FILE = 'snapcart-for-woocommerce.php';

/**
 * Every place the version is written, as a regex with the version in group 2.
 * Each pattern is deliberately anchored to its surrounding syntax so it cannot
 * match a version number that happens to appear in prose.
 */
const TARGETS = [
	{
		file: PLUGIN_FILE,
		label: 'plugin header',
		pattern: /^(\s*\*\s*Version:\s*)(\d+\.\d+\.\d+)(\s*)$/m,
	},
	{
		file: PLUGIN_FILE,
		label: 'SNAPCART_VERSION',
		pattern: /^(define\( 'SNAPCART_VERSION', ')(\d+\.\d+\.\d+)(' \);)$/m,
	},
	{
		file: 'readme.txt',
		label: 'stable tag',
		pattern: /^(Stable tag: )(\d+\.\d+\.\d+)(\s*)$/m,
	},
	{
		file: 'package.json',
		label: 'package.json',
		pattern: /^(\t"version": ")(\d+\.\d+\.\d+)(",)$/m,
	},
	{
		file: 'blocks/cart-icon/block.json',
		label: 'block.json',
		pattern: /^(\t"version": ")(\d+\.\d+\.\d+)(",)$/m,
	},
	{
		file: 'blocks/cart-icon/index.asset.php',
		label: 'block asset manifest',
		pattern: /^(\t'version'\s*=> ')(\d+\.\d+\.\d+)(',)$/m,
	},
];

/**
 * Read the version currently in the plugin header, which is the source of truth.
 *
 * @return {Promise<string>}
 */
async function currentVersion() {
	const source = await readFile( join( root, PLUGIN_FILE ), 'utf8' );
	const match = source.match( /^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$/m );

	if ( ! match ) {
		throw new Error( `No Version header found in ${ PLUGIN_FILE }` );
	}

	return match[ 1 ];
}

/**
 * Work out the version being moved to.
 *
 * @param {string} from Current version.
 * @param {string} arg  Command argument.
 * @return {string}
 */
function resolve( from, arg ) {
	if ( /^\d+\.\d+\.\d+$/.test( arg ) ) {
		return arg;
	}

	const [ major, minor, patch ] = from.split( '.' ).map( Number );

	switch ( arg ) {
		case 'major':
			return `${ major + 1 }.0.0`;
		case 'minor':
			return `${ major }.${ minor + 1 }.0`;
		case 'patch':
		case '':
		case undefined:
			return `${ major }.${ minor }.${ patch + 1 }`;
		default:
			throw new Error( `Unrecognised argument "${ arg }". Use patch, minor, major or an explicit x.y.z.` );
	}
}

const arg = process.argv[ 2 ] || 'patch';
const from = await currentVersion();

if ( '--check' === arg ) {
	console.log( `Plugin header says ${ from }. Checking the other five locations:\n` );

	let mismatched = 0;

	for ( const target of TARGETS ) {
		const source = await readFile( join( root, target.file ), 'utf8' );
		const match = source.match( target.pattern );
		const found = match ? match[ 2 ] : null;
		const ok = found === from;

		if ( ! ok ) {
			mismatched++;
		}

		console.log( `  ${ ok ? 'ok  ' : 'DRIFT' }  ${ target.label.padEnd( 22 ) } ${ found || 'not found' }` );
	}

	console.log(
		mismatched
			? `\n${ mismatched } location(s) out of step. Run: node tools/version.mjs ${ from }`
			: '\nAll locations agree.'
	);

	process.exit( mismatched ? 1 : 0 );
}

const to = resolve( from, arg );

if ( to === from ) {
	console.log( `Already at ${ to }; nothing to do.` );
	process.exit( 0 );
}

// Group edits per file so a file touched twice is read and written once.
const byFile = new Map();

for ( const target of TARGETS ) {
	if ( ! byFile.has( target.file ) ) {
		byFile.set( target.file, [] );
	}
	byFile.get( target.file ).push( target );
}

for ( const [ file, targets ] of byFile ) {
	const path = join( root, file );
	let source = await readFile( path, 'utf8' );

	for ( const target of targets ) {
		if ( ! target.pattern.test( source ) ) {
			throw new Error( `Could not find the ${ target.label } version in ${ file }. Update tools/version.mjs.` );
		}
		source = source.replace( target.pattern, `$1${ to }$3` );
		console.log( `  ${ file } — ${ target.label }` );
	}

	await writeFile( path, source, 'utf8' );
}

console.log( `\n${ from } -> ${ to }` );
console.log( 'Next: add a "= ' + to + ' =" section to the readme.txt changelog, then npm run build.' );
