/**
 * ShieldCraft: replace the tokens printed in the page with fresh ones and add the proof that the
 * script ran. Fresh tokens keep pages served from a cache working, and the time trap then counts
 * from when the visitor opened the page.
 */

const config = window.shieldcraftConfig || {};
const TOKEN = 'shieldcraft_token';
const PROOF = 'shieldcraft_js';

// Refresh again when a page has been open for this long.
const MAX_AGE = 60 * 60 * 1000;

let pending = null;
let fetchedAt = 0;

function wrappers( root = document ) {
	return Array.from( root.querySelectorAll( '[data-shieldcraft]' ) );
}

function fill( wrapper, tokens ) {
	const data = tokens[ wrapper.dataset.shieldcraft ];
	const token = wrapper.querySelector( `input[name="${ TOKEN }"]` );
	const proof = wrapper.querySelector( `input[name="${ PROOF }"]` );
	if ( data && token && proof ) {
		token.value = data.token;
		proof.value = data.proof;
	}
}

/**
 * Fetch fresh tokens for every protected form on the page (or under root) and fill them in.
 *
 * @param {Element|Document} root Where to look.
 * @return {Promise<void>} Resolves when done (also on failure: the printed token stays).
 */
export function refresh( root = document ) {
	const found = wrappers( root );
	if ( ! found.length || ! config.tokenUrl ) {
		return Promise.resolve();
	}
	const contexts = [
		...new Set( found.map( ( w ) => w.dataset.shieldcraft ) ),
	];
	const url = new URL( config.tokenUrl, window.location.href );
	url.searchParams.set( 'context', contexts.join( ',' ) );
	pending = window
		.fetch( url.toString(), {
			credentials: 'same-origin',
			cache: 'no-store',
		} )
		.then( ( response ) => ( response.ok ? response.json() : null ) )
		.then( ( data ) => {
			if ( data && data.tokens ) {
				found.forEach( ( wrapper ) => fill( wrapper, data.tokens ) );
				fetchedAt = Date.now();
			}
		} )
		.catch( () => {} )
		.finally( () => {
			pending = null;
		} );
	return pending;
}

function onInteract( event ) {
	if (
		! pending &&
		Date.now() - fetchedAt > MAX_AGE &&
		event.target.closest &&
		event.target.closest( 'form' )?.querySelector( '[data-shieldcraft]' )
	) {
		refresh();
	}
}

function start() {
	refresh();
	document.addEventListener( 'focusin', onInteract );
	window.shieldcraft = {
		refresh,
		protect: ( form ) => refresh( form ),
	};
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
