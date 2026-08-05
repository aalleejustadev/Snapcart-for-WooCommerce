<?php
/**
 * Front-end behaviour: the cart icon, the live count and the add-to-cart
 * confirmation popup.
 *
 * @package SnapCart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SnapCart_Frontend
 */
final class SnapCart_Frontend {

	/**
	 * WooCommerce session key holding the product that was last added.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'snapcart_notify';

	/**
	 * Script and style handle.
	 *
	 * @var string
	 */
	const HANDLE = 'snapcart';

	/**
	 * A stored payload older than this is ignored, so a stale session can never
	 * trigger a popup on an unrelated page view.
	 *
	 * @var int
	 */
	const PAYLOAD_TTL = 90;

	/**
	 * Singleton instance.
	 *
	 * @var SnapCart_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SnapCart_Frontend
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_shortcode( 'snapcart_icon', array( $this, 'render_icon' ) );

		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_data' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'remember_last_added' ), 20, 6 );
		add_action( 'wp_footer', array( $this, 'render_placeholder' ) );

		// Fallback transport for add-to-cart paths that do not return cart
		// fragments, such as the Store API behind the WooCommerce product blocks.
		// WooCommerce's own AJAX endpoint is used instead of admin-ajax.php so
		// the request never boots the admin.
		add_action( 'wc_ajax_snapcart_notify', array( $this, 'ajax_notify' ) );

		// The badge must stay correct when the cart changes anywhere other than
		// an add — removing a line on the cart page, emptying it, or editing a
		// quantity. Cart fragments cover some of those, but plenty of themes let
		// shop owners switch fragments off for performance, so the count is
		// available on its own endpoint too.
		add_action( 'wc_ajax_snapcart_count', array( $this, 'ajax_count' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Cart icon
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register the "Snap Cart" block from its block.json metadata.
	 *
	 * Rendering is shared with the shortcode, so both stay in step.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) || ! is_readable( SNAPCART_PATH . 'blocks/cart-icon/block.json' ) ) {
			return;
		}

		register_block_type(
			SNAPCART_PATH . 'blocks/cart-icon',
			array( 'render_callback' => array( $this, 'render_block' ) )
		);
	}

	/**
	 * Hand the block editor the link to the settings screen.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_data() {
		if ( ! class_exists( 'SnapCart_Admin' ) || ! wp_script_is( 'snapcart-cart-icon-editor-script', 'registered' ) ) {
			return;
		}

		$paths = SnapCart_Icons::paths( SnapCart_Options::get( 'icon_fill' ) );
		$style = SnapCart_Icons::normalize( SnapCart_Options::get( 'icon_style' ) );

		wp_add_inline_script(
			'snapcart-cart-icon-editor-script',
			'window.SnapCartBlock = ' . wp_json_encode(
				array(
					'settingsUrl' => admin_url( 'admin.php?page=' . SnapCart_Admin::PAGE_SLUG ),
					// So the editor preview shows the icon the store actually uses,
					// in the fill style it actually uses.
					'iconPath'    => $paths[ $style ],
					'iconSize'    => (int) SnapCart_Options::get( 'icon_size' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Render callback for the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		// Blank wording means "follow the site-wide label setting", which is the
		// same rule the shortcode uses.
		$markup = $this->render_icon(
			array( 'label' => isset( $attributes['label'] ) ? (string) $attributes['label'] : '' )
		);

		if ( '' === $markup ) {
			return '';
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';

		return '<div ' . $wrapper . '>' . $markup . '</div>';
	}

	/**
	 * Render the cart icon.
	 *
	 * Usage: [snapcart_icon] or [snapcart_icon label="Cart"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_icon( $atts ) {
		if ( ! $this->cart_available() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				// Wording override. Supplying one implies the label is wanted,
				// so a shortcode keeps working whatever the site-wide toggle says.
				'label'      => '',
				// Explicit yes/no override for that toggle.
				'show_label' => '',
			),
			(array) $atts,
			'snapcart_icon'
		);

		$count = $this->get_cart_count();
		$label = $this->resolve_label( $atts );

		$aria_text = sprintf(
			/* translators: %d: number of items currently in the cart. */
			_n( 'Cart, %d item', 'Cart, %d items', $count, 'snapcart-for-woocommerce' ),
			$count
		);

		$classes = array(
			'snapcart-icon',
			'snapcart-icon--badge-' . SnapCart_Options::get( 'badge_position' ),
		);

		if ( '' !== $label ) {
			$classes[] = 'snapcart-icon--label-' . SnapCart_Options::get( 'label_position' );
		}

		$markup = sprintf(
			'<a class="%1$s" href="%2$s" rel="nofollow" aria-label="%3$s">',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $this->get_link_target() ),
			esc_attr( $aria_text )
		);

		// The count sits inside the glyph rather than the link, so it stays
		// pinned to the icon instead of drifting to the far side of the label.
		$markup .= '<span class="snapcart-icon__glyph">' . $this->get_icon_svg() . $this->get_count_html( $count ) . '</span>';

		if ( '' !== $label ) {
			$markup .= '<span class="snapcart-icon__label">' . esc_html( $label ) . '</span>';
		}

		$markup .= '</a>';

		/**
		 * Filter the rendered cart icon markup.
		 *
		 * @since 2.0.0
		 *
		 * @param string $markup Icon markup.
		 * @param int    $count  Current cart item count.
		 */
		return apply_filters( 'snapcart_icon_html', $markup, $count );
	}

	/**
	 * Work out the wording to show beside the icon, or an empty string for none.
	 *
	 * Shortcode and block attributes win over the site-wide setting, so a single
	 * placement can differ from the rest of the site without changing it there.
	 *
	 * @param array $atts Resolved shortcode attributes.
	 * @return string
	 */
	private function resolve_label( array $atts ) {
		$override = trim( (string) $atts['label'] );
		$toggle   = strtolower( trim( (string) $atts['show_label'] ) );

		if ( '' !== $toggle ) {
			$show = in_array( $toggle, array( 'yes', 'true', '1', 'on' ), true );
		} else {
			// Supplying wording is itself a request to show it.
			$show = '' !== $override || SnapCart_Options::is_on( 'enable_label' );
		}

		if ( ! $show ) {
			return '';
		}

		$text = '' !== $override
			? $override
			: SnapCart_Options::text( 'label_text', __( 'Cart', 'snapcart-for-woocommerce' ) );

		/**
		 * Filter the wording shown beside the cart icon.
		 *
		 * @since 2.0.10
		 *
		 * @param string $text Label text; an empty string hides the label.
		 */
		return (string) apply_filters( 'snapcart_icon_label', $text );
	}

	/**
	 * Where the icon should link to.
	 *
	 * @return string
	 */
	private function get_link_target() {
		$url = 'checkout' === SnapCart_Options::get( 'link_target' ) ? wc_get_checkout_url() : wc_get_cart_url();

		/**
		 * Filter the URL the cart icon links to.
		 *
		 * @since 2.0.0
		 *
		 * @param string $url Destination URL.
		 */
		return (string) apply_filters( 'snapcart_icon_url', $url );
	}

	/**
	 * Total item quantity currently in the cart.
	 *
	 * @return int
	 */
	private function get_cart_count() {
		if ( ! $this->cart_available() ) {
			return 0;
		}
		return (int) WC()->cart->get_cart_contents_count();
	}

	/**
	 * Escaped markup for the count badge.
	 *
	 * @param int $count Item count.
	 * @return string
	 */
	private function get_count_html( $count ) {
		$classes = 'snapcart-count';

		if ( $count < 1 && SnapCart_Options::is_on( 'hide_empty_badge' ) ) {
			$classes .= ' snapcart-count--empty';
		}

		return sprintf(
			'<span class="%1$s" aria-hidden="true">%2$s</span>',
			esc_attr( $classes ),
			esc_html( (string) $count )
		);
	}

	/**
	 * The chosen icon as an inline SVG.
	 *
	 * Every icon is drawn with `currentColor`, so it follows the icon color
	 * setting or, when that is cleared, the surrounding header.
	 *
	 * @return string
	 */
	private function get_icon_svg() {
		return SnapCart_Icons::svg(
			(string) SnapCart_Options::get( 'icon_style' ),
			(int) SnapCart_Options::get( 'icon_size' ),
			'snapcart-icon__svg',
			(string) SnapCart_Options::get( 'icon_fill' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Live count
	|--------------------------------------------------------------------------
	*/

	/**
	 * Refresh the badge (and the popup transport element) via cart fragments.
	 *
	 * @param array $fragments Existing fragments.
	 * @return array
	 */
	public function cart_fragments( $fragments ) {
		if ( ! is_array( $fragments ) ) {
			$fragments = array();
		}

		$fragments['.snapcart-count'] = $this->get_count_html( $this->get_cart_count() );

		// Only hand over the payload during a genuine add-to-cart request.
		// WooCommerce also rebuilds fragments on ordinary page loads, and
		// consuming the session there would swallow the popup.
		if ( $this->is_add_to_cart_request() ) {
			$fragments['.snapcart-notify-data'] = $this->build_transport_element( $this->consume_payload() );
		}

		return $fragments;
	}

	/**
	 * Whether the current request is the WooCommerce AJAX add-to-cart endpoint.
	 *
	 * @return bool
	 */
	private function is_add_to_cart_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing check; WooCommerce verifies the request itself.
		if ( isset( $_REQUEST['wc-ajax'] ) && 'add_to_cart' === sanitize_key( wp_unslash( $_REQUEST['wc-ajax'] ) ) ) {
			return true;
		}

		$classic = isset( $_REQUEST['add-to-cart'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['add-to-cart'] ) ) : '';

		return '' !== $classic;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/*
	|--------------------------------------------------------------------------
	| Add-to-cart popup
	|--------------------------------------------------------------------------
	*/

	/**
	 * Record which product was just added.
	 *
	 * Only the identifiers are stored. The display payload is built when it is
	 * read, which keeps the add-to-cart request fast and guarantees prices are
	 * never served from a stale session.
	 *
	 * @param string $cart_item_key  Cart item key.
	 * @param int    $product_id     Product ID.
	 * @param int    $quantity       Quantity added.
	 * @param int    $variation_id   Variation ID.
	 * @param array  $variation      Variation data.
	 * @param array  $cart_item_data Extra cart item data.
	 * @return void
	 */
	public function remember_last_added( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		unset( $cart_item_key, $variation, $cart_item_data );

		if ( ! $this->popup_enabled() || ! $this->session_available() ) {
			return;
		}

		WC()->session->set(
			self::SESSION_KEY,
			array(
				'id'     => absint( $variation_id ? $variation_id : $product_id ),
				'parent' => absint( $product_id ),
				'qty'    => max( 1, absint( $quantity ) ),
				'time'   => time(),
			)
		);
	}

	/**
	 * Read the stored payload, clear it, and expand it for display.
	 *
	 * @return array|null
	 */
	private function consume_payload() {
		if ( ! $this->popup_enabled() || ! $this->session_available() ) {
			return null;
		}

		$stored = WC()->session->get( self::SESSION_KEY );

		if ( ! is_array( $stored ) || empty( $stored['id'] ) ) {
			return null;
		}

		WC()->session->set( self::SESSION_KEY, null );

		// Ignore anything left behind by an abandoned or cached page view.
		$age = time() - ( isset( $stored['time'] ) ? (int) $stored['time'] : 0 );
		if ( $age < 0 || $age > self::PAYLOAD_TTL ) {
			return null;
		}

		$product = wc_get_product( absint( $stored['id'] ) );

		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$show_price = SnapCart_Options::is_on( 'popup_show_price' );
		$show_image = SnapCart_Options::is_on( 'popup_show_image' );

		$context_id = isset( $stored['parent'] ) ? absint( $stored['parent'] ) : absint( $stored['id'] );

		$payload = array(
			'name'      => SnapCart_Format::name( $product ),
			'qty'       => isset( $stored['qty'] ) ? absint( $stored['qty'] ) : 1,
			'price'     => $show_price ? SnapCart_Format::price( $product ) : '',
			'image'     => $show_image ? SnapCart_Format::thumbnail( $product ) : '',
			'cartUrl'   => $this->get_link_target(),
			'recommend' => SnapCart_Recommendations::payload( $context_id ),
		);

		/**
		 * Filter the popup payload just before it is sent to the browser.
		 *
		 * @since 2.0.0
		 *
		 * @param array      $payload Popup payload.
		 * @param WC_Product $product Product that was added.
		 */
		return apply_filters( 'snapcart_popup_payload', $payload, $product );
	}

	/**
	 * The hidden element WooCommerce swaps in to carry the payload.
	 *
	 * @param array|null $payload Popup payload.
	 * @return string
	 */
	private function build_transport_element( $payload ) {
		$json = null === $payload ? '' : wp_json_encode( $payload );

		if ( ! is_string( $json ) ) {
			$json = '';
		}

		return sprintf( '<div class="snapcart-notify-data" data-notify="%s"></div>', esc_attr( $json ) );
	}

	/**
	 * Print the empty transport element the fragment replaces.
	 *
	 * @return void
	 */
	public function render_placeholder() {
		if ( ! $this->popup_enabled() || ! $this->assets_enabled() ) {
			return;
		}
		echo '<div class="snapcart-notify-data" data-notify=""></div>';
	}

	/**
	 * AJAX fallback used when an add-to-cart path returns no cart fragments.
	 *
	 * Returns nothing but the caller's own just-added product. A failed nonce
	 * responds with an empty payload rather than an error, so a stale nonce in
	 * a cached page degrades quietly instead of logging a console error.
	 *
	 * @return void
	 */
	public function ajax_notify() {
		if ( ! $this->popup_enabled() || ! check_ajax_referer( 'snapcart_notify', 'nonce', false ) ) {
			wp_send_json_success( null );
		}

		wp_send_json_success( $this->consume_payload() );
	}

	/**
	 * Return how many items are in the caller's cart.
	 *
	 * Read-only, changes nothing, and reveals only what the caller's own
	 * session already holds, so it carries no nonce — a nonce would simply go
	 * stale inside a cached page and break the badge.
	 *
	 * @return void
	 */
	public function ajax_count() {
		wp_send_json_success( array( 'count' => $this->get_cart_count() ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Assets
	|--------------------------------------------------------------------------
	*/

	/**
	 * Enqueue the front-end stylesheet and script.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->assets_enabled() ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// The stylesheet uses CSS logical properties throughout, so it is
		// already correct in right-to-left locales without a separate build.
		wp_enqueue_style(
			self::HANDLE,
			SNAPCART_URL . 'assets/css/snapcart' . $suffix . '.css',
			array(),
			SNAPCART_VERSION
		);

		$custom_css = $this->get_custom_properties();
		if ( '' !== $custom_css ) {
			wp_add_inline_style( self::HANDLE, $custom_css );
		}

		wp_enqueue_script(
			self::HANDLE,
			SNAPCART_URL . 'assets/js/snapcart' . $suffix . '.js',
			array( 'jquery' ),
			SNAPCART_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.SnapCartData = ' . wp_json_encode( $this->get_script_data() ) . ';',
			'before'
		);
	}

	/**
	 * Data handed to the front-end script.
	 *
	 * @return array
	 */
	private function get_script_data() {
		$notify = $this->get_pageload_payload();

		if ( null !== $notify ) {
			// A popup is about to render for this specific visitor, so this
			// response must never be stored by a full-page cache.
			$this->prevent_page_cache();
		}

		$has_wc_ajax  = class_exists( 'WC_AJAX' );
		$show_message = SnapCart_Options::is_on( 'popup_show_message' );

		return array(
			'popupEnabled'   => $this->popup_enabled(),
			'popupStyle'     => (string) SnapCart_Options::get( 'popup_style' ),
			'autoDismiss'    => (int) SnapCart_Options::get( 'autodismiss_seconds' ) * 1000,
			'hideEmptyBadge' => SnapCart_Options::is_on( 'hide_empty_badge' ),
			'showTick'       => SnapCart_Options::is_on( 'popup_show_tick' ),
			'notify'         => $notify,
			'ajaxUrl'        => $has_wc_ajax ? WC_AJAX::get_endpoint( 'snapcart_notify' ) : '',
			'countUrl'       => $has_wc_ajax ? WC_AJAX::get_endpoint( 'snapcart_count' ) : '',
			'nonce'          => wp_create_nonce( 'snapcart_notify' ),
			'i18n'           => array(
				'heading'   => SnapCart_Options::text( 'popup_heading', __( 'Added to your cart', 'snapcart-for-woocommerce' ) ),
				// Sent as an empty string when switched off, so the paragraph is
				// never created and its text never reaches the page at all.
				'message'   => $show_message
					? SnapCart_Options::text( 'popup_message', __( 'Saved to your cart and ready whenever you are.', 'snapcart-for-woocommerce' ) )
					: '',
				'primary'   => SnapCart_Options::text( 'popup_primary_label', __( 'View cart', 'snapcart-for-woocommerce' ) ),
				'secondary' => SnapCart_Options::text( 'popup_secondary_label', __( 'Continue shopping', 'snapcart-for-woocommerce' ) ),
				'close'     => __( 'Close', 'snapcart-for-woocommerce' ),
				'prev'      => __( 'Previous products', 'snapcart-for-woocommerce' ),
				'next'      => __( 'More products', 'snapcart-for-woocommerce' ),
				/* translators: %d: quantity added. */
				'qty'       => __( 'Qty %d', 'snapcart-for-woocommerce' ),
			),
		);
	}

	/**
	 * The payload for a non-AJAX add, where the browser reloads the page.
	 *
	 * Suppressed on the cart and checkout pages, where the customer can already
	 * see what they added.
	 *
	 * @return array|null
	 */
	private function get_pageload_payload() {
		if ( ! $this->popup_enabled() ) {
			return null;
		}

		// On the cart and checkout the shopper can already see what they added,
		// so the stored payload is discarded rather than left to surface on
		// whichever page they happen to open next.
		if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			$this->discard_payload();
			return null;
		}

		return $this->consume_payload();
	}

	/**
	 * Drop the stored payload without building anything for display.
	 *
	 * @return void
	 */
	private function discard_payload() {
		if ( $this->session_available() && is_array( WC()->session->get( self::SESSION_KEY ) ) ) {
			WC()->session->set( self::SESSION_KEY, null );
		}
	}

	/**
	 * Build the CSS custom properties for any colors the shop owner has set.
	 *
	 * Nothing is printed when the defaults are in use, so the theme's own
	 * styling stays untouched.
	 *
	 * @return string
	 */
	private function get_custom_properties() {
		$map = array(
			'icon_color'   => '--snapcart-icon-color',
			'badge_bg'     => '--snapcart-badge-bg',
			'badge_color'  => '--snapcart-badge-color',
			'popup_bg'     => '--snapcart-surface',
			'accent_color' => '--snapcart-accent',
		);

		$rules = array();

		foreach ( $map as $option_key => $property ) {
			$value = (string) SnapCart_Options::get( $option_key );
			if ( '' !== $value ) {
				$rules[] = $property . ':' . $value;
			}
		}

		// Hovering the icon dims it by default. Once a hover color is chosen the
		// change of color is the feedback, so the dimming is switched off — two
		// signals at once reads as a mistake rather than as emphasis.
		$hover = (string) SnapCart_Options::get( 'icon_hover_color' );
		if ( '' !== $hover ) {
			$rules[] = '--snapcart-icon-hover-color:' . $hover;
			$rules[] = '--snapcart-icon-hover-opacity:1';
		}

		// Clearing a border color is how the shop owner turns that border off,
		// so the width collapses with it rather than leaving a transparent edge.
		foreach ( array(
			'badge_border' => array( '--snapcart-badge-border', 'badge_border_opacity' ),
			'popup_border' => array( '--snapcart-popup-border', 'popup_border_opacity' ),
		) as $option_key => $config ) {
			list( $property, $opacity_key ) = $config;
			$color                          = (string) SnapCart_Options::get( $option_key );

			if ( '' === $color ) {
				$rules[] = $property . '-width:0';
				$rules[] = $property . ':transparent';
			} else {
				$rules[] = $property . ':' . $this->rgba( $color, (int) SnapCart_Options::get( $opacity_key ) );
			}
		}

		// The backdrop behind the centred dialog.
		$overlay = (string) SnapCart_Options::get( 'overlay_color' );
		if ( '' !== $overlay ) {
			$rules[] = '--snapcart-overlay:' . $this->rgba( $overlay, (int) SnapCart_Options::get( 'overlay_opacity' ) );
		}

		// Button label color. When the shop owner clears it, a readable color
		// is derived from the button background instead of leaving it unset.
		$accent      = (string) SnapCart_Options::get( 'accent_color' );
		$accent_text = (string) SnapCart_Options::get( 'accent_text_color' );

		if ( '' !== $accent_text ) {
			$rules[] = '--snapcart-accent-contrast:' . $accent_text;
		} elseif ( '' !== $accent ) {
			$rules[] = '--snapcart-accent-contrast:' . $this->readable_contrast( $accent );
		}

		foreach ( $this->get_radius_rules() as $rule ) {
			$rules[] = $rule;
		}

		return ':root{' . implode( ';', $rules ) . '}';
	}

	/**
	 * Corner radius custom properties for the chosen styling preset.
	 *
	 * The inner radius covers the product row, thumbnails and suggestion cards,
	 * so a "sharp" choice squares off the whole confirmation rather than
	 * leaving rounded details behind.
	 *
	 * @return string[]
	 */
	private function get_radius_rules() {
		$popup = array(
			'sharp' => array( '0', '0' ),
			'soft'  => array( '14px', '8px' ),
			'round' => array( '24px', '12px' ),
		);

		$button = array(
			'sharp' => '0',
			'soft'  => '8px',
			'pill'  => '999px',
		);

		$arrow = array(
			'sharp'  => '0',
			'soft'   => '6px',
			'circle' => '999px',
		);

		$popup_key  = (string) SnapCart_Options::get( 'popup_radius' );
		$button_key = (string) SnapCart_Options::get( 'button_radius' );
		$arrow_key  = (string) SnapCart_Options::get( 'arrow_radius' );

		$popup_key  = isset( $popup[ $popup_key ] ) ? $popup_key : 'soft';
		$button_key = isset( $button[ $button_key ] ) ? $button_key : 'soft';
		$arrow_key  = isset( $arrow[ $arrow_key ] ) ? $arrow_key : 'circle';

		return array(
			'--snapcart-radius:' . $popup[ $popup_key ][0],
			'--snapcart-inner-radius:' . $popup[ $popup_key ][1],
			'--snapcart-btn-radius:' . $button[ $button_key ],
			'--snapcart-arrow-radius:' . $arrow[ $arrow_key ],
		);
	}

	/**
	 * Split a hex color into its red, green and blue components.
	 *
	 * @param string $hex Hex color, with or without the leading hash.
	 * @return int[]|null Three channel values, or null when unparseable.
	 */
	private function hex_to_rgb( $hex ) {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return null;
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Build an `rgba()` value from a hex color and a percentage opacity.
	 *
	 * @param string $hex     Hex color.
	 * @param int    $percent Opacity, 0-100.
	 * @return string
	 */
	private function rgba( $hex, $percent ) {
		$rgb = $this->hex_to_rgb( $hex );

		if ( null === $rgb ) {
			return 'transparent';
		}

		$alpha = max( 0, min( 100, (int) $percent ) ) / 100;

		// Trim trailing zeros so the declaration stays compact: 0.5, not 0.50.
		$alpha = rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' );

		return sprintf( 'rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], '' === $alpha ? '0' : $alpha );
	}

	/**
	 * Pick black or white text for a given background color.
	 *
	 * @param string $hex Hex color, e.g. #1a1a1a.
	 * @return string
	 */
	private function readable_contrast( $hex ) {
		$rgb = $this->hex_to_rgb( $hex );

		if ( null === $rgb ) {
			return '#ffffff';
		}

		// Relative luminance, per WCAG 2.1.
		$channel = static function ( $value ) {
			$value /= 255;
			return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		};

		$luminance = 0.2126 * $channel( $rgb[0] ) + 0.7152 * $channel( $rgb[1] ) + 0.0722 * $channel( $rgb[2] );

		return $luminance > 0.179 ? '#000000' : '#ffffff';
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Whether front-end assets should load on this request.
	 *
	 * @return bool
	 */
	private function assets_enabled() {
		$load = ! is_admin() && ! is_feed();

		/**
		 * Filter whether SnapCart's front-end assets are loaded.
		 *
		 * Return false to skip the stylesheet and script entirely, for example
		 * on landing pages that have no header cart.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $load Whether to load assets.
		 */
		return (bool) apply_filters( 'snapcart_load_assets', $load );
	}

	/**
	 * Whether the confirmation popup is switched on.
	 *
	 * @return bool
	 */
	private function popup_enabled() {
		return SnapCart_Options::is_on( 'enable_popup' );
	}

	/**
	 * Whether WooCommerce's cart is ready to be queried.
	 *
	 * @return bool
	 */
	private function cart_available() {
		return function_exists( 'WC' ) && null !== WC() && isset( WC()->cart ) && WC()->cart instanceof WC_Cart;
	}

	/**
	 * Whether the WooCommerce session is ready.
	 *
	 * @return bool
	 */
	private function session_available() {
		return function_exists( 'WC' ) && null !== WC() && isset( WC()->session ) && is_object( WC()->session );
	}

	/**
	 * Tell page caches to skip this response.
	 *
	 * `DONOTCACHEPAGE` is deliberately unprefixed. It is the shared constant that
	 * WP Rocket, W3 Total Cache, WP Super Cache and Batcache all look for, so a
	 * prefixed name would be read by nothing and a cached page could then serve
	 * one shopper's confirmation to somebody else.
	 *
	 * @return void
	 */
	private function prevent_page_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- De-facto standard constant read by page caching plugins; see the note above.
		}
		if ( function_exists( 'nocache_headers' ) && ! headers_sent() ) {
			nocache_headers();
		}
	}
}
