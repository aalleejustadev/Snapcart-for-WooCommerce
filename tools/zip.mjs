#!/usr/bin/env node
/**
 * Build the distributable zip for WordPress.org.
 *
 * Stages only the files that should ship (everything not matched by
 * .distignore) into a folder named after the plugin slug, then zips it — so the
 * archive unpacks to `snapcart-for-woocommerce/`, which is what the plugin
 * directory expects.
 *
 * Usage: node tools/zip.mjs
 */

import { readFile, rm, mkdir, cp, rename } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const SLUG = 'snapcart-for-woocommerce';
const build = join( root, 'build' );

// Staged outside the plugin directory: copying a directory into a subdirectory
// of itself is rejected by the filesystem.
const stage = join( tmpdir(), `snapcart-build-${ process.pid }` );
const staged = join( stage, SLUG );

const ignore = ( await readFile( join( root, '.distignore' ), 'utf8' ) )
	.split( '\n' )
	.map( ( line ) => line.trim() )
	.filter( ( line ) => line !== '' && ! line.startsWith( '#' ) );

// `build` itself must never be copied into itself.
ignore.push( 'build' );

const excluded = new Set( ignore.filter( ( p ) => ! p.includes( '*' ) ) );
const patterns = ignore
	.filter( ( p ) => p.includes( '*' ) )
	.map( ( p ) => new RegExp( '^' + p.replace( /\./g, '\\.' ).replace( /\*/g, '.*' ) + '$' ) );

await rm( build, { recursive: true, force: true } );
await rm( stage, { recursive: true, force: true } );
await mkdir( staged, { recursive: true } );
await mkdir( build, { recursive: true } );

await cp( root, staged, {
	recursive: true,
	filter: ( source ) => {
		const rel = source.slice( root.length + 1 );

		if ( rel === '' ) {
			return true;
		}

		const name = rel.split( '/' ).pop();

		if ( excluded.has( name ) || excluded.has( rel ) ) {
			return false;
		}

		return ! patterns.some( ( pattern ) => pattern.test( name ) );
	},
} );

const zipName = `${ SLUG }.zip`;
const zipPath = join( build, zipName );

execFileSync( 'zip', [ '-rqX', zipName, SLUG ], { cwd: stage } );
await rename( join( stage, zipName ), zipPath );
await rm( stage, { recursive: true, force: true } );

console.log( `Built ${ zipPath }` );
