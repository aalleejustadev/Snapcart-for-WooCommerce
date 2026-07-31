=== SnapCart for WooCommerce ===
Contributors: alimurtaza
Tags: woocommerce, cart, add to cart, mini cart, cart icon
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.0
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

* **Icon** — shopping bag, trolley or basket, at the size and colour you want. Clear the colour and the icon inherits your header instead, so it stays legible on light and dark themes alike.
* **Count badge** — position it left or right, set its background, number and border colours, choose the border's opacity, or hide the badge while the cart is empty.
* **Destination** — send the icon to the cart page or straight to checkout.
* **Confirmation style** — a centred dialog that commands attention, or a corner notice that never interrupts browsing.
* **Corners** — square off the confirmation and its buttons, or round them, to match your theme.
* **Every word** — headline, supporting line and both button labels are yours to write.
* **Button colour** — pick any colour; the label switches between black and white automatically so it stays readable.
* **Timing** — close after a set number of seconds, or leave it until the shopper closes it.
* **Suggestions** — off by default. Choose products related to what was just added, featured products, best sellers, newest products, or a list you pick by hand.

= Built to stay out of the way =

* **Light.** About 6 KB of CSS and 7 KB of JavaScript, minified, loaded from your own server. No frameworks, no font downloads, no external requests of any kind.
* **Fast.** Suggested product IDs are cached, and the cache is cleared automatically when you edit a product. Turn suggestions off and no product data is queried at all.
* **Correct prices, always.** Prices are read fresh on every request rather than cached, so multi-currency, tax-by-country and role-based pricing plugins all display the right figure.
* **Cache friendly.** Pages that carry a confirmation are marked as uncacheable, so a full-page cache can never serve one shopper's confirmation to somebody else.
* **Accessible.** The centred dialog traps keyboard focus, closes on Escape and returns focus where it started. The corner notice is announced politely and never steals focus. Auto-close pauses while a shopper is reading or using it. Reduced-motion and high-contrast preferences are respected.
* **Translation ready**, and right-to-left languages are supported without a second stylesheet.
* **Compatible** with WooCommerce High-Performance Order Storage and the Cart and Checkout blocks, and works with classic AJAX add-to-cart, standard page reloads and the newer product blocks alike.

= Adding the icon =

Block and site editor themes: search the inserter for **Cart Icon**.

Classic themes and header builders: use the shortcode.

`[snapcart_icon]`

`[snapcart_icon label="Cart"]`

= Privacy =

SnapCart does not collect, store or transmit any personal data. It makes no external requests, sets no cookies of its own, and adds no tracking. It reads WooCommerce's existing cart session purely to know which product was just added.

== Installation ==

1. Go to **Plugins → Add New → Upload Plugin** and upload the zip, or search for "SnapCart" from **Plugins → Add New**.
2. Activate SnapCart. WooCommerce must be installed and active.
3. Add the cart icon to your header — search the block inserter for **Cart Icon**, or paste the `[snapcart_icon]` shortcode into a header, widget or HTML element.
4. Adjust everything else under **WooCommerce → SnapCart**.

The confirmation is on as soon as you activate, so step 4 is optional.

== Frequently Asked Questions ==

= Do I need to know how to code? =

No. Add one block or one shortcode to your header, and set the rest from the settings screen.

= Will it work with my theme? =

Yes. The icon takes its colour and size from the surrounding header, and the confirmation is styled independently of your theme, so it looks the same everywhere. If you want to fine-tune it, every colour is a CSS custom property such as `--snapcart-accent` that you can override in Additional CSS.

= The count does not update until I refresh the page =

Live updating relies on WooCommerce's AJAX add-to-cart, which applies to shop and category pages. Turn it on under **WooCommerce → Settings → Products → Enable AJAX add to cart buttons on archives**. On a single product page most themes reload the page instead, and the count is correct after the reload.

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

= 2.0.0 =
* New: choose between a centred dialog and a corner notice for the confirmation.
* New: a **Cart Icon** block for block themes and the site editor.
* New: pick the icon shape — shopping bag, trolley or basket — its size and its colour.
* New: set the count badge position, background, number and border colours, and hide it while the cart is empty.
* New: the count badge border has its own colour and opacity, defaulting to white at 50%.
* New: point the cart icon at the cart page or straight at checkout.
* New: edit the headline, supporting line and both button labels.
* New: a Styling tab with corner options for the confirmation and its buttons — sharp, soft, rounded or pill.
* New: set the main button colour, with the label colour chosen automatically for contrast.
* New: suggest products related to the item just added, alongside featured, best selling, newest and hand-picked.
* New: choose whether the confirmation shows the product photo and price.
* New: opt in to removing your settings when the plugin is deleted.
* Fixed: variable product prices no longer append WooCommerce's screen-reader text, which showed as "Price range: … through …" beside the price.
* Fixed: a confirmation could be swallowed when WooCommerce refreshed its cart fragments during an ordinary page load.
* Fixed: an add-to-cart made through the WooCommerce product blocks now shows a confirmation.
* Fixed: the icon colour was hardcoded and could disappear on light headers; it is now a setting, and clearing it makes the icon follow your header colour.
* Fixed: shop managers could open the settings screen but not save it.
* Improved: prices are no longer cached, so multi-currency and tax-by-country plugins always display correctly.
* Improved: only product IDs are cached, and the cache clears itself when you edit a product.
* Improved: pages carrying a confirmation are marked uncacheable for full-page caches.
* Improved: keyboard focus is trapped inside the centred dialog and restored on close; the corner notice is announced politely without taking focus.
* Improved: auto-close pauses while a shopper is hovering or using the confirmation.
* Improved: settings moved into a single database row and migrated automatically from 1.x.
* Improved: assets are minified and the script is deferred.
* Improved: right-to-left layouts, reduced-motion and high-contrast preferences are all handled.

= 1.1.0 =
* Added an optional product suggestions strip in the popup, with cached queries.
* Added customisable popup and suggestions headings.
* Redesigned the settings screen.

= 1.0.0 =
* Initial release: cart icon shortcode with a live count, and the add-to-cart popup.

== Upgrade Notice ==

= 2.0.0 =
A substantial update. Fixes variable product prices showing WooCommerce's screen-reader text, adds a Cart Icon block, a corner notice style, full control over wording and colours, and related-product suggestions. Your 1.x settings are migrated automatically.
