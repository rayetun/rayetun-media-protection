/**
 * RayEtun Media Protection admin entry point. Mounts the React app into the container the PHP
 * page renders, and applies the persisted theme preference.
 */
import { createRoot } from '@wordpress/element';
import App from './app/App';
import './styles/app.css';

const data = window.markguardData || {};

// Resolve the theme. A per-browser choice in localStorage wins (so the setting
// sticks across reloads immediately, without waiting on a server round-trip);
// otherwise fall back to the server-saved preference. The default is light.
let theme = data.theme || 'light';
try {
	const stored = window.localStorage.getItem( 'mg-theme' );
	if ( stored === 'light' || stored === 'dark' || stored === 'auto' ) {
		theme = stored;
	}
} catch ( e ) {
	/* localStorage unavailable — fall back to the server value. */
}
if ( theme === 'light' || theme === 'dark' ) {
	document.documentElement.setAttribute( 'data-mg-theme', theme );
} else {
	// 'auto' — follow the OS via prefers-color-scheme.
	document.documentElement.removeAttribute( 'data-mg-theme' );
}

document.addEventListener( 'DOMContentLoaded', () => {
	const mount = document.getElementById( 'markguard-admin-app' );
	if ( mount ) {
		createRoot( mount ).render( <App /> );
		// The WordPress admin menu is pinned via CSS (styles/app.css) rather
		// than WP's own height-based JS, which measures before our SPA renders.
	}
} );
