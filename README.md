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
