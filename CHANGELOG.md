# Changelog

## 1.18.1

- Replaced platform-dependent emoji flags in setup with a lightweight local SVG subset from the MIT-licensed flag-icons project.
- Aligned preview flags, language labels, and navigation arrows consistently.

## 1.18.0

- Redesigned first-run setup as a spacious, interactive five-step assistant with OpenLingua's own visual language.
- Added searchable language cards, primary-language synchronization, URL structure choices, and translation workflow defaults.
- Added a live selector designer supporting flags, names, native names, inline or dropdown layouts, and footer placement.
- Added a final review screen and clearer progress, navigation, responsive behavior, and completion states.

## 1.17.0

- Add a polished three-step first-install wizard for selecting languages, URL structure, and language-selector appearance.
- Open the wizard automatically on the first OpenLingua visit only for genuinely new installations.
- Preserve uninterrupted access for upgraded and previously configured sites.

## 1.16.0

- Add a guided setup assistant and a centralized behavior settings screen.
- Configure missing translations, initial status, slug generation, copied metadata, and source-change handling.
- Control automatic job batching, retries, monthly limits, result status, and duplicate queue protection.
- Separate translation permissions from notification recipients and honor the configured capability in editing actions.
- Add incomplete-translation indexing controls, timed string discovery, shared attachments in separated media mode, and maintenance actions.
- Preserve data on uninstall by default and require a second explicit confirmation before settings-driven cleanup.

## 1.15.0

- Discover structured visual-builder documents stored in JSON or nested post metadata without hardcoding plugin names.
- Keep translated fields attached to stable builder element IDs when components are reordered.
- Exclude ACF-managed data, URLs, media, styling controls, caches, code, and opaque objects from generic discovery.
- Add a read-only visual-builder field inspector to OpenLingua diagnostics.
- Translate taxonomy SEO fields for supported SEO integrations and filter AIOSEO sitemap entries by language visibility.
- Preview portable imports, report invalid records and conflicts, create a database backup, and offer exact rollback.
- Filter the translation workspace by pending or translated fields, show visible counts, link to WordPress revisions, and cache translation-memory indexes persistently.
- Test PHP 7.4 through 8.3 in CI, build the installable archive, and run the official Plugin Check action against the packaged plugin.
- Add WordPress.org artwork, a refreshed POT catalog, and explicit compatibility documentation.

## 1.14.0

- Create or safely adopt translated WooCommerce variations through a stable source-variation relationship.
- Map global product attribute terms, default attributes, and variation slugs to target-language taxonomy translations.
- Synchronize variation pricing, stock, downloads, dimensions, images, status, and ordering while leaving unique SKUs and global identifiers untouched.
- Add variation descriptions to the manual editor, translation memory, and automatic translation jobs.

## 1.13.0

- Preserve Divi, Gutenberg, and nested ACF translations across deleted, inserted, and reordered source structures.
- Add ordered language fallback chains and contextual translation-memory provenance.
- Translate attachment metadata while continuing to reuse the original media file.
- Add a public content-extractor contract and Elementor JSON extractor, integrated with the manual editor, automatic jobs, and translation memory.
- Make WordPress core, Yoast SEO, Rank Math, and SEOPress sitemap output use translated URLs and exclude hidden languages.
- Recover interrupted automatic translation jobs and expose clearer retry states.
- Expand safe WooCommerce synchronization to operational download, dimension, and related-product data.
- Add deterministic schema migrations and opt-in uninstall cleanup.

## 1.12.0

- Stop exposing translation actions for attachments and reject legacy direct attachment-duplication requests.
- Reuse the same WordPress media IDs and URLs across translated pages in unified mode.
- Hide existing translated attachment copies without deleting their physical files or database records.
- Add an optional separated mode that classifies new uploads by the current administrative content language.
- Filter both the Media Library and AJAX media selectors used by editors and visual builders.
- Preserve an All languages view and make switching media modes reversible.

## 1.11.0

- Store a per-field source snapshot for Divi translations instead of trusting mutable occurrence numbers.
- Detect deleted, inserted, and reordered modules and prevent adjacent target values from shifting upward.
- Recover legacy shifted values through exact unchanged fields and translation-memory ownership.
- Rebuild saved translations on the latest source layout so new sections, carousel images, and technical settings are synchronized automatically.
- Add a regression scenario covering a deleted carousel item, shifted testimonials, and a newly added image.

## 1.10.1

- Never hide an entire shortcode, React widget, slider, or dynamic plugin while unknown strings use the REST fallback.
- Apply preloaded translations directly from the mutation observer before the browser's next paint.
- Keep unknown labels visible until a translation exists, preventing large blank areas that look like broken content.

## 1.10.0

- Preload known shortcode translations in the page response for immediate client-side application.
- Protect target-language dynamic roots until their first translation pass completes, preventing source-language flashes.
- Apply translations synchronously from the server dictionary or short-lived browser cache as JavaScript nodes appear.
- Batch REST fallback requests for new text and avoid observer loops on already translated nodes.

## 1.9.1

- Exclude Divi alignment, orientation, placement, direction, and common visual-control enum values from translation fields.
- Add regression coverage for `button_alignment="center"`.

## 1.9.0

- Add a front-end WordPress toolbar action that opens the current page in OpenLingua's translation editor.
- Show the action only for linked translations and users who can edit both the source and target content.

## 1.8.2

- Exclude encoded Slider Revolution shortcodes and technical module attributes from Divi translation fields.
- Preserve Slider Revolution aliases and module structure exactly when saving a page translation.
- Cover visible rendered slider layers through OpenLingua's dynamic shortcode discovery and translation editor.
- Repair previously altered Slider Revolution module payloads from the linked source layout during translation saves.

## 1.8.1

