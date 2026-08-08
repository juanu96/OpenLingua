=== OpenLingua ===
Contributors: juanu96
Tags: multilingual, translation, languages, custom post types, localization
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free, community-first multilingual foundation for WordPress.

== Description ==

OpenLingua is free software for managing translated WordPress content from one installation. Core translation features work without an external account.

Stable supported features:

* Languages, locales, pretty `/en/` URLs, query fallback, language switcher, and translated redirects.
* A catalog of more than 60 languages plus custom languages, native names, locales, flags or symbols, and RTL direction.
* Directory, query-parameter, or per-language domain URL modes.
* Configurable public visibility, administration locale, browser redirect, footer switcher, and missing-translation behavior.
* Pages, posts, public custom post types, attachments, categories, tags, and custom taxonomies.
* One-click translation drafts with translated hierarchy and taxonomy mapping.
* Block-level Gutenberg translation for native, nested, reusable, dynamic, and third-party block content.
* Translation workflow statuses and outdated-source detection.
* Per-language menu assignment for registered theme locations.
* Language-aware public archives, searches, canonical URLs, `hreflang`, and `x-default`.
* String registration, plural helper, editing, and optional gettext discovery.
* Pluggable machine-translation providers and a consent-based background job queue.
* Safe baseline WooCommerce product metadata synchronization without duplicating SKUs or sales statistics.
* Portable JSON backup/merge import, REST endpoints, WP-CLI commands, diagnostics, object caching, privacy text, and multisite-safe per-site tables.
* Data preservation on uninstall unless `OPENLINGUA_REMOVE_DATA` is explicitly enabled.

OpenLingua does not send content to a translation service unless an administrator configures a provider and explicitly starts an automatic translation job. See External services below before enabling a provider.

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

== External services ==

OpenLingua can optionally send content selected for automatic translation to one configured provider. These integrations are disabled until an administrator adds their own API credential, selects that provider, and starts a translation job. OpenLingua itself does not create provider accounts, collect provider payments, or receive the submitted content.

Depending on the selected provider, the plugin sends the source and target language identifiers, the human-readable content segments being translated, and an instruction to preserve structural markup. The configured API credential is sent only to that provider for authentication. Provider model-list requests send the credential but no site content. Review the applicable provider terms and privacy policy before use:

