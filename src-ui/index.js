/**
 * RayEtun Media Protection admin entry point. Mounts the React app into the container the PHP
 * page renders, and applies the persisted theme preference.
 */
import { createRoot } from '@wordpress/element';
import App from './app/App';
import './styles/app.css';

const data = window.markguardData || {};

// Apply a saved theme override (Settings screen sets this in 8.8); otherwise
// the CSS prefers-color-scheme default applies.
if ( data.theme === 'light' || data.theme === 'dark' ) {
	document.documentElement.setAttribute( 'data-mg-theme', data.theme );
}

document.addEventListener( 'DOMContentLoaded', () => {
	const mount = document.getElementById( 'markguard-admin-app' );
	if ( mount ) {
		createRoot( mount ).render( <App /> );
		// The WordPress admin menu is pinned via CSS (styles/app.css) rather
		// than WP's own height-based JS, which measures before our SPA renders.
	}
} );
