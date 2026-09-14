<!--
SPDX-FileCopyrightText: 2026 Vitor Mattos
SPDX-License-Identifier: AGPL-3.0-or-later
-->
# vitormattos.github.io

Source code for <https://vitormattos.github.io>, built with [Jigsaw](https://jigsaw.tighten.com/).

The site is intended to keep articles, talks, academic work and public professional history in a versioned, portable and privacy-conscious format.

## Development

```bash
composer install
composer build
composer test
```

For local preview:

```bash
composer serve
```

## Quality

Checks are intentionally split into independent GitHub Actions workflows so failures are attributable to one concern:

- Composer validation and security audit
- PHP syntax lint
- PHP coding standards
- REUSE/SPDX licensing compliance
- production build and smoke tests

## License

Software is licensed under `AGPL-3.0-or-later`. SPDX metadata is required for source files. Content may adopt another explicit free-content license later.
