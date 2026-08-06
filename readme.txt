=== OpenLingua ===
Contributors: openlingua
Tags: multilingual, translation, languages, acf, custom post types, woocommerce
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A free, community-first multilingual foundation for WordPress.

== Description ==

OpenLingua 1.0 is an original GPL implementation. It does not include WPML code and is not a drop-in WPML replacement.

Stable supported features:

* Languages, locales, pretty `/en/` URLs, query fallback, language switcher, and translated redirects.
* Pages, posts, public custom post types, attachments, categories, tags, and custom taxonomies.
* One-click translation drafts with translated hierarchy and taxonomy mapping.
* Gutenberg/classic content and configurable metadata policies for ACF and page-builder data.
* Translation workflow statuses and outdated-source detection.
* Per-language menu assignment for registered theme locations.
* Language-aware public archives, searches, canonical URLs, `hreflang`, and `x-default`.
* String registration, plural helper, editing, and optional gettext discovery.
* Pluggable machine-translation providers and a consent-based background job queue.
* Safe baseline WooCommerce product metadata synchronization without duplicating SKUs or sales statistics.
* Portable JSON backup/merge import, REST endpoints, WP-CLI commands, diagnostics, object caching, privacy text, and multisite-safe per-site tables.
* Data preservation on uninstall unless `OPENLINGUA_REMOVE_DATA` is explicitly enabled.

OpenLingua does not send content to an external translation service unless a separate provider is installed, configured, and explicitly selected for a job.

== Installation ==

1. Upload and activate OpenLingua.
2. Open OpenLingua in the WordPress administration area.
3. Configure languages and a default language.
4. Edit content or taxonomy terms to create linked translations.
5. Assign navigation menus and custom-field policies where needed.

== Developer API ==

`OpenLingua\register_string( 'footer_title', 'Contact us', 'theme' );`

`OpenLingua\translate_string( 'footer_title', 'Contact us', 'theme' );`

`OpenLingua\translate_plural( 'item', 'items', $count, 'Item', 'Items', 'theme' );`

`OpenLingua\translated_post_id( get_the_ID(), 'es' );`

`OpenLingua\translated_term_id( $term_id, 'es' );`

`OpenLingua\set_menu_translation( 'primary', 'es', $menu_id );`

Provider integrations implement `OpenLingua\Contracts\Translation_Provider` and register through `OpenLingua\register_provider()` or the `openlingua_translation_providers` filter.

REST endpoints:

* `GET /wp-json/openlingua/v1/languages`
* `GET /wp-json/openlingua/v1/translations/post/123`
* `GET /wp-json/openlingua/v1/translations/term/45`
* `POST /wp-json/openlingua/v1/jobs` (authenticated editors)
* `GET /wp-json/openlingua/v1/diagnostics` (administrators)

WP-CLI:

* `wp openlingua languages`
* `wp openlingua export --file=openlingua.json`
* `wp openlingua import --file=openlingua.json`
* `wp openlingua diagnostics`

== Scope and compatibility ==

ACF and builders are supported through metadata policies. Complex components that store remote data or proprietary serialized structures may require a small adapter using the documented filters.

WooCommerce 1.0 support covers translated product content and synchronized operational product metadata. Full multilingual checkout text, variation duplication, order localization, subscriptions, and third-party WooCommerce extensions require dedicated adapters and are not claimed as supported by this release.

== Changelog ==

= 1.0.0 =

* Added modular service registry and public extension contracts.
* Added menus, media, editorial workflow, custom-field policies, and source-change tracking.
* Added safe WooCommerce product metadata policy and synchronization.
* Added translation providers, queued jobs, REST job creation, and cron processing.
* Added string discovery, plural translation, portable JSON import/export, and WP-CLI.
* Added runtime/object caching, Site Health diagnostics, privacy disclosure, capabilities, and expanded tests.

= 0.9.0 =

* Added diagnostics, caching, privacy guidance, multisite-safe lifecycle behavior, and capabilities.

= 0.8.0 =

* Added portable backup/merge import, administrative REST operations, and WP-CLI commands.

= 0.7.0 =

* Added optional string discovery, plural helpers, and string portability.

= 0.6.0 =

* Added provider contracts and a background translation-job queue.

= 0.5.0 =

* Added a safe WooCommerce product metadata integration layer.

= 0.4.0 =

* Added reusable metadata policies for ACF and page-builder fields.

= 0.3.0 =

* Added translation workflow state, menu mapping, and attachment translations.

= 0.2.0 =

* Added taxonomy translations, pretty URLs, REST discovery, language-aware queries, and SEO metadata.

= 0.1.0 =

* Initial multilingual content, custom-field, string, and language-switcher foundation.
