/**
 * SnapCart for WooCommerce — front-end behaviour.
 *
 * Responsibilities:
 *   - Animate the cart count when an item is added.
 *   - Show the add-to-cart confirmation, as a centred dialog or a corner toast.
 *   - Cover every add-to-cart route: classic AJAX, full page reloads, and the
 *     Store API used by the WooCommerce product blocks.
 *
 * Every value rendered here is sanitised server-side and written with
 * textContent, so no product data is ever parsed as HTML.
 */
( function ( $ ) {
	'use strict';

	var data = window.SnapCartData || {};
	var i18n = data.i18n || {};

	var current = null; // { root, popup, style, restoreFocus }
	var dismissTimer = null;
	var dismissRemaining = 0;
	var dismissStartedAt = 0;
	var pendingFetch = false;

	var FOCUSABLE =
		'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	/* ---------------------------------------------------------------------
	 * Small DOM helpers
	 * ------------------------------------------------------------------ */

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null && text !== '' ) {
			node.textContent = String( text );
		}
		return node;
	}

	function svg( paths, size ) {
		var node = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		node.setAttribute( 'viewBox', '0 0 24 24' );
		node.setAttribute( 'width', size );
		node.setAttribute( 'height', size );
		node.setAttribute( 'fill', 'none' );
		node.setAttribute( 'stroke', 'currentColor' );
		node.setAttribute( 'stroke-width', '3' );
		node.setAttribute( 'stroke-linecap', 'round' );
		node.setAttribute( 'stroke-linejoin', 'round' );
		node.setAttribute( 'aria-hidden', 'true' );
		node.setAttribute( 'focusable', 'false' );

		paths.forEach( function ( d ) {
			var path = document.createElementNS( 'http://www.w3.org/2000/svg', 'path' );
			path.setAttribute( 'd', d );
			node.appendChild( path );
		} );

		return node;
	}

	function thumb( className, src ) {
		var img = document.createElement( 'img' );
		img.className = className;
		img.src = src;
		img.alt = '';
		img.loading = 'lazy';
		img.decoding = 'async';
		img.setAttribute( 'aria-hidden', 'true' );
		return img;
	}

	/* ---------------------------------------------------------------------
	 * Count badge
	 * ------------------------------------------------------------------ */

	function bumpBadge() {
		var badges = document.querySelectorAll( '.snapcart-count' );

		Array.prototype.forEach.call( badges, function ( badge ) {
			badge.classList.add( 'snapcart-count--bump' );
			window.setTimeout( function () {
				badge.classList.remove( 'snapcart-count--bump' );
			}, 220 );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Auto-dismiss timer — pauses while the shopper is interacting
	 * ------------------------------------------------------------------ */

	function startDismiss( ms ) {
		if ( ! ms || ms <= 0 ) {
			return;
		}
		dismissRemaining = ms;
		resumeDismiss();
	}

	function resumeDismiss() {
		if ( dismissRemaining <= 0 || dismissTimer ) {
			return;
		}
		dismissStartedAt = Date.now();
		dismissTimer = window.setTimeout( close, dismissRemaining );
	}

	function pauseDismiss() {
		if ( ! dismissTimer ) {
			return;
		}
		window.clearTimeout( dismissTimer );
		dismissTimer = null;
		dismissRemaining -= Date.now() - dismissStartedAt;
	}

	function clearDismiss() {
		if ( dismissTimer ) {
			window.clearTimeout( dismissTimer );
		}
		dismissTimer = null;
		dismissRemaining = 0;
	}

	/* ---------------------------------------------------------------------
	 * Open / close
	 * ------------------------------------------------------------------ */

	function close() {
		if ( ! current ) {
			return;
		}

		clearDismiss();
		document.removeEventListener( 'keydown', onKeydown, true );

		var closing = current;
		current = null;
		closing.root.classList.remove( 'is-open' );

		window.setTimeout( function () {
			if ( closing.root.parentNode ) {
				closing.root.parentNode.removeChild( closing.root );
			}
			if ( closing.restoreFocus && document.body.contains( closing.restoreFocus ) ) {
				try {
					closing.restoreFocus.focus();
				} catch ( e ) {
					/* The previously focused element went away; nothing to do. */
				}
			}
		}, 240 );
	}

	function onKeydown( event ) {
		if ( ! current ) {
			return;
		}

		if ( event.key === 'Escape' || event.keyCode === 27 ) {
			close();
			return;
		}

		// The centred variant is a modal dialog, so focus must stay inside it.
		if ( current.style === 'center' && ( event.key === 'Tab' || event.keyCode === 9 ) ) {
			trapFocus( event );
		}
	}

	function trapFocus( event ) {
		var focusable = current.popup.querySelectorAll( FOCUSABLE );

		if ( ! focusable.length ) {
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		var active = document.activeElement;

		if ( event.shiftKey && ( active === first || ! current.popup.contains( active ) ) ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && active === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	/* ---------------------------------------------------------------------
	 * Markup
	 * ------------------------------------------------------------------ */

	function buildCard( notify, style ) {
		var popup = el( 'div', 'snapcart-popup' );

		// Close button.
		var closeBtn = el( 'button', 'snapcart-popup__close' );
		closeBtn.type = 'button';
		closeBtn.setAttribute( 'aria-label', i18n.close || 'Close' );
		closeBtn.appendChild( svg( [ 'M5 5l14 14', 'M19 5L5 19' ], 15 ) );
		closeBtn.addEventListener( 'click', close );
		popup.appendChild( closeBtn );

		// Heading with the success tick.
		var heading = el( 'h2', 'snapcart-popup__heading' );
		heading.id = 'snapcart-heading';

		var check = el( 'span', 'snapcart-popup__check' );
		check.setAttribute( 'aria-hidden', 'true' );
		check.appendChild( svg( [ 'M4 12l5 5L20 6' ], 13 ) );

		heading.appendChild( check );
		heading.appendChild(
			document.createTextNode( i18n.heading || 'Added to your cart' )
		);
		popup.appendChild( heading );

		if ( i18n.message ) {
			popup.appendChild( el( 'p', 'snapcart-popup__message', i18n.message ) );
		}

		// The product that was added.
		var item = el( 'div', 'snapcart-popup__item' );

		if ( notify.image ) {
			item.appendChild( thumb( 'snapcart-popup__thumb', notify.image ) );
		}

		var meta = el( 'div', 'snapcart-popup__meta' );
		meta.appendChild( el( 'p', 'snapcart-popup__name', notify.name ) );

		var sub = el( 'div', 'snapcart-popup__sub' );
		var qty = parseInt( notify.qty, 10 );
		sub.appendChild(
			el(
				'span',
				'snapcart-popup__qty',
				( i18n.qty || 'Qty %d' ).replace( '%d', qty > 0 ? qty : 1 )
			)
		);

		if ( notify.price ) {
			sub.appendChild( el( 'span', 'snapcart-popup__price', notify.price ) );
		}

		meta.appendChild( sub );
		item.appendChild( meta );
		popup.appendChild( item );

		// Actions.
		var actions = el( 'div', 'snapcart-popup__actions' );

		var primary = el( 'a', 'snapcart-btn snapcart-btn--primary', i18n.primary || 'View cart' );
		primary.href = notify.cartUrl || '#';
		actions.appendChild( primary );

		var secondary = el(
			'button',
			'snapcart-btn snapcart-btn--ghost',
			i18n.secondary || 'Keep shopping'
		);
		secondary.type = 'button';
		secondary.addEventListener( 'click', close );
		actions.appendChild( secondary );

		popup.appendChild( actions );

		var rec = buildRecommendations( notify.recommend );
		if ( rec ) {
			popup.appendChild( rec );
		}

		// Hovering or focusing the card holds the auto-dismiss timer, so the
		// confirmation never disappears while it is being read or used.
		popup.addEventListener( 'mouseenter', pauseDismiss );
		popup.addEventListener( 'mouseleave', resumeDismiss );
		popup.addEventListener( 'focusin', pauseDismiss );

		if ( style === 'center' ) {
			popup.setAttribute( 'role', 'dialog' );
			popup.setAttribute( 'aria-modal', 'true' );
			popup.setAttribute( 'aria-labelledby', 'snapcart-heading' );
		}

		return { popup: popup, focusTarget: primary };
	}

	function buildRecommendations( rec ) {
		if ( ! rec || ! rec.items || ! rec.items.length ) {
			return null;
		}

		var wrap = el( 'div', 'snapcart-rec' );

		if ( rec.heading ) {
			var headingId = 'snapcart-rec-heading';
			var heading = el( 'p', 'snapcart-rec__heading', rec.heading );
			heading.id = headingId;
			wrap.appendChild( heading );
			wrap.setAttribute( 'role', 'group' );
			wrap.setAttribute( 'aria-labelledby', headingId );
		}

		var track = el( 'div', 'snapcart-rec__track' );

		rec.items.forEach( function ( item ) {
			if ( ! item || ! item.name || ! item.url ) {
				return;
			}

			var card = el( 'a', 'snapcart-rec__card' );
			card.href = item.url;

			if ( item.image ) {
				card.appendChild( thumb( 'snapcart-rec__thumb', item.image ) );
			}

			card.appendChild( el( 'span', 'snapcart-rec__name', item.name ) );

			if ( item.price ) {
				card.appendChild( el( 'span', 'snapcart-rec__price', item.price ) );
			}

			track.appendChild( card );
		} );

		if ( ! track.childNodes.length ) {
			return null;
		}

		wrap.appendChild( track );
		return wrap;
	}

	/* ---------------------------------------------------------------------
	 * Show
	 * ------------------------------------------------------------------ */

	function show( notify ) {
		if ( ! notify || ! notify.name ) {
			return;
		}

		if ( current ) {
			// Replace an on-screen confirmation immediately, without waiting for
			// the close animation, so rapid adds do not stack up.
			clearDismiss();
			document.removeEventListener( 'keydown', onKeydown, true );
			if ( current.root.parentNode ) {
				current.root.parentNode.removeChild( current.root );
			}
			current = null;
		}

		var style = data.popupStyle === 'toast' ? 'toast' : 'center';
		var built = buildCard( notify, style );
		var root;

		if ( style === 'toast' ) {
			// A toast does not take focus, so it is announced politely instead
			// of being exposed as a modal dialog.
			root = el( 'div', 'snapcart-toast' );
			root.setAttribute( 'role', 'status' );
			root.setAttribute( 'aria-live', 'polite' );
		} else {
			root = el( 'div', 'snapcart-overlay' );
			root.addEventListener( 'click', function ( event ) {
				if ( event.target === root ) {
					close();
				}
			} );
		}

		root.appendChild( built.popup );
		document.body.appendChild( root );

		current = {
			root: root,
			popup: built.popup,
			style: style,
			restoreFocus: style === 'center' ? document.activeElement : null,
		};

		// Force a reflow so the opening transition runs.
		void root.offsetHeight;
		root.classList.add( 'is-open' );

		if ( style === 'center' && built.focusTarget ) {
			built.focusTarget.focus();
		}

		document.addEventListener( 'keydown', onKeydown, true );

		startDismiss( parseInt( data.autoDismiss, 10 ) || 0 );
	}

	/* ---------------------------------------------------------------------
	 * Reading the payload
	 * ------------------------------------------------------------------ */

	function parsePayload( raw ) {
		if ( ! raw ) {
			return null;
		}
		try {
			return JSON.parse( raw );
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Read the payload out of the fragments WooCommerce just handed us.
	 *
	 * The in-page element is only consulted as a fallback when fragments were
	 * supplied, because WooCommerce restores fragments from sessionStorage on
	 * page load and that copy can describe an earlier add.
	 */
	function readFromFragments( fragments ) {
		if ( ! fragments ) {
			return null;
		}

		if ( fragments[ '.snapcart-notify-data' ] ) {
			var holder = document.createElement( 'div' );
			holder.innerHTML = fragments[ '.snapcart-notify-data' ];
			var node = holder.firstElementChild;
			if ( node ) {
				var payload = parsePayload( node.getAttribute( 'data-notify' ) );
				if ( payload ) {
					return payload;
				}
			}
		}

		var existing = document.querySelector( '.snapcart-notify-data' );
		return existing ? parsePayload( existing.getAttribute( 'data-notify' ) ) : null;
	}

	/**
	 * Ask the server for the payload. Used for add-to-cart routes that return
	 * no cart fragments, such as the WooCommerce product blocks.
	 */
	function fetchPayload() {
		if ( pendingFetch || ! data.ajaxUrl || ! window.fetch ) {
			return;
		}

		pendingFetch = true;

		var body = new window.FormData();
		body.append( 'nonce', data.nonce || '' );

		window
			.fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( json ) {
				if ( json && json.success && json.data ) {
					show( json.data );
				}
			} )
			.catch( function () {
				/* A failed lookup simply means no confirmation is shown. */
			} )
			.then( function () {
				pendingFetch = false;
			} );
	}

	function handleAdd( fragments ) {
		window.setTimeout( bumpBadge, 60 );

		if ( ! data.popupEnabled ) {
			return;
		}

		var payload = readFromFragments( fragments );

		if ( payload && payload.name ) {
			show( payload );
		} else {
			fetchPayload();
		}
	}

	/* ---------------------------------------------------------------------
	 * Wiring
	 * ------------------------------------------------------------------ */

	// Classic AJAX add-to-cart on shop and category pages.
	$( document.body ).on( 'added_to_cart', function ( event, fragments ) {
		handleAdd( fragments );
	} );

	// WooCommerce block and Store API adds, which return no cart fragments.
	$( document.body ).on(
		'wc-blocks_added_to_cart experimental__woocommerce_blocks-cart-add-item',
		function () {
			handleAdd( null );
		}
	);

	// Full page reload after a non-AJAX add on a single product page.
	$( function () {
		if ( data.popupEnabled && data.notify && data.notify.name ) {
			show( data.notify );
		}
	} );
} )( window.jQuery );
