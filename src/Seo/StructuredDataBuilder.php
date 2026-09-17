<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Seo;

final class StructuredDataBuilder
{
    /**
     * @param array{locale: string, isEnglish: bool, siteUrl: string, canonicalUrl: string, pageType: string} $urlData
     *
     * @return array<string, mixed>
     */
    public function build(
        object $page,
        array $urlData,
        string $pageTitle,
        string $description,
        mixed $schemaType,
        ?int $updatedAt,
        string $socialImage,
    ): array {
        $ids = $this->ids($page, $urlData['siteUrl'], $urlData['canonicalUrl']);
        $graph = $this->baseGraph($page, $urlData, $pageTitle, $description, $socialImage, $ids);

        if ($urlData['pageType'] === 'ProfilePage') {
            $graph[2]['mainEntity'] = ['@id' => $ids['person']];
        }

        if (!$this->hasContentSchema($schemaType)) {
            return $this->wrap($graph);
        }

        $graph[] = $this->contentNode(
            $page,
            $urlData,
            $pageTitle,
            $description,
            $schemaType,
            $updatedAt,
            $socialImage,
            $ids,
        );
        $graph[2]['mainEntity'] = ['@id' => $ids['content']];
        $graph[] = $this->breadcrumbNode($pageTitle, $schemaType, $urlData, $ids['breadcrumb']);
        $graph[2]['breadcrumb'] = ['@id' => $ids['breadcrumb']];

        return $this->wrap($graph);
    }

    /**
     * @return array{person: string, website: string, webpage: string, content: string, breadcrumb: string}
     */
    private function ids(object $page, string $siteUrl, string $canonicalUrl): array
    {
        return [
            'person' => (string) $page->author['id'],
            'website' => $siteUrl . '/#website',
            'webpage' => $canonicalUrl . '#webpage',
            'content' => $canonicalUrl . '#content',
            'breadcrumb' => $canonicalUrl . '#breadcrumb',
        ];
    }

    /**
     * @param array{locale: string, isEnglish: bool, siteUrl: string, canonicalUrl: string, pageType: string} $urlData
     * @param array{person: string, website: string, webpage: string, content: string, breadcrumb: string} $ids
     *
     * @return list<array<string, mixed>>
     */
    private function baseGraph(
        object $page,
        array $urlData,
        string $pageTitle,
        string $description,
        string $socialImage,
        array $ids,
    ): array {
        return [
            [
                '@type' => 'WebSite',
                '@id' => $ids['website'],
                'url' => $urlData['siteUrl'] . '/',
                'name' => $page->siteName,
                'description' => $page->siteDescription,
                'inLanguage' => $page->locales,
            ],
            [
                '@type' => 'Person',
                '@id' => $ids['person'],
                'name' => $page->author['name'],
                'url' => $urlData['siteUrl'] . '/',
                'image' => $page->author['socialImage'] ?? $page->author['avatar'],
                'sameAs' => $this->sameAs($page),
                'knowsAbout' => $page->author['knowsAbout'],
                'worksFor' => [
                    '@type' => 'Organization',
                    'name' => $page->author['organization']['name'],
                    'url' => $page->author['organization']['url'],
                ],
            ],
            [
                '@type' => $urlData['pageType'],
                '@id' => $ids['webpage'],
                'url' => $urlData['canonicalUrl'],
                'name' => $pageTitle,
                'description' => $description,
                'image' => $socialImage,
                'primaryImageOfPage' => [
                    '@type' => 'ImageObject',
                    'url' => $socialImage,
                ],
                'inLanguage' => $urlData['locale'],
                'isPartOf' => ['@id' => $ids['website']],
                'about' => ['@id' => $ids['person']],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function sameAs(object $page): array
    {
        $sameAs = [];
        foreach ($page->author['profiles'] ?? [] as $profile) {
            if ($profile['sameAs'] ?? false) {
                $sameAs[] = $profile['url'];
            }
        }

        return $sameAs;
    }

    private function hasContentSchema(mixed $schemaType): bool
    {
        return in_array($schemaType, ['Article', 'ScholarlyArticle', 'CreativeWork'], true);
    }

    /**
     * @param array{locale: string, isEnglish: bool, siteUrl: string, canonicalUrl: string, pageType: string} $urlData
     * @param array{person: string, website: string, webpage: string, content: string, breadcrumb: string} $ids
     *
     * @return array<string, mixed>
     */
    private function contentNode(
        object $page,
        array $urlData,
        string $pageTitle,
        string $description,
        string $schemaType,
        ?int $updatedAt,
        string $socialImage,
        array $ids,
    ): array {
        $content = [
            '@type' => $schemaType,
            '@id' => $ids['content'],
            'url' => $urlData['canonicalUrl'],
            'name' => $pageTitle,
            'headline' => $pageTitle,
            'description' => $description,
            'image' => $socialImage,
            'inLanguage' => $urlData['locale'],
            'author' => ['@id' => $ids['person']],
            'publisher' => ['@id' => $ids['person']],
            'mainEntityOfPage' => ['@id' => $ids['webpage']],
        ];

        $this->addDates($content, $page, $updatedAt);
        $this->addAcademicData($content, $page, $schemaType, $ids['person'], $urlData['siteUrl']);

        return $content;
    }

    /**
     * @param array<string, mixed> $content
     */
    private function addDates(array &$content, object $page, ?int $updatedAt): void
    {
        if ($page->date ?? false) {
            $content['datePublished'] = date(DATE_ATOM, (int) $page->date);
        } elseif ($page->year ?? false) {
            $content['datePublished'] = (string) $page->year;
        }

        if ($updatedAt !== null) {
            $content['dateModified'] = date(DATE_ATOM, $updatedAt);
        }
    }

    /**
     * @param array<string, mixed> $content
     */
    private function addAcademicData(
        array &$content,
        object $page,
        string $schemaType,
        string $personId,
        string $siteUrl,
    ): void {
        if ($schemaType !== 'ScholarlyArticle' || !is_array($page->academic ?? null)) {
            return;
        }

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

    /**
     * @param array{locale: string, isEnglish: bool, siteUrl: string, canonicalUrl: string, pageType: string} $urlData
     *
     * @return array<string, mixed>
     */
    private function breadcrumbNode(string $pageTitle, string $schemaType, array $urlData, string $breadcrumbId): array
    {
        [$sectionPath, $sectionName] = $this->section($schemaType, $urlData['isEnglish']);

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $breadcrumbId,
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $urlData['isEnglish'] ? 'Home' : 'Início',
                    'item' => $urlData['siteUrl'] . ($urlData['isEnglish'] ? '/' : '/pt-BR'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $sectionName,
                    'item' => $urlData['siteUrl'] . $sectionPath,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $pageTitle,
                    'item' => $urlData['canonicalUrl'],
                ],
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function section(string $schemaType, bool $isEnglish): array
    {
        if (in_array($schemaType, ['Article', 'ScholarlyArticle'], true)) {
            return $isEnglish ? ['/articles', 'Articles'] : ['/pt-BR/artigos', 'Artigos'];
        }

        return $isEnglish ? ['/talks', 'Talks'] : ['/pt-BR/palestras', 'Palestras'];
    }

    /**
     * @param list<array<string, mixed>> $graph
     *
     * @return array<string, mixed>
     */
    private function wrap(array $graph): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
