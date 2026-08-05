<?php
/**
 * Central option registry: defaults, sanitising and typed access.
 *
 * Every setting lives in one autoloaded option row, so reading settings on the
 * front end costs nothing beyond the alloptions cache WordPress already loads.
 *
 * @package SnapCart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SnapCart_Options
 */
final class SnapCart_Options {

	/**
	 * Option name holding the whole settings array.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'snapcart_settings';

	/**
	 * Option name holding the schema version, used for upgrade routines.
	 *
	 * @var string
	 */
	const VERSION_KEY = 'snapcart_db_version';

	/**
	 * Runtime cache of the merged settings array.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * Text defaults are stored empty on purpose: the translated fallback is
	 * resolved at render time, so switching site language keeps working and we
	 * never call a translation function before `init`.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Cart icon. An empty color means "inherit from the theme".
			'icon_style'             => 'bag',
			'icon_fill'              => 'outline',
			'icon_size'              => 24,
			'icon_color'             => '#000000',
			// Empty means "keep the resting color and dim it slightly", which is
			// what SnapCart did before the setting existed.
			'icon_hover_color'       => '',
			'enable_label'           => 'no',
			'label_text'             => '',
			'label_position'         => 'right',
			'badge_position'         => 'right',
			'badge_bg'               => '#000000',
			'badge_color'            => '#ffffff',
			'badge_border'           => '#ffffff',
			'badge_border_opacity'   => 50,
			'hide_empty_badge'       => 'yes',
			'link_target'            => 'cart',

			// Added to cart confirmation.
			'enable_popup'           => 'yes',
			'popup_style'            => 'center',
			'popup_heading'          => '',
			'popup_message'          => '',
			'popup_primary_label'    => '',
			'popup_secondary_label'  => '',
			'popup_show_tick'        => 'yes',
			'popup_show_message'     => 'yes',
			'popup_show_price'       => 'yes',
			'popup_show_image'       => 'yes',
			'autodismiss_seconds'    => 6,

			// Styling.
			'overlay_color'          => '#101218',
			'overlay_opacity'        => 55,
			'popup_bg'               => '#ffffff',
			'popup_border'           => '#e6e7eb',
			'popup_border_opacity'   => 100,
			'accent_color'           => '#000000',
			'accent_text_color'      => '#ffffff',
			'popup_radius'           => 'soft',
			'button_radius'          => 'soft',
			'arrow_radius'           => 'circle',

			// Recommendations.
			'enable_recommendations' => 'no',
			'recommend_heading'      => '',
			'recommend_source'       => 'related',
			'recommend_products'     => array(),
			'recommend_count'        => 6,
			'recommend_show_price'   => 'yes',

			// Housekeeping.
			'delete_data'            => 'no',
		);
	}

	/**
	 * Allowed values for the settings that are constrained to a fixed list.
	 *
	 * @return array<string, string[]>
	 */
	public static function choices() {
		return array(
			'icon_style'       => array( 'bag', 'cart', 'basket', 'tote', 'handbag' ),
			'icon_fill'        => array( 'outline', 'filled' ),
			'label_position'   => array( 'left', 'right' ),
			'badge_position'   => array( 'right', 'left' ),
			'link_target'      => array( 'cart', 'checkout' ),
			'popup_style'      => array( 'center', 'toast' ),
			'popup_radius'     => array( 'sharp', 'soft', 'round' ),
			'button_radius'    => array( 'sharp', 'soft', 'pill' ),
			'arrow_radius'     => array( 'sharp', 'soft', 'circle' ),
			'recommend_source' => array( 'related', 'featured', 'bestselling', 'recent', 'handpicked' ),
		);
	}

	/**
	 * Settings stored as a yes/no string.
	 *
	 * @return string[]
	 */
	private static function boolean_keys() {
		return array(
			'enable_label',
			'hide_empty_badge',
			'enable_popup',
			'popup_show_tick',
			'popup_show_message',
			'popup_show_price',
			'popup_show_image',
			'enable_recommendations',
			'recommend_show_price',
			'delete_data',
		);
	}

	/**
	 * Settings stored as free text.
	 *
	 * @return string[]
	 */
	private static function text_keys() {
		return array(
			'label_text',
			'popup_heading',
			'popup_message',
			'popup_primary_label',
			'popup_secondary_label',
			'recommend_heading',
		);
	}

	/**
	 * Settings stored as a hex color (empty string means "inherit").
	 *
	 * @return string[]
	 */
	private static function color_keys() {
		return array(
			'icon_color',
			'icon_hover_color',
			'badge_bg',
			'badge_color',
			'badge_border',
			'overlay_color',
			'popup_bg',
			'popup_border',
			'accent_color',
			'accent_text_color',
		);
	}

	/**
	 * Integer settings and their allowed range.
	 *
	 * @return array<string, int[]>
	 */
	private static function int_ranges() {
		return array(
			'icon_size'            => array( 16, 48 ),
			'badge_border_opacity' => array( 0, 100 ),
			'overlay_opacity'      => array( 0, 100 ),
			'popup_border_opacity' => array( 0, 100 ),
			'autodismiss_seconds'  => array( 0, 60 ),
			'recommend_count'      => array( 1, 12 ),
		);
	}