- Import historical translation pairs when a language pair first opens in the editor.
- Recognize copied source-language values as untranslated placeholders that memory may safely replace.

## 1.8.0

- Add local exact-match translation memory across posts, pages, custom post types, Divi, Gutenberg, ACF, and SEO fields.
- Learn reusable translations after manual saves and completed automatic translation jobs.
- Fill only empty matching fields and visibly identify memory-applied values.
- Synchronize repeated source text inside the translation editor without overwriting existing translations.

## 1.7.1

- Divi global color information and similar encoded configuration objects are no longer exposed as translation fields.
- Machine-payload detection now handles URL encoding, object-like data, UUID metadata, configuration keys, and global design attributes.
- Third-party module titles and body content remain available for translation.

## 1.7.0

- Replaced the fixed `et_pb_*`-only scan with generic third-party Divi module discovery.
- Registered Divi module callbacks, standard builder metadata, and third-party Divi naming conventions are recognized automatically.
- Human-readable text attributes and leaf-module body content are extracted without hardcoding a specific extension.
- Technical configuration, responsive controls, URLs, IDs, JSON, styles, dynamic payloads, and nested container duplicates are excluded.
- Added regression coverage modeled on carousel items and an unknown metadata-identified module.

## 1.6.2

- Fixed the Global content submenu registration order that caused WordPress to return a 403 access error.

## 1.6.1

- Resolved all blocking errors reported by WordPress Plugin Check.
- Replaced dynamic routing SQL placeholders with a simpler prepared lookup, safe post-type filtering, and object caching.
- Added persistent caching and explicit invalidation for translation groups and interface strings.
- Hardened portable JSON imports with uploaded-file, size, extension, and MIME validation.
- Centralized translation-editor request arrays while preserving field-specific sanitization after nonce verification.

## 1.6.0

- Gutenberg reusable-block and navigation references now map to published translations.
- Internal URLs in block attributes and markup now point to translated posts automatically.
- Navigation-link post and taxonomy IDs are updated together with their destination URLs.
- Query arguments and fragments are preserved; external and unavailable destinations are left untouched.
- Added relationship regression tests.

## 1.5.0

- Added a dedicated Global content translation screen for Site Editor templates, template parts, navigation entities, and reusable patterns.
- Published block-template and template-part translations are selected automatically for the frontend language.
- Draft or missing translations safely fall back to the source template.
- Added regression tests for translated global template resolution.

## 1.4.0

- Interface translations stored under Strings are now applied to theme and plugin gettext output.
- Added separate keys for contextual and basic plural strings.
- Native WordPress locale output remains the fallback when no custom translation exists.
- Disabled discovery no longer creates missing string records as a side effect of rendering.

## 1.3.4

- WordPress now initializes locale-dependent output using the language detected from the request path, query mode, or configured domain.
- Calendar captions, weekday labels, navigation text, search controls, and other core strings use the current language locale.
- Day, month, and year archive links retain the current language URL.

## 1.3.3

- Gutenberg tables now expose each header, cell, and caption independently in manual and automatic translation.
- Table structure and untranslated neighboring cells remain unchanged during replacement.

## 1.3.2

- The switcher keeps the current language visible when a page has no published alternatives.
- Single-language content uses a non-interactive indicator instead of an empty dropdown.

## 1.3.1

- Language switchers now hide unavailable or unpublished translations on singular content and taxonomy archives.
- Non-content views such as post archives continue to link to each public language homepage.

## 1.3.0

- Added a dedicated Gutenberg extractor for native, nested, freeform, and third-party blocks.
- Added block-level manual and automatic translation while preserving serialized block structure.
- Added dynamic block output discovery and translation through the Strings workflow.
- Added translated references for synchronized patterns backed by `wp_block` posts.
- Added Gutenberg extraction and structural-preservation tests.

## 1.2.10

- Sanitized submitted settings, redirects, uploads, and interface or shortcode translations.
- Prepared dynamic table identifiers using WordPress identifier placeholders.
- Removed obsolete manual text-domain loading.

## 1.2.9

- Escaped the allowed translation-job action markup identified by Plugin Check.

## 1.2.8

- Prepared the remaining dynamic database identifiers used by administration, jobs, and portability.
- Replaced discouraged HTML stripping calls with the corresponding WordPress API.

## 1.2.7

- Standardized visual-editor typography regardless of embedded heading, link, or emphasis markup.
- Strengthened section separators with a labeled background and accent border.

## 1.2.6

- Added horizontal dividers to identify content, custom-field, and SEO translation sections.

## 1.2.5

- Added horizontal separation between the original and translated content panels.

## 1.2.4

- Increased the spacing between translation field cards on desktop and mobile.

## 1.2.3

- Reworked the translation editor into an original OpenLingua workspace with independent field cards, a distinct language route, and responsive actions.

## 1.2.2

- Reworked the string administration queries to use prepared table identifiers and parameters.
- Updated the declared WordPress compatibility to 7.0.

## 1.2.1

- Added the complete distribution license and directory-ready release metadata.
- Documented all optional external translation services and their data flow.
- Expanded the WordPress privacy-policy suggestion for provider use and local data retention.
- Added REST argument validation and a bounded request size for dynamic shortcode translation.

## 1.1.0

- Replaces the original language table with an independently designed, sectioned settings screen.
- Adds a catalog of more than 60 languages and support for custom language codes, native names, locales, symbols, and RTL direction.
- Adds working directory, query-string, and per-language domain URL strategies.
- Adds switcher display controls, dropdown and footer modes, missing-translation behavior, and public language hiding.
- Adds a configurable administration locale, opt-in browser-language redirect, and explicit gettext discovery control.
- Applies hidden-language policy to REST discovery and SEO alternate links.
- Adds safe cross-domain redirect allowlisting and routing coverage for query and domain modes.

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
