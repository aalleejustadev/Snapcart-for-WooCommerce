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

		// Only the centred layout draws a backdrop, so the corner notice hides
		// the setting rather than offering one that does nothing.
		var centered = 'center' === $( '.snapcart-row-layout input:checked' ).val();

		setRowsVisible( '.snapcart-row-label', $( '#snapcart-enable-label' ).is( ':checked' ) );
		setRowsVisible( '.snapcart-row-popup', popupOn );
		setRowsVisible( '.snapcart-row-centered', popupOn && centered );
		setRowsVisible( '.snapcart-row-rec', recOn );
		setRowsVisible( '.snapcart-row-handpicked', handpicked );

		// A group whose rows have all been hidden would leave its sub-heading
		// stranded, so the whole group goes with them.
		$( '.snapcart-group' ).each( function () {
			var visible = $( this ).find( 'tr' ).filter( function () {
				return this.style.display !== 'none';
			} ).length;

			this.hidden = 0 === visible;
		} );

		// Explain any panel left empty by the confirmation being off, rather
		// than leaving it blank.
		var notices = document.querySelectorAll( '.snapcart-panel__notice[data-requires="popup"]' );
		Array.prototype.forEach.call( notices, function ( notice ) {
			notice.hidden = popupOn;
		} );
	}

	$( document ).on(
		'change',
		'#snapcart-enable-popup, #snapcart-enable-rec, #snapcart-rec-source, #snapcart-enable-label, .snapcart-row-layout input',
		sync
	);

	/* ---------------------------------------------------------------------
	 * Live cart icon preview
	 *
	 * Answers "what will this look like?" while the shop owner is still
	 * choosing, which no single control can do on its own. The server renders
	 * it correctly first, so it is never blank or wrong before this runs.
	 * ------------------------------------------------------------------ */

	var preview = document.getElementById( 'snapcart-preview' );

	function fieldValue( key ) {
		return $( '[name="snapcart_settings[' + key + ']"]' ).not( ':radio' ).val();
	}

	function checkedValue( key ) {
		return $( '[name="snapcart_settings[' + key + ']"]:checked' ).val();
	}

	/**
	 * Turn a hex color and a 0-100 opacity into an rgba() string, mirroring
	 * what the plugin does server-side so the preview matches the front end.
	 */
	function rgba( hex, percent ) {
		var clean = String( hex || '' ).replace( '#', '' );

		if ( 3 === clean.length ) {
			clean = clean[ 0 ] + clean[ 0 ] + clean[ 1 ] + clean[ 1 ] + clean[ 2 ] + clean[ 2 ];
		}

		if ( 6 !== clean.length ) {
			return 'transparent';
		}

		var alpha = Math.max( 0, Math.min( 100, parseInt( percent, 10 ) || 0 ) ) / 100;

		return 'rgba(' +
			parseInt( clean.slice( 0, 2 ), 16 ) + ',' +
			parseInt( clean.slice( 2, 4 ), 16 ) + ',' +
			parseInt( clean.slice( 4, 6 ), 16 ) + ',' + alpha + ')';
	}

	function updatePreview() {
		if ( ! preview ) {
			return;
		}

		var icons = strings.icons || {};
		var shape = checkedValue( 'icon_style' );
		var size = parseInt( fieldValue( 'icon_size' ), 10 ) || 24;
		var position = checkedValue( 'badge_position' ) || 'right';
		var border = fieldValue( 'badge_border' );
		var labelOn = $( '#snapcart-enable-label' ).is( ':checked' );
		// Native trim rather than $.trim, which jQuery 4 removed.
		var labelText = String( fieldValue( 'label_text' ) || '' ).trim() || strings.defaultLabel || 'Cart';
		var labelSide = checkedValue( 'label_position' ) || 'right';

		Array.prototype.forEach.call(
			preview.querySelectorAll( '.snapcart-preview-icon' ),
			function ( node ) {
				node.classList.toggle( 'snapcart-preview-icon--left', 'left' === position );
				node.classList.toggle( 'snapcart-preview-icon--right', 'left' !== position );

				var svg = node.querySelector( 'svg' );
				if ( svg ) {
					svg.setAttribute( 'width', size );
					svg.setAttribute( 'height', size );
					if ( icons[ shape ] ) {
						svg.innerHTML = icons[ shape ];
					}
				}

				var label = node.querySelector( '.snapcart-preview-icon__label' );
				if ( label ) {
					label.textContent = labelText;
					label.hidden = ! labelOn;
				}

				node.classList.toggle( 'snapcart-preview-icon--label-left', labelOn && 'left' === labelSide );
			}
		);

		// An empty color means "inherit", which in the preview means letting the
		// pane's own light or dark text color show through.
		preview.style.setProperty( '--preview-icon', fieldValue( 'icon_color' ) || '' );
		preview.style.setProperty( '--preview-badge-bg', fieldValue( 'badge_bg' ) || 'transparent' );
		preview.style.setProperty( '--preview-badge-color', fieldValue( 'badge_color' ) || '' );
		preview.style.setProperty(
			'--preview-badge-border',
			border ? rgba( border, fieldValue( 'badge_border_opacity' ) ) : 'transparent'
		);
	}

	// Covers typing, spinners, radio cards and the color picker's own events.
	$( document ).on(
		'change input',
		'[name^="snapcart_settings[icon_"], [name^="snapcart_settings[badge_"], [name^="snapcart_settings[label_"], #snapcart-enable-label',
		updatePreview
	);

	/* ---------------------------------------------------------------------
	 * Color pickers
	 * ------------------------------------------------------------------ */

	function initColorPickers() {
		var fields = $( '.snapcart-color' );

		if ( ! fields.length || typeof $.fn.wpColorPicker !== 'function' ) {
			return;
		}

		// The picker writes the new value to the input after its own callback
		// runs, so the preview is refreshed on the next tick rather than reading
		// a value that is still one change behind.
		fields.wpColorPicker( {
			change: function () {
				window.setTimeout( updatePreview, 0 );
			},
			clear: function () {
				window.setTimeout( updatePreview, 0 );
			},
		} );
	}

	/* ---------------------------------------------------------------------
	 * Notices, shown as toasts
	 *
	 * "Settings saved." is a confirmation nobody needs to keep reading, so it
	 * clears itself. A validation error is not: those stay until dismissed.
	 * ------------------------------------------------------------------ */

	var TOAST_LIFETIME = 5000;

	function dismissToast( notice ) {
		// Reuse WordPress's own dismiss button when it has been added, so the
		// notice is torn down exactly as a manual dismissal would.
		var button = notice.querySelector( '.notice-dismiss' );

		if ( button ) {
			button.click();
			return;
		}

		notice.classList.remove( 'is-visible' );
		window.setTimeout( function () {
			if ( notice.parentNode ) {
				notice.parentNode.removeChild( notice );
			}
		}, 250 );
	}

	function initToasts() {
		var stack = document.getElementById( 'snapcart-toasts' );

		if ( ! stack ) {
			return;
		}

		Array.prototype.forEach.call( stack.querySelectorAll( '.notice' ), function ( notice ) {
			// Announced politely, since the toast sits away from where the eye
			// is and a screen reader would otherwise never reach it in time.
			if ( ! notice.getAttribute( 'role' ) ) {
				notice.setAttribute( 'role', 'status' );
			}

			window.setTimeout( function () {
				notice.classList.add( 'is-visible' );
			}, 20 );

			if ( ! notice.classList.contains( 'notice-success' ) ) {
				return;
			}

			var remaining = TOAST_LIFETIME;
			var timer = null;
			var startedAt = 0;

			function start() {
				if ( timer || remaining <= 0 ) {
					return;
				}
				startedAt = Date.now();
				timer = window.setTimeout( function () {
					dismissToast( notice );
				}, remaining );
			}

			function pause() {
				if ( ! timer ) {
					return;
				}
				window.clearTimeout( timer );
				timer = null;
				remaining -= Date.now() - startedAt;
			}

			// Hovering or tabbing into the toast holds it, so it cannot vanish
			// mid-read or while the dismiss button has focus.
			notice.addEventListener( 'mouseenter', pause );
			notice.addEventListener( 'mouseleave', start );
			notice.addEventListener( 'focusin', pause );
			notice.addEventListener( 'focusout', start );

			start();
		} );
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
		initToasts();
		sync();
		updatePreview();
	} );
} )( jQuery );