	/**
	 * Get the full, defaults-merged settings array.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			self::$cache = array_merge( self::defaults(), $stored );
		}
		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Value returned when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Whether a yes/no setting is enabled.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Get a text setting, falling back to a translated default when empty.
	 *
	 * @param string $key      Setting key.
	 * @param string $fallback Translated fallback.
	 * @return string
	 */
	public static function text( $key, $fallback ) {
		$value = trim( (string) self::get( $key, '' ) );
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Drop the runtime cache (used after saving, and in tests).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Sanitise a full settings array coming from the settings screen.
	 *
	 * Unknown keys are discarded, so nothing unexpected can be persisted.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$defaults = self::defaults();
		$choices  = self::choices();
		$booleans = self::boolean_keys();
		$texts    = self::text_keys();
		$colors   = self::color_keys();
		$ints     = self::int_ranges();
		$clean    = array();

		foreach ( $defaults as $key => $default ) {
			if ( in_array( $key, $booleans, true ) ) {
				// Unchecked checkboxes are simply absent from the POST body.
				$raw           = isset( $input[ $key ] ) ? $input[ $key ] : 'no';
				$clean[ $key ] = in_array( $raw, array( 'yes', '1', 1, true ), true ) ? 'yes' : 'no';
				continue;
			}

			if ( isset( $choices[ $key ] ) ) {
				$raw           = isset( $input[ $key ] ) ? (string) $input[ $key ] : $default;
				$clean[ $key ] = in_array( $raw, $choices[ $key ], true ) ? $raw : $default;
				continue;
			}

			if ( in_array( $key, $texts, true ) ) {
				$raw           = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
				$clean[ $key ] = sanitize_text_field( $raw );
				continue;
			}

			if ( in_array( $key, $colors, true ) ) {
				$raw           = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
				$hex           = '' === $raw ? '' : sanitize_hex_color( $raw );
				$clean[ $key ] = is_string( $hex ) ? $hex : '';
				continue;
			}

			if ( isset( $ints[ $key ] ) ) {
				list( $min, $max ) = $ints[ $key ];
				$raw               = isset( $input[ $key ] ) ? $input[ $key ] : $default;
				$clean[ $key ]     = max( $min, min( $max, absint( $raw ) ) );
				continue;
			}

			if ( 'recommend_products' === $key ) {
				$raw           = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
				$ids           = array_filter( array_map( 'absint', $raw ) );
				$clean[ $key ] = array_values( array_slice( array_unique( $ids ), 0, 50 ) );
				continue;
			}

			$clean[ $key ] = $default;
		}

		self::flush_cache();

		/**
		 * Filter the sanitised settings array before it is stored.
		 *
		 * @since 2.0.0
		 *
		 * @param array $clean Sanitised settings.
		 * @param array $input Raw submitted settings.
		 */
		return apply_filters( 'snapcart_sanitize_settings', $clean, $input );
	}

	/*
	|--------------------------------------------------------------------------
	| Install / upgrade
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create defaults on first run and migrate the flat 1.x options.
	 *
	 * Safe to call on every request; it returns early once the stored schema
	 * version matches the current one.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored_version = get_option( self::VERSION_KEY, '' );

		if ( SNAPCART_VERSION === $stored_version ) {
			return;
		}

		$settings = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $settings ) ) {
			$settings = self::migrate_from_1x();
			add_option( self::OPTION_KEY, $settings, '', 'yes' );
		}

		update_option( self::VERSION_KEY, SNAPCART_VERSION, false );
		self::flush_cache();
	}

	/**
	 * Build a settings array from the individual options used by 1.0 / 1.1.
	 *
	 * @return array
	 */
	private static function migrate_from_1x() {
		$settings = self::defaults();

		$map = array(
			'snapcart_enable_popup'           => 'enable_popup',
			'snapcart_autodismiss_seconds'    => 'autodismiss_seconds',
			'snapcart_cart_heading'           => 'popup_heading',
			'snapcart_enable_recommendations' => 'enable_recommendations',
			'snapcart_recommend_heading'      => 'recommend_heading',
			'snapcart_recommend_source'       => 'recommend_source',
			'snapcart_recommend_products'     => 'recommend_products',
			'snapcart_recommend_count'        => 'recommend_count',
		);

		$found = array();
		foreach ( $map as $legacy_key => $new_key ) {
			$value = get_option( $legacy_key, null );
			if ( null !== $value ) {
				$found[ $new_key ] = $value;
			}
		}

		if ( empty( $found ) ) {
			return $settings;
		}

		$settings = self::sanitize( array_merge( $settings, $found ) );

		// The legacy rows are no longer read; remove them so they stop being
		// autoloaded on every request.
		foreach ( array_keys( $map ) as $legacy_key ) {
			delete_option( $legacy_key );
		}

		return $settings;
	}
}
