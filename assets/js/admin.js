/**
 * SnapCart for WooCommerce — settings screen behaviour.
 *
 * Deliberately small and dependency-free beyond jQuery, which WordPress already
 * loads in the admin. The switches and card pickers are styled purely in CSS;
 * nothing here builds or decorates a control. This file only:
 *
 *   - switches tabs,
 *   - hides settings that do not currently apply,
 *   - and copies the shortcode.
 *
 * With JavaScript unavailable every panel and field simply stays visible, so
 * the screen remains fully usable.
 */
( function ( $ ) {
	'use strict';

	var strings = window.SnapCartAdmin || {};

	/* ---------------------------------------------------------------------
	 * Tabs
	 * ------------------------------------------------------------------ */

	var tabs = Array.prototype.slice.call(
		document.querySelectorAll( '.snapcart-tabs .nav-tab' )
	);

	function activate( panelId, focusTab ) {
		var matched = false;

		tabs.forEach( function ( tab ) {
			var isCurrent = tab.getAttribute( 'data-panel' ) === panelId;
			var panel = document.getElementById( 'snapcart-panel-' + tab.getAttribute( 'data-panel' ) );

			tab.classList.toggle( 'nav-tab-active', isCurrent );
			tab.setAttribute( 'aria-selected', isCurrent ? 'true' : 'false' );
			tab.tabIndex = isCurrent ? 0 : -1;

			if ( panel ) {
				panel.hidden = ! isCurrent;
				panel.classList.toggle( 'is-active', isCurrent );
			}

			if ( isCurrent ) {
				matched = true;
				if ( focusTab ) {
					tab.focus();
				}
			}
		} );

		return matched;
	}

	function currentIndex() {
		for ( var i = 0; i < tabs.length; i++ ) {
			if ( tabs[ i ].getAttribute( 'aria-selected' ) === 'true' ) {
				return i;
			}
		}
		return 0;
	}

	function initTabs() {
		if ( ! tabs.length ) {
			return;
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var panelId = tab.getAttribute( 'data-panel' );
				activate( panelId, false );
				// Remembered so saving the form returns to the same tab.
				try {
					window.sessionStorage.setItem( 'snapcartTab', panelId );
				} catch ( e ) {
					/* Private browsing; the tab simply is not remembered. */
				}
			} );
		} );

		// Left and right arrows move between tabs, per the tabs pattern.
		document.querySelector( '.snapcart-tabs' ).addEventListener( 'keydown', function ( event ) {
			var offset = 0;

			if ( event.key === 'ArrowRight' ) {
				offset = 1;
			} else if ( event.key === 'ArrowLeft' ) {
				offset = -1;
			} else if ( event.key === 'Home' ) {
				offset = -tabs.length;
			} else if ( event.key === 'End' ) {
				offset = tabs.length;
			} else {
				return;
			}

			event.preventDefault();

			var next = Math.min( tabs.length - 1, Math.max( 0, currentIndex() + offset ) );
			activate( tabs[ next ].getAttribute( 'data-panel' ), true );
		} );

		var remembered = null;
		try {
			remembered = window.sessionStorage.getItem( 'snapcartTab' );
		} catch ( e ) {
			/* Storage unavailable; fall back to the first tab. */
		}

		// A remembered tab that no longer exists would leave every panel hidden,
		// so fall back to the first one.
		if ( remembered && ! activate( remembered, false ) ) {
			activate( tabs[ 0 ].getAttribute( 'data-panel' ), false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Conditional fields
	 * ------------------------------------------------------------------ */

	function setRowsVisible( selector, visible ) {
		$( selector ).each( function () {
			var row = this.closest ? this.closest( 'tr' ) : null;
			if ( row ) {
				row.style.display = visible ? '' : 'none';
			}
		} );
	}

	function sync() {
		var popupOn = $( '#snapcart-enable-popup' ).is( ':checked' );
		var recOn = popupOn && $( '#snapcart-enable-rec' ).is( ':checked' );
		var handpicked = recOn && 'handpicked' === $( '#snapcart-rec-source' ).val();

		setRowsVisible( '.snapcart-row-popup', popupOn );
		setRowsVisible( '.snapcart-row-rec', recOn );
		setRowsVisible( '.snapcart-row-handpicked', handpicked );

		// Explain any panel left empty by the confirmation being off, rather
		// than leaving it blank.
		var notices = document.querySelectorAll( '.snapcart-panel__notice[data-requires="popup"]' );
		Array.prototype.forEach.call( notices, function ( notice ) {
			notice.hidden = popupOn;
		} );
	}

	$( document ).on(
		'change',
		'#snapcart-enable-popup, #snapcart-enable-rec, #snapcart-rec-source',
		sync
	);

	/* ---------------------------------------------------------------------
	 * Colour pickers
	 * ------------------------------------------------------------------ */

	function initColorPickers() {
		var fields = $( '.snapcart-color' );

		if ( fields.length && typeof $.fn.wpColorPicker === 'function' ) {
			fields.wpColorPicker();
		}
	}

	/* ---------------------------------------------------------------------
	 * Copy the shortcode
	 * ------------------------------------------------------------------ */

	function legacyCopy( done ) {
		try {
			if ( document.execCommand( 'copy' ) ) {
				done();
			}
		} catch ( e ) {
			/* Clipboard unavailable; the text is already selected to copy by hand. */
		}
	}

	function initCopy() {
		$( '.snapcart-copy__btn' ).on( 'click', function () {
			var button = this;
			var input = document.getElementById( button.getAttribute( 'data-target' ) );

			if ( ! input ) {
				return;
			}

			var confirmCopy = function () {
				var original = button.textContent;
				button.textContent = strings.copied || 'Copied';
				window.setTimeout( function () {
					button.textContent = original;
				}, 1500 );
			};

			input.select();
			input.setSelectionRange( 0, 99999 );

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( input.value ).then( confirmCopy, function () {
					legacyCopy( confirmCopy );
				} );
			} else {
				legacyCopy( confirmCopy );
			}
		} );
	}

	$( function () {
		initTabs();
		initColorPickers();
		initCopy();
		sync();
	} );
} )( jQuery );
