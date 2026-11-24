<!--
SPDX-FileCopyrightText: 2026 Vitor Mattos
SPDX-License-Identifier: AGPL-3.0-or-later
-->
# vitormattos.github.io

Source code for <https://vitormattos.github.io>, built with [Jigsaw](https://jigsaw.tighten.com/) and its official Vite integration.

The site is intended to keep articles, talks, academic work and public professional history in a versioned, portable and privacy-conscious format.

## Development

Use PHP 8.6, Composer 2.11.3 and Node.js 26 LTS with npm 12.2.3. Run PHP CS Fixer under PHP 8.5 until it supports PHP 8.6 syntax. Install PHP and frontend dependencies:

```bash
composer install
npm ci
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

The PDF archive renderer is installed separately with `npm ci --prefix scripts/slides` and `npm exec --prefix scripts/slides -- puppeteer browsers install chrome`.

Styles are authored in SCSS under `source/_assets/scss/` and compiled by Vite through `@tighten/jigsaw-vite-plugin`.
