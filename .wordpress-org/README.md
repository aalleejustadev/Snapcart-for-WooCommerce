# WordPress.org listing assets

These files are **not** part of the plugin zip. They live in the `assets/`
directory at the top level of the plugin's SVN repository, alongside `trunk/`
and `tags/` — not inside them.

```
snapcart-for-woocommerce/
├── assets/     ← the files below go here
├── tags/
└── trunk/      ← the plugin itself
```

## What you still need to produce

| File | Size | Notes |
| --- | --- | --- |
| `icon-256x256.png` | 256×256 | Shown in search results and the plugin card. |
| `icon-128x128.png` | 128×128 | Fallback for older screens. |
| `banner-1544x500.png` | 1544×500 | Retina header on the plugin page. |
| `banner-772x250.png` | 772×250 | Standard header. |
| `screenshot-1.png` | ≥ 1200px wide | Must match caption 1 in `readme.txt`. |
| `screenshot-2.png` | ≥ 1200px wide | Corner notice style. |
| `screenshot-3.png` | ≥ 1200px wide | Suggested products strip. |
| `screenshot-4.png` | ≥ 1200px wide | Cart icon and count in a header. |
| `screenshot-5.png` | ≥ 1200px wide | The settings screen. |

Screenshot numbering must line up with the `== Screenshots ==` list in
`readme.txt`. `.jpg` is accepted in place of `.png` for all of the above.

Everything you upload must be your own work or GPL-compatible, including any
fonts or stock photography used in the banner.

## Publishing checklist

1. Submit the plugin for review at <https://wordpress.org/plugins/developers/add/>.
   Upload the zip produced by `npm run zip`.
2. Wait for the review email. Approval grants you SVN access.
3. `svn checkout https://plugins.svn.wordpress.org/snapcart-for-woocommerce`
4. Copy the plugin files into `trunk/` and these images into `assets/`.
5. `svn copy trunk tags/2.0.0` — the tag must match `Stable tag` in `readme.txt`.
6. `svn commit -m "Release 2.0.0"`

Releases are driven by `Stable tag`, so a release only goes live once that
value points at a directory that exists under `tags/`.
