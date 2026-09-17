<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Seo;

final class SeoMetadataBuilder
{
    public function __construct(
        private readonly PageUrlResolver $urlResolver,
        private readonly SocialImageResolver $socialImageResolver,
        private readonly StructuredDataBuilder $structuredDataBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(object $page): array
    {
        $urlData = $this->urlResolver->resolve($page);
        $pageTitle = (string) ($page->title ?? $page->siteName);
        $description = (string) ($page->description ?? $page->siteDescription);
        $schemaType = $page->schemaType ?? null;
        $updatedAt = $this->timestamp($page->updated ?? null);
        $socialImage = $this->socialImageResolver->resolve(
            $page,
            $urlData['siteUrl'],
            $urlData['isProfilePage'],
            $pageTitle,
        );

        return [
            ...$this->localeMetadata($urlData),
            ...$this->documentMetadata($page, $pageTitle, $description, $schemaType, $updatedAt),
            'canonicalUrl' => $urlData['canonicalUrl'],
            'alternateCanonicalUrl' => $urlData['alternateCanonicalUrl'],
            'englishCanonicalUrl' => $urlData['englishCanonicalUrl'],
            'socialImage' => $socialImage,
            'structuredData' => $this->structuredDataBuilder->build(
                $page,
                $urlData,
                $pageTitle,
                $description,
                $schemaType,
                $updatedAt,
                $socialImage['url'],
            ),
        ];
    }

    /**
     * @param array{locale: string, isEnglish: bool, siteUrl: string} $urlData
     *
     * @return array<string, string>
     */
    private function localeMetadata(array $urlData): array
    {
        if ($urlData['isEnglish']) {
            return [
                'locale' => $urlData['locale'],
                'alternateLocale' => 'pt-BR',
                'ogLocale' => 'en_US',
                'ogAlternateLocale' => 'pt_BR',
            ];
        }

        return [
            'locale' => $urlData['locale'],
            'alternateLocale' => 'en',
            'ogLocale' => 'pt_BR',
            'ogAlternateLocale' => 'en_US',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentMetadata(
        object $page,
        string $pageTitle,
        string $description,
        mixed $schemaType,
        ?int $updatedAt,
    ): array {
        $isEnglish = ($page->locale ?? $page->defaultLocale) === 'en';
        $isArticle = in_array($schemaType, ['Article', 'ScholarlyArticle'], true);
        $documentTitle = $pageTitle;
        if ($page->title ?? false) {
            $documentTitle .= ' · ' . $page->siteName;
        }

        return [
            'description' => $description,
            'pageTitle' => $pageTitle,
            'documentTitle' => $documentTitle,
            'authorName' => (string) ($page->academic['author'] ?? $page->author['name']),
            'indexable' => ($page->production ?? false) && ($page->indexable ?? false),
            'rssTitle' => $page->siteName . ' — ' . ($isEnglish ? 'Articles' : 'Artigos'),
            'rssUrl' => rtrim((string) $page->siteUrl, '/') . ($isEnglish ? '/feed.xml' : '/pt-BR/feed.xml'),
            'ogType' => $isArticle ? 'article' : 'website',
            'siteName' => (string) $page->siteName,
            'publishedTime' => $isArticle ? $this->publishedTime($page) : null,
            'modifiedTime' => $isArticle && $updatedAt !== null ? date(DATE_ATOM, $updatedAt) : null,
        ];
    }

    private function publishedTime(object $page): ?string
    {
        if (!($page->date ?? false)) {
            return null;
        }

        return date(DATE_ATOM, (int) $page->date);
    }

    private function timestamp(mixed $value): ?int
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
}
