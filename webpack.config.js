/**
 * Custom webpack config for MarkGuard.
 *
 * @wordpress/scripts auto-detects the gallery block.json and builds its entries,
 * but as soon as any block.json is present it STOPS emitting the default
 * `index.js` entry — which is our admin single-page app. We extend the default
 * config to add that admin entry back, so both the block assets and the admin
 * bundle are produced in one build.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

// wp-scripts exports either a single config (script) or [script, module].
const configs = Array.isArray( defaultConfig ) ? defaultConfig : [ defaultConfig ];
const scriptConfig = configs[ 0 ];

const blockEntries =
	typeof scriptConfig.entry === 'function' ? scriptConfig.entry() : scriptConfig.entry;

scriptConfig.entry = {
	...blockEntries,
	// The admin SPA. Emits build/index.js (+ index.css, index.asset.php).
	index: path.resolve( process.cwd(), 'src-ui', 'index.js' ),
};

module.exports = defaultConfig;
