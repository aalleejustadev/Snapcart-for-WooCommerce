<?php
/**
 * Product formatting helpers shared by the popup and the recommendations strip.
 *
 * @package SnapCart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SnapCart_Format
 */
final class SnapCart_Format {

	/**
	 * Flatten WooCommerce price markup into a short, readable string.
	 *
	 * WooCommerce injects visually hidden helper text into price markup — most
	 * notably `wc_format_price_range()`, which appends
	 * `<span class="screen-reader-text">Price range: X through Y</span>`.
	 * Running `wp_strip_all_tags()` over that markup turns the hidden text into
	 * visible text ("$45.00 – $47.50Price range: $45.00 through $47.50"), so the
	 * hidden spans have to be removed *before* the tags are stripped.
	 *
	 * @param string $html Price markup.
	 * @return string Plain text price.
	 */
	public static function plain( $html ) {
		$html = (string) $html;

		if ( '' === $html ) {
			return '';
		}

		// Drop any visually hidden helper text WooCommerce (or a theme) added.
		$stripped = preg_replace(
			'#<span[^>]*class\s*=\s*(["\'])[^"\']*\bscreen-reader-text\b[^"\']*\1[^>]*>.*?</span>#is',
			'',
			$html
		);
		if ( null !== $stripped ) {
			$html = $stripped;
		}

		// Sale prices render as <del>old</del> <ins>new</ins>; keep only the
		// price the customer actually pays.
		if ( false !== stripos( $html, '<ins' ) ) {
			$matched = array();
			if ( preg_match( '#<ins[^>]*>(.*?)</ins>#is', $html, $matched ) ) {
				$html = $matched[1];
			}
		}

		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );

		// Collapse the whitespace left behind by the removed markup.
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Build a compact, tax-aware price string for a product.
	 *
	 * Deliberately avoids `get_price_html()` so third-party markup, sale
	 * strikethroughs and screen-reader helper text never reach the popup.
	 *
	 * @param WC_Product $product Product object.
	 * @return string Plain text price, or an empty string when unavailable.
	 */
	public static function price( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$price = '';

		if ( $product->is_type( 'variable' ) ) {
			$price = self::range_price( $product );
		} elseif ( $product->is_type( 'grouped' ) ) {
			$price = self::grouped_price( $product );
		} elseif ( '' !== $product->get_price() ) {
			$price = self::plain( wc_price( wc_get_price_to_display( $product ) ) );
		}

		/**
		 * Filter the plain text price shown by SnapCart.
		 *
		 * @since 2.0.0
		 *
		 * @param string     $price   Plain text price.
		 * @param WC_Product $product Product object.
		 */
		return (string) apply_filters( 'snapcart_product_price', $price, $product );
	}

	/**
	 * Price string for a variable product, as a range when min and max differ.
	 *
	 * @param WC_Product $product Variable product.
	 * @return string
	 */
	private static function range_price( $product ) {
		$min_raw = $product->get_variation_price( 'min', false );
		$max_raw = $product->get_variation_price( 'max', false );

		if ( '' === $min_raw ) {
			return '';
		}

		$min = wc_get_price_to_display( $product, array( 'price' => $min_raw ) );
		$max = wc_get_price_to_display( $product, array( 'price' => $max_raw ) );

		return self::format_range( $min, $max );
	}

	/**
	 * Price string for a grouped product, derived from its children.
	 *
	 * @param WC_Product $product Grouped product.
	 * @return string
	 */
	private static function grouped_price( $product ) {
		$prices = array();

		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( $child instanceof WC_Product && '' !== $child->get_price() ) {
				$prices[] = (float) wc_get_price_to_display( $child );
			}
		}

		if ( empty( $prices ) ) {
			return '';
		}

		return self::format_range( min( $prices ), max( $prices ) );
	}

	/**
	 * Render a price range without WooCommerce's screen-reader helper text.
	 *
	 * @param float $min Lowest price.
	 * @param float $max Highest price.
	 * @return string
	 */
	private static function format_range( $min, $max ) {
		$min = (float) $min;
		$max = (float) $max;

		if ( $min === $max ) {
			return self::plain( wc_price( $min ) );
		}

		return sprintf(
			/* translators: 1: lowest price, 2: highest price. */
			_x( '%1$s – %2$s', 'price range', 'snapcart-for-woocommerce' ),
			self::plain( wc_price( $min ) ),
			self::plain( wc_price( $max ) )
		);
	}

	/**
	 * Small square thumbnail URL for a product, with a placeholder fallback.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public static function thumbnail( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$image_id = $product->get_image_id();

		if ( $image_id ) {
			$src = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				return (string) $src[0];
			}
		}

		return (string) wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	/**
	 * Product name, flattened and trimmed for display in a compact card.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public static function name( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		return wp_strip_all_tags( $product->get_name() );
	}
}
