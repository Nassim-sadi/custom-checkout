# Custom Checkout Algeria

[![Release](https://img.shields.io/github/v/release/Nassim-sadi/custom-checkout?label=Release)](https://github.com/Nassim-sadi/custom-checkout/releases/latest)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/requires-WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/requires-WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com)

A WooCommerce checkout built for the Algerian market: **wilaya / commune** instead of a postal
address, **live Yalidine tariffs**, and **pickup from a Yalidine stop desk**.

The default WooCommerce checkout asks for Address 1, Address 2, Company and Postcode — fields
that are meaningless for a local Algerian customer. This plugin removes them and replaces them
with a three-field flow that maps directly onto how Yalidine actually ships.

## Features

- **Simplified fields** — drops Address 1/2, Company and Postcode; one *Full Name* field replaces
  First/Last name.
- **Wilaya & commune** — all 58 wilayas with their communes. Served from the Yalidine API, with a
  bundled static dataset as a fallback when no API credentials are configured.
- **Live delivery pricing** — express home and stop-desk tariffs per commune, plus an oversize
  surcharge computed from the billable weight.
- **Stop-desk pickup** — every Yalidine desk in the chosen commune, with its address. The customer
  must pick one; checkout is blocked if none is selected.
- **Delivery charge at checkout only** — the fee is added on the checkout page, so the cart and the
  sidebar cart always show the plain product total.
- **Survives a refresh** — wilaya, commune, delivery type, stop desk and fee are restored from the
  WooCommerce session, so the customer can hit *Update order* or reload without losing their place.
- **Fewer API calls** — the desk list is fetched **once per wilaya** and filtered in the browser,
  rather than once per commune. Lists are cached for an hour server-side *and* in `sessionStorage`.
- **Setup wizard** — enter your Yalidine credentials and origin wilaya in a guided flow.
- **Order integration** — wilaya, commune, delivery type, stop desk and fee are saved to the order
  and shown on the WooCommerce order screen, with one-click Yalidine parcel creation.
- **Algerian phone validation** — mobile numbers are checked against the `05`, `06`, `07` prefixes.
- **HPOS compatible** and tested with WooCommerce Checkout Blocks **disabled on purpose**, since the
  checkout is rendered from the classic shortcode.

## Requirements

| | |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| WooCommerce | 5.0+ |

A [Yalidine](https://www.yalidine.com/) account is required for live tariffs and stop desks.
Without credentials the checkout still works using the bundled static dataset, but no delivery
price is calculated.

## Installation

1. Download the latest `custom-checkout-algeria.zip` from
   [**Releases**](https://github.com/Nassim-sadi/custom-checkout/releases/latest).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip, and activate it.
   *(Installing the zip is important — it keeps the plugin folder named
   `custom-checkout-algeria`, matching the text domain. Installing a mis-named copy would leave you
   with two plugins.)*
3. Follow the setup wizard, or open **WooCommerce → Custom Checkout** and fill in your Yalidine
   `api_id`, `api_token` and origin wilaya.
4. Choose whether home delivery and/or stop-desk pickup are offered.

After setup, clear the cache once from the settings page if prices look stale.

## How it works

The plugin hooks `woocommerce_checkout_fields` to strip the default address fields and inject
`wilaya`, `commune` and the delivery radios. JavaScript then:

1. Resolves the selected wilaya, and fetches **all** its stop desks in one request.
2. Caches the result for an hour and resolves the desk list client-side (see
   [Stop desk resolution](#stop-desk-resolution)).
3. Posts the selection to WooCommerce's AJAX endpoint to resolve the live delivery fee.

Fees are resolved server-side in `CCA_API::get_price_for_commune()`. Billable weight is
`max(actual, volumetric)`, where volumetric weight is `length × width × height × 0.0002` per item,
and an oversize surcharge is applied to every kilogram above **5 kg**.

`add_fee()` is guarded by `is_checkout()` so the charge never leaks into the cart or the order
received page, while still surviving WooCommerce's checkout AJAX refreshes.

## Stop desk resolution

Yalidine assigns each desk to the commune it is **physically located in**, which is not necessarily
the commune the customer lives in. Concretely, in Batna the desk *"Agence du CHU Route de Tazoult"*
is registered under commune **Batna (501)**, and Tazoult (508) has no desk at all. Selecting
Tazoult therefore finds nothing, even though a desk carrying the name "Tazoult" is one click away.

The desk list is only rendered once a commune is chosen. `assets/js/cca-desks.js` then resolves it
from the single wilaya-wide payload — no extra API call:

| Offered set | Count | Result |
| --- | --- | --- |
| Desks with `commune_id === C` | 1 | Auto-selected, shown as a confirmed card |
| same | more than 1 | The customer picks from the commune's desks |
| none, but the wilaya has desks | any | *"Pas de stop desk dans votre commune…"* plus every desk in the wilaya, each badged with its own commune |
| none at all | 0 | *"Aucun stop desk dans cette wilaya"*, nothing to pick |

Selection precedence is: a desk restored from the WooCommerce session **if it is still on offer**,
otherwise the single offered desk, otherwise nothing — and `validate_fields()` then blocks
submission until the customer chooses.

Two properties keep this safe. Fees are priced from the **customer's** commune
(`CCA_API::get_price_for_commune()`), so the desk choice never changes the amount. Parcel creation
already re-resolves the chosen centre and sends the **desk's** commune
(`CCA_Orders::create_parcels()`), so cross-commune desks were always supported.

A desk whose payload carries no `commune_id` is never counted as being in the customer's commune,
which also fixes an older bug where a `by_commune: false` payload skipped filtering entirely and
displayed every desk in the wilaya as though it were local.

`node tests/desk-plan.test.js` (or `npm test`) covers the partitioning and selection precedence
against the real Batna fixture.

## Frequently asked

**Why is there no delivery cost in the cart?**
The delivery charge can only be calculated once a wilaya and commune are known, which happens at
checkout. The cart deliberately shows the product total alone.

**Can I still use a WooCommerce shipping method?**
Yes. If a shipping method already covers the delivery amount, the Yalidine fee is not added on top
of it.

**Does it work with Checkout Blocks?**
No — by design. The plugin declares `cart_checkout_blocks` incompatible and renders the classic
checkout.

**A commune has no stop desk.**
Yalidine files each desk under the commune it physically sits in, so plenty of communes have none.
Instead of dead-ending, the plugin falls back to every desk in the wilaya and badges each one with
its real commune, so a desk you actually want stays reachable. See
[Stop desk resolution](#stop-desk-resolution).

## Project structure

```
custom-checkout.php            Bootstrap, plugin header, HPOS + blocks declarations
includes/
  class-cca-api.php            Yalidine HTTP client, tariffs, billable weight, caching
  class-cca-checkout.php       Checkout fields, centre AJAX, validation, session state, fees
  class-cca-orders.php         Order meta, admin columns, Yalidine parcel creation
  class-cca-settings.php       Settings storage, admin page, cache purge
  class-cca-setup-wizard.php   Guided setup
  class-cca-admin-links.php    Plugins-screen links and the readme viewer
assets/js/checkout.js          Wilaya/commune/desk rendering, caching, fee refresh
assets/js/cca-desks.js         Pure stop desk resolution (commune / fallback / selection)
assets/js/cities-data.js       Bundled wilaya + commune fallback dataset
templates/                     Admin templates
tests/desk-plan.test.js        Unit tests for cca-desks.js (`npm test`, no dependencies)
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md), or the
[releases page](https://github.com/Nassim-sadi/custom-checkout/releases).

## Contributing

Issues and pull requests are welcome. Please include the WordPress, WooCommerce and PHP versions
from **Dashboard → Updates → Debug Info**, and the plugin version.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---

Developed by [Nassim Studio](https://nassimstudio.com)
