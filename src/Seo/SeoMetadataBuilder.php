<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Seo;

use App\Presentations\PresentationThumbnailResolver;

final class SeoMetadataBuilder
{
    public function __construct(private readonly PresentationThumbnailResolver $thumbnailResolver)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(object $page): array
    {
        $locale = (string) ($page->locale ?? $page->defaultLocale);
        $isEnglish = $locale === 'en';
        $siteUrl = rtrim((string) $page->siteUrl, '/');
        $path = $this->normalizePath('/' . ltrim((string) $page->getPath(), '/'));
        $canonicalUrl = $siteUrl . ($path === '/' ? '/' : $path);
        $description = (string) ($page->description ?? $page->siteDescription);
        $pageTitle = (string) ($page->title ?? $page->siteName);
        $documentTitle = $pageTitle;
        if ($page->title ?? false) {
            $documentTitle .= ' · ' . $page->siteName;
        }

        $alternateCanonicalUrl = $this->alternateCanonicalUrl($page, $siteUrl);
        $englishCanonicalUrl = $canonicalUrl;
        if (!$isEnglish) {
            $englishCanonicalUrl = $alternateCanonicalUrl ?? $siteUrl . '/';
        }

        $schemaType = $page->schemaType ?? null;
        $isProfilePage = in_array($path, ['/', '/pt-BR'], true);
        $pageType = (string) ($page->pageType ?? 'WebPage');
        if ($isProfilePage && !($page->pageType ?? false)) {
            $pageType = 'ProfilePage';
        }

        $updatedAt = $this->updatedAt($page->updated ?? null);
        $socialImage = $this->socialImage($page, $siteUrl, $isProfilePage, $pageTitle);
        $structuredData = $this->structuredData(
            $page,
            $locale,
            $isEnglish,
            $siteUrl,
            $canonicalUrl,
            $alternateCanonicalUrl,
            $pageTitle,
            $description,
            $schemaType,
            $pageType,
            $updatedAt,
            $socialImage['url'],
        );

        $isArticle = in_array($schemaType, ['Article', 'ScholarlyArticle'], true);
        $effectiveIndexable = ($page->production ?? false) && ($page->indexable ?? false);
        $authorName = (string) ($page->academic['author'] ?? $page->author['name']);

        return [
            'locale' => $locale,
            'alternateLocale' => $isEnglish ? 'pt-BR' : 'en',
            'ogLocale' => $isEnglish ? 'en_US' : 'pt_BR',
            'ogAlternateLocale' => $isEnglish ? 'pt_BR' : 'en_US',
            'canonicalUrl' => $canonicalUrl,
            'alternateCanonicalUrl' => $alternateCanonicalUrl,
            'englishCanonicalUrl' => $englishCanonicalUrl,
            'description' => $description,
            'pageTitle' => $pageTitle,
            'documentTitle' => $documentTitle,
            'authorName' => $authorName,
            'indexable' => $effectiveIndexable,
            'rssTitle' => $page->siteName . ' — ' . ($isEnglish ? 'Articles' : 'Artigos'),
            'rssUrl' => $siteUrl . ($isEnglish ? '/feed.xml' : '/pt-BR/feed.xml'),
            'ogType' => $isArticle ? 'article' : 'website',
            'siteName' => (string) $page->siteName,
            'socialImage' => $socialImage,
            'publishedTime' => $isArticle ? $this->publishedTime($page) : null,
            'modifiedTime' => $isArticle && $updatedAt !== null ? date(DATE_ATOM, $updatedAt) : null,
            'structuredData' => $structuredData,
        ];
    }

    private function normalizePath(string $path): string
    {
        if ($path === '/') {
            return '/';
        }

        return rtrim($path, '/');
    }

    private function alternateCanonicalUrl(object $page, string $siteUrl): ?string
    {
        $alternateUrl = $this->nonEmptyString($page->alternateUrl ?? null);
        if ($alternateUrl === null) {
            return null;
        }

        $path = $this->normalizePath('/' . ltrim($alternateUrl, '/'));

        return $siteUrl . ($path === '/' ? '/' : $path);
    }

