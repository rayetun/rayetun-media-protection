/**
 * Protected File Library — front-end filtering. Dependency-free; each library on
 * the page is wired independently.
 */
( function () {
	'use strict';

	function init( root ) {
		root.querySelectorAll( '.mg-library__filter' ).forEach( function ( fbtn ) {
			fbtn.addEventListener( 'click', function () {
				var filter = fbtn.getAttribute( 'data-mg-filter' ) || '';
				root.querySelectorAll( '.mg-library__filter' ).forEach( function ( b ) {
					var active = b === fbtn;
					b.classList.toggle( 'is-active', active );
					b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
				root.querySelectorAll( '.mg-library__cell' ).forEach( function ( cell ) {
					var tags = ( cell.getAttribute( 'data-mg-tags' ) || '' ).split( /\s+/ ).filter( Boolean );
					cell.hidden = filter !== '' && tags.indexOf( filter ) === -1;
				} );
			} );
		} );
	}

	function boot() {
		document.querySelectorAll( '.mg-library' ).forEach( init );
	}

	if ( document.readyState !== 'loading' ) {
		boot();
	} else {
		document.addEventListener( 'DOMContentLoaded', boot );
	}
} )();
