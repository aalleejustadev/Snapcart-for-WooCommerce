# SnapCart for WooCommerce

A live cart icon for your header and a fast add-to-cart confirmation that keeps
shoppers browsing instead of bouncing them to the cart page.

[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)

When a shopper adds something to their cart, two things decide what happens
next: whether they believe it worked, and whether it is easy to carry on
shopping. SnapCart handles both.

---

## Contents

- [Why it exists](#why-it-exists)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Settings](#settings)
- [How it works](#how-it-works)
- [For developers](#for-developers)
- [Building from source](#building-from-source)
- [Project layout](#project-layout)
- [Contributing](#contributing)
- [License](#license)

---

## Why it exists

Most stores do one of two things after an add-to-cart, and both leak sales:

- **Nothing visible happens.** The shopper is not sure it worked, so they click
  again or go to the cart to check.
- **They are redirected to the cart page.** Browsing momentum is gone, and the
  path back to the product they were looking at is not obvious.

SnapCart confirms the add in place, shows exactly what was added, and gives one
clear route onward to the cart and one back to browsing.

## Features

#### For shoppers

- A cart icon with a count that updates the moment the cart changes — adding,
  removing a line on the cart page, or editing a quantity.
- A confirmation showing the product photo, name, quantity and price.
- A main button through to the cart or checkout, and a second that closes the
  confirmation so they keep browsing.
- An optional scrollable strip of suggested products.

#### For store owners

| Area | What you control |
| --- | --- |
| Icon | Five shapes (bag, trolley, basket, tote, handbag), size, color |
| Label | Optional wording beside the icon, on either side, inheriting the icon color |
| Count badge | Position, background, number and border colors, border opacity, hide when empty |
| Destination | Cart page or straight to checkout |
| Confirmation | Centred dialog or corner notice, headline, supporting line, both button labels, auto-close timing, which parts appear, popup and backdrop colors, corner radii, button colors |
| Suggestions | Related, featured, best selling, newest or hand-picked; heading, count, prices, desktop arrows |
| Data | Whether settings are removed when the plugin is deleted |

#### Under the hood

- About 20 KB of CSS and JS, minified, served from your own server. No
  frameworks, no icon fonts, no external requests of any kind.
- Prices are read fresh per request, never cached, so multi-currency,
  tax-by-country and role-based pricing plugins always display correctly.
- Only product IDs are cached, and the cache clears itself when you edit a
  product.
- Pages carrying a confirmation are marked uncacheable, so a full-page cache can
  never serve one shopper's confirmation to somebody else.
- Compatible with High-Performance Order Storage and the Cart and Checkout
  blocks. Works with classic AJAX add-to-cart, standard page reloads, and the
  newer product blocks.
- Accessible: the centred dialog traps focus, closes on <kbd>Esc</kbd> and
  restores focus; the corner notice is announced politely and never steals
  focus; auto-close pauses while a shopper is reading it; reduced-motion and
  high-contrast preferences are respected.
- Translation ready, and right-to-left correct without a second stylesheet.

## Requirements

| | Minimum |
| --- | --- |
| WordPress | 6.5 |
| WooCommerce | 8.0 |
| PHP | 7.4 |

## Installation

### From WordPress.org

Search for **SnapCart** under **Plugins → Add New**, install and activate.

### From a release zip

Download the zip from [Releases](https://github.com/aalleejustadev/snapcart-for-woocommerce/releases),
then **Plugins → Add New → Upload Plugin**.

### From source

```bash
git clone https://github.com/aalleejustadev/snapcart-for-woocommerce.git
cd snapcart-for-woocommerce
npm run build          # generates the minified assets the plugin loads
```

Then symlink or copy the directory into `wp-content/plugins/`.

> The minified assets are committed to the repository, so a plain clone works
> without running the build. You only need it after editing CSS or JS.

## Usage

### Block editor and site editor

Search the block inserter for **Snap Cart** and drop it into your header
template. The only per-block setting is an optional text label; everything else
is set once for the whole site.

### Classic themes and header builders

Paste the shortcode into any HTML, shortcode or widget element:

```text
[snapcart_icon]
```

Overriding the site-wide Label setting for one placement:

```text
[snapcart_icon label="Basket"]
[snapcart_icon show_label="no"]
```

### In a template

```php
<?php echo do_shortcode( '[snapcart_icon]' ); ?>
```

## Settings

Everything lives under **WooCommerce → SnapCart**, split across four tabs. Each
appearance setting sits with the feature it affects rather than in a separate
styling section:

| Tab | Covers |
| --- | --- |
| Cart icon | Shape, size, color, count position and colors, empty-cart behaviour, link destination |
| Confirmation | On/off, centred or corner, all wording, timing, which parts appear, plus popup and button colors and corners |
| Suggestions | Source, heading, how many, prices |
| Data | What happens on deletion |

## How it works

The cart count reaches the browser by whichever route is available, in order of
cost:

1. **WooCommerce cart fragments.** On a classic AJAX add, WooCommerce hands the
   new badge markup straight to the page. No extra request.
2. **A dedicated endpoint.** Cart page removals, quantity edits and cart block
   changes carry no fragments, and many themes let shop owners disable fragments
   for performance. SnapCart listens for those events and asks
   `?wc-ajax=snapcart_count` for the current number, debounced so several events
   from one action cost one request.

The confirmation payload works the same way: it rides along with cart fragments
when they exist, and falls back to `?wc-ajax=snapcart_notify` for add-to-cart
routes that return none, such as the Store API behind the product blocks.

Only identifiers go into the WooCommerce session. Names, prices and images are
resolved when the payload is read, which keeps the add-to-cart request fast and
means a stale session can never serve a stale price.

## For developers

### Filters

| Filter | Purpose |
| --- | --- |
| `snapcart_icon_html` | The rendered cart icon markup |
| `snapcart_icon_url` | Where the icon links |
| `snapcart_icon_label` | The wording shown beside the icon |
| `snapcart_popup_payload` | The confirmation payload before it is sent |
| `snapcart_product_price` | The plain text price shown for a product |
| `snapcart_recommendation_items` | The suggestion cards |
| `snapcart_sanitize_settings` | Settings just before they are stored |
| `snapcart_load_assets` | Return `false` to skip the front-end assets entirely |

```php
// Skip SnapCart's assets on a landing page with no header cart.
add_filter( 'snapcart_load_assets', function ( $load ) {
	return is_page( 'campaign-landing' ) ? false : $load;
} );
```

### Styling

Every color and radius is a CSS custom property, so a theme can restyle
SnapCart without fighting specificity:

```css
:root {
	--snapcart-accent: #0b6b4f;
	--snapcart-accent-contrast: #ffffff;
	--snapcart-badge-bg: #0b6b4f;
	--snapcart-radius: 4px;
	--snapcart-btn-radius: 999px;
}
```

### Settings storage

All settings live in one autoloaded option, `snapcart_settings`, so reading them
on the front end costs nothing beyond the `alloptions` cache WordPress already
loads. Settings from 1.x are migrated automatically on first admin load.

## Building from source

Node 18 or newer. There is no bundler and no dependency tree — the build is two
small scripts.

```bash
npm run build   # minify CSS and JS
npm run pot     # regenerate languages/snapcart-for-woocommerce.pot
npm run zip     # build/snapcart-for-woocommerce.zip, ready for WordPress.org
```

### Releasing

The version appears in six places that must agree — WordPress reads the plugin
header, the update checker reads the readme's stable tag, the block editor reads
the block metadata, and asset cache-busting reads the PHP constant. Editing them
by hand reliably misses one, so use the release script:

```bash
npm run release:patch    # 2.0.1 -> 2.0.2, then rebuild assets and the POT
npm run release:minor    # 2.0.2 -> 2.1.0
npm run release:major    # 2.1.0 -> 3.0.0
npm run version:check    # report any location that has drifted out of step
```

`version:check` exits non-zero when it finds a mismatch, so it works as a CI or
pre-commit gate. After bumping, add a matching `= X.Y.Z =` section to the
changelog in `readme.txt`; WordPress.org shows an empty changelog if the stable
tag has no entry of its own.

Coding standards, if you have PHPCS with WPCS installed:

```bash
composer global require wp-coding-standards/wpcs --dev
phpcs      # uses phpcs.xml.dist
phpcbf     # fix what can be fixed automatically
```

The readable sources ship alongside the minified ones and load whenever
`SCRIPT_DEBUG` is enabled, which keeps the plugin compliant with the
WordPress.org rule that human-readable source is always available.

## Project layout

```text
snapcart-for-woocommerce.php   Bootstrap, constants, WooCommerce compatibility
uninstall.php                  Opt-in cleanup, multisite aware
includes/
  class-snapcart-options.php          Defaults, sanitising, 1.x migration
  class-snapcart-icons.php            The cart icon set, shared front and back
  class-snapcart-format.php           Price and product formatting
  class-snapcart-recommendations.php  Suggestion queries and caching
  class-snapcart-frontend.php         Shortcode, block, fragments, endpoints
  class-snapcart-admin.php            Settings screen
blocks/cart-icon/              Block metadata and no-build editor script
assets/                        Front-end and admin CSS and JS
languages/                     Translation template
tools/                         Build, POT and zip scripts
```

## Contributing

Issues and pull requests are welcome.

- Follow the WordPress Coding Standards; `phpcs.xml.dist` is set up for it.
- Escape on output, sanitise on input, and prefix anything global with
  `snapcart` / `SnapCart` / `SNAPCART`.
- Run `npm run build` and `npm run pot` before committing changes to assets or
  user-facing strings.
- Keep the front-end payload small. It loads on every page of somebody's store.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

WooCommerce is a trademark of Automattic Inc. This plugin is an independent
extension and is not affiliated with or endorsed by Automattic.
