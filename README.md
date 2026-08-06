# OpenLingua

OpenLingua is a free GPL multilingual foundation for WordPress. Version 1.1 supports translated content and taxonomies, comprehensive language settings, custom-field policies, menus, strings, language-aware routing and SEO, extensible translation providers, portability, and operational tooling.

This project is an original implementation. It neither contains proprietary WPML code nor promises drop-in WPML compatibility.

## Architecture

Core domain services live in `src/`. Optional capabilities live in `src/modules/` and implement `OpenLingua\Contracts\Module`. Provider integrations implement `OpenLingua\Contracts\Translation_Provider`. See [the architecture guide](docs/ARCHITECTURE.md) and [provider guide](docs/PROVIDERS.md).

## Quality checks

```bash
php tests/run.php
php tests/modules.php
```

Every PHP file must also pass `php -l` before release.

## License

GPL-2.0-or-later.
