---
permalink: /feed.xml
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $siteUrl = rtrim($page->siteUrl, '/');
    $latestDate = null;
    foreach ($articlesEn as $article) {
        if ($article->date ?? false) {
            $latestDate ??= $article->date;
        }
    }
@endphp
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ $page->siteName }}</title>
    <link>{{ $siteUrl }}/</link>
    <description>{{ $page->siteDescription }}</description>
    <language>en</language>
    <atom:link href="{{ $siteUrl }}/feed.xml" rel="self" type="application/rss+xml" />
    @if ($latestDate)
        <lastBuildDate>{{ date(DATE_RSS, $latestDate) }}</lastBuildDate>
    @endif
    @foreach ($articlesEn as $article)
        @php($url = $siteUrl . '/' . ltrim($article->getPath(), '/'))
        <item>
            <title>{{ $article->title }}</title>
            <link>{{ $url }}</link>
            <guid isPermaLink="true">{{ $url }}</guid>
            @if ($article->date ?? false)
                <pubDate>{{ date(DATE_RSS, $article->date) }}</pubDate>
            @endif
            <description>{{ $article->description }}</description>
        </item>
    @endforeach
</channel>
</rss>
