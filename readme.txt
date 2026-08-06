=== OpenLingua ===
Contributors: openlingua
Tags: multilingual, translation, languages, acf, custom post types
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

A community-first multilingual foundation for WordPress.

== Description ==

OpenLingua 0.2 provides:

* Languages and locale switching through the `lang` query parameter.
* Translation relationships for pages, posts and every post type with an editing UI.
* One-click draft creation that copies Gutenberg/classic content, taxonomies and all custom fields, including ACF field values and ACF field-key references.
* A language switcher shortcode: `[openlingua_switcher]`.
* A public PHP API for registered strings and translated post IDs.
* An administration screen for editing strings registered through the PHP API.
* Translation relationships and one-click draft creation for categories, tags and custom taxonomies.
* Pretty language-prefix URLs such as `/en/about/` and `/es/about/` without duplicating WordPress rewrite rules.
* Front-end archive, search and home-page filtering by the active language.
* Automatic redirects to the matching post or term translation when available.
* `hreflang` and `x-default` discovery links for published translations.
* Canonical URL integration for WordPress core, Yoast SEO, Rank Math and SEOPress.
* Read-only REST endpoints for configured languages and public translation relationships.
* Automatic database upgrades and one-time rewrite flushing after version changes.
* Tables prefixed with the site's WordPress database prefix; multisite-safe per site.
* Data preservation on uninstall unless `OPENLINGUA_REMOVE_DATA` is explicitly enabled.

This is an original implementation. It does not include or copy WPML code and does not claim drop-in compatibility with WPML.

== Developer API ==

`OpenLingua\register_string( 'footer_title', 'Contact us', 'theme' );`

`OpenLingua\translate_string( 'footer_title', 'Contact us', 'theme' );`

`OpenLingua\translated_post_id( get_the_ID(), 'es' );`

`OpenLingua\translated_term_id( $term_id, 'es' );`

REST endpoints:

* `GET /wp-json/openlingua/v1/languages`
* `GET /wp-json/openlingua/v1/translations/post/123`
* `GET /wp-json/openlingua/v1/translations/term/45`

== Roadmap ==

0.3: navigation menu synchronization, media translation policy, import/export and translation status.

0.4: WooCommerce and page-builder adapters, translation jobs and pluggable machine-translation providers.

== Changelog ==

= 0.2.0 =

* Added taxonomy-term translations and term metadata copying.
* Added pretty language-prefix URLs and request-prefix detection.
* Added language-aware post archives and translated parent/taxonomy mapping during duplication.
* Added canonical, hreflang and x-default SEO metadata.
* Added read-only languages and translations REST endpoints.
* Added automatic database upgrades and routing tests.

= 0.1.0 =

* Initial multilingual content, ACF metadata, string translation and language-switcher foundation.
