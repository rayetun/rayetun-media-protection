/**
 * Protected Gallery front-end behaviour. Dependency-free: lightbox (open/close,
 * ESC, focus restore), client-side filtering, and a right-click deterrent on
 * gallery images. Each gallery on the page is wired independently.
 */
( function () {
	'use strict';

	function initGallery( root ) {
		var lightbox = root.querySelector( '.mg-gallery__lightbox' );
		if ( ! lightbox ) {
			return;
		}
		var imgEl = lightbox.querySelector( '.mg-gallery__preview' );
		var titleEl = lightbox.querySelector( '.mg-gallery__title' );
		var dlEl = lightbox.querySelector( '.mg-gallery__download' );
		var lockEl = lightbox.querySelector( '.mg-gallery__locked' );
		var lastTrigger = null;

		function open( item, trigger ) {
			if ( imgEl ) {
				imgEl.src = item.preview || '';
				imgEl.alt = item.title || '';
			}
			if ( titleEl ) {
				titleEl.textContent = item.title || '';
			}
			// The download anchor doubles as a "log in" button when access is
			// locked but signing in would help; a plain notice shows when the
			// visitor is signed in yet still lacks access.
			if ( ! item.locked ) {
				if ( dlEl ) {
					dlEl.hidden = false;
					dlEl.classList.remove( 'mg-gallery__download--login' );
					dlEl.href = item.downloadUrl || '#';
					dlEl.textContent = item.downloadLabel || '';
				}
				if ( lockEl ) {
					lockEl.hidden = true;
				}
			} else if ( item.loginUrl ) {
				if ( dlEl ) {
					dlEl.hidden = false;
					dlEl.classList.add( 'mg-gallery__download--login' );
					dlEl.href = item.loginUrl;
					dlEl.textContent = item.loginText || '';
				}
				if ( lockEl ) {
					lockEl.hidden = true;
				}
			} else {
				if ( dlEl ) {
					dlEl.hidden = true;
				}
				if ( lockEl ) {
					lockEl.hidden = false;
					lockEl.textContent = item.loginText || '';
				}
			}
			lightbox.hidden = false;
			document.documentElement.classList.add( 'mg-gallery-lock' );
			lastTrigger = trigger;
			var close = lightbox.querySelector( '.mg-gallery__close' );
			if ( close ) {
				close.focus();
			}
		}

		function close() {
			lightbox.hidden = true;
			document.documentElement.classList.remove( 'mg-gallery-lock' );
			if ( lastTrigger && typeof lastTrigger.focus === 'function' ) {
				lastTrigger.focus();
			}
		}

		root.querySelectorAll( '.mg-gallery__item' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				try {
					open( JSON.parse( btn.getAttribute( 'data-mg-item' ) ), btn );
				} catch ( e ) {
					/* malformed item data — ignore */
				}
			} );
			btn.addEventListener( 'contextmenu', function ( e ) {
				e.preventDefault();
			} );
		} );

		if ( imgEl ) {
			imgEl.addEventListener( 'contextmenu', function ( e ) {
				e.preventDefault();
			} );
		}

		lightbox.querySelectorAll( '[data-mg-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', close );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( lightbox.hidden ) {
				return;
			}
			if ( e.key === 'Escape' ) {
				close();
				return;
			}
			// Trap Tab focus inside the open dialog.
			if ( e.key === 'Tab' ) {
				var focusables = Array.prototype.slice
					.call( lightbox.querySelectorAll( 'button, a[href]' ) )
					.filter( function ( el ) {
						return ! el.hidden && el.offsetParent !== null;
					} );
				if ( ! focusables.length ) {
					return;
				}
				var first = focusables[ 0 ];
				var last = focusables[ focusables.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		} );

		root.querySelectorAll( '.mg-gallery__filter' ).forEach( function ( fbtn ) {
			fbtn.addEventListener( 'click', function () {
				var filter = fbtn.getAttribute( 'data-mg-filter' ) || '';
				root.querySelectorAll( '.mg-gallery__filter' ).forEach( function ( b ) {
					var active = b === fbtn;
					b.classList.toggle( 'is-active', active );
					b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
				root.querySelectorAll( '.mg-gallery__cell' ).forEach( function ( cell ) {
					var tags = ( cell.getAttribute( 'data-mg-tags' ) || '' ).split( /\s+/ ).filter( Boolean );
					cell.hidden = filter !== '' && tags.indexOf( filter ) === -1;
				} );
			} );
		} );
	}

	function boot() {
		document.querySelectorAll( '.mg-gallery' ).forEach( initGallery );
	}

	if ( document.readyState !== 'loading' ) {
		boot();
	} else {
		document.addEventListener( 'DOMContentLoaded', boot );
	}
} )();
