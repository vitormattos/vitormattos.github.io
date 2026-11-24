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


## Media archive

The archive is generated from the public talk collections and the recording
resources in `data/talks.php` (including event appearances and native
`presentation.video`). Duplicate YouTube URLs are consolidated. Historic
recordings marked `availability: unavailable` or `unverified` remain on their
talk pages but are excluded from the playable gallery. Use the recording's
actual date, not the slide deck's creation date.

For independent recordings, podcasts and approved photos, add one JSON
sidecar per item under `data/media/`. The filename is the stable identifier;
use the existing examples for bilingual titles/descriptions, provenance,
related talk/article slugs, date context and media type. Photos additionally
require an optimized public image and thumbnail, dimensions, credit and a
license. Do not import private cloud metadata or exact capture coordinates.

The map uses the public-domain Natural Earth 1:50m country geography and first-order administrative boundaries
(v5.1.2), vendored as a compact, localized GeoJSON in
`source/media-assets/world.geojson`. The map and labels load locally on demand;
no map tiles, tracking services, geocoding requests or personal coordinates
are used. Add documented city centers with region/country information to
`data/media-cities.json` for future public events.
