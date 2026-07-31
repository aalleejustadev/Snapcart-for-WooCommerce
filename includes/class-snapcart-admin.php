<?php
/**
 * The SnapCart settings screen, built on the WordPress Settings API.
 *
 * @package SnapCart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SnapCart_Admin
 */
final class SnapCart_Admin {

	/**
	 * Settings API option group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'snapcart_settings_group';

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'snapcart';

	/**
	 * Capability required to view and save settings.
	 *
	 * Shop managers hold `manage_woocommerce` but not `manage_options`, so the
	 * option group's save capability is lowered to match the menu.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Singleton instance.
	 *
	 * @var SnapCart_Admin|null
	 */
	private static $instance = null;

	/**
	 * Registered sections, in the order they appear as tabs.
	 *
	 * @var array<string, array>
	 */
	private $tabs = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return SnapCart_Admin
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'option_page_capability_' . self::OPTION_GROUP, array( $this, 'settings_capability' ) );
		add_filter( 'plugin_action_links_' . SNAPCART_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Lower the option group capability to match the menu capability.
	 *
	 * @return string
	 */
	public function settings_capability() {
		return self::CAPABILITY;
	}

	/**
	 * Add the settings screen under the WooCommerce menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'SnapCart', 'snapcart-for-woocommerce' ),
			__( 'SnapCart', 'snapcart-for-woocommerce' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'snapcart-for-woocommerce' )
		);

		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Add a support link under the plugin row.
	 *
	 * @param array  $meta Existing row meta.
	 * @param string $file Plugin file being rendered.
	 * @return array
	 */
	public function row_meta( $meta, $file ) {
		if ( SNAPCART_BASENAME !== $file ) {
			return $meta;
		}

		$meta[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( 'https://wordpress.org/support/plugin/snapcart-for-woocommerce/' ),
			esc_html__( 'Support', 'snapcart-for-woocommerce' )
		);

		return $meta;
	}

	/**
	 * Whether the given admin page hook is our settings screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private function is_settings_screen( $hook ) {
		return 'woocommerce_page_' . self::PAGE_SLUG === $hook;
	}

	/**
	 * Load admin assets, but only on our own screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->is_settings_screen( $hook ) ) {
			return;
		}

		// WooCommerce's product picker and the core colour picker are already
		// part of WordPress and WooCommerce; SnapCart ships neither.
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'wp-color-picker' );

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_style(
			'snapcart-admin',
			SNAPCART_URL . 'assets/css/admin' . $suffix . '.css',
			array( 'woocommerce_admin_styles' ),
			SNAPCART_VERSION
		);

		wp_enqueue_script(
			'snapcart-admin',
			SNAPCART_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'jquery', 'wp-color-picker' ),
			SNAPCART_VERSION,
			true
		);

		wp_localize_script(
			'snapcart-admin',
			'SnapCartAdmin',
			array( 'copied' => __( 'Copied', 'snapcart-for-woocommerce' ) )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Settings registration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register the single settings option and every field on the screen.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			SnapCart_Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'SnapCart_Options', 'sanitize' ),
				'default'           => SnapCart_Options::defaults(),
				'show_in_rest'      => false,
			)
		);

		$this->add_section(
			'icon',
			__( 'Cart icon', 'snapcart-for-woocommerce' ),
			__( 'How the cart button looks in your header, and where it sends shoppers.', 'snapcart-for-woocommerce' )
		);

		$this->add_field( 'icon_style', __( 'Icon shape', 'snapcart-for-woocommerce' ), 'icon', 'field_icon_style' );
		$this->add_field( 'icon_size', __( 'Icon size', 'snapcart-for-woocommerce' ), 'icon', 'field_icon_size' );
		$this->add_field( 'icon_color', __( 'Icon colour', 'snapcart-for-woocommerce' ), 'icon', 'field_icon_color' );
		$this->add_field( 'badge_position', __( 'Count position', 'snapcart-for-woocommerce' ), 'icon', 'field_badge_position' );
		$this->add_field( 'badge_colors', __( 'Count colours', 'snapcart-for-woocommerce' ), 'icon', 'field_badge_colors' );
		$this->add_field( 'hide_empty_badge', __( 'Empty cart', 'snapcart-for-woocommerce' ), 'icon', 'field_hide_empty_badge' );
		$this->add_field( 'link_target', __( 'Icon links to', 'snapcart-for-woocommerce' ), 'icon', 'field_link_target' );

		$this->add_section(
			'popup',
			__( 'Add-to-cart confirmation', 'snapcart-for-woocommerce' ),
			__( 'Reassure shoppers the moment they add something, and give them a clear route to checkout without losing their place.', 'snapcart-for-woocommerce' ),
			__( 'Confirmation', 'snapcart-for-woocommerce' )
		);

		$this->add_field( 'enable_popup', __( 'Confirmation', 'snapcart-for-woocommerce' ), 'popup', 'field_enable_popup' );
		$this->add_field( 'popup_style', __( 'Appearance', 'snapcart-for-woocommerce' ), 'popup', 'field_popup_style', 'snapcart-row-popup' );
		$this->add_field( 'popup_heading', __( 'Headline', 'snapcart-for-woocommerce' ), 'popup', 'field_popup_heading', 'snapcart-row-popup' );
		$this->add_field( 'popup_message', __( 'Supporting text', 'snapcart-for-woocommerce' ), 'popup', 'field_popup_message', 'snapcart-row-popup' );
		$this->add_field( 'popup_buttons', __( 'Button wording', 'snapcart-for-woocommerce' ), 'popup', 'field_popup_buttons', 'snapcart-row-popup' );
		$this->add_field( 'popup_details', __( 'Product details', 'snapcart-for-woocommerce' ), 'popup', 'field_popup_details', 'snapcart-row-popup' );
		$this->add_field( 'autodismiss_seconds', __( 'Close automatically', 'snapcart-for-woocommerce' ), 'popup', 'field_autodismiss', 'snapcart-row-popup' );

		$this->add_section(
			'style',
			__( 'Styling', 'snapcart-for-woocommerce' ),
			__( 'Match the confirmation to the rest of your store. Square corners suit a bold, editorial look; softer corners feel friendlier.', 'snapcart-for-woocommerce' ),
			'',
			__( 'These settings shape the add-to-cart confirmation. Turn the confirmation on to use them.', 'snapcart-for-woocommerce' )
		);

		$this->add_field( 'popup_radius', __( 'Popup corners', 'snapcart-for-woocommerce' ), 'style', 'field_popup_radius', 'snapcart-row-popup' );
		$this->add_field( 'button_radius', __( 'Button corners', 'snapcart-for-woocommerce' ), 'style', 'field_button_radius', 'snapcart-row-popup' );
		$this->add_field( 'accent_color', __( 'Button colours', 'snapcart-for-woocommerce' ), 'style', 'field_accent_color', 'snapcart-row-popup' );

		$this->add_section(
			'recommend',
			__( 'Product suggestions', 'snapcart-for-woocommerce' ),
			__( 'Add a scrollable strip of products to the confirmation to lift average order value. Off by default; when it is off, no extra product data is loaded at all.', 'snapcart-for-woocommerce' ),
			__( 'Suggestions', 'snapcart-for-woocommerce' ),
			__( 'Suggestions appear inside the add-to-cart confirmation. Turn the confirmation on to use them.', 'snapcart-for-woocommerce' )
		);

		$this->add_field( 'enable_recommendations', __( 'Suggestions', 'snapcart-for-woocommerce' ), 'recommend', 'field_enable_recommendations', 'snapcart-row-popup' );
		$this->add_field( 'recommend_heading', __( 'Strip heading', 'snapcart-for-woocommerce' ), 'recommend', 'field_recommend_heading', 'snapcart-row-popup snapcart-row-rec' );
		$this->add_field( 'recommend_source', __( 'Which products', 'snapcart-for-woocommerce' ), 'recommend', 'field_recommend_source', 'snapcart-row-popup snapcart-row-rec' );
		$this->add_field( 'recommend_products', __( 'Chosen products', 'snapcart-for-woocommerce' ), 'recommend', 'field_recommend_products', 'snapcart-row-popup snapcart-row-rec snapcart-row-handpicked' );
		$this->add_field( 'recommend_count', __( 'How many', 'snapcart-for-woocommerce' ), 'recommend', 'field_recommend_count', 'snapcart-row-popup snapcart-row-rec' );
		$this->add_field( 'recommend_show_price', __( 'Show prices', 'snapcart-for-woocommerce' ), 'recommend', 'field_recommend_show_price', 'snapcart-row-popup snapcart-row-rec' );

		$this->add_section(
			'advanced',
			__( 'Data', 'snapcart-for-woocommerce' ),
			__( 'What happens to your settings if you ever remove the plugin.', 'snapcart-for-woocommerce' )
		);

		$this->add_field( 'delete_data', __( 'On deletion', 'snapcart-for-woocommerce' ), 'advanced', 'field_delete_data' );
	}

	/**
	 * Register a settings section, which is rendered as one tab panel.
	 *
	 * @param string $id          Section id suffix.
	 * @param string $title       Section title, used as the tab label.
	 * @param string $description Section description.
	 * @param string $tab_label   Shorter label for the tab itself, if the title
	 *                            is too long to sit comfortably in a tab.
	 * @param string $notice      Message shown in place of the fields when the
	 *                            confirmation they belong to is switched off.
	 * @return void
	 */
	private function add_section( $id, $title, $description, $tab_label = '', $notice = '' ) {
		add_settings_section(
			'snapcart_' . $id,
			$title,
			'__return_empty_string',
			self::PAGE_SLUG
		);

		$this->tabs[ $id ] = array(
			'title'       => $title,
			'label'       => '' === $tab_label ? $title : $tab_label,
			'description' => $description,
			'notice'      => $notice,
		);
	}

	/**
	 * Register a settings field.
	 *
	 * @param string $id       Field id.
	 * @param string $title    Field label.
	 * @param string $section  Section id suffix.
	 * @param string $callback Method on this class that renders the control.
	 * @param string $class    Extra row classes, used for conditional display.
	 * @return void
	 */
	private function add_field( $id, $title, $section, $callback, $class = '' ) {
		add_settings_field(
			'snapcart_' . $id,
			$title,
			array( $this, $callback ),
			self::PAGE_SLUG,
			'snapcart_' . $section,
			'' === $class ? array() : array( 'class' => $class )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Field helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * The `name` attribute for a setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function name( $key ) {
		return SnapCart_Options::OPTION_KEY . '[' . $key . ']';
	}

	/**
	 * Render a switch control.
	 *
	 * This is a plain checkbox: the switch appearance is drawn entirely in CSS
	 * from the input's own checked and focus states, so it needs no JavaScript
	 * and stays operable by keyboard and screen reader exactly as a checkbox.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Label text.
	 * @param string $description Help text.
	 * @param string $id          Optional element id.
	 * @return void
	 */
	private function toggle( $key, $label, $description = '', $id = '' ) {
		printf(
			'<label class="snapcart-toggle"><input type="checkbox" class="snapcart-toggle__input" %1$s name="%2$s" value="yes" %3$s /><span class="snapcart-toggle__track" aria-hidden="true"></span><span class="snapcart-toggle__text">%4$s</span></label>',
			'' === $id ? '' : 'id="' . esc_attr( $id ) . '"',
			esc_attr( $this->name( $key ) ),
			checked( 'yes', SnapCart_Options::get( $key ), false ),
			esc_html( $label )
		);

		$this->description( $description );
	}

	/**
	 * Render a row of selectable cards backed by radio inputs.
	 *
	 * Used where seeing the choice matters more than reading its name. Like the
	 * switch above, the appearance is pure CSS driven by `:checked`, so the
	 * control is a native radio group with all of its keyboard behaviour intact.
	 *
	 * @param string $key         Setting key.
	 * @param array  $choices     value => array( label, preview markup ).
	 * @param string $description Help text.
	 * @return void
	 */
	private function radio_cards( $key, array $choices, $description = '' ) {
		$current = (string) SnapCart_Options::get( $key );

		printf(
			'<div class="snapcart-cards" role="radiogroup" aria-label="%s">',
			esc_attr( $this->field_label( $key ) )
		);

		foreach ( $choices as $value => $choice ) {
			printf(
				'<label class="snapcart-card-option"><input type="radio" class="snapcart-card-option__input" name="%1$s" value="%2$s" %3$s /><span class="snapcart-card-option__body"><span class="snapcart-card-option__preview">%4$s</span><span class="snapcart-card-option__label">%5$s</span></span></label>',
				esc_attr( $this->name( $key ) ),
				esc_attr( (string) $value ),
				checked( $current, (string) $value, false ),
				$choice['preview'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup supplied by the caller below.
				esc_html( $choice['label'] )
			);
		}

		echo '</div>';

		$this->description( $description );
	}

	/**
	 * Accessible group label for a card picker.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function field_label( $key ) {
		$labels = array(
			'icon_style'    => __( 'Icon shape', 'snapcart-for-woocommerce' ),
			'popup_style'   => __( 'Confirmation appearance', 'snapcart-for-woocommerce' ),
			'popup_radius'  => __( 'Popup corners', 'snapcart-for-woocommerce' ),
			'button_radius' => __( 'Button corners', 'snapcart-for-woocommerce' ),
		);

		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * Render a text input.
	 *
	 * @param string $key         Setting key.
	 * @param string $placeholder Placeholder showing the default wording.
	 * @param string $description Help text.
	 * @return void
	 */
	private function text_input( $key, $placeholder, $description = '' ) {
		printf(
			'<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />',
			esc_attr( $this->name( $key ) ),
			esc_attr( (string) SnapCart_Options::get( $key ) ),
			esc_attr( $placeholder )
		);

		$this->description( $description );
	}

	/**
	 * Render a colour picker.
	 *
	 * @param string $key      Setting key.
	 * @param string $fallback Default colour shown when nothing is set.
	 * @return void
	 */
	private function color_input( $key, $fallback ) {
		printf(
			'<input type="text" class="snapcart-color" name="%1$s" value="%2$s" data-default-color="%3$s" />',
			esc_attr( $this->name( $key ) ),
			esc_attr( (string) SnapCart_Options::get( $key ) ),
			esc_attr( $fallback )
		);
	}

	/**
	 * Render a select control.
	 *
	 * @param string $key         Setting key.
	 * @param array  $options     value => label pairs.
	 * @param string $description Help text.
	 * @param string $id          Optional element id.
	 * @return void
	 */
	private function select( $key, array $options, $description = '', $id = '' ) {
		printf(
			'<select %1$s name="%2$s">',
			'' === $id ? '' : 'id="' . esc_attr( $id ) . '"',
			esc_attr( $this->name( $key ) )
		);

		$current = (string) SnapCart_Options::get( $key );

		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $value ),
				selected( $current, (string) $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		$this->description( $description );
	}

	/**
	 * Render a number input.
	 *
	 * @param string $key         Setting key.
	 * @param int    $min         Minimum value.
	 * @param int    $max         Maximum value.
	 * @param string $suffix      Unit shown after the field.
	 * @param string $description Help text.
	 * @return void
	 */
	private function number_input( $key, $min, $max, $suffix = '', $description = '' ) {
		printf(
			'<input type="number" class="small-text" min="%1$d" max="%2$d" step="1" name="%3$s" value="%4$d" />',
			(int) $min,
			(int) $max,
			esc_attr( $this->name( $key ) ),
			(int) SnapCart_Options::get( $key )
		);

		if ( '' !== $suffix ) {
			echo ' <span class="snapcart-suffix">' . esc_html( $suffix ) . '</span>';
		}

		$this->description( $description );
	}

	/**
	 * Render field help text.
	 *
	 * @param string $text Help text.
	 * @return void
	 */
	private function description( $text ) {
		if ( '' !== $text ) {
			echo '<p class="description">' . esc_html( $text ) . '</p>';
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Fields — cart icon
	|--------------------------------------------------------------------------
	*/

	/**
	 * Icon shape.
	 *
	 * @return void
	 */
	public function field_icon_style() {
		$labels = array(
			'bag'      => __( 'Shopping bag', 'snapcart-for-woocommerce' ),
			'cart'     => __( 'Trolley', 'snapcart-for-woocommerce' ),
			'basket'   => __( 'Basket', 'snapcart-for-woocommerce' ),
			'tote'     => __( 'Tote', 'snapcart-for-woocommerce' ),
			'handbag'  => __( 'Handbag', 'snapcart-for-woocommerce' ),
		);

		$choices = array();

		foreach ( $labels as $style => $label ) {
			$choices[ $style ] = array(
				'label'   => $label,
				'preview' => SnapCart_Icons::svg( $style, 26, 'snapcart-card-option__icon' ),
			);
		}

		$this->radio_cards(
			'icon_style',
			$choices,
			__( 'Outline icons drawn as inline SVG, so they stay crisp at any size and add nothing to load.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Icon size.
	 *
	 * @return void
	 */
	public function field_icon_size() {
		$this->number_input( 'icon_size', 16, 48, __( 'pixels', 'snapcart-for-woocommerce' ) );
	}

	/**
	 * Count badge position.
	 *
	 * @return void
	 */
	public function field_badge_position() {
		$this->select(
			'badge_position',
			array(
				'right' => __( 'Top right of the icon', 'snapcart-for-woocommerce' ),
				'left'  => __( 'Top left of the icon', 'snapcart-for-woocommerce' ),
			)
		);
	}

	/**
	 * Count badge colours.
	 *
	 * @return void
	 */
	public function field_badge_colors() {
		$swatches = array(
			'badge_bg'     => array( __( 'Background', 'snapcart-for-woocommerce' ), '#000000' ),
			'badge_color'  => array( __( 'Number', 'snapcart-for-woocommerce' ), '#ffffff' ),
			'badge_border' => array( __( 'Border', 'snapcart-for-woocommerce' ), '#ffffff' ),
		);

		echo '<div class="snapcart-color-pair">';

		foreach ( $swatches as $key => $swatch ) {
			echo '<span class="snapcart-color-pair__item"><span class="snapcart-color-pair__label">' . esc_html( $swatch[0] ) . '</span>';
			$this->color_input( $key, $swatch[1] );
			echo '</span>';
		}

		echo '</div>';

		echo '<p class="snapcart-stack snapcart-stack--inline">';
		echo '<span class="snapcart-stack__label">' . esc_html__( 'Border opacity', 'snapcart-for-woocommerce' ) . '</span>';
		$this->number_input( 'badge_border_opacity', 0, 100, '%' );
		echo '</p>';

		$this->description( __( 'The border is always 1px and solid. Clear its colour to remove it altogether. Clear a background or number colour to inherit your theme instead.', 'snapcart-for-woocommerce' ) );
	}

	/**
	 * Cart icon colour.
	 *
	 * @return void
	 */
	public function field_icon_color() {
		$this->color_input( 'icon_color', '#ffffff' );
		$this->description( __( 'White suits a dark header. Clear this field to inherit your header text colour instead, which is the safer choice if your header is light or changes colour on scroll.', 'snapcart-for-woocommerce' ) );
	}

	/**
	 * Whether to hide the badge at zero.
	 *
	 * @return void
	 */
	public function field_hide_empty_badge() {
		$this->toggle(
			'hide_empty_badge',
			__( 'Hide the number when the cart is empty', 'snapcart-for-woocommerce' ),
			__( 'Most stores prefer a clean icon until the first item is added.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Icon link destination.
	 *
	 * @return void
	 */
	public function field_link_target() {
		$this->select(
			'link_target',
			array(
				'cart'     => __( 'The cart page', 'snapcart-for-woocommerce' ),
				'checkout' => __( 'Straight to checkout', 'snapcart-for-woocommerce' ),
			),
			__( 'Sending shoppers straight to checkout shortens the path to purchase, but removes their chance to review the cart first.', 'snapcart-for-woocommerce' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Fields — confirmation
	|--------------------------------------------------------------------------
	*/

	/**
	 * Enable the confirmation.
	 *
	 * @return void
	 */
	public function field_enable_popup() {
		$this->toggle(
			'enable_popup',
			__( 'Show a confirmation when a shopper adds an item', 'snapcart-for-woocommerce' ),
			__( 'This does not affect the cart icon and live count.', 'snapcart-for-woocommerce' ),
			'snapcart-enable-popup'
		);
	}

	/**
	 * Confirmation appearance.
	 *
	 * @return void
	 */
	public function field_popup_style() {
		$this->radio_cards(
			'popup_style',
			array(
				'center' => array(
					'label'   => __( 'Centred dialog', 'snapcart-for-woocommerce' ),
					'preview' => '<span class="snapcart-preview snapcart-preview--center"><span class="snapcart-preview__card"></span></span>',
				),
				'toast'  => array(
					'label'   => __( 'Corner notice', 'snapcart-for-woocommerce' ),
					'preview' => '<span class="snapcart-preview snapcart-preview--toast"><span class="snapcart-preview__card"></span></span>',
				),
			),
			__( 'The centred dialog dims the page and takes keyboard focus, so it is hard to miss. The corner notice never interrupts browsing, which suits stores where shoppers add several items in a row.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Confirmation headline.
	 *
	 * @return void
	 */
	public function field_popup_heading() {
		$this->text_input(
			'popup_heading',
			__( 'Added to your cart', 'snapcart-for-woocommerce' ),
			__( 'Speak to the shopper. Leave blank to use the default wording.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Confirmation supporting text.
	 *
	 * @return void
	 */
	public function field_popup_message() {
		$this->text_input(
			'popup_message',
			__( 'Saved to your cart and ready whenever you are.', 'snapcart-for-woocommerce' ),
			__( 'One short line under the headline. Leave blank to use the default wording.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Button wording.
	 *
	 * @return void
	 */
	public function field_popup_buttons() {
		echo '<p class="snapcart-stack">';
		echo '<span class="snapcart-stack__label">' . esc_html__( 'Main button', 'snapcart-for-woocommerce' ) . '</span>';
		$this->text_input( 'popup_primary_label', __( 'View cart', 'snapcart-for-woocommerce' ) );
		echo '</p>';

		echo '<p class="snapcart-stack">';
		echo '<span class="snapcart-stack__label">' . esc_html__( 'Second button', 'snapcart-for-woocommerce' ) . '</span>';
		$this->text_input( 'popup_secondary_label', __( 'Keep shopping', 'snapcart-for-woocommerce' ) );
		echo '</p>';
	}

	/**
	 * Which product details to include.
	 *
	 * @return void
	 */
	public function field_popup_details() {
		$this->toggle( 'popup_show_image', __( 'Show the product photo', 'snapcart-for-woocommerce' ) );
		$this->toggle( 'popup_show_price', __( 'Show the price', 'snapcart-for-woocommerce' ) );
		$this->description( __( 'Prices follow your tax display settings and any active sale price.', 'snapcart-for-woocommerce' ) );
	}

	/**
	 * Corner radius of the confirmation itself.
	 *
	 * @return void
	 */
	public function field_popup_radius() {
		$this->radio_cards(
			'popup_radius',
			array(
				'sharp' => $this->radius_choice( __( 'Sharp', 'snapcart-for-woocommerce' ), 'sharp' ),
				'soft'  => $this->radius_choice( __( 'Soft', 'snapcart-for-woocommerce' ), 'soft' ),
				'round' => $this->radius_choice( __( 'Rounded', 'snapcart-for-woocommerce' ), 'round' ),
			),
			__( 'Also applied to the product row, thumbnails and suggestion cards, so the whole confirmation stays consistent.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Corner radius of the confirmation buttons.
	 *
	 * @return void
	 */
	public function field_button_radius() {
		$this->radio_cards(
			'button_radius',
			array(
				'sharp' => $this->radius_choice( __( 'Sharp', 'snapcart-for-woocommerce' ), 'sharp' ),
				'soft'  => $this->radius_choice( __( 'Soft', 'snapcart-for-woocommerce' ), 'soft' ),
				'pill'  => $this->radius_choice( __( 'Pill', 'snapcart-for-woocommerce' ), 'pill' ),
			),
			__( 'Applies to both buttons. Match whatever your theme does with its own buttons.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Build one card for a corner radius picker.
	 *
	 * @param string $label   Visible label.
	 * @param string $variant Preview modifier: sharp, soft, round or pill.
	 * @return array
	 */
	private function radius_choice( $label, $variant ) {
		return array(
			'label'   => $label,
			'preview' => '<span class="snapcart-radius snapcart-radius--' . esc_attr( $variant ) . '"></span>',
		);
	}

	/**
	 * Accent colour.
	 *
	 * @return void
	 */
	public function field_accent_color() {
		$swatches = array(
			'accent_color'      => array( __( 'Background', 'snapcart-for-woocommerce' ), '#000000' ),
			'accent_text_color' => array( __( 'Label', 'snapcart-for-woocommerce' ), '#ffffff' ),
		);

		echo '<div class="snapcart-color-pair">';

		foreach ( $swatches as $key => $swatch ) {
			echo '<span class="snapcart-color-pair__item"><span class="snapcart-color-pair__label">' . esc_html( $swatch[0] ) . '</span>';
			$this->color_input( $key, $swatch[1] );
			echo '</span>';
		}

		echo '</div>';

		$this->description( __( 'Applies to the main button. Clear the label colour and SnapCart picks black or white for you, whichever reads better on the background you chose.', 'snapcart-for-woocommerce' ) );
	}

	/**
	 * Auto-dismiss delay.
	 *
	 * @return void
	 */
	public function field_autodismiss() {
		$this->number_input(
			'autodismiss_seconds',
			0,
			60,
			__( 'seconds', 'snapcart-for-woocommerce' ),
			__( 'Set to 0 to leave it on screen until the shopper closes it. The countdown pauses while they hover or use the confirmation.', 'snapcart-for-woocommerce' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Fields — suggestions
	|--------------------------------------------------------------------------
	*/

	/**
	 * Enable suggestions.
	 *
	 * @return void
	 */
	public function field_enable_recommendations() {
		$this->toggle(
			'enable_recommendations',
			__( 'Suggest more products inside the confirmation', 'snapcart-for-woocommerce' ),
			'',
			'snapcart-enable-rec'
		);
	}

	/**
	 * Suggestions heading.
	 *
	 * @return void
	 */
	public function field_recommend_heading() {
		$this->text_input(
			'recommend_heading',
			__( 'You might also like', 'snapcart-for-woocommerce' ),
			__( 'Leave blank to use the default wording.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Suggestion source.
	 *
	 * @return void
	 */
	public function field_recommend_source() {
		$this->select(
			'recommend_source',
			array(
				'related'     => __( 'Related to what they just added', 'snapcart-for-woocommerce' ),
				'featured'    => __( 'Featured products', 'snapcart-for-woocommerce' ),
				'bestselling' => __( 'Best sellers', 'snapcart-for-woocommerce' ),
				'recent'      => __( 'Newest products', 'snapcart-for-woocommerce' ),
				'handpicked'  => __( 'Products you choose', 'snapcart-for-woocommerce' ),
			),
			__( 'Related products use the categories and tags shared with the item just added, so the suggestions stay relevant.', 'snapcart-for-woocommerce' ),
			'snapcart-rec-source'
		);
	}

	/**
	 * Hand-picked products.
	 *
	 * @return void
	 */
	public function field_recommend_products() {
		$selected = array_filter( array_map( 'absint', (array) SnapCart_Options::get( 'recommend_products' ) ) );

		printf(
			'<select class="wc-product-search" multiple="multiple" name="%1$s[]" data-placeholder="%2$s" data-action="woocommerce_json_search_products_and_variations">',
			esc_attr( $this->name( 'recommend_products' ) ),
			esc_attr__( 'Search for a product…', 'snapcart-for-woocommerce' )
		);

		foreach ( $selected as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product instanceof WC_Product ) {
				printf(
					'<option value="%1$d" selected="selected">%2$s</option>',
					(int) $product_id,
					esc_html( wp_strip_all_tags( $product->get_formatted_name() ) )
				);
			}
		}

		echo '</select>';

		$this->description( __( 'Shown in the order you add them. Only used when "Products you choose" is selected above.', 'snapcart-for-woocommerce' ) );
	}

	/**
	 * How many suggestions.
	 *
	 * @return void
	 */
	public function field_recommend_count() {
		$this->number_input(
			'recommend_count',
			1,
			12,
			__( 'products', 'snapcart-for-woocommerce' ),
			__( 'The strip scrolls sideways, so four to six keeps it readable on a phone.', 'snapcart-for-woocommerce' )
		);
	}

	/**
	 * Whether suggestions show prices.
	 *
	 * @return void
	 */
	public function field_recommend_show_price() {
		$this->toggle( 'recommend_show_price', __( 'Show a price under each suggestion', 'snapcart-for-woocommerce' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Fields — data
	|--------------------------------------------------------------------------
	*/

	/**
	 * Whether to remove settings on uninstall.
	 *
	 * @return void
	 */
	public function field_delete_data() {
		$this->toggle(
			'delete_data',
			__( 'Delete SnapCart settings when the plugin is deleted', 'snapcart-for-woocommerce' ),
			__( 'Left off, your settings survive a deactivate and reinstall. SnapCart never touches your products, orders or customers either way.', 'snapcart-for-woocommerce' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Page
	|--------------------------------------------------------------------------
	*/

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SnapCart settings.', 'snapcart-for-woocommerce' ) );
		}
		?>
		<div class="wrap snapcart-admin">
			<h1 class="snapcart-admin__title">
				<?php esc_html_e( 'SnapCart', 'snapcart-for-woocommerce' ); ?>
				<span class="snapcart-admin__badge">v<?php echo esc_html( SNAPCART_VERSION ); ?></span>
			</h1>
			<p class="snapcart-admin__tagline">
				<?php esc_html_e( 'A cart icon that counts itself, and a confirmation that keeps shoppers moving instead of bouncing them to the cart page.', 'snapcart-for-woocommerce' ); ?>
			</p>
			<hr class="wp-header-end" />

			<?php settings_errors(); ?>

			<div class="snapcart-admin__grid">
				<div class="snapcart-admin__main">
					<form action="options.php" method="post">
						<?php
						settings_fields( self::OPTION_GROUP );
						$this->render_tabs();
						$this->render_panels();
						submit_button( __( 'Save changes', 'snapcart-for-woocommerce' ) );
						?>
					</form>
				</div>

				<div class="snapcart-admin__side">
					<?php $this->render_setup_card(); ?>
					<?php $this->render_notes_card(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the tab strip.
	 *
	 * Uses WordPress's own `nav-tab` markup, so it inherits core admin styling
	 * and looks native on every colour scheme without us shipping a theme for it.
	 *
	 * @return void
	 */
	private function render_tabs() {
		echo '<nav class="nav-tab-wrapper snapcart-tabs" role="tablist">';

		$first = true;

		foreach ( $this->tabs as $id => $tab ) {
			printf(
				'<button type="button" class="nav-tab%1$s" id="snapcart-tab-%2$s" role="tab" aria-controls="snapcart-panel-%2$s" aria-selected="%3$s" tabindex="%4$d" data-panel="%2$s">%5$s</button>',
				$first ? ' nav-tab-active' : '',
				esc_attr( $id ),
				$first ? 'true' : 'false',
				$first ? 0 : -1,
				esc_html( $tab['label'] )
			);

			$first = false;
		}

		echo '</nav>';
	}

	/**
	 * Render one panel per registered section.
	 *
	 * Every panel is present in the page and inactive ones are hidden with CSS,
	 * so a single save submits every setting no matter which tab is open.
	 *
	 * @return void
	 */
	private function render_panels() {
		$first = true;

		foreach ( $this->tabs as $id => $tab ) {
			printf(
				'<div class="snapcart-panel%1$s" id="snapcart-panel-%2$s" role="tabpanel" aria-labelledby="snapcart-tab-%2$s"%3$s>',
				$first ? ' is-active' : '',
				esc_attr( $id ),
				$first ? '' : ' hidden'
			);

			printf( '<h2 class="snapcart-panel__title">%s</h2>', esc_html( $tab['title'] ) );
			printf( '<p class="snapcart-panel__intro">%s</p>', esc_html( $tab['description'] ) );

			if ( '' !== $tab['notice'] ) {
				printf(
					'<p class="snapcart-panel__notice" data-requires="popup" hidden>%s</p>',
					esc_html( $tab['notice'] )
				);
			}

			echo '<table class="form-table" role="presentation">';
			do_settings_fields( self::PAGE_SLUG, 'snapcart_' . $id );
			echo '</table>';

			echo '</div>';

			$first = false;
		}
	}

	/**
	 * "Add the icon" sidebar card.
	 *
	 * @return void
	 */
	private function render_setup_card() {
		?>
		<div class="snapcart-card">
			<h2 class="snapcart-card__title"><?php esc_html_e( 'Add the icon to your header', 'snapcart-for-woocommerce' ); ?></h2>

			<p><?php esc_html_e( 'Using a block theme or the site editor: search the block inserter for "Snap Cart".', 'snapcart-for-woocommerce' ); ?></p>

			<p><?php esc_html_e( 'Using a classic theme or a header builder: drop this shortcode into any HTML, shortcode or widget area.', 'snapcart-for-woocommerce' ); ?></p>

			<div class="snapcart-copy">
				<input type="text" class="snapcart-copy__input" id="snapcart-shortcode" value="[snapcart_icon]" readonly />
				<button type="button" class="button snapcart-copy__btn" data-target="snapcart-shortcode">
					<?php esc_html_e( 'Copy', 'snapcart-for-woocommerce' ); ?>
				</button>
			</div>

			<p class="description">
				<?php
				printf(
					/* translators: %s: shortcode example including a label attribute. */
					esc_html__( 'To show wording beside the icon, use %s.', 'snapcart-for-woocommerce' ),
					'<code>[snapcart_icon label="Cart"]</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * "Good to know" sidebar card.
	 *
	 * @return void
	 */
	private function render_notes_card() {
		$notes = array(
			__( 'The count updates the moment a shopper adds something on a shop or category page, with no page reload.', 'snapcart-for-woocommerce' ),
			__( 'On a single product page most themes reload the page. The confirmation appears straight after the reload.', 'snapcart-for-woocommerce' ),
			__( 'If your store is set to redirect to the cart after adding an item, the confirmation is skipped on purpose — the shopper is already looking at their cart.', 'snapcart-for-woocommerce' ),
			__( 'Nothing is added to your pages until it is needed, and no data ever leaves your server.', 'snapcart-for-woocommerce' ),
		);

		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			array_unshift(
				$notes,
				__( 'Heads up: your store currently redirects to the cart after every add, so the confirmation will not appear. Turn that off under WooCommerce → Settings → Products to use it.', 'snapcart-for-woocommerce' )
			);
		}
		?>
		<div class="snapcart-card">
			<h2 class="snapcart-card__title"><?php esc_html_e( 'Good to know', 'snapcart-for-woocommerce' ); ?></h2>
			<ul class="snapcart-notes">
				<?php foreach ( $notes as $note ) : ?>
					<li><?php echo esc_html( $note ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
