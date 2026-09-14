{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $locale = $page->locale ?? $page->defaultLocale;
    $isEnglish = $locale === 'en';
    $path = '/' . ltrim($page->getPath(), '/');
    $canonicalUrl = rtrim($page->siteUrl, '/') . ($path === '/' ? '/' : $path);
    $description = $page->description ?? $page->siteDescription;
    $pageTitle = $page->title ?? $page->siteName;
    $documentTitle = $page->title ? $page->title . ' · ' . $page->siteName : $page->siteName;
    $alternateCanonicalUrl = ($page->alternateUrl ?? false)
        ? rtrim($page->siteUrl, '/') . $page->alternateUrl
        : null;
    $englishCanonicalUrl = $isEnglish
        ? $canonicalUrl
        : ($alternateCanonicalUrl ?? rtrim($page->siteUrl, '/') . '/');
    $schemaType = $page->schemaType ?? null;
    $pageType = $page->pageType ?? (($path === '/' || $path === '/pt-BR/') ? 'ProfilePage' : 'WebPage');
    $personId = $page->author['id'];
    $websiteId = rtrim($page->siteUrl, '/') . '/#website';
    $webpageId = $canonicalUrl . '#webpage';
    $contentId = $canonicalUrl . '#content';
    $sameAs = array_values(array_filter([
        $page->author['github'] ?? null,
        $page->author['linkedin'] ?? null,
    ]));

    $graph = [
        [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => rtrim($page->siteUrl, '/') . '/',
            'name' => $page->siteName,
            'description' => $page->siteDescription,
            'inLanguage' => $page->locales,
        ],
        [
            '@type' => 'Person',
            '@id' => $personId,
            'name' => $page->author['name'],
            'url' => rtrim($page->siteUrl, '/') . '/',
            'sameAs' => $sameAs,
            'knowsAbout' => $page->author['knowsAbout'],
            'worksFor' => [
                '@type' => 'Organization',
                'name' => $page->author['organization']['name'],
                'url' => $page->author['organization']['url'],
            ],
        ],
        [
            '@type' => $pageType,
            '@id' => $webpageId,
            'url' => $canonicalUrl,
            'name' => $pageTitle,
            'description' => $description,
            'inLanguage' => $locale,
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $personId],
        ],
    ];

    if ($pageType === 'ProfilePage') {
        $graph[2]['mainEntity'] = ['@id' => $personId];
    }

    if (in_array($schemaType, ['Article', 'CreativeWork'], true)) {
        $content = [
            '@type' => $schemaType,
            '@id' => $contentId,
            'url' => $canonicalUrl,
            'name' => $pageTitle,
            'headline' => $pageTitle,
            'description' => $description,
            'inLanguage' => $locale,
            'author' => ['@id' => $personId],
            'publisher' => ['@id' => $personId],
            'mainEntityOfPage' => ['@id' => $webpageId],
        ];

        if ($page->date ?? false) {
            $content['datePublished'] = date(DATE_ATOM, $page->date);
        }

        $graph[] = $content;
        $graph[2]['mainEntity'] = ['@id' => $contentId];

        $isArticle = $schemaType === 'Article';
        $sectionPath = $isArticle
            ? ($isEnglish ? '/articles/' : '/pt-BR/artigos/')
            : ($isEnglish ? '/talks/' : '/pt-BR/palestras/');
        $sectionName = $isArticle
            ? ($isEnglish ? 'Articles' : 'Artigos')
            : ($isEnglish ? 'Talks' : 'Palestras');
        $breadcrumbId = $canonicalUrl . '#breadcrumb';

        $graph[] = [
            '@type' => 'BreadcrumbList',
            '@id' => $breadcrumbId,
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $isEnglish ? 'Home' : 'Início',
                    'item' => rtrim($page->siteUrl, '/') . ($isEnglish ? '/' : '/pt-BR/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $sectionName,
                    'item' => rtrim($page->siteUrl, '/') . $sectionPath,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $pageTitle,
                    'item' => $canonicalUrl,
                ],
            ],
        ];
        $graph[2]['breadcrumb'] = ['@id' => $breadcrumbId];
    }

    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
@endphp
<meta name="description" content="{{ $description }}">
<meta name="author" content="{{ $page->author['name'] }}">
@if (! ($page->indexable ?? false))
    <meta name="robots" content="noindex,nofollow,noarchive">
@endif
<link rel="canonical" href="{{ $canonicalUrl }}">
<link rel="alternate" hreflang="{{ $locale }}" href="{{ $canonicalUrl }}">
@if ($alternateCanonicalUrl)
    <link rel="alternate" hreflang="{{ $isEnglish ? 'pt-BR' : 'en' }}" href="{{ $alternateCanonicalUrl }}">
@endif
<link rel="alternate" hreflang="x-default" href="{{ $englishCanonicalUrl }}">
<link rel="alternate" type="application/rss+xml" title="{{ $page->siteName }} — {{ $isEnglish ? 'Articles' : 'Artigos' }}" href="{{ rtrim($page->siteUrl, '/') }}{{ $isEnglish ? '/feed.xml' : '/pt-BR/feed.xml' }}">
<meta property="og:type" content="{{ $schemaType === 'Article' ? 'article' : 'website' }}">
<meta property="og:title" content="{{ $documentTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $page->siteName }}">
<meta property="og:locale" content="{{ $isEnglish ? 'en_US' : 'pt_BR' }}">
<meta property="og:locale:alternate" content="{{ $isEnglish ? 'pt_BR' : 'en_US' }}">
@if ($schemaType === 'Article' && ($page->date ?? false))
    <meta property="article:published_time" content="{{ date(DATE_ATOM, $page->date) }}">
@endif
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $documentTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
