# Changelog

All notable changes to Custom Checkout Algeria are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.2] - 2026-10-01

### Added
- Stop desk resolution extracted into `assets/js/cca-desks.js`, a dependency-free pure module, with
  `tests/desk-plan.test.js` covering the partitioning and selection precedence against the real
  Batna fixture. Run with `node tests/desk-plan.test.js` or `npm test`; no npm dependencies.
- When a commune has no desk but its wilaya does, the plugin now offers the wilaya's desks
  instead of dead-ending, each badged with the commune it actually sits in.
- New `assets/js/cca-desks.js` enqueued ahead of `checkout.js`; new i18n strings
  `noCenterInWilaya`, `notInYourCommune` and `useHome`.

### Changed
- A commune with exactly one desk now auto-selects it, so there is nothing left to choose. The same
  rule applies to the wilaya fallback, so a single-desk wilaya behaves consistently either way.
  With more than one desk on offer nothing is preselected and `validate_fields()` still blocks
  submission until the customer picks.
- A desk restored from the WooCommerce session is honoured only while it remains on offer; a stale
  `stopdesk_id` from before a wilaya or commune change is now discarded instead of being kept.

### Fixed
- A desk whose payload carries no `commune_id` is no longer counted as belonging to the customer's
  commune. Previously a `by_commune: false` payload skipped filtering altogether and displayed every
  desk in the wilaya as though it were in the selected commune.

## [1.2.1] - 2026-10-01

### Added
- Readme viewer on the Plugins screen. WordPress only surfaces `readme.txt` for
  plugins installed from the WordPress.org directory, so a self-hosted or zip-installed
  copy showed no documentation at all. `CCA_Admin_Links` now renders the bundled
  `readme.txt` at *Plugins → Readme*.
- `plugin_row_meta` links on the Plugins screen: GitHub, Releases, Changelog and Readme
  (with the current version). Scoped by `plugin_basename()` so other plugins are untouched.
- `plugin_action_links` shortcuts for *Settings*, *Readme*, and *Setup* — the latter
  appears only while the plugin is unconfigured. All gated on `manage_woocommerce`.
- Rewrote `README.md` for GitHub: it documented the pre-Yalidine plugin and described a
  project structure that no longer matched the code.

### Fixed
- `readme.txt` parsing in the new viewer: wrapped list items were split into stray
  paragraphs, `= 1.2.0 =` sub-headings were ignored, and the short description was
  misread as a header field.
- Double-escaped `&middot;` in the readme page version line.
- Markdown links in `readme.txt` are now restricted to `http`, `https` and `mailto`, so
  a `javascript:` or `ftp:` target is left as literal text instead of becoming a link.

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

[1.2.1]: https://github.com/Nassim-sadi/custom-checkout/releases/tag/v1.2.1
[1.2.0]: https://github.com/Nassim-sadi/custom-checkout/releases/tag/v1.2.0
[1.1.0]: https://github.com/Nassim-sadi/custom-checkout/releases/tag/v1.1.0
[1.0.0]: https://github.com/Nassim-sadi/custom-checkout/releases/tag/v1.0.0
