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

## Jigsaw, Vite and SCSS

- Jigsaw is the static-site generator; Vite uses `@tighten/jigsaw-vite-plugin`.
- SCSS source lives under `source/_assets/scss/`. Do not restore a second CSS source tree.
- Vite emits hashed assets under `/assets/build/`.
- Jigsaw 1.8.8's `vite()` helper returns an absolute path beginning with `/assets/build/` and does not apply Jigsaw's `baseUrl`. In layouts, keep the pattern `{{ $page->baseUrl }}{{ vite(...) }}` so preview assets resolve under `/pr-preview/pr-N/`.
- Preview builds use `PREVIEW_BASE_URL` and `NODE_ENV=preview`; production builds use the canonical root URL.

## URL and indexing model

- `baseUrl` means where the current build is being served. It changes for previews.
- `siteUrl` means the canonical production origin and must remain `https://vitormattos.github.io` even in previews.
- Canonical URLs, hreflang and JSON-LD must use `siteUrl`, never the preview `baseUrl`.
- Canonical path policy follows Jigsaw's generated URLs: `/` is the only canonical URL ending in `/`; all non-root canonical URLs omit the trailing slash (for example `/articles`, `/pt-BR`, `/articles/example`). Keep canonical, hreflang, Open Graph, JSON-LD, sitemap, RSS and crawlable internal links consistent with this rule.
- Navigation and compiled asset URLs use `baseUrl` so preview links stay inside the preview.
- Preview HTML must contain `noindex,nofollow,noarchive`.
- Indexable pages explicitly allow unrestricted snippets, large image previews and unrestricted video previews. Do not weaken these directives unless there is a content-policy reason to do so.
- The production root `robots.txt` must disallow `/pr-preview/`. A nested `robots.txt` inside a preview path is not authoritative under the robots exclusion standard.
- Preview builds must not generate `sitemap.xml`; production builds do.
- The custom `/404.html` is always `noindex` and must never enter the sitemap.

## Crawlable information architecture

- English collection hubs: `/articles` and `/talks`.
- Portuguese collection hubs: `/pt-BR/artigos` and `/pt-BR/palestras`.
- Main navigation links to these real hub pages, not only to homepage fragments.
- Detail pages should remain reachable from both the homepage and the corresponding collection hub.
- Detail-page structured data includes breadcrumbs through the appropriate collection hub.

## SEO and answer-engine requirements

- Keep one canonical URL for every indexable document.
- Language pairs must expose self-referencing hreflang, reciprocal alternate hreflang and `x-default` pointing to the English canonical version.
- Keep factual Schema.org JSON-LD for `WebSite`, `Person`, `ProfilePage`/`WebPage`, `CollectionPage`, `Article`, `CreativeWork` and `BreadcrumbList` as appropriate. Do not invent awards, credentials, employment, relationships or dates merely to enrich structured data.
- Articles and talks should have useful `title`, `description`, `date`, `locale`, `alternateUrl` and `schemaType` front matter.
- Use optional `updated` front matter only when a substantive revision actually occurred. When present it drives Schema.org `dateModified`, Open Graph `article:modified_time` and sitemap `lastmod`; otherwise publication `date` is used where appropriate. Never use CI/build time as a fake modification date.
- Content should answer its topic clearly near the beginning, use descriptive headings, and remain written for humans. Do not add keyword stuffing, hidden text or fake FAQ schema.
- `sitemap.xml` should contain only canonical HTML pages and real publication/modification dates when available. Do not use build time as fake `lastmod` because that makes every URL appear changed on every build.
- RSS feeds are available at `/feed.xml` and `/pt-BR/feed.xml`.
- `/llms.txt` is a machine-readable discovery aid, not a guaranteed ranking or indexing mechanism. Keep it factual and synchronized with public content.
- Open Graph and social metadata should reflect the same canonical title, description and URL as the page. Add social images only when real assets exist.
- After production is live, submit the root sitemap to Google Search Console and Bing Webmaster Tools. Verification tokens are account-specific and should not be invented in source.
- IndexNow is a valid future enhancement for notifying Bing and participating engines about changed URLs, but do not submit every URL on every CI run indiscriminately; integrate it only with a stable key and a changed-URL strategy.

## Content semantics and accessibility

- Use semantic landmarks and elements (`main`, `article`, `header`, `nav`, `time`) rather than styling generic containers when semantics exist.
- Keep the keyboard skip link functional.
- The visible language switch uses preview-aware `baseUrl`; head hreflang uses canonical `siteUrl`.

## CI and tests

- GitHub Actions must be pinned to immutable commit SHAs with an exact version comment beside the SHA.
- Dependabot covers Composer, npm and GitHub Actions.
- `SITE_BUILD_DIR` lets the same PHPUnit suite test `build_production` and `build_preview`. Do not hardcode production paths in tests that are also run by preview CI.
- `EXPECTED_BASE_URL` is used to verify preview-aware asset URLs.
- Tests enforce canonical URLs, hreflang, structured data, preview `noindex`, production robots rules, sitemap scope, feeds, `llms.txt`, 404 exclusion and the rule against exposing internal application-purpose language.
- Add regression tests when fixing deployment, SEO or URL-generation bugs rather than relying only on visual inspection.
- Prefer `npm ci` when a committed `package-lock.json` is present. Prefer reproducible Composer installs once `composer.lock` is committed.

## Licensing and REUSE

- Software/configuration currently uses `AGPL-3.0-or-later` unless explicitly changed.
- Keep SPDX metadata on source files.
- Markdown content should carry SPDX metadata directly in the file after YAML front matter.
- Files whose syntax cannot safely carry comments, or plain-text templates where a source comment would leak into output, may be covered by `REUSE.toml` instead.
- Run/observe the REUSE CI whenever adding a new file type.

## Engineering style

Keep the site understandable and lightweight. Prefer Jigsaw/Vite capabilities already in the project over introducing parallel build systems or infrastructure. Do not add elaborate secret-management, deployment or security architecture without a concrete requirement. When changing an established decision in this file, update this file in the same change.
