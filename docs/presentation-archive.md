<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Presentation archive

The site treats presentation providers as sources of one presentation archive instead of separate catalogs. Public talks remain in the existing Jigsaw talk collections while source-specific archival material lives under `presentations/<source>/<id>/`.

## Common model

A presentation archive has a stable source, immutable source ID, title, description, language, historical publication/update dates, tags, source URL, local thumbnail when available, and an `export.json` describing archived Release assets.

Current sources:

- `slides.com`: HTML/CSS source archive, local thumbnail, content-addressed PDF Release assets.
- `slideshare`: sanitized export metadata, local thumbnail, original downloadable file when SlideShare still serves it, and PDF conversion when the original format is supported.

GitHub Release tags are source-qualified (`slides-com-<id>` and `slideshare-<id>`) so IDs from different providers cannot collide.

## SlideShare import privacy boundary

The raw SlideShare account export must not be committed. It contains account/contact/social information unrelated to the talks. `scripts/import-slideshare-export.php` accepts the private JSON and CSV exports locally and writes only an explicit allowlist of presentation fields.

Allowed public archive fields are presentation identity, title, description, language, visibility, original/download URLs, historical upload date, presentation tags and exported presentation statistics. Account registration, contact details, followed users and comments made on other presentations are excluded.

Example:

```bash
php scripts/import-slideshare-export.php /private/slideshare.json /private/slideshare.csv
```

The generated `presentations/slideshare/import.json` documents the privacy filter but does not identify the private source files or reproduce their account data.

## SlideShare publication

`Publish SlideShare archive` processes the sanitized metadata after merge or through manual dispatch. For each presentation it:

1. tries to obtain a public thumbnail from the SlideShare page;
2. tries to download the original presentation using the URL supplied by the SlideShare data export;
3. preserves supported original files as content-addressed GitHub Release assets;
4. uses an existing PDF directly or converts PPT/PPTX/ODP files to PDF with LibreOffice;
5. creates a first-page PNG thumbnail from the PDF when a public thumbnail is unavailable;
6. creates/updates a source-qualified GitHub Release and uploads the thumbnail, original file and PDF without replacing historical assets;
7. writes `export.json` and updates the managed talk front matter with local thumbnail and Release download URLs;
8. commits generated archive state to `main`, after which the presentation publisher deployment bridge rebuilds the site.

The process is idempotent. Release asset names include a content hash, so unchanged files are reused and changed files create a new immutable snapshot.

## Historical dates and GitHub Releases

The original presentation date is authoritative for the site and is preserved in `metadata.json`, the Jigsaw talk date and the Release description. SlideShare imports are created with `make_latest=false` so they do not replace the repository's current Latest release.

GitHub does not expose a supported field for setting a historical `published_at` timestamp when creating a Release. Therefore the public Releases list cannot be reliably reordered to 2011-2017 publication dates. The archive does not rewrite Git history or fabricate release timestamps to work around that limitation.
