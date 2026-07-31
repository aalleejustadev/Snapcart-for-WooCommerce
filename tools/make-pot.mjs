#!/usr/bin/env node
/**
 * Generate languages/snapcart-for-woocommerce.pot.
 *
 * Scans the PHP and JavaScript sources for the standard WordPress translation
 * functions and writes a gettext template. Run after changing any user-facing
 * string: `node tools/make-pot.mjs`
 */

import { readFile, writeFile, readdir, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join, relative, sep } from 'node:path';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const DOMAIN = 'snapcart-for-woocommerce';
const SKIP_DIRS = new Set( [ 'node_modules', '.git', 'languages', 'tools', '.wordpress-org' ] );

// Function name → which argument positions carry translatable text.
const FUNCTIONS = {
	__: [ 0 ],
	_e: [ 0 ],
	esc_html__: [ 0 ],
	esc_html_e: [ 0 ],
	esc_attr__: [ 0 ],
	esc_attr_e: [ 0 ],
	_x: [ 0, 1 ],
	esc_html_x: [ 0, 1 ],
	esc_attr_x: [ 0, 1 ],
	_n: [ 0, 1 ],
	_nx: [ 0, 1, 3 ],
};

const CONTEXT_ARG = { _x: 1, esc_html_x: 1, esc_attr_x: 1, _nx: 3 };
const PLURAL_FN = new Set( [ '_n', '_nx' ] );

async function walk( dir, out = [] ) {
	for ( const entry of await readdir( dir, { withFileTypes: true } ) ) {
		if ( entry.name.startsWith( '.' ) && entry.name !== '.' ) {
			continue;
		}
		const full = join( dir, entry.name );
		if ( entry.isDirectory() ) {
			if ( ! SKIP_DIRS.has( entry.name ) ) {
				await walk( full, out );
			}
		} else if ( /\.(php|js)$/.test( entry.name ) && ! /\.min\.js$/.test( entry.name ) ) {
			out.push( full );
		}
	}
	return out;
}

/**
 * Split a call's argument list into raw argument strings, respecting quotes,
 * nesting and escapes.
 */
function splitArgs( source, startIndex ) {
	const args = [];
	let depth = 0;
	let quote = null;
	let buffer = '';

	for ( let i = startIndex; i < source.length; i++ ) {
		const char = source[ i ];

		if ( quote ) {
			buffer += char;
			if ( char === '\\' ) {
				buffer += source[ ++i ] ?? '';
			} else if ( char === quote ) {
				quote = null;
			}
			continue;
		}

		if ( char === "'" || char === '"' ) {
			quote = char;
			buffer += char;
			continue;
		}

		if ( char === '(' || char === '[' ) {
			depth++;
		} else if ( char === ')' || char === ']' ) {
			if ( depth === 0 ) {
				args.push( buffer.trim() );
				return { args, end: i };
			}
			depth--;
		}

		if ( char === ',' && depth === 0 ) {
			args.push( buffer.trim() );
			buffer = '';
			continue;
		}

		buffer += char;
	}

	return { args, end: source.length };
}

