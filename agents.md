<!--
SPDX-FileCopyrightText: 2026 Vitor Mattos
SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Repository guidance for agents

This file records architectural decisions and recurring pitfalls for automated contributors. Update it whenever a change introduces a new invariant, workaround or deployment constraint.

## Project purpose

This repository is Vitor Mattos's public professional site and long-term archive for articles, talks, research, projects and public professional history. It should read as an authentic professional site first, not as an application or evidence dossier. Public copy must not mention Global Talent, visa strategy or the site's use as immigration evidence.

English is the canonical editorial language. Brazilian Portuguese translations live under `/pt-BR/` and are added when useful; they are not generated at build time.

## Branches and deployment

- Default branch: `main`. Do not reintroduce references to `master`.
- Production source is built from `main` and published to the root of `gh-pages`.
- Pull-request previews are published by `rossjrw/pr-preview-action` to `gh-pages/pr-preview/pr-N/`.
- Production deployment must preserve `pr-preview/` and must not force-push over active previews.
- No separate preview repository and no custom preview PAT are required. Use the repository `GITHUB_TOKEN` with the workflow permissions already declared.
- Preview concurrency is scoped per PR (`pr-preview-N`) with `cancel-in-progress: true`; production has a separate cancelling group. Never combine them.
- Every successful commit written to `gh-pages` can trigger GitHub's managed Pages workflow; that workflow does not inherit repository workflow concurrency.

## Jigsaw, Vite and SCSS

- Jigsaw is the static-site generator; Vite uses `@tighten/jigsaw-vite-plugin`.
- SCSS source lives under `source/_assets/scss/`; Vite emits hashed assets under `/assets/build/`.
- Jigsaw's `vite()` helper does not apply `baseUrl`; layouts must keep `{{ $page->baseUrl }}{{ vite(...) }}`.
- Preview builds use `PREVIEW_BASE_URL`; canonical URLs always use production `siteUrl`.

## Theme architecture

- Light/dark behavior follows `prefers-color-scheme`; explicit user choice is stored in `localStorage['theme']`.
- Theme colors are CSS custom properties. Theme preference must never alter content, URLs, indexing or builds.

## Presentation architecture

- Reveal.js is the default renderer for native web decks and is bundled from npm.
- Native presentation content lives in `presentations/<slug>/<locale>.md`, outside Jigsaw, with matching metadata records in `source/_talks*`.
- Native Markdown must remain independently reusable and must not contain Blade/Jigsaw coupling.
- `App\Listeners\CopyPresentations` copies presentation source unchanged into the build.
- Rendering belongs in `_layouts/talk.blade.php` and `_partials/talk/*`; formats include Reveal, Slides.com/iframe, PDF and generic external resources.
- Presentation pages can expose overview, reading/scroll and fullscreen modes plus source/download resources.
- Talk collection pages support grid and list views. The preference is presentation-only and stored as `localStorage['talk-gallery-view']`.

### Slides.com synchronization

- `.github/workflows/sync-slides.yml` synchronizes public owned decks using the read-only secret `SLIDES_API_TOKEN`. Never commit, print or otherwise persist the token.
- The workflow runs daily or manually and opens/updates `automation/slides-com-sync`; synchronization reaches `main` only through a pull request.
- `scripts/sync-slides.php` is the single owner of synchronized data. It imports only decks whose API `visibility` is `all`.
- Generated talk records are prefixed `slides-com-` and carry `managed: slides.com`. The synchronizer may delete/replace only these managed records; it must never alter native presentation records.
- Archived Slides.com material lives only under `presentations/slides.com/<deck-id>/`. Native decks must never be stored there.
- Archive `deck_html`, deck CSS and API metadata so public presentations remain inspectable independently of the Slides.com iframe. Preserve the original Slides.com URL for provenance.
- A read-only Slides.com key can list/fetch decks and `deck_html`, but creating PDF/ZIP exports requires a read-write key. Do not silently broaden the synchronization credential. Add export generation only as an explicit, separately reviewed capability.
- The Slides.com API is an import source, not the canonical authoring system for native decks. Sync must never erase locally authored Reveal/Markdown presentations.

## URL and indexing model

- `baseUrl` is the current build location; `siteUrl` is the canonical production origin `https://vitormattos.github.io`.
- Canonical, hreflang, Open Graph, JSON-LD, sitemap and RSS use `siteUrl`; navigation/assets use `baseUrl`.
- `/` is the only canonical URL ending in `/`; non-root canonical URLs do not end in `/`.
- Preview HTML is `noindex,nofollow,noarchive`; preview builds do not generate a sitemap.
- Production root robots blocks `/pr-preview/` and `/presentations/`; raw presentation sources do not enter the sitemap.
- `/404.html` is always noindex.

## Crawlable information architecture

- English hubs: `/articles`, `/talks`; Portuguese hubs: `/pt-BR/artigos`, `/pt-BR/palestras`.
- Detail pages remain reachable from their collection hub and relevant homepage sections.

## SEO and answer-engine requirements

- Keep one canonical URL per indexable document and reciprocal language alternates when a real translation exists.
- Structured data must remain factual; never invent awards, credentials, dates or relationships.
- Thin/placeholder pages remain `indexable: false`.
- Use real publication/modification dates; never use CI time as fake `lastmod`.
- `/llms.txt` is a discovery aid, not a ranking guarantee.

## Content semantics and accessibility

- Use semantic landmarks and elements; preserve keyboard navigation and the skip link.
- Language switches use preview-aware `baseUrl`; hreflang uses canonical `siteUrl`.

## CI and tests

- GitHub Actions must be pinned to immutable commit SHAs with an exact version comment.
- Dependabot covers Composer, npm and Actions.
- `SITE_BUILD_DIR` and `EXPECTED_BASE_URL` make tests preview-aware.
- Add regression tests for deployment, SEO, URL generation and presentation-gallery behavior.

## Licensing and REUSE

- Software/configuration uses `AGPL-3.0-or-later` unless explicitly changed; presentation/editorial content may use `CC-BY-SA-4.0` when declared.
- Keep SPDX metadata on every source/generated file, including synchronized presentation archives.
- Files that cannot safely carry comments may be covered by `REUSE.toml`.

## Engineering style

Keep the site understandable and lightweight. Prefer Jigsaw/Vite/Reveal capabilities already in the project over parallel systems. Public site copy must not expose internal application strategy. Update this file whenever architecture or invariants change.
