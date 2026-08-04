#!/usr/bin/env node
/**
 * SnapCart build script.
 *
 * Produces the minified CSS and JS that ship alongside the readable sources.
 * The readable files are what load when SCRIPT_DEBUG is on, which keeps the
 * plugin compliant with the WordPress.org rule that human-readable source is
 * always available.
 *
 * Usage: node tools/build.mjs
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );

/**
 * Minify CSS: strip comments, collapse whitespace, tidy punctuation.
 *
 * The colon is handled apart from the other punctuation, and only ever has
 * whitespace removed *after* it. A space before a colon is not decoration — in
 * `.card :where(p)` or `.card :hover` it is the descendant combinator, and
 * eating it silently rewrites the rule to mean "the card itself" instead of
 * "elements inside the card". Declarations are written `prop: value`, so
 * trimming one side is all the minification that was ever being bought here.
 *
 * @param {string} css Source CSS.
 * @return {string} Minified CSS.
 */
function minifyCss( css ) {
	return css
		.replace( /\/\*[\s\S]*?\*\//g, '' )
		.replace( /\s+/g, ' ' )
		.replace( /\s*([{};,>])\s*/g, '$1' )
		.replace( /:\s+/g, ':' )
		.replace( /;}/g, '}' )
		.trim();
}

/**
 * Minify JS conservatively: drop full-line and block comments, then collapse
 * indentation. Deliberately does not rename anything, so the output stays
 * readable and the transformation stays safe.
 *
 * @param {string} js Source JS.
 * @return {string} Minified JS.
 */
function minifyJs( js ) {
	const lines = js.split( '\n' );
	const out = [];
	let inBlockComment = false;

	for ( const rawLine of lines ) {
		let line = rawLine.trim();

		if ( inBlockComment ) {
			const end = line.indexOf( '*/' );
			if ( end === -1 ) {
				continue;
			}
			line = line.slice( end + 2 ).trim();
			inBlockComment = false;
		}

		// Whole-line block comment openers.
		if ( line.startsWith( '/*' ) ) {
			const end = line.indexOf( '*/' );
			if ( end === -1 ) {
				inBlockComment = true;
				continue;
			}
			line = ( line.slice( 0, 0 ) + line.slice( end + 2 ) ).trim();
		}

		// Whole-line // comments only — never touch anything mid-line, so URLs
		// and strings containing "//" are left alone.
		if ( line.startsWith( '//' ) ) {
			continue;
		}

		if ( line === '' ) {
			continue;
		}

		out.push( line );
	}

	return out.join( '\n' );
}

const targets = [
	{ from: 'assets/css/snapcart.css', to: 'assets/css/snapcart.min.css', fn: minifyCss },
	{ from: 'assets/js/snapcart.js', to: 'assets/js/snapcart.min.js', fn: minifyJs },
	{ from: 'assets/css/admin.css', to: 'assets/css/admin.min.css', fn: minifyCss },
	{ from: 'assets/js/admin.js', to: 'assets/js/admin.min.js', fn: minifyJs },
];

let frontend = 0;
let admin = 0;

for ( const { from, to, fn } of targets ) {
	const source = await readFile( join( root, from ), 'utf8' );
	const output = fn( source );
	await writeFile( join( root, to ), output + '\n', 'utf8' );

	const saved = Math.round( ( 1 - output.length / source.length ) * 100 );

	if ( to.includes( 'admin' ) ) {
		admin += output.length;
	} else {
		frontend += output.length;
	}

	console.log(
		`${ to.padEnd( 32 ) } ${ String( source.length ).padStart( 6 ) } → ${ String(
			output.length
		).padStart( 6 ) } bytes (-${ saved }%)`
	);
}

console.log( `\nFront end, every page:      ${ frontend } bytes uncompressed.` );
console.log( `Admin, settings screen only: ${ admin } bytes uncompressed.` );
