---
permalink: /llms.txt
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
# Vitor Mattos

> Public professional archive about free software, PHP, Linux, LibreSign, software engineering, research, talks and sustainable software communities.

Canonical site: {{ rtrim($page->siteUrl, '/') }}/

## Identity

- Name: Vitor Mattos
- Role: software developer, CTO and worker-owner at LibreCode; LibreSign maintainer
- Organization: {{ $page->author['organization']['name'] }} — {{ $page->author['organization']['url'] }}
- GitHub: {{ $page->author['github'] }}
- LinkedIn: {{ $page->author['linkedin'] }}

## Primary topics

@foreach ($page->author['knowsAbout'] as $topic)
- {{ $topic }}
@endforeach

## Articles

@foreach ($articlesEn as $article)
- [{{ $article->title }}]({{ rtrim($page->siteUrl, '/') }}/{{ ltrim($article->getPath(), '/') }}): {{ $article->description }}
@endforeach

## Talks

@foreach ($talksEn as $talk)
- [{{ $talk->title }}]({{ rtrim($page->siteUrl, '/') }}/{{ ltrim($talk->getPath(), '/') }}): {{ $talk->description }}
@endforeach

## Language versions

- English is the canonical editorial language.
- Brazilian Portuguese content is available under {{ rtrim($page->siteUrl, '/') }}/pt-BR/ when a translation exists.

## Discovery

- Sitemap: {{ rtrim($page->siteUrl, '/') }}/sitemap.xml
- English RSS: {{ rtrim($page->siteUrl, '/') }}/feed.xml
- Portuguese RSS: {{ rtrim($page->siteUrl, '/') }}/pt-BR/feed.xml
