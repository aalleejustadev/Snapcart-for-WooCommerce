<?php
/**
 * SnapCart for WooCommerce — uninstall cleanup.
 *
 * Runs only when the plugin is deleted from the WordPress admin.
 *
 * Settings are kept unless the shop owner opted in to removing them, so
 * deleting and reinstalling does not silently wipe a configured store.
 * SnapCart never creates tables and never touches products, orders or
 * customers, so there is nothing else to undo.
 *
 * @package SnapCart
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove SnapCart's data from the current site.
 *
 * @return void
 */
function snapcart_uninstall_site() {
	// The cached suggestion list is disposable, so it always goes.
	delete_transient( 'snapcart_recommend_ids' );

	$settings = get_option( 'snapcart_settings', array() );
	$opted_in = is_array( $settings ) && isset( $settings['delete_data'] ) && 'yes' === $settings['delete_data'];

	if ( ! $opted_in ) {
		return;
	}

	delete_option( 'snapcart_settings' );
	delete_option( 'snapcart_db_version' );

	// Options used by SnapCart 1.x, in case an upgrade never ran.
	$legacy = array(
		'snapcart_enable_popup',
		'snapcart_autodismiss_seconds',
		'snapcart_cart_heading',
		'snapcart_enable_recommendations',
		'snapcart_recommend_heading',
		'snapcart_recommend_source',
		'snapcart_recommend_products',
		'snapcart_recommend_count',
	);

	foreach ( $legacy as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	$snapcart_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 0,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $snapcart_site_ids as $snapcart_site_id ) {
		switch_to_blog( (int) $snapcart_site_id );
		snapcart_uninstall_site();
		restore_current_blog();
	}

	unset( $snapcart_site_ids, $snapcart_site_id );
} else {
	snapcart_uninstall_site();
}
