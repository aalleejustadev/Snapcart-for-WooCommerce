/**
 * SnapCart "Snap Cart" block — editor registration.
 *
 * Written against the global `wp` packages with no build step, so the file that
 * ships is the file that runs. The front end is rendered in PHP; the editor
 * shows a lightweight static preview so typing in the sidebar stays instant.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ExternalLink = wp.components.ExternalLink;

	var blockData = window.SnapCartBlock || {};

	// The path data comes from the plugin's own icon set, passed in by PHP so
	// the preview matches whichever icon the store has chosen.
	function CartIcon() {
		var size = blockData.iconSize || 24;

		return el( 'svg', {
			width: size,
			height: size,
			viewBox: '0 0 24 24',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: 1.6,
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
			'aria-hidden': 'true',
			focusable: 'false',
			dangerouslySetInnerHTML: { __html: blockData.iconPath || '' },
		} );
	}

	wp.blocks.registerBlockType( 'snapcart/cart-icon', {
		edit: function ( props ) {
			var label = props.attributes.label || '';

			var preview = el(
				'span',
				{ className: 'snapcart-block-preview' },
				el( CartIcon ),
				label
					? el( 'span', { className: 'snapcart-block-preview__label' }, label )
					: null,
				el( 'span', { className: 'snapcart-block-preview__badge' }, '3' )
			);

			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Cart Icon', 'snapcart-for-woocommerce' ) },
						el( TextControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Text Label', 'snapcart-for-woocommerce' ),
							help: __(
								'Overrides the site-wide Label setting for this block only. Leave blank to follow that setting.',
								'snapcart-for-woocommerce'
							),
							value: label,
							onChange: function ( value ) {
								props.setAttributes( { label: value } );
							},
						} ),
						el(
							'p',
							{ className: 'snapcart-block-help' },
							__(
								'The icon style, badge colors and link destination are set once for the whole site.',
								'snapcart-for-woocommerce'
							),
							' ',
							el(
								ExternalLink,
								{
									href: blockData.settingsUrl || '#',
								},
								__( 'Open SnapCart settings', 'snapcart-for-woocommerce' )
							)
						)
					)
				),
				el( 'div', useBlockProps(), preview )
			);
		},

		save: function () {
			// Rendered on the server so the count is always current.
			return null;
		},
	} );
} )( window.wp );
