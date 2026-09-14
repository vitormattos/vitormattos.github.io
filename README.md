<!--
SPDX-FileCopyrightText: 2026 Vitor Mattos
SPDX-License-Identifier: AGPL-3.0-or-later
-->
# vitormattos.github.io

Source code for <https://vitormattos.github.io>, built with [Jigsaw](https://jigsaw.tighten.com/) and its official Vite integration.

The site is intended to keep articles, talks, academic work and public professional history in a versioned, portable and privacy-conscious format.

## Development

Install PHP and frontend dependencies:

```bash
composer install
npm install
```

Build the production site and run tests:

```bash
composer build
composer test
```

For local development with Vite and Jigsaw rebuilds:

```bash
composer serve
```

Styles are authored in SCSS under `source/_assets/scss/` and compiled by Vite through `@tighten/jigsaw-vite-plugin`.

## Quality

Checks are intentionally split into independent GitHub Actions workflows so failures are attributable to one concern:

- Composer validation and security audit
- PHP syntax lint
- PHP coding standards
- REUSE/SPDX licensing compliance
- Vite/SCSS production build and smoke tests

Dependencies are monitored by Dependabot for Composer, npm and GitHub Actions.

## License

Software is licensed under `AGPL-3.0-or-later`. SPDX metadata is required for source files. Content may adopt another explicit free-content license later.
