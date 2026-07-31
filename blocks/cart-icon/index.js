/**
 * SnapCart "Cart Icon" block — editor registration.
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

	function BagIcon() {
		return el(
			'svg',
			{
				width: 24,
				height: 24,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 1.6,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
				'aria-hidden': 'true',
				focusable: 'false',
			},
			el( 'path', {
				d: 'M6 8h12l1.1 11.6a1.5 1.5 0 0 1-1.5 1.65H6.4a1.5 1.5 0 0 1-1.5-1.65L6 8Z',
			} ),
			el( 'path', { d: 'M9 10V6.75a3 3 0 0 1 6 0V10' } )
		);
	}

	wp.blocks.registerBlockType( 'snapcart/cart-icon', {
		edit: function ( props ) {
			var label = props.attributes.label || '';

			var preview = el(
				'span',
				{ className: 'snapcart-block-preview' },
				el( BagIcon ),
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
						{ title: __( 'Cart icon', 'snapcart-for-woocommerce' ) },
						el( TextControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Text label', 'snapcart-for-woocommerce' ),
							help: __(
								'Optional wording shown beside the icon, such as "Cart". Leave blank for icon only.',
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
								'The icon style, badge colours and link destination are set once for the whole site.',
								'snapcart-for-woocommerce'
							),
							' ',
							el(
								ExternalLink,
								{
									href:
										( window.SnapCartBlock && window.SnapCartBlock.settingsUrl ) ||
										'#',
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
