{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $locale = $page->locale ?? $page->defaultLocale;
    $isEnglish = $locale === 'en';
    $siteUrl = rtrim($page->siteUrl, '/');
    $rawPath = '/' . ltrim($page->getPath(), '/');
    $path = $rawPath === '/' ? '/' : rtrim($rawPath, '/');
    $canonicalUrl = $siteUrl . ($path === '/' ? '/' : $path);
    $description = $page->description ?? $page->siteDescription;
    $pageTitle = $page->title ?? $page->siteName;
    $documentTitle = $page->title ? $page->title . ' · ' . $page->siteName : $page->siteName;
    $effectiveIndexable = ($page->production ?? false) && ($page->indexable ?? false);
    $alternatePath = null;
    if ($page->alternateUrl ?? false) {
        $rawAlternatePath = '/' . ltrim($page->alternateUrl, '/');
        $alternatePath = $rawAlternatePath === '/' ? '/' : rtrim($rawAlternatePath, '/');
    }
    $alternateCanonicalUrl =
        $alternatePath !== null ? $siteUrl . ($alternatePath === '/' ? '/' : $alternatePath) : null;
    $englishCanonicalUrl = $isEnglish ? $canonicalUrl : $alternateCanonicalUrl ?? $siteUrl . '/';
    $schemaType = $page->schemaType ?? null;
    $isProfilePage = in_array($path, ['/', '/pt-BR'], true);
    $pageType = $page->pageType ?? ($isProfilePage ? 'ProfilePage' : 'WebPage');
    $personId = $page->author['id'];
    $websiteId = $siteUrl . '/#website';
    $webpageId = $canonicalUrl . '#webpage';
    $contentId = $canonicalUrl . '#content';
    $sameAs = [];
    foreach ($page->author['profiles'] ?? [] as $profile) {
        if ($profile['sameAs'] ?? false) {
            $sameAs[] = $profile['url'];
        }
    }
    $updatedAt = null;
    if ($page->updated ?? false) {
        $updatedAt = is_int($page->updated) ? $page->updated : (strtotime((string) $page->updated) ?: null);
    }

    $presentation = $page->presentation ?? [];
    $academic = $page->academic ?? [];
    $pageSocialImage =
        $page->socialImage ??
        ($page->image ??
            ($page->thumbnail ??
                ($academic['socialImage'] ??
                    null ??
                    ($academic['image'] ?? null ?? ($academic['thumbnail'] ?? null)))));
    $socialImageWidth = (int) ($page->socialImageWidth ?? ($page->imageWidth ?? 0));
    $socialImageHeight = (int) ($page->socialImageHeight ?? ($page->imageHeight ?? 0));

    if ($pageSocialImage === null && ($page->slidesId ?? false)) {
        $presentationType = $presentation['type'] ?? 'external';
        $sourceDirectory = $presentationType === 'slideshare' ? 'slideshare' : 'slides.com';
        $deckDir = 'presentations/' . $sourceDirectory . '/' . $page->slidesId;
        $localThumbnails = glob($deckDir . '/thumbnail.*') ?: [];
        if ($localThumbnails !== []) {
            $pageSocialImage = '/' . $localThumbnails[0];
            $imageSize = @getimagesize($localThumbnails[0]);
            if (is_array($imageSize)) {
                $socialImageWidth = (int) $imageSize[0];
                $socialImageHeight = (int) $imageSize[1];
            }
        }
    }

    if ($pageSocialImage === null && ($presentation['thumbnail'] ?? false)) {
        $pageSocialImage = $presentation['thumbnail'];
        $socialImageWidth = (int) ($presentation['thumbnailWidth'] ?? 0);
        $socialImageHeight = (int) ($presentation['thumbnailHeight'] ?? 0);
    }

    $socialImageCandidate = $pageSocialImage ?? ($page->author['socialImage'] ?? $page->author['avatar']);
    $socialImage = preg_match('#^https?://#i', (string) $socialImageCandidate)
        ? (string) $socialImageCandidate
        : $siteUrl . '/' . ltrim((string) $socialImageCandidate, '/');
    if ($pageSocialImage === null) {
        $socialImageWidth = 512;
        $socialImageHeight = 512;
    }
    $socialImageAlt = $page->socialImageAlt ?? ($isProfilePage ? $page->author['name'] : $pageTitle);
    $twitterCard = $pageSocialImage !== null ? 'summary_large_image' : 'summary';

    $graph = [
        [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => $siteUrl . '/',
            'name' => $page->siteName,
            'description' => $page->siteDescription,
            'inLanguage' => $page->locales,
        ],
        [
            '@type' => 'Person',
            '@id' => $personId,
            'name' => $page->author['name'],
            'url' => $siteUrl . '/',
            'image' => $page->author['socialImage'] ?? $page->author['avatar'],
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
            'image' => $socialImage,
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => $socialImage,
            ],
            'inLanguage' => $locale,
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $personId],
        ],
    ];

    if ($pageType === 'ProfilePage') {
        $graph[2]['mainEntity'] = ['@id' => $personId];
    }

    if (in_array($schemaType, ['Article', 'ScholarlyArticle', 'CreativeWork'], true)) {
        $content = [
            '@type' => $schemaType,
            '@id' => $contentId,
            'url' => $canonicalUrl,
            'name' => $pageTitle,
            'headline' => $pageTitle,
            'description' => $description,
            'image' => $socialImage,
            'inLanguage' => $locale,
            'author' => ['@id' => $personId],
            'publisher' => ['@id' => $personId],
            'mainEntityOfPage' => ['@id' => $webpageId],
        ];

        if ($page->date ?? false) {
            $content['datePublished'] = date(DATE_ATOM, $page->date);
        } elseif ($page->year ?? false) {
            $content['datePublished'] = (string) $page->year;
        }
        if ($updatedAt !== null) {
            $content['dateModified'] = date(DATE_ATOM, $updatedAt);
        }
        if ($schemaType === 'ScholarlyArticle' && ($page->academic ?? false)) {
            $academic = $page->academic;
            $content['author'] = [
                '@type' => 'Person',
                '@id' => $personId,
                'name' => $academic['author'] ?? $page->author['name'],
                'url' => $siteUrl . '/',
            ];
            if ($academic['institution'] ?? false) {
                $content['sourceOrganization'] = [
                    '@type' => 'EducationalOrganization',
                    'name' => $academic['institution'],
                ];
            }
            if ($academic['keywords'] ?? false) {
                $content['keywords'] = $academic['keywords'];
            }
        }

        $graph[] = $content;
        $graph[2]['mainEntity'] = ['@id' => $contentId];

        $isArticle = in_array($schemaType, ['Article', 'ScholarlyArticle'], true);
        $sectionPath = $isArticle
            ? ($isEnglish
                ? '/articles'
                : '/pt-BR/artigos')
            : ($isEnglish
                ? '/talks'
                : '/pt-BR/palestras');
        $sectionName = $isArticle ? ($isEnglish ? 'Articles' : 'Artigos') : ($isEnglish ? 'Talks' : 'Palestras');
        $breadcrumbId = $canonicalUrl . '#breadcrumb';

        $graph[] = [
            '@type' => 'BreadcrumbList',
            '@id' => $breadcrumbId,
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $isEnglish ? 'Home' : 'Início',
                    'item' => $siteUrl . ($isEnglish ? '/' : '/pt-BR'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $sectionName,
                    'item' => $siteUrl . $sectionPath,
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
<meta name="author" content="{{ $page->academic['author'] ?? $page->author['name'] }}">
@if (!$effectiveIndexable)
    <meta name="robots" content="noindex,nofollow,noarchive">
@else
    <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">
@endif
<link rel="canonical" href="{{ $canonicalUrl }}">
<link rel="alternate" hreflang="{{ $locale }}" href="{{ $canonicalUrl }}">
@if ($alternateCanonicalUrl)
    <link rel="alternate" hreflang="{{ $isEnglish ? 'pt-BR' : 'en' }}" href="{{ $alternateCanonicalUrl }}">
@endif
<link rel="alternate" hreflang="x-default" href="{{ $englishCanonicalUrl }}">
<link rel="alternate" type="application/rss+xml"
    title="{{ $page->siteName }} — {{ $isEnglish ? 'Articles' : 'Artigos' }}"
    href="{{ $siteUrl }}{{ $isEnglish ? '/feed.xml' : '/pt-BR/feed.xml' }}">
<meta property="og:type"
    content="{{ in_array($schemaType, ['Article', 'ScholarlyArticle'], true) ? 'article' : 'website' }}">
<meta property="og:title" content="{{ $documentTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $page->siteName }}">
<meta property="og:locale" content="{{ $isEnglish ? 'en_US' : 'pt_BR' }}">
<meta property="og:locale:alternate" content="{{ $isEnglish ? 'pt_BR' : 'en_US' }}">
<meta property="og:image" content="{{ $socialImage }}">
<meta property="og:image:secure_url" content="{{ $socialImage }}">
@if ($socialImageWidth > 0 && $socialImageHeight > 0)
    <meta property="og:image:width" content="{{ $socialImageWidth }}">
    <meta property="og:image:height" content="{{ $socialImageHeight }}">
@endif
<meta property="og:image:alt" content="{{ $socialImageAlt }}">
@if (in_array($schemaType, ['Article', 'ScholarlyArticle'], true) && ($page->date ?? false))
    <meta property="article:published_time" content="{{ date(DATE_ATOM, $page->date) }}">
@endif
@if (in_array($schemaType, ['Article', 'ScholarlyArticle'], true) && $updatedAt !== null)
    <meta property="article:modified_time" content="{{ date(DATE_ATOM, $updatedAt) }}">
@endif
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $documentTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $socialImage }}">
<meta name="twitter:image:alt" content="{{ $socialImageAlt }}">
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
