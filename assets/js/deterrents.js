/**
 * MarkGuard front-end deterrents. Dependency-free; loaded only when the
 * "Deterrent layer" feature is enabled. Discourages casual copying of media —
 * it is not a security boundary (signed, access-controlled serving is).
 *
 * Config is provided by PHP as window.markguardDeterrents:
 *   { rightClick: bool, dragDrop: bool, keyboard: bool, message: string }
 */
( function () {
	'use strict';

	var cfg = window.markguardDeterrents || {};
	var message = typeof cfg.message === 'string' ? cfg.message : '';

	// True when the event happened on (or inside) an image the visitor might try
	// to copy. Scoped to media so normal browsing — links, text, forms — is left
	// alone.
	function onImage( target ) {
		return !! ( target && target.closest && target.closest( 'img, picture' ) );
	}

	function isEditable( el ) {
		if ( ! el ) {
			return false;
		}
		if ( el.isContentEditable ) {
			return true;
		}
		var tag = el.tagName;
		return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
	}

	// A small, self-dismissing notice so the visitor understands why the action
	// did nothing. aria-live so it is announced to assistive tech.
	var toastEl = null;
	var toastTimer = null;
	function toast() {
		if ( ! message ) {
			return;
		}
		if ( ! toastEl ) {
			toastEl = document.createElement( 'div' );
			toastEl.className = 'markguard-deterrent-toast';
			toastEl.setAttribute( 'role', 'status' );
			toastEl.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( toastEl );
		}
		toastEl.textContent = message;
		// Force reflow so re-triggering restarts the transition.
		void toastEl.offsetWidth;
		toastEl.classList.add( 'is-visible' );
		window.clearTimeout( toastTimer );
		toastTimer = window.setTimeout( function () {
			toastEl.classList.remove( 'is-visible' );
		}, 1800 );
	}

	if ( cfg.rightClick ) {
		document.addEventListener( 'contextmenu', function ( e ) {
			if ( onImage( e.target ) ) {
				e.preventDefault();
				toast();
			}
		} );
	}

	if ( cfg.dragDrop ) {
		document.addEventListener( 'dragstart', function ( e ) {
			if ( onImage( e.target ) ) {
				e.preventDefault();
			}
		} );
	}

	if ( cfg.keyboard ) {
		document.addEventListener( 'keydown', function ( e ) {
			var key = ( e.key || '' ).toUpperCase();
			var mod = e.ctrlKey || e.metaKey;
			var blocked = false;

			if ( key === 'F12' ) {
				// Devtools.
				blocked = true;
			} else if ( mod && e.shiftKey && ( key === 'I' || key === 'J' || key === 'C' ) ) {
				// Devtools / inspector / console.
				blocked = true;
			} else if ( mod && ! e.shiftKey && ( key === 'U' || key === 'S' ) && ! isEditable( e.target ) ) {
				// View source / save page — but never hijack Ctrl/Cmd+S in a field.
				blocked = true;
			}

			if ( blocked ) {
				e.preventDefault();
				toast();
			}
		} );
	}
} )();
