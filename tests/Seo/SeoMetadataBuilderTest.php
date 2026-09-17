<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Seo;

use App\Presentations\PresentationThumbnailResolver;
use App\Seo\PageUrlResolver;
use App\Seo\SeoMetadataBuilder;
use App\Seo\SocialImageResolver;
use App\Seo\StructuredDataBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeoMetadataBuilderTest extends TestCase
{
    private string $projectRoot;
    private SeoMetadataBuilder $builder;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/seo-metadata-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot, 0777, true);
        $thumbnailResolver = new PresentationThumbnailResolver($this->projectRoot);
        $this->builder = new SeoMetadataBuilder(
            new PageUrlResolver(),
            new SocialImageResolver($thumbnailResolver),
            new StructuredDataBuilder(),
        );
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);
    }

    #[DataProvider('pageMetadataProvider')]
    public function testBuildsPageMetadata(array $overrides, array $expected): void
    {
        $page = $this->page($overrides['path'] ?? '/', $overrides);
        $metadata = $this->builder->build($page);

        foreach ($expected as $key => $value) {
            self::assertSame($value, $metadata[$key]);
        }
    }

    public static function pageMetadataProvider(): iterable
    {
        yield 'profile page' => [
            ['path' => '/'],
            [
                'canonicalUrl' => 'https://vitormattos.github.io/',
                'ogType' => 'website',
                'authorName' => 'Vitor Mattos',
            ],
        ];

        yield 'portuguese article' => [
            [
                'path' => '/pt-BR/artigos/exemplo/',
                'locale' => 'pt-BR',
                'title' => 'Artigo de exemplo',
                'description' => 'Descrição do artigo.',
                'schemaType' => 'Article',
                'alternateUrl' => '/articles/example/',
            ],
            [
                'canonicalUrl' => 'https://vitormattos.github.io/pt-BR/artigos/exemplo',
                'alternateCanonicalUrl' => 'https://vitormattos.github.io/articles/example',
                'englishCanonicalUrl' => 'https://vitormattos.github.io/articles/example',
                'ogType' => 'article',
            ],
        ];
    }

    public function testBuildsArticleStructuredDataAndDates(): void
    {
        $page = $this->page('/articles/example', [
            'title' => 'Example',
            'schemaType' => 'Article',
            'date' => strtotime('2026-09-10 12:00:00 UTC'),
            'updated' => '2026-09-11',
            'socialImage' => '/images/article.png',
            'socialImageWidth' => 1200,
            'socialImageHeight' => 630,
        ]);

        $metadata = $this->builder->build($page);
        $graph = $metadata['structuredData']['@graph'];

        self::assertSame('Article', $graph[3]['@type']);
        self::assertSame('BreadcrumbList', $graph[4]['@type']);
        self::assertNotNull($metadata['publishedTime']);
        self::assertNotNull($metadata['modifiedTime']);
        self::assertSame('summary_large_image', $metadata['socialImage']['twitterCard']);
    }

    public function testInvalidUpdatedDateDoesNotEmitModifiedTime(): void
    {
        $page = $this->page('/articles/example', [
            'title' => 'Example',
            'schemaType' => 'Article',
            'updated' => 'not-a-date',
        ]);

        $metadata = $this->builder->build($page);

        self::assertNull($metadata['modifiedTime']);
        self::assertArrayNotHasKey('dateModified', $metadata['structuredData']['@graph'][3]);
    }

    private function page(string $path, array $overrides = []): SeoPageStub
    {
        unset($overrides['path']);

        return new SeoPageStub($path, array_replace_recursive([
            'siteUrl' => 'https://vitormattos.github.io',
            'siteName' => 'Vitor Mattos',
            'siteDescription' => 'Site description',
            'defaultLocale' => 'en',
            'locale' => 'en',
            'locales' => ['en', 'pt-BR'],
            'production' => true,
            'indexable' => true,
            'author' => [
                'name' => 'Vitor Mattos',
                'id' => 'https://vitormattos.github.io/#person',
                'avatar' => 'https://example.com/avatar.png',
                'socialImage' => 'https://example.com/avatar-512.png',
                'profiles' => [
                    'github' => [
                        'url' => 'https://github.com/vitormattos',
                        'sameAs' => true,
                    ],
                ],
                'knowsAbout' => ['PHP', 'Free software'],
                'organization' => [
                    'name' => 'LibreCode',
                    'url' => 'https://librecode.coop/',
                ],
            ],
        ], $overrides));
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;
            is_dir($entryPath) ? $this->deleteDirectory($entryPath) : unlink($entryPath);
        }

        rmdir($path);
    }
}
