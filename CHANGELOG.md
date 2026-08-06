# Changelog

## 1.0.0

- Introduces a reusable module registry and explicit module/provider contracts.
- Adds per-language menu assignments, attachment translations, translation statuses, and source-change detection.
- Adds configurable `translate`, `copy`, `copy-once`, and `ignore` policies for ACF and arbitrary post metadata.
- Adds safe WooCommerce product metadata synchronization while excluding unique SKUs and derived sales data.
- Adds pluggable translation providers, background jobs, authenticated REST job creation, failure tracking, and editorial review state.
- Adds optional gettext discovery, plural string helpers, portable JSON backup/merge import, and WP-CLI operations.
- Adds object caching, Site Health diagnostics, privacy disclosure, translation capabilities, and a data-preserving uninstall policy.
- Adds architecture, provider, scope, and operational documentation plus module-level tests.

## 0.9.0

- Added runtime caching, diagnostics, privacy guidance, multisite-safe lifecycle handling, and translator capabilities.

## 0.8.0

- Added portable JSON export and merge import, administrative REST operations, and WP-CLI commands.

## 0.7.0

- Added opt-in gettext discovery, plural string helpers, and string portability.

## 0.6.0

- Added the translation-provider contract and background translation job queue.

## 0.5.0

- Added a defensive WooCommerce product metadata policy and synchronization layer.

## 0.4.0

- Added configurable and filterable metadata policies for ACF, builders, and custom integrations.

## 0.3.0

- Added language-specific menu locations, attachment translation support, editorial statuses, and stale-source detection.

## 0.2.0

### Taxonomy translations

- Adds language and translation relationships for categories, tags, and custom taxonomies with an admin UI.
- Creates term translation drafts through a nonce-protected action and copies descriptions, hierarchy, and term metadata.
- Maps translated parent terms and translated taxonomy assignments when content translations are created.
- Cleans translation relationships when terms are deleted.

### Language routing

- Generates directory-style language URLs such as `/en/` and `/es/`.
- Detects and removes the language prefix before WordPress resolves its native permalink rules.
- Preserves query strings and fragments and leaves external URLs unchanged.
- Redirects mismatched post and term requests to the requested translation, or back to the source-language canonical URL when no translation exists.

### Content queries and SEO

- Filters public main archives, searches, and home queries to the active language.
- Keeps unassigned legacy content visible in the default language.
- Emits `hreflang` alternatives plus `x-default` for published translation groups.
- Supplies language-aware canonical URLs to WordPress core, Yoast SEO, Rank Math, and SEOPress.

### REST and lifecycle

- Adds read-only endpoints for site languages and public post or term translation maps.
- Runs schema upgrades automatically when the plugin version changes.
- Schedules a one-time rewrite flush after upgrades or language-list changes.
- Adds standalone routing tests for prefix insertion, replacement, external URL protection, and request detection.

## 0.1.0

- Introduced the multilingual data model, post and custom-post-type translation workflows, ACF-compatible metadata copying, language switcher, string API, and safe uninstall policy.