* OpenAI API: `https://api.openai.com/v1/models` and `https://api.openai.com/v1/responses`. [Terms](https://openai.com/policies/terms-of-use/) and [Privacy](https://openai.com/policies/privacy-policy/).
* Anthropic API: `https://api.anthropic.com/v1/models` and `https://api.anthropic.com/v1/messages`. [Terms](https://www.anthropic.com/legal/commercial-terms) and [Privacy](https://www.anthropic.com/legal/privacy).
* Google Gemini API: `https://generativelanguage.googleapis.com/v1beta/models` and its `generateContent` endpoint. [Terms](https://ai.google.dev/gemini-api/terms) and [Privacy](https://policies.google.com/privacy).
* Google Cloud Translation API: `https://translation.googleapis.com/language/translate/v2`. [Terms](https://cloud.google.com/terms/) and [Privacy](https://policies.google.com/privacy).

API credentials are stored encrypted in the WordPress options table when OpenSSL is available. Translation jobs and error summaries are stored in the site's database. Site owners remain responsible for obtaining any required consent and for their provider account, billing, retention settings, and data-processing obligations.

== Privacy ==

Without an enabled automatic-translation provider, OpenLingua keeps language relationships, translated strings, settings, workflow status, and jobs in the local WordPress database. It does not include analytics or usage tracking. Administrators can copy the suggested disclosure added by OpenLingua to WordPress's Privacy Policy Guide and adapt it to the site's actual configuration.

Removing the plugin preserves data by default to prevent accidental loss. To request permanent removal during uninstall, define `OPENLINGUA_REMOVE_DATA` as `true` before uninstalling and make a backup first.

== Frequently Asked Questions ==

= Do I need a paid API? =

No. Manual translation works without an external service. Automatic translation requires an account and credential from the provider selected by the site administrator, and that provider may charge for usage.

= Does OpenLingua guarantee search indexing or legal compliance? =

No. OpenLingua outputs language-aware URLs and SEO metadata, but indexing decisions belong to search engines. Site owners must review translated content, privacy disclosures, provider terms, and laws applicable to their site.

= What happens to my translations when I uninstall the plugin? =

They are preserved by default. See the Privacy section for the explicit opt-in removal constant.

== Changelog ==

= 1.8.0 =
* Added a local exact-match translation memory for manual and automatic translations.
* Repeated empty fields are filled from previously saved translations without overwriting existing content.
* Matching repeated fields in the same editor stay synchronized after a translation is entered.
* Added a visual indicator when a value was applied from translation memory.

= 1.7.1 =

* Exclude URL-encoded Divi configuration objects and UUID-based global design metadata from translation fields.
* Expand automatic technical-attribute detection without hiding third-party module titles or content.

= 1.7.0 =

* Discover translatable attributes and body text from third-party Divi modules automatically.
* Recognize registered Divi modules and unknown modules carrying standard Divi metadata.
* Exclude technical settings, URLs, IDs, JSON, styling and dynamic-content payloads.
* Avoid duplicated content from nested carousel and container modules.

= 1.6.2 =

* Register the Global content screen after the OpenLingua parent menu so administrators can access it correctly.

= 1.6.1 =

* Resolve all blocking errors reported by WordPress Plugin Check.
* Replace the dynamic route query with a fully prepared and cached lookup.
* Add persistent cache handling for translation relationships and interface strings.
* Harden JSON imports with uploaded-file, size and file-type validation.
* Clarify nonce handling and field-specific sanitization in the translation editor.

= 1.6.0 =

* Map Gutenberg reusable-block and navigation references to published translations.
* Rewrite internal block links to translated posts while preserving query arguments and fragments.
* Keep external links and missing or unpublished translation destinations unchanged.

= 1.5.0 =

* Add a Global content screen for block templates, template parts, navigation and reusable patterns.
* Resolve published block-template and template-part translations for the requested frontend language.
* Keep source templates as fallback while translations remain unpublished.

= 1.4.0 =

* Apply saved interface translations to gettext output from themes and plugins.
* Support contextual gettext strings and basic singular/plural variants.
* Preserve native WordPress translations as fallback and avoid discovery writes while disabled.

= 1.3.4 =

* Detect the requested language before WordPress renders locale-dependent content such as calendars.
* Keep calendar day, month, and year archive links inside the current language path.

= 1.3.3 =

* Map Gutenberg table headers, cells, and captions as individual translation fields.
* Preserve table rows, sections, attributes, and inline formatting when translations are applied.

= 1.3.2 =

* Keep the current language visible when no translated destination is available.
* Avoid rendering an empty dropdown for content with only one available language.

= 1.3.1 =

* Hide languages without a published translation from switchers on individual content.
* Apply translation availability checks to pages, posts, custom post types, and taxonomy terms.

= 1.3.0 =

* Added block-level Gutenberg extraction and editing without exposing block comments or JSON.
* Added generic support for nested third-party block attributes and dynamic block output.
* Added translated synchronized-pattern reference mapping and automatic-translation integration.

= 1.2.10 =

* Reduced actionable Plugin Check warnings by sanitizing submitted settings and translation fields.
* Prepared dynamic table identifiers with WordPress identifier placeholders.
* Removed obsolete manual text-domain loading; WordPress loads plugin translations automatically.

= 1.2.9 =

* Escaped the translation job action markup reported by Plugin Check.

= 1.2.8 =

* Reworked remaining database reads to use prepared identifier placeholders.
* Replaced discouraged HTML stripping calls with WordPress APIs.

= 1.2.7 =

* Standardized typography in visual translation fields and strengthened section dividers.

= 1.2.6 =

* Added visual dividers between content, custom field, and SEO translation sections.

= 1.2.5 =

* Separated original and translated content into distinct side-by-side panels.

= 1.2.4 =

* Improved spacing between translation field cards.

= 1.2.3 =

* Introduced an original OpenLingua translation workspace design with responsive field cards.

= 1.2.2 =

* Updated database queries to use prepared identifier placeholders.
* Declared compatibility with WordPress 7.0.

= 1.2.1 =

* Added complete distribution licensing and clearer third-party service disclosures.
* Updated release metadata and privacy guidance for directory submission.
* Hardened dynamic shortcode translation requests and validation.

= 1.2.0 =

* Added visual translation editing for builder content and custom fields.
* Added language-aware content, taxonomy, menu, shortcode, SEO, and Theme Builder workflows.
* Added configurable OpenAI, Anthropic, Gemini, and Google Translate providers with queued jobs.
* Added language-switcher presentation controls, diagnostics, and administration usability improvements.

= 1.1.0 =

* Replaced the basic language table with a complete, sectioned language-settings experience.
* Added a built-in language catalog, custom languages, native names, flags, locales, and text direction.
* Added functional directory, query-parameter, and per-language domain routing modes.
* Added configurable switcher content, dropdown/footer output, missing-translation handling, and hidden languages.
* Added administration-language selection, opt-in browser-language redirect, and theme string discovery controls.
* Added domain redirect allowlisting and expanded routing tests.

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
