<!--
SPDX-FileCopyrightText: 2026 Vitor Mattos
SPDX-License-Identifier: CC-BY-SA-4.0
-->

# Portfolio homepage design review

This note records the design and engineering rationale behind the 2026 homepage refresh.

## Objective

The portfolio is a public professional record, not a campaign landing page. It should let an international evaluator understand Vitor Mattos' technical identity quickly, then verify the work through primary sources.

The current UK Global Talent digital-technology guidance evaluates recognition, international reputation and impact, innovation, contributions outside normal employment, significant technical/commercial contributions, and research/publications. The public site should make the corresponding evidence easy to find without mentioning the application process itself.

Primary references:

- GOV.UK, Global Talent digital technology eligibility: https://www.gov.uk/global-talent-digital-technology/eligibility
- GOV.UK, endorsement evidence: https://www.gov.uk/global-talent-digital-technology/documents-you-need-to-apply-endorsement
- GOV.UK, Immigration Rules Appendix Global Talent: https://www.gov.uk/guidance/immigration-rules/immigration-rules-appendix-global-talent

## Evidence from portfolio and web-design research

### Prefer low visual complexity and familiar structure

Tuch et al. found that visual complexity and prototypicality affect aesthetic judgments within tens of milliseconds. Low-complexity, familiar websites received stronger aesthetic evaluations.

Reference:
- Tuch, Presslaber, Stoecklin, Opwis & Bargas-Avila (2012), *The role of visual complexity and prototypicality regarding first impression of websites*: https://research.google/pubs/the-role-of-visual-complexity-and-prototypicality-regarding-first-impression-of-websites-working-towards-understanding-aesthetic-judgments/

Design consequence:
- keep one clear hero message;
- avoid oversized identity text and decorative animation;
- use a conventional hierarchy: introduction, selected work, speaking, writing.

### Credibility comes from professional design and verifiable evidence

Stanford's Web Credibility Project found that visual design strongly affects credibility judgments and recommends making claims easy to verify through third-party references and source material.

References:
- Stanford Web Credibility Guidelines: https://credibility.stanford.edu/guidelines/
- Fogg et al., *How Do People Evaluate a Web Site's Credibility?*: https://credibility.stanford.edu/pdf/How_Do_People_Evaluate_a_Web_Site%27s_Credibility_v37.pdf

Design consequence:
- link LibreSign to its project, source code, and Digital Public Goods record;
- link LibreCode and community work to primary public records;
- avoid unverified marketing claims and decorative counters.

### Portfolio usefulness depends on concise structure and authentic work samples

A systematic scoping review of employer expectations for ePortfolios found that useful portfolios help reviewers differentiate candidates quickly but that excessive information is a disadvantage. Recommended content includes professional work samples and clear, concise structure.

Reference:
- McKay & Watty (2021), *Enhancing Graduate Employability through Targeting ePortfolios to Employer Expectations*: https://eric.ed.gov/?id=EJ1294417

Design consequence:
- show selected evidence on the homepage instead of the full archive;
- keep complete talks and article catalogs on dedicated pages;
- treat the homepage as a map to evidence, not the evidence archive itself.

### Semantic structure supports scanning and accessibility

W3C recommends clear section hierarchy, descriptive headings, visible keyboard focus, and predictable navigation.

References:
- WCAG 2.2: https://www.w3.org/TR/wcag/
- WAI page structure guidance: https://www.w3.org/WAI/tutorials/page-structure/
- WAI headings guidance: https://www.w3.org/WAI/tutorials/page-structure/headings/

Design consequence:
- one page-level `h1`;
- descriptive `h2` sections and `h3` cards;
- no interaction required to reveal core evidence;
- existing skip link and focus treatment remain part of the base layout.

## Built With Jigsaw portfolio review

The gallery is useful for identifying patterns, but many entries are historical and some repositories have since disappeared or migrated to other frameworks. A framework choice should therefore be based on maintainability rather than visual imitation.

Recent or still-inspectable examples reviewed:

| Portfolio | Observed stack | Useful lesson |
| --- | --- | --- |
| Shakir El Amrani | Jigsaw, Tailwind CSS, Laravel Mix | strong evidence sections and project links; homepage is information-dense |
| Hesam Rad | Jigsaw, Vite, PostCSS | modern Jigsaw/Vite path with a relatively small front-end stack |
| John Cosio | Jigsaw, Tailwind CSS, Alpine.js, Laravel Mix | component utilities help, but add framework/runtime surface |
| Eduar Bastidas | Jigsaw, Tailwind CSS, Alpine.js, particle effects | demonstrates richer visual treatment, but decorative effects are not necessary for this portfolio's goal |
| Yusuf Taufiq | Jigsaw, Tailwind CSS, Laravel Mix, ESLint, Swiper, Typed.js, Tippy | mature linting and interaction stack, but considerably more JavaScript |
| Jethro May | migrated from Jigsaw to Astro/React/Tailwind | gallery entries describe a point in time; current maintainability matters more than copying a historical stack |

### CSS decision

Do **not** introduce Tailwind merely because several gallery portfolios use it.

The current site already has:
- modular Sass;
- CSS custom properties for themes;
- Stylelint;
- Vite;
- a small semantic HTML surface.

For this site, custom Sass has lower migration cost, avoids utility-heavy markup, and keeps visual rules close to the domain components. The homepage therefore receives a dedicated `_home.scss` module rather than a new CSS framework.

### JavaScript decision

The homepage itself does not need new JavaScript. This is deliberate.

For the JavaScript that already exists:
- keep Vite for bundling;
- keep Prettier for deterministic formatting;
- use Node's built-in test runner for pure logic where possible;
- avoid adding a browser-test framework until there is behavior that requires it.

The icon renderer was refactored so its markup/rendering behavior can be unit-tested without introducing another test dependency.

## Homepage information architecture

1. **Purpose-led hero** — role and domain, not a giant repetition of the person's name.
2. **Current focus** — compact orientation for a reviewer.
3. **Selected work** — LibreSign, LibreCode, and public/community work, each with verification links.
4. **Selected talks** — three items, with the full archive one click away.
5. **Recent writing/research** — three items, with the full archive one click away.

This gives a reviewer a short first-pass path while preserving the deeper evidence elsewhere in the site.