    private function updatedAt(mixed $value): ?int
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @return array{url: string, width: int, height: int, alt: string, twitterCard: string}
     */
    private function socialImage(object $page, string $siteUrl, bool $isProfilePage, string $pageTitle): array
    {
        $academic = $page->academic ?? [];
        if (!is_array($academic)) {
            $academic = [];
        }

        $image = $this->firstNonEmptyString([
            $page->socialImage ?? null,
            $page->image ?? null,
            $page->thumbnail ?? null,
            $academic['socialImage'] ?? null,
            $academic['image'] ?? null,
            $academic['thumbnail'] ?? null,
        ]);
        $width = $this->firstPositiveInt([
            $page->socialImageWidth ?? null,
            $page->imageWidth ?? null,
        ]);
        $height = $this->firstPositiveInt([
            $page->socialImageHeight ?? null,
            $page->imageHeight ?? null,
        ]);

        if ($image === null) {
            $presentationThumbnail = $this->thumbnailResolver->resolve($page);
            if ($presentationThumbnail !== null) {
                $image = $presentationThumbnail['path'];
                $width = $presentationThumbnail['width'];
                $height = $presentationThumbnail['height'];
            }
        }

        $hasPageImage = $image !== null;
        if (!$hasPageImage) {
            $image = $this->firstNonEmptyString([
                $page->author['socialImage'] ?? null,
                $page->author['avatar'] ?? null,
            ]);
            $width = 512;
            $height = 512;
        }

        if ($image === null) {
            throw new \RuntimeException('No social image or author avatar is configured.');
        }

        $alt = $this->nonEmptyString($page->socialImageAlt ?? null);
        if ($alt === null) {
            $alt = $isProfilePage ? (string) $page->author['name'] : $pageTitle;
        }

        return [
            'url' => $this->absoluteUrl($siteUrl, $image),
            'width' => $width,
            'height' => $height,
            'alt' => $alt,
            'twitterCard' => $hasPageImage ? 'summary_large_image' : 'summary',
        ];
    }

    private function absoluteUrl(string $siteUrl, string $image): string
    {
        if (preg_match('#^https?://#i', $image) === 1) {
            return $image;
        }

        if (str_starts_with($image, '//')) {
            return 'https:' . $image;
        }

        return rtrim($siteUrl, '/') . '/' . ltrim($image, '/');
    }

    private function publishedTime(object $page): ?string
    {
        if (!($page->date ?? false)) {
            return null;
        }

        return date(DATE_ATOM, (int) $page->date);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(
        object $page,
        string $locale,
        bool $isEnglish,
        string $siteUrl,
        string $canonicalUrl,
        ?string $alternateCanonicalUrl,
        string $pageTitle,
        string $description,
        mixed $schemaType,
        string $pageType,
        ?int $updatedAt,
        string $socialImage,
    ): array {
        $personId = (string) $page->author['id'];
        $websiteId = $siteUrl . '/#website';
        $webpageId = $canonicalUrl . '#webpage';
        $contentId = $canonicalUrl . '#content';
        $sameAs = [];
        foreach ($page->author['profiles'] ?? [] as $profile) {
            if ($profile['sameAs'] ?? false) {
                $sameAs[] = $profile['url'];
            }
        }

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
                $content['datePublished'] = date(DATE_ATOM, (int) $page->date);
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
            $breadcrumbId = $canonicalUrl . '#breadcrumb';
            $isArticle = in_array($schemaType, ['Article', 'ScholarlyArticle'], true);
            $sectionPath = $isEnglish ? '/talks' : '/pt-BR/palestras';
            $sectionName = $isEnglish ? 'Talks' : 'Palestras';
            if ($isArticle) {
                $sectionPath = $isEnglish ? '/articles' : '/pt-BR/artigos';
                $sectionName = $isEnglish ? 'Articles' : 'Artigos';
            }

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

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * @param list<mixed> $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            $candidate = $this->nonEmptyString($value);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param list<mixed> $values
     */
    private function firstPositiveInt(array $values): int
    {
        foreach ($values as $value) {
            $number = (int) $value;
            if ($number > 0) {
                return $number;
            }
        }

        return 0;
    }
}
