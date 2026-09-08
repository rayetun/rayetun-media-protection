/**
 * Thin wrapper around @wordpress/api-fetch pre-configured with the RayEtun Media Protection
 * REST root and nonce injected by wp_localize_script.
 */
import apiFetch from '@wordpress/api-fetch';

const data = window.markguardData || {};

if ( data.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( data.nonce ) );
}

/**
 * GET/POST helper against the markguard/v1 namespace.
 *
 * @param {string} path   Path after markguard/v1, e.g. '/dashboard'.
 * @param {Object} [opts] apiFetch options (method, data, …).
 */
export function mgFetch( path, opts = {} ) {
	return apiFetch( {
		url: `${ data.restUrl }${ path }`,
		...opts,
	} );
}
