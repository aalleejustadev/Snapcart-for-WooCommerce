<?php
/**
 * The cart icon set.
 *
 * Kept in one place so the front end and the icon picker on the settings screen
 * can never drift apart. Every icon is a small inline SVG drawn with
 * `currentColor`, so there is no icon font or sprite sheet to load.
 *
 * @package SnapCart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SnapCart_Icons
 */
final class SnapCart_Icons {

	/**
	 * The default icon, used whenever an unknown style is requested.
	 *
	 * @var string
	 */
	const FALLBACK = 'bag';

	/**
	 * SVG path data for each icon, drawn on a 24x24 grid.
	 *
	 * @return array<string, string>
	 */
	public static function paths() {
		return array(
			// Shopping bag — the Apple and Shopify convention.
			'bag'      => '<path d="M6 8h12l1.1 11.6a1.5 1.5 0 0 1-1.5 1.65H6.4a1.5 1.5 0 0 1-1.5-1.65L6 8Z"/><path d="M9 10V6.75a3 3 0 0 1 6 0V10"/>',

			// Trolley — the Amazon and Etsy convention.
			'cart'     => '<circle cx="9.5" cy="20" r="1.35"/><circle cx="17.5" cy="20" r="1.35"/><path d="M2.75 3.75h2.1l2.4 11.35a1.5 1.5 0 0 0 1.47 1.19h8.24a1.5 1.5 0 0 0 1.47-1.17l1.57-6.87H6.1"/>',

			// Hand basket — common on grocery and marketplace stores.
			'basket'   => '<path d="M2.75 9.25h18.5l-1.6 9.4a2 2 0 0 1-1.97 1.6H6.32a2 2 0 0 1-1.97-1.6l-1.6-9.4Z"/><path d="m8.5 4.25-2 5M15.5 4.25l2 5"/><path d="M9.75 12.75v3.5M14.25 12.75v3.5"/>',

			// Tote — favoured by fashion and boutique stores.
			'tote'     => '<path d="M4.2 9.4h15.6l-1.05 10.9a1.6 1.6 0 0 1-1.6 1.45H6.85a1.6 1.6 0 0 1-1.6-1.45L4.2 9.4Z"/><path d="M8.3 9.4V5.15a1.55 1.55 0 0 1 3.1 0V9.4"/><path d="M12.6 9.4V5.15a1.55 1.55 0 0 1 3.1 0V9.4"/>',

			// Handbag — a squarer body with the handle above it.
			'handbag'  => '<path d="M3.25 8.75h17.5v9.6a3 3 0 0 1-3 3H6.25a3 3 0 0 1-3-3v-9.6Z"/><path d="M8.5 8.75a3.5 3.5 0 0 1 7 0"/>',
		);
	}

	/**
	 * Icon names that have been retired, mapped to what replaced them.
	 *
	 * `cart-alt` was a second trolley drawn with a closed basket. At the sizes a
	 * header icon actually renders it was indistinguishable from `cart`, so it
	 * was withdrawn rather than shipped as a near-duplicate. Stores that had
	 * selected it keep a trolley.
	 *
	 * @var array<string, string>
	 */
	const ALIASES = array(
		'cart-alt' => 'cart',
	);

	/**
	 * Resolve a stored icon name to one we currently ship.
	 *
	 * @param string $style Icon style key, possibly retired or unknown.
	 * @return string
	 */
	public static function normalize( $style ) {
		$style = (string) $style;

		if ( isset( self::ALIASES[ $style ] ) ) {
			$style = self::ALIASES[ $style ];
		}

		return array_key_exists( $style, self::paths() ) ? $style : self::FALLBACK;
	}

	/**
	 * Render an icon as inline SVG markup.
	 *
	 * The returned string contains no dynamic data — only path data defined
	 * above and an integer size — so it is safe to echo directly.
	 *
	 * @param string $style     Icon style key.
	 * @param int    $size      Width and height in pixels.
	 * @param string $css_class Class applied to the SVG element.
	 * @return string
	 */
	public static function svg( $style, $size, $css_class = 'snapcart-icon__svg' ) {
		$paths = self::paths();
		$style = self::normalize( $style );

		return sprintf(
			'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $css_class ),
			(int) $size,
			$paths[ $style ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static path data defined in this class.
		);
	}
}
