# Changelog

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
