=== OpenLingua ===
Contributors: openlingua
Tags: multilingual, translation, languages, acf, custom post types
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

A community-first multilingual foundation for WordPress.

== Description ==

OpenLingua 0.1 provides:

* Languages and locale switching through the `lang` query parameter.
* Translation relationships for pages, posts and every post type with an editing UI.
* One-click draft creation that copies Gutenberg/classic content, taxonomies and all custom fields, including ACF field values and ACF field-key references.
* A language switcher shortcode: `[openlingua_switcher]`.
* A public PHP API for registered strings and translated post IDs.
* An administration screen for editing strings registered through the PHP API.
* Tables prefixed with the site's WordPress database prefix; multisite-safe per site.
* Data preservation on uninstall unless `OPENLINGUA_REMOVE_DATA` is explicitly enabled.

This is an original implementation. It does not include or copy WPML code and does not claim drop-in compatibility with WPML.

== Developer API ==

`OpenLingua\register_string( 'footer_title', 'Contact us', 'theme' );`

`OpenLingua\translate_string( 'footer_title', 'Contact us', 'theme' );`

`OpenLingua\translated_post_id( get_the_ID(), 'es' );`

== Roadmap ==

0.2: taxonomy-term translations, REST endpoints, pretty language URLs and hreflang.

0.3: navigation menu synchronization, media translation policy, import/export and translation status.

0.4: WooCommerce and page-builder adapters, translation jobs and pluggable machine-translation providers.
