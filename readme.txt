=== SnapCart for WooCommerce ===
Contributors: iamalimurtazaa
Tags: woocommerce, cart, add to cart, mini cart, cart icon
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A live cart icon for your header and a fast add-to-cart confirmation that keeps shoppers browsing instead of bouncing them to the cart page.

== Description ==

When a shopper adds something to their cart, two things decide what happens next: whether they believe it worked, and whether it is easy to carry on shopping. SnapCart handles both.

It gives you a cart icon with a count that updates the moment something is added, and a confirmation that appears in place — so shoppers are never thrown out of the page they were browsing.

SnapCart is deliberately small. There is no page builder, no dashboard, no account, and nothing is sent anywhere. Two small files load on the front end and that is the whole footprint.

= What your shoppers see =

* A cart icon in your header with a live item count.
* A confirmation the instant they add something, showing the product photo, name, quantity and price.
* A clear main button through to the cart or checkout, and a second button that closes the confirmation so they keep browsing.
* Optionally, a scrollable strip of suggested products to encourage another add.

= What you control =

* **Icon** — five shapes covering how the biggest stores do it: shopping bag, trolley, basket, tote and handbag. Set the size and color, or clear the color to inherit your header so it stays legible on light and dark themes alike.
* **Label** — optionally show wording such as "Cart" beside the icon, on either side. It takes the icon's color automatically, and a shortcode or block can override it in one place.
* **Count badge** — position it left or right, set its background, number and border colors, choose the border's opacity, or hide the badge while the cart is empty.
* **Destination** — send the icon to the cart page or straight to checkout.
* **Confirmation style** — a centred dialog that commands attention, or a corner notice that never interrupts browsing.
* **Corners** — square off the confirmation and its buttons, or round them, to match your theme.
* **Colors** — the popup background and border, the dimmed backdrop behind it and its opacity, and the button background and label.
* **What appears** — switch off the green tick, the supporting line, the product photo or the price. Anything switched off is left out of the page entirely rather than hidden with CSS.
* **Every word** — headline, supporting line and both button labels are yours to write.
* **Button colors** — set the background and the label separately, or clear the label color and let SnapCart pick the readable one.
* **Timing** — close after a set number of seconds, or leave it until the shopper closes it.
* **Suggestions** — off by default. Choose products related to what was just added, featured products, best sellers, newest products, or a list you pick by hand. On desktop the strip gets arrows; on touch it is swiped.

= Built to stay out of the way =

* **Light.** About 12 KB of CSS and 14 KB of JavaScript, minified, loaded from your own server. No frameworks, no font downloads, no external requests of any kind.
* **Fast.** Suggested product IDs are cached, and the cache is cleared automatically when you edit a product. Turn suggestions off and no product data is queried at all.
* **Correct prices, always.** Prices are read fresh on every request rather than cached, so multi-currency, tax-by-country and role-based pricing plugins all display the right figure.
* **Cache friendly.** Pages that carry a confirmation are marked as uncacheable, so a full-page cache can never serve one shopper's confirmation to somebody else.
* **Accessible.** The centred dialog traps keyboard focus, closes on Escape and returns focus where it started. The corner notice is announced politely and never steals focus. Auto-close pauses while a shopper is reading or using it. Reduced-motion and high-contrast preferences are respected.
* **Translation ready**, and right-to-left languages are supported without a second stylesheet.
* **Compatible** with WooCommerce High-Performance Order Storage and the Cart and Checkout blocks, and works with classic AJAX add-to-cart, standard page reloads and the newer product blocks alike.

= Adding the icon =

Block and site editor themes: search the inserter for **Snap Cart**.

Classic themes and header builders: use the shortcode.

`[snapcart_icon]`

`[snapcart_icon label="Basket"]` — overrides the Label setting here only.

`[snapcart_icon show_label="no"]` — hides the label at this placement.

= Privacy =

SnapCart does not collect, store or transmit any personal data. It makes no external requests, sets no cookies of its own, and adds no tracking. It reads WooCommerce's existing cart session purely to know which product was just added.

= Source code =

Every file SnapCart ships is human readable. The minified stylesheet and script are built from `assets/css/snapcart.css` and `assets/js/snapcart.js`, both of which are included in the plugin and are what load when `SCRIPT_DEBUG` is on.

Development happens in the open, and the build script that produces the minified files lives with the source:

https://github.com/aalleejustadev/snapcart-for-woocommerce

= Trademarks =

WooCommerce is a trademark of Automattic Inc. SnapCart for WooCommerce is an independent extension and is not affiliated with, endorsed by, or sponsored by Automattic or the WooCommerce project.

== Installation ==

1. Go to **Plugins → Add New → Upload Plugin** and upload the zip, or search for "SnapCart" from **Plugins → Add New**.
2. Activate SnapCart. WooCommerce must be installed and active.
3. Add the cart icon to your header — search the block inserter for **Snap Cart**, or paste the `[snapcart_icon]` shortcode into a header, widget or HTML element.
4. Adjust everything else under **WooCommerce → SnapCart**.

The confirmation is on as soon as you activate, so step 4 is optional.

== Frequently Asked Questions ==

