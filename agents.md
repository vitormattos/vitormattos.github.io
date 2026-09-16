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
- PDF presentations, including LaTeX-generated academic decks, may live under `presentations/<slug>/` and use `presentation.type: pdf` with a root-relative `presentation.url` in the corresponding talk record.
- `App\Listeners\CopyPresentations` copies presentation source unchanged into the build.
- Rendering belongs in `_layouts/talk.blade.php` and `_partials/talk/*`; formats include Reveal, Slides.com/iframe, PDF and generic external resources.
- Presentation pages can expose overview, reading/scroll and fullscreen modes plus source/download resources.
- Talk collection pages support grid and list views. The preference is presentation-only and stored as `localStorage['talk-gallery-view']`.
- Presentation business rules belong in testable PHP classes under `src/Presentations/`. CLI scripts under `scripts/` should be thin entrypoints; do not put domain policy in shell scripts.
- Curated provider-independent talk metadata lives in `data/talks.php`. The stable array key is the talk slug. `aliases` identify provider copies; `appearances` record event/date/location/mode; `resources` hold slides, PDFs, videos, code or source material; `sources` record provenance such as CFP issues or official event pages.
- Talk metadata is independent of Slides.com, SlideShare, GitHub and any other presentation provider. `App\Presentations\TalkMetadata` resolves the metadata across provider-specific copies of the same talk and accepts root-relative resources for files hosted by this site.
- Synchronizers may enrich provider-managed talk records but must never write, replace or delete `data/talks.php`.

### Slides.com synchronization

- `.github/workflows/sync-slides.yml` synchronizes public owned decks using `SLIDES_API_TOKEN`. Never commit, print or otherwise persist the token.
- The workflow runs daily or manually and opens/updates `automation/slides-com-sync`; synchronization reaches `main` only through a pull request.
- `scripts/sync-slides.php` imports only decks whose API `visibility` is `all`. Privacy filtering is a business invariant and must have regression tests when changed.
- Generated talk records are prefixed `slides-com-` and carry `managed: slides.com`. The synchronizer may delete/replace only these managed records; it must never alter native presentation records or `data/talks.php`.
- Archived Slides.com material lives only under `presentations/slides.com/<deck-id>/`. Native decks must never be stored there.
- Archive `deck_html`, deck CSS and API metadata so public presentations remain inspectable independently of the Slides.com iframe. Preserve the original public Slides.com URL for provenance and rendering.
- PDF archives are rendered from public Slides.com URLs with the pinned DeckTape version defined by `PdfExportPolicy`; they do not use the Slides.com export API or its export quota.
- PDF generation is best effort and atomic: an invalid new render must never replace a previous valid PDF. A PDF is publishable only when it satisfies the tested signature and minimum-size contract.
- The Slides.com API is an import source, not the canonical authoring system for native decks. Sync must never erase locally authored Reveal/Markdown/PDF presentations.

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
- Tests are contract/regression tests for behavior and business invariants, not a coverage target. New presentation behavior must ship with tests that would fail if its rule is broken.
- `data/talks.php` is validated in tests: slugs, dates, URLs/root-relative paths, event modes and country codes must remain structurally valid.
- Critical presentation contracts include: private/team decks are never exported; only the expected public Slides.com owner URL is accepted; renderer version and dimensions are deterministic; invalid PDFs are rejected; native presentations and curated talk metadata are never deleted by synchronization.
- Add regression tests for deployment, SEO, URL generation and presentation-gallery behavior.

## Licensing and REUSE

- Software/configuration uses `AGPL-3.0-or-later` unless explicitly changed; presentation/editorial content may use `CC-BY-SA-4.0` when declared.
- Keep SPDX metadata on every source/generated file, including synchronized presentation archives.
- Files that cannot safely carry comments may be covered by `REUSE.toml`.

## Engineering style

Keep the site understandable and lightweight. Prefer PHP for repository automation when it contains testable business rules; use shell only for trivial process glue with no domain decisions. Prefer Jigsaw/Vite/Reveal capabilities already in the project over parallel systems. Public site copy must not expose internal application strategy. Update this file whenever architecture or invariants change.
