---
permalink: /pt-BR/feed.xml
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $siteUrl = rtrim($page->siteUrl, '/');
    $latestDate = null;
    foreach ($articles as $article) {
        $latestDate ??= $article->date;
    }
@endphp
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ $page->siteName }}</title>
    <link>{{ $siteUrl }}/pt-BR/</link>
    <description>{{ $page->siteDescription }}</description>
    <language>pt-BR</language>
    <atom:link href="{{ $siteUrl }}/pt-BR/feed.xml" rel="self" type="application/rss+xml" />
    @if ($latestDate)
        <lastBuildDate>{{ date(DATE_RSS, $latestDate) }}</lastBuildDate>
    @endif
    @foreach ($articles as $article)
        @php($url = $siteUrl . '/' . ltrim($article->getPath(), '/'))
        <item>
            <title>{{ $article->title }}</title>
            <link>{{ $url }}</link>
            <guid isPermaLink="true">{{ $url }}</guid>
            <pubDate>{{ date(DATE_RSS, $article->date) }}</pubDate>
            <description>{{ $article->description }}</description>
        </item>
    @endforeach
</channel>
</rss>