= Do I need to know how to code? =

No. Add one block or one shortcode to your header, and set the rest from the settings screen.

= Will it work with my theme? =

Yes. Pick the icon's shape, size and color on the settings screen, or clear the color and it follows your header. The confirmation is styled independently of your theme, so it looks the same everywhere. If you want to fine-tune it, every color and corner radius is a CSS custom property such as `--snapcart-accent` that you can override in Additional CSS.

= The count does not update until I refresh the page =

The count refreshes whenever the cart changes, including removing a line or editing a quantity on the cart page, and it keeps working on stores that have switched WooCommerce cart fragments off for performance.

Adding from a shop or category page without a reload does rely on WooCommerce's AJAX add-to-cart, under **WooCommerce → Settings → Products → Enable AJAX add to cart buttons on archives**. On a single product page most themes reload the page instead, and the count is correct after the reload.

= The confirmation never appears =

Check whether **WooCommerce → Settings → Products → Redirect to the cart page after successful addition** is switched on. When it is, shoppers land on the cart page immediately and SnapCart skips the confirmation on purpose. SnapCart tells you on its settings screen when it detects this.

= Will it slow my site down =

It adds one small stylesheet and one deferred script, both served from your own server, with no external requests. Product suggestions are off by default; while they are off, SnapCart runs no product queries at all. With them on, the results are cached and refreshed automatically when you edit a product.

= Does it work with a caching plugin? =

Yes. Any page carrying a confirmation is marked uncacheable, so a cached copy is never served to a different shopper.

= Does it work with variable products? =

Yes. Variable products show a price range, and the variation the shopper actually chose is what appears in the confirmation.

= Does it work with multi-currency or tax-inclusive pricing? =

Yes. Prices are calculated per request using WooCommerce's own display functions, so whatever your currency, tax and pricing plugins decide is what SnapCart shows.

= Can I remove the confirmation and keep just the icon? =

Yes. Turn off the confirmation under **WooCommerce → SnapCart** and the icon with its live count carries on working.

= What happens to my settings if I delete the plugin? =

They are kept, so reinstalling picks up where you left off. If you would rather they were removed, tick the option under **Data** on the settings screen before deleting.

= Can developers change what it renders? =

Yes. Filters are available for the icon markup (`snapcart_icon_html`), its link (`snapcart_icon_url`), the confirmation payload (`snapcart_popup_payload`), the displayed price (`snapcart_product_price`), the suggestion list (`snapcart_recommendation_items`) and whether assets load at all (`snapcart_load_assets`).

== Screenshots ==

1. The add-to-cart confirmation, with the product and a clear route to the cart.
2. The corner notice style, which never interrupts browsing.
3. Suggested products inside the confirmation.
4. The cart icon and live count in a site header.
5. The settings screen under WooCommerce → SnapCart.

== Changelog ==

= 2.0.12 =
* Fixed: themes could bleed their own styling into the confirmation and the cart icon — button padding and uppercase labels, underlines and carets on the icon, heading borders, and squashed product thumbnails on themes that force `img { height: auto }`. Both now render the same on any theme or child theme.
* Fixed: the CSS build step could merge a descendant selector into a compound one, so a rule intended for elements inside the confirmation was applied to the confirmation itself.

= 2.0.11 =
* Fixed: the cart icon color picker still offered white as its "Default", so resetting it disagreed with the black a fresh install actually uses.

= 2.0.10 =
* New: show wording such as "Cart" beside the icon, with a toggle, your own text, and a choice of which side it sits on. It inherits the icon color, so the two always match.
* New: `show_label="yes|no"` shortcode attribute, alongside `label="..."`, so one placement can differ from the site-wide setting.
* Changed: the cart icon now defaults to black rather than white, which suits the light headers most themes ship with.
* Fixed: the item count now stays pinned to the icon when a label is shown, instead of sitting past the end of the wording.
* Fixed: a settings screen script used a jQuery function removed in jQuery 4.

= 2.0.9 =
* Improved: "Settings saved." now appears as a toast in the corner and clears itself after five seconds, pausing if you hover or tab into it. Validation errors still stay put until dismissed.

= 2.0.8 =
* New: a live preview of your cart icon on the settings screen, shown on both a light and a dark header, updating as you change the shape, size, colours and count position.
* Improved: the item count position is now chosen from two small pictures of your own icon rather than a dropdown.

= 2.0.7 =
* Improved: the settings screen is grouped so related options sit together. The backdrop now sits with the layout that produces it, all three button settings share one group, and the cart icon tab separates the icon from its item count.
* Improved: the backdrop setting is hidden when the corner notice is selected, since only the centred dialog draws one.

= 2.0.6 =
* Changed: the settings screen and documentation now use US spelling for "color" throughout.

= 2.0.5 =
* Changed: the confirmation settings tab is now titled "Added to Cart Confirmation".

= 2.0.4 =
* Changed: every label and section title on the settings screen now uses title case.

