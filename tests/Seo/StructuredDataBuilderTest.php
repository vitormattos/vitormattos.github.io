<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Seo;

use App\Seo\StructuredDataBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StructuredDataBuilderTest extends TestCase
{
    #[DataProvider('schemaProvider')]
    public function testBuildsContentAndBreadcrumbForSupportedSchemas(
        string $schemaType,
        bool $isEnglish,
        string $expectedSection,
    ): void {
        $page = $this->page();
        $urlData = [
            'locale' => $isEnglish ? 'en' : 'pt-BR',
            'isEnglish' => $isEnglish,
            'siteUrl' => 'https://example.com',
            'canonicalUrl' => 'https://example.com/example',
            'pageType' => 'WebPage',
        ];

        $result = (new StructuredDataBuilder())->build(
            $page,
            $urlData,
            'Example',
            'Description',
            $schemaType,
            null,
            'https://example.com/image.png',
        );

        self::assertSame($schemaType, $result['@graph'][3]['@type']);
        self::assertSame('BreadcrumbList', $result['@graph'][4]['@type']);
        self::assertSame($expectedSection, $result['@graph'][4]['itemListElement'][1]['name']);
    }

    public static function schemaProvider(): iterable
    {
        yield 'english article' => ['Article', true, 'Articles'];
        yield 'portuguese article' => ['Article', false, 'Artigos'];
        yield 'english scholarly article' => ['ScholarlyArticle', true, 'Articles'];
        yield 'portuguese talk' => ['CreativeWork', false, 'Palestras'];
    }

    public function testPlainPageDoesNotCreateContentOrBreadcrumbNodes(): void
    {
        $page = $this->page();
        $urlData = [
            'locale' => 'en',
            'isEnglish' => true,
            'siteUrl' => 'https://example.com',
            'canonicalUrl' => 'https://example.com/about',
            'pageType' => 'WebPage',
        ];

        $result = (new StructuredDataBuilder())->build(
            $page,
            $urlData,
            'About',
            'Description',
            null,
            null,
            'https://example.com/image.png',
        );

        self::assertCount(3, $result['@graph']);
        self::assertArrayNotHasKey('mainEntity', $result['@graph'][2]);
    }

    public function testScholarlyArticleAddsAcademicMetadata(): void
    {
        $page = $this->page();
        $page->academic = [
            'author' => 'Academic Author',
            'institution' => 'Example University',
            'keywords' => ['free software'],
        ];
        $urlData = [
            'locale' => 'en',
            'isEnglish' => true,
            'siteUrl' => 'https://example.com',
            'canonicalUrl' => 'https://example.com/article',
            'pageType' => 'WebPage',
        ];

        $result = (new StructuredDataBuilder())->build(
            $page,
            $urlData,
            'Article',
            'Description',
            'ScholarlyArticle',
            null,
            'https://example.com/image.png',
        );
        $content = $result['@graph'][3];

        self::assertSame('Academic Author', $content['author']['name']);
        self::assertSame('Example University', $content['sourceOrganization']['name']);
        self::assertSame(['free software'], $content['keywords']);
    }

    private function page(): SeoPageStub
    {
        return new SeoPageStub('/example', [
            'siteName' => 'Vitor Mattos',
            'siteDescription' => 'Site description',
            'locales' => ['en', 'pt-BR'],
            'author' => [
                'name' => 'Vitor Mattos',
                'id' => 'https://example.com/#person',
                'avatar' => 'https://example.com/avatar.png',
                'socialImage' => 'https://example.com/avatar-512.png',
                'profiles' => [],
                'knowsAbout' => ['PHP'],
                'organization' => [
                    'name' => 'LibreCode',
                    'url' => 'https://librecode.coop/',
                ],
            ],
        ]);
    }
}