/** Unquote a PHP/JS single- or double-quoted literal, or return null. */
function literal( raw ) {
	if ( ! raw || raw.length < 2 ) {
		return null;
	}
	const quote = raw[ 0 ];
	if ( ( quote !== "'" && quote !== '"' ) || raw[ raw.length - 1 ] !== quote ) {
		return null;
	}
	const body = raw.slice( 1, -1 );
	return quote === "'"
		? body.replace( /\\'/g, "'" ).replace( /\\\\/g, '\\' )
		: body.replace( /\\"/g, '"' ).replace( /\\\\/g, '\\' );
}

function escapePo( text ) {
	return text.replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ).replace( /\n/g, '\\n' );
}

/**
 * Find a `translators:` comment directly above the given offset.
 *
 * Only the three preceding lines are considered, so a comment belonging to an
 * earlier call is never attached to an unrelated string.
 */
function translatorComment( source, index ) {
	const lines = source.slice( 0, index ).split( '\n' ).slice( -4, -1 );

	for ( let i = lines.length - 1; i >= 0; i-- ) {
		const match = lines[ i ].match( /(?:\/\*+|\/\/|\*)\s*(translators:.*?)(?:\*\/\s*)?$/i );

		if ( match ) {
			return match[ 1 ].replace( /\s+/g, ' ' ).trim();
		}
	}

	return null;
}

const entries = new Map();

for ( const file of await walk( root ) ) {
	const source = await readFile( file, 'utf8' );
	const rel = relative( root, file ).split( sep ).join( '/' );
	const pattern = /\b(__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_x|esc_html_x|esc_attr_x|_n|_nx)\s*\(/g;

	let match;
	while ( ( match = pattern.exec( source ) ) !== null ) {
		const fn = match[ 1 ];
		// Skip method calls and definitions such as `$this->__(` or `function __(`.
		const prefix = source.slice( Math.max( 0, match.index - 12 ), match.index );
		if ( /(->|::|function\s+)\s*$/.test( prefix ) ) {
			continue;
		}

		const { args } = splitArgs( source, pattern.lastIndex );
		const domainIndex = PLURAL_FN.has( fn ) ? ( fn === '_nx' ? 4 : 3 ) : FUNCTIONS[ fn ].length;
		const domain = literal( args[ domainIndex ] );

		if ( domain !== DOMAIN ) {
			continue;
		}

		const singular = literal( args[ 0 ] );
		if ( singular === null ) {
			continue;
		}

		const plural = PLURAL_FN.has( fn ) ? literal( args[ 1 ] ) : null;
		const contextArg = CONTEXT_ARG[ fn ];
		const context = contextArg !== undefined ? literal( args[ contextArg ] ) : null;

		const line = source.slice( 0, match.index ).split( '\n' ).length;
		const key = `${ context ?? '' }${ singular }${ plural ?? '' }`;

		if ( ! entries.has( key ) ) {
			entries.set( key, {
				singular,
				plural,
				context,
				comment: translatorComment( source, match.index ),
				refs: [],
			} );
		}

		const entry = entries.get( key );
		entry.refs.push( `${ rel }:${ line }` );
		if ( ! entry.comment ) {
			entry.comment = translatorComment( source, match.index );
		}
	}
}

const header = `# Copyright (C) ${ new Date().getFullYear() } Ali Murtaza
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: SnapCart for WooCommerce 2.0.0\\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/${ DOMAIN }/\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"POT-Creation-Date: ${ new Date().toISOString().replace( /\.\d+Z$/, '+00:00' ) }\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: ${ DOMAIN }\\n"
`;

const blocks = [ ...entries.values() ]
	.sort( ( a, b ) => a.refs[ 0 ].localeCompare( b.refs[ 0 ] ) )
	.map( ( entry ) => {
		const lines = [];
		if ( entry.comment ) {
			lines.push( `#. ${ entry.comment }` );
		}
		lines.push( `#: ${ entry.refs.join( ' ' ) }` );
		if ( entry.context ) {
			lines.push( `msgctxt "${ escapePo( entry.context ) }"` );
		}
		lines.push( `msgid "${ escapePo( entry.singular ) }"` );
		if ( entry.plural ) {
			lines.push( `msgid_plural "${ escapePo( entry.plural ) }"` );
			lines.push( 'msgstr[0] ""' );
			lines.push( 'msgstr[1] ""' );
		} else {
			lines.push( 'msgstr ""' );
		}
		return lines.join( '\n' );
	} );

await mkdir( join( root, 'languages' ), { recursive: true } );
await writeFile(
	join( root, 'languages', `${ DOMAIN }.pot` ),
	`${ header }\n${ blocks.join( '\n\n' ) }\n`,
	'utf8'
);

console.log( `Wrote languages/${ DOMAIN }.pot — ${ entries.size } strings.` );
