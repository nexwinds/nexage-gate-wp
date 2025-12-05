=== NEXAGE Gate ===
Contributors: nexwinds
Tags: age verification, modal, woocommerce
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

JavaScript-driven modal age verification with WooCommerce integration.

== Description ==
NEXAGE Gate provides a lightweight, JavaScript-driven age verification modal for WordPress. It optionally integrates with WooCommerce to restrict product/category content. Works with caching plugins via minimal CSS/JS and server endpoints.

Key features:
- Modal age check: date entry or yes/no
- Optional "Remember" approval with a cookie
- Path-based restrictions and WooCommerce category rules
- SVG placeholder for restricted product images

== Installation ==
1. Copy the `nexage-gate` folder to `wp-content/plugins/`.
2. In WordPress Admin, go to `Plugins` and activate `NEXAGE Gate`.

== Admin Settings ==
- General Settings: minimum age, scope (`global` or `custom`), method (`date` or `yes/no`), cookie settings
- Text Customization: labels and descriptions for desktop/mobile
- Visual Customization: colors, border, radius, responsive scales, optional logo
- Custom Restrictions: path rules (one per line, `*` wildcard) and WooCommerce categories

== How It Works ==
- Restricted pages without an approved cookie are visually hidden; a modal container `#nexage-gate-root` is added in the footer.
- Assets are registered as `nexage-gate` and localized with `nexageGate` variables.
- The script sets `nexage_gate_access=approved` or `denied`.

== Assets ==
- Source: `assets/src/modal.css`, `assets/src/modal.js`
- Minified: `assets/dist/modal.min.css`, `assets/dist/modal.min.js`
- Minification runs on plugin activation and when saving settings

== Endpoints ==
- `/?nexage_gate_svg=1&age=XX` returns an SVG badge
- `/?nexage_gate_blocked=1` serves a 403 page for denied access

== WooCommerce Integration ==
If WooCommerce is active and categories are selected, restricted product images are replaced with the age SVG until approval.

== Notes ==
- Requires PHP 7.4+
- Author: Nexwinds (https://nexwinds.com)

== Changelog ==
See `changelog.txt`.
