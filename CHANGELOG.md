# Changelog

All notable changes to Custom Checkout Algeria are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-10-01

### Added
- Checkout selection survives a refresh: the WooCommerce session snapshot
  (`CCA_Checkout::session_state()`) is localized as `ccaCheckout.restore` and the
  frontend re-selects wilaya, commune, delivery type, stop desk and fee. Restores on
  `ready` and on `pageshow` (back/forward navigation).
- Stop-desk cards now show the desk address and commune, so two desks in the same
  wilaya are distinguishable.
- Explicit empty state for a commune with no stop desk ("Aucun stop desk dans cette
  commune") plus a hint to fall back to home delivery.
- 1 hour `sessionStorage` cache for the commune and centre lists.
- Wilaya prefetch of the centre list, so switching to stop desk renders instantly.
- `README.txt` (WordPress.org format), this changelog, `.gitattributes` and a GitHub
  Actions workflow that builds a tagged release zip.

### Fixed
- Delivery type was not restored after a refresh. The session stores `stopdesk` while
  the radio inputs use `desk`, so selecting by radio value matched nothing. Delivery
  values are now normalised in the frontend.
- The delivery fee no longer leaks into the cart. `add_fee()` was hooked to
  `woocommerce_cart_calculate_fees` with no page guard, so the session fee left behind
  by a previous checkout was added to the cart and sidebar cart totals on every other
  page. The fee and the shipping-package cache bust are now applied on the checkout
  page only.
- `CCA_API::clear_cache()` now drops the matching object-cache entries. The raw SQL
  delete alone left values readable for the rest of the request and would not clear
  them at all with a persistent cache (Redis/Memcached).
- Yalidine load reduced from one call per commune to one call per wilaya: centres are
  fetched for the whole wilaya and filtered client-side. A per-commune fallback still
  fires if a payload carries no `commune_id`.
- The stop desk is no longer auto-selected. Checkout validation rejects an order that
  has no stop desk selected.
- The onboarding wizard rendered unstyled. `assets/css/admin.css` targeted
  `.yalidine-*` selectors that exist nowhere in the markup; it now uses the `.cca-*`
  classes actually emitted by the templates.
- No `sleep()` on an HTTP 429 from Yalidine; the request returns immediately with a
  retry hint instead of blocking checkout.

### Changed
- Caches: wilayas/communes/centres 1 hour, routes 60 minutes, as before.
- Bumped to 1.2.0.

## [1.1.0] - 2026-06-30

### Added
- Yalidine integration: express home/desk tariffs, oversize surcharge from the
  billable weight, and parcel creation.
- Guided setup wizard plus a settings page.
- Stop-desk selection per commune.
- HPOS compatibility declared, WooCommerce Checkout Blocks disabled in favour of the
  classic shortcode checkout.

### Fixed
- `sleep()` removed from the 429 handler so a rate limit can no longer stall checkout.
- Hidden `cca_wilaya_name` field, cached wilaya loading and a static commune fallback
  when no API credentials are configured.
- `CCA_Settings::is_configured()` used to decide whether the API path is taken.

## [1.0.0] - 2026-06-24

### Added
- Initial release: simplified checkout fields, wilaya/commune selection, Algerian
  phone validation, delivery type selection and admin order integration.

[1.2.0]: https://github.com/Nassim-sadi/custom-checkout/releases/tag/v1.2.0
[1.1.0]: https://github.com/Nassim-sadi/custom-checkout/releases/tag/v1.1.0
[1.0.0]: https://github.com/Nassim-sadi/custom-checkout/releases/tag/v1.0.0
