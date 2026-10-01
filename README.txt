=== Custom Checkout Algeria ===
Contributors: nassimstudio
Tags: checkout, woocommerce, algeria, yalidine, delivery
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 11.1
Stable tag: 1.2.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simplified WooCommerce checkout for Algeria: wilaya/commune selection, live Yalidine express tariffs and stop-desk pickup.

== Description ==

Custom Checkout Algeria replaces the default WooCommerce checkout with a short form
built for the Algerian market: wilaya and commune instead of a postal address, live
delivery pricing from Yalidine, and pickup from a Yalidine stop desk.

**Features**

*   **Simplified fields**: drops Address 1/2, Company and Postcode; single "Full Name"
    field instead of First/Last.
*   **Wilaya & commune**: all 58 wilayas and their communes. Served from the Yalidine
    API, with a bundled static dataset used when no API credentials are configured.
*   **Live delivery pricing**: express home and stop-desk tariffs per commune, plus an
    oversize surcharge computed from the billable weight.
*   **Stop-desk pickup**: lists every Yalidine desk in the selected commune with its
    address. The customer must choose one; the order is blocked if none is selected.
*   **Delivery charge only at checkout**: the delivery fee is added on the checkout
    page, so the cart and sidebar cart always show the plain product total.
*   **Checkout that survives a refresh**: the chosen wilaya, commune, delivery type,
    stop desk and fee are restored from the WooCommerce session.
*   **Fewer API calls**: the centre list is fetched once per wilaya and filtered in the
    browser, and lists are cached for an hour on both the server and in the session.
*   **Setup wizard**: enter your Yalidine credentials and choose the origin wilaya.
*   **Order integration**: wilaya, commune, delivery type, stop desk and fee are saved
    to the order and shown in the WooCommerce order screen. A parcel can be created in
    Yalidine with one click.
*   **HPOS compatible** and tested with WooCommerce Blocks disabled, because the
    checkout is rendered from the classic shortcode.

== Installation ==

1.  Upload the `custom-checkout-algeria` folder to `/wp-content/plugins/`, or install
    the plugin zip through *Plugins > Add New > Upload Plugin*.
2.  Activate the plugin. WooCommerce must be active, otherwise activation is blocked.
3.  Follow the setup wizard, or go to *Settings > Custom Checkout Algeria* and enter:
    *   your Yalidine `api_id` and `api_token`,
    *   the origin wilaya you ship from.
4.  Choose whether home delivery and/or stop-desk pickup are offered.

== Frequently Asked Questions ==

= Do I need a Yalidine account? =

Yes, for live tariffs and stop desks. Without credentials the checkout still works and
falls back to the bundled static wilaya/commune dataset, but no delivery price is
calculated.

= Why is there no delivery cost in the cart? =

The delivery charge is calculated only once a wilaya and commune are known, which
happens at checkout. The cart shows the product total alone.

= Can I still use a WooCommerce shipping method? =

Yes. If a shipping method already covers the delivery amount, the Yalidine delivery
fee is not added on top of it.

= Does it work with the Checkout Blocks? =

No. The plugin renders the classic checkout, and declares Checkout Blocks
incompatible on purpose.

== Changelog ==

= 1.2.2 =

*   Yalidine files a desk under the commune it physically sits in, so many communes have
    none. A commune with no desk now falls back to the desks of its wilaya instead of
    stopping the customer, and each offered desk is badged with its real commune. In
    Batna, for example, "Agence du CHU Route de Tazoult" belongs to Batna, not Tazoult.
*   A commune with exactly one desk now selects it automatically, so there is nothing
    left to choose.
*   A stop desk remembered from a previous visit is restored only while it is still
    offered; a stale choice from before a wilaya or commune change is discarded.
*   Desks with no commune attached are no longer treated as being in the selected
    commune, which could previously show every desk in the wilaya as though it were local.

= 1.2.1 =

*   The readme is now shown on the Plugins screen. WordPress only renders readme.txt for
    plugins installed from the WordPress.org directory, so a zip-installed copy had no
    documentation. Use *Plugins -> Readme*, or the GitHub, Releases and Changelog links
    next to the plugin row.
*   Settings and Readme shortcuts next to Activate / Deactivate.
*   Updated the GitHub README, which still described the plugin before the Yalidine
    integration.

= 1.2.0 =

*   Checkout selection is restored after a refresh.
*   Delivery fee is now applied on the checkout page only and no longer leaks into the
    cart total.
*   Delivery type restore fixed (session stores "stopdesk", radios use "desk").
*   Centre lists are fetched once per wilaya instead of once per commune, and cached in
    the session for an hour.
*   Stop desks show their address, and a commune without a desk shows an explicit
    message instead of silently selecting the first desk.
*   The setup wizard is styled correctly.
*   Cache purge now clears object-cache entries as well as database rows.

== Upgrade Notice ==

= 1.2.2 =
Communes without a stop desk now offer the wilaya's desks instead of blocking checkout.

= 1.2.1 =
Documentation is now reachable from the Plugins screen.

= 1.2.0 =
Delivery fees are no longer added to the cart; they appear at checkout only.
