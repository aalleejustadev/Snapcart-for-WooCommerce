<?php
/**
 * Plugin Name:       SnapCart for WooCommerce
 * Plugin URI:        https://github.com/aalleejustadev/snapcart-for-woocommerce
 * Description:       A live cart icon for your header and a fast add-to-cart confirmation that keeps shoppers browsing, with optional product suggestions.
 * Version:           2.0.12
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Ali Murtaza
 * Author URI:        https://github.com/aalleejustadev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       snapcart-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.9
 *
 * SnapCart for WooCommerce is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or (at your
 * option) any later version.
 *
 * SnapCart for WooCommerce is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 *
 * WooCommerce is a trademark of Automattic Inc. This plugin is an independent
 * extension and is not affiliated with or endorsed by Automattic.
 *
 * @package SnapCart
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/

define( 'SNAPCART_VERSION', '2.0.12' );
define( 'SNAPCART_FILE', __FILE__ );
define( 'SNAPCART_PATH', plugin_dir_path( __FILE__ ) );
define( 'SNAPCART_URL', plugin_dir_url( __FILE__ ) );
define( 'SNAPCART_BASENAME', plugin_basename( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| WooCommerce feature compatibility
|--------------------------------------------------------------------------
*/

add_action(
	'before_woocommerce_init',
	static function () {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		// SnapCart stores nothing in order tables and renders nothing inside the
		// cart or checkout templates, so it is compatible with both features.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SNAPCART_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SNAPCART_FILE, true );
	}
);

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

add_action( 'plugins_loaded', 'snapcart_bootstrap' );

/**
 * Load the plugin once every other plugin is available.
 *
 * @return void
 */
function snapcart_bootstrap() {
	if ( ! snapcart_requirements_met() ) {
		add_action( 'admin_notices', 'snapcart_requirements_notice' );
		return;
	}

	require_once SNAPCART_PATH . 'includes/class-snapcart-options.php';
	require_once SNAPCART_PATH . 'includes/class-snapcart-icons.php';
	require_once SNAPCART_PATH . 'includes/class-snapcart-format.php';
	require_once SNAPCART_PATH . 'includes/class-snapcart-recommendations.php';
	require_once SNAPCART_PATH . 'includes/class-snapcart-frontend.php';

	SnapCart_Recommendations::init();
	SnapCart_Frontend::instance();

	if ( is_admin() ) {
		require_once SNAPCART_PATH . 'includes/class-snapcart-admin.php';

		// Settings fall back to their defaults in memory, so the upgrade check
		// only needs to run where it can be acted on. Keeping it out of
		// front-end requests avoids both a query and an option write on every
		// page view.
		SnapCart_Options::maybe_upgrade();
		SnapCart_Admin::instance();
	}
}

/**
 * Whether WooCommerce is present and new enough.
 *
 * @return bool
 */
function snapcart_requirements_met() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return false;
	}

	return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.0', '>=' );
}

/**
 * Explain, once and only to users who can act on it, why SnapCart is idle.
 *
 * @return void
 */
function snapcart_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$message = class_exists( 'WooCommerce' )
		? __( 'SnapCart for WooCommerce needs WooCommerce 8.0 or newer. Please update WooCommerce to switch SnapCart back on.', 'snapcart-for-woocommerce' )
		: __( 'SnapCart for WooCommerce needs WooCommerce to be installed and active. Nothing has been changed on your site in the meantime.', 'snapcart-for-woocommerce' );

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html( $message )
	);
}

/*
|--------------------------------------------------------------------------
| Activation
|--------------------------------------------------------------------------
*/

register_activation_hook( SNAPCART_FILE, 'snapcart_activate' );

/**
 * Seed default settings on activation.
 *
 * @return void
 */
function snapcart_activate() {
	require_once SNAPCART_PATH . 'includes/class-snapcart-options.php';
	SnapCart_Options::maybe_upgrade();
}

register_deactivation_hook( SNAPCART_FILE, 'snapcart_deactivate' );

/**
 * Clear the cached suggestion list on deactivation.
 *
 * @return void
 */
function snapcart_deactivate() {
	delete_transient( 'snapcart_recommend_ids' );
}