= 2.0.3 =
* New: choose sharp, soft or circular corners for the suggestion carousel arrows.
* Fixed: hovering a carousel arrow could leave the glyph almost invisible on themes that force a white button hover color. The arrow now fills with your button color and the glyph switches to the contrasting label color.
* Changed: the default second button now reads "Continue shopping" instead of "Keep shopping".
* Changed: settings tab labels are capitalised.

= 2.0.2 =
* Changed: the Styling tab has been removed. Every appearance setting now sits with the feature it affects — icon and count colors under Cart icon, popup and button colors and corners under Added to Cart Confirmation.

= 2.0.1 =
* Maintenance: the plugin version is now kept in step across the plugin header, readme, block metadata and translation template by a single release step, so an update can never ship with mismatched version numbers.

= 2.0.0 =
* New: choose between a centred dialog and a corner notice for the confirmation.
* New: a **Snap Cart** block for block themes and the site editor.
* New: five cart icon shapes — shopping bag, trolley, basket, tote and handbag — with size and color settings.
* New: set the count badge position, background, number and border colors, and hide it while the cart is empty.
* New: the count badge border has its own color and opacity, defaulting to white at 50%.
* New: point the cart icon at the cart page or straight at checkout.
* New: edit the headline, supporting line and both button labels.
* New: corner options for the confirmation and its buttons — sharp, soft, rounded or pill.
* New: color and opacity settings for the dimmed backdrop, plus the popup background and its 1px border.
* New: switch the green tick and the supporting line off individually. Neither is written to the page when off.
* New: arrows on the suggestions strip for desktop visitors, alongside swiping on touch.
* New: separate background and label colors for the main button. Clear the label color and a readable one is chosen for you.
* New: suggest products related to the item just added, alongside featured, best selling, newest and hand-picked.
* New: choose whether the confirmation shows the product photo and price.
* New: opt in to removing your settings when the plugin is deleted.
* Fixed: variable product prices no longer append WooCommerce's screen-reader text, which showed as "Price range: … through …" beside the price.
* Fixed: the count now updates the moment an item is removed or a quantity is changed on the cart page, instead of waiting for a page reload. It also stays correct on stores that have switched WooCommerce cart fragments off for performance.
* Fixed: a confirmation could be swallowed when WooCommerce refreshed its cart fragments during an ordinary page load.
* Fixed: an add-to-cart made through the WooCommerce product blocks now shows a confirmation.
* Fixed: the icon color was hardcoded and could disappear on light headers; it is now a setting, and clearing it makes the icon follow your header color.
* Fixed: shop managers could open the settings screen but not save it.
* Improved: prices are no longer cached, so multi-currency and tax-by-country plugins always display correctly.
* Improved: only product IDs are cached, and the cache clears itself when you edit a product.
* Improved: pages carrying a confirmation are marked uncacheable for full-page caches.
* Improved: keyboard focus is trapped inside the centred dialog and restored on close; the corner notice is announced politely without taking focus.
* Improved: auto-close pauses while a shopper is hovering or using the confirmation.
* Improved: settings moved into a single database row and migrated automatically from 1.x.
* Improved: assets are minified and the script is deferred.
* Improved: right-to-left layouts, reduced-motion and high-contrast preferences are all handled.
* Improved: the confirmation is fully responsive down to the narrowest phones — buttons stack, spacing tightens, and a card taller than the screen scrolls without its top being cut off.
* Improved: text inside the confirmation no longer shrinks on themes that reduce the root font size.
* Improved: the suggestions strip scrolls without showing a scrollbar.
* Changed: the default suggestions heading is now "Discover more".

= 1.1.0 =
* Added an optional product suggestions strip in the popup, with cached queries.
* Added customisable popup and suggestions headings.
* Redesigned the settings screen.

= 1.0.0 =
* Initial release: cart icon shortcode with a live count, and the add-to-cart popup.

== Upgrade Notice ==

= 2.0.12 =
The confirmation and cart icon now hold their own styling on themes that were overriding them. Your settings are unchanged.

= 2.0.11 =
A small settings screen fix. Nothing on your store changes.

= 2.0.10 =
Adds an optional text label beside the cart icon. The icon's default color changes from white to black; if you had set a color yourself it is untouched.

= 2.0.9 =
A settings screen refinement. Nothing on your store changes.

= 2.0.8 =
Adds a live cart icon preview to the settings screen. Nothing on your store changes.

= 2.0.7 =
Settings are grouped more sensibly. Nothing you have configured is lost.

= 2.0.6 =
A spelling change on the settings screen. Nothing on your store changes.

= 2.0.5 =
A settings screen wording change. Nothing on your store changes.

= 2.0.4 =
A wording tidy-up on the settings screen. Nothing on your store changes.

= 2.0.3 =
Fixes a carousel arrow that could become invisible on hover, and adds a corner style for the arrows.

= 2.0.2 =
Settings have moved: the Styling tab is gone and its options now sit with the feature they affect. Nothing you have configured is lost.

= 2.0.1 =
A maintenance release with no changes to how SnapCart behaves on your store.

= 2.0.0 =
A substantial update. Fixes variable product prices showing WooCommerce's screen-reader text, adds a Snap Cart block, a corner notice style, full control over wording and colors, and related-product suggestions. Your 1.x settings are migrated automatically.
