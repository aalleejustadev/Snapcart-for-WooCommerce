<?php
/**
 * Builds the optional product suggestions shown inside the confirmation popup.
 *
 * Only product IDs are cached. Names, prices and images are resolved per
 * request from the object cache, so multi-currency, tax-by-country and
 * role-based pricing plugins always render the correct figures.
 *
 * @package SnapCart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SnapCart_Recommendations
 */
final class SnapCart_Recommendations {

	/**
	 * Single transient holding the cached ID list plus the signature it was
	 * built from. Using one fixed key keeps uninstall cleanup trivial.
	 *
	 * @var string
	 */
	const TRANSIENT = 'snapcart_recommend_ids';

	/**
	 * How long a cached ID list stays valid.
	 *
	 * @var int
	 */
	const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Guard so a single request never flushes the cache more than once.
	 *
	 * @var bool
	 */
	private static $flushed = false;

	/**
	 * Register cache invalidation hooks.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_delete_product', 'woocommerce_trash_product' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
		add_action( 'update_option_' . SnapCart_Options::OPTION_KEY, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Delete the cached ID list.
	 *
	 * @return void
	 */
	public static function flush() {
		if ( self::$flushed ) {
			return;
		}
		self::$flushed = true;
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Build the recommendations payload, or null when there is nothing to show.
	 *
	 * @param int $context_product_id Product that was just added, used by the
	 *                                "related products" source.
	 * @return array|null { heading: string, items: array[] }
	 */
	public static function payload( $context_product_id = 0 ) {
		if ( ! SnapCart_Options::is_on( 'enable_recommendations' ) ) {
			return null;
		}

		$items = self::items( $context_product_id );

		if ( empty( $items ) ) {
			return null;
		}

		return array(
			'heading' => SnapCart_Options::text( 'recommend_heading', __( 'Discover more', 'snapcart-for-woocommerce' ) ),
			'items'   => $items,
		);
	}

	/**
	 * Resolve recommendation cards, excluding the product just added.
	 *
	 * @param int $context_product_id Product that was just added.
	 * @return array[]
	 */
	private static function items( $context_product_id ) {
		$count      = (int) SnapCart_Options::get( 'recommend_count' );
		$show_price = SnapCart_Options::is_on( 'recommend_show_price' );
		$context_id = absint( $context_product_id );

		// Ask for a couple of extras so removing the current product and any
		// hidden items still leaves a full strip.
		$ids = self::ids( $count + 2, $context_id );

		$items = array();

		foreach ( $ids as $product_id ) {
			if ( count( $items ) >= $count ) {
				break;
			}

			if ( $product_id === $context_id ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
				continue;
			}

			$items[] = array(
				'name'  => SnapCart_Format::name( $product ),
				'price' => $show_price ? SnapCart_Format::price( $product ) : '',
				'image' => SnapCart_Format::thumbnail( $product ),
				'url'   => (string) get_permalink( $product_id ),
			);
		}

		/**
		 * Filter the recommendation cards before they are sent to the browser.
		 *
		 * @since 2.0.0
		 *
		 * @param array[] $items      Recommendation cards.
		 * @param int     $context_id Product that was just added.
		 */
		return apply_filters( 'snapcart_recommendation_items', $items, $context_id );
	}

	/**
	 * Get the product IDs for the configured source, using the cache where the
	 * result is not specific to a single product.
	 *
	 * @param int $limit      Number of IDs to fetch.
	 * @param int $context_id Product that was just added.
	 * @return int[]
	 */
	private static function ids( $limit, $context_id ) {
		$source = (string) SnapCart_Options::get( 'recommend_source' );

		if ( 'handpicked' === $source ) {
			$picked = (array) SnapCart_Options::get( 'recommend_products' );
			return array_slice( array_values( array_filter( array_map( 'absint', $picked ) ) ), 0, $limit );
		}

		if ( 'related' === $source ) {
			// WooCommerce caches related products itself, keyed per product.
			return $context_id ? array_map( 'absint', (array) wc_get_related_products( $context_id, $limit ) ) : array();
		}

		$signature = md5( wp_json_encode( array( SNAPCART_VERSION, $source, $limit ) ) );
		$cached    = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['sig'], $cached['ids'] ) && $cached['sig'] === $signature ) {
			return array_map( 'absint', (array) $cached['ids'] );
		}

		$ids = 'bestselling' === $source ? self::query_bestselling( $limit ) : self::query_products( $source, $limit );

		set_transient( self::TRANSIENT, array( 'sig' => $signature, 'ids' => $ids ), self::TTL );

		return $ids;
	}

	/**
	 * Query featured or recently published products via the WooCommerce CRUD API.
	 *
	 * @param string $source featured|recent.
	 * @param int    $limit  Number of IDs.
	 * @return int[]
	 */
	private static function query_products( $source, $limit ) {
		$args = array(
			'status'     => 'publish',
			'limit'      => $limit,
			'return'     => 'ids',
			'visibility' => 'catalog',
			'orderby'    => 'date',
			'order'      => 'DESC',
		);

		if ( 'featured' === $source ) {
			$args['featured'] = true;
		}

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$args['stock_status'] = 'instock';
		}

		$ids = wc_get_products( $args );

		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * Query best sellers, ordered by WooCommerce's `total_sales` meta.
	 *
	 * @param int $limit Number of IDs.
	 * @return int[]
	 */
	private static function query_bestselling( $limit ) {
		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed by WooCommerce; result is cached in a transient.
			'orderby'                => 'meta_value_num',
			'order'                  => 'DESC',
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Runs at most once per cache period.
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => array( 'exclude-from-catalog' ),
					'operator' => 'NOT IN',
				),
			),
		);

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$args['tax_query'][0]['terms'][] = 'outofstock';
		}

		$query = new WP_Query( $args );

		return array_map( 'absint', (array) $query->posts );
	}
}
