<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Seo;

use App\Presentations\PresentationThumbnailResolver;
use App\Seo\SeoMetadataBuilder;
use PHPUnit\Framework\TestCase;

final class SeoMetadataBuilderTest extends TestCase
{
    private string $projectRoot;
    private SeoMetadataBuilder $builder;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/seo-metadata-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot, 0777, true);
        $this->builder = new SeoMetadataBuilder(new PresentationThumbnailResolver($this->projectRoot));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);
    }

    public function testProfilePageUsesAuthorImageAsFallback(): void
    {
        $page = $this->page('/');

        $metadata = $this->builder->build($page);

        self::assertSame('https://vitormattos.github.io/', $metadata['canonicalUrl']);
        self::assertSame('summary', $metadata['socialImage']['twitterCard']);
        self::assertSame('https://example.com/avatar-512.png', $metadata['socialImage']['url']);
        self::assertSame(512, $metadata['socialImage']['width']);
        self::assertSame(512, $metadata['socialImage']['height']);
        self::assertSame('Vitor Mattos', $metadata['socialImage']['alt']);
        self::assertSame('ProfilePage', $metadata['structuredData']['@graph'][2]['@type']);
    }

    public function testArticleUsesExplicitSocialImageAndArticleMetadata(): void
    {
        $page = $this->page('/pt-BR/artigos/exemplo/');
        $page->locale = 'pt-BR';
        $page->title = 'Artigo de exemplo';
        $page->description = 'Descrição do artigo.';
        $page->schemaType = 'Article';
        $page->date = strtotime('2026-09-10 12:00:00 UTC');
        $page->updated = '2026-09-11';
        $page->socialImage = '/images/article.png';
        $page->socialImageWidth = 1200;
        $page->socialImageHeight = 630;
        $page->alternateUrl = '/articles/example/';

        $metadata = $this->builder->build($page);

        self::assertSame('https://vitormattos.github.io/pt-BR/artigos/exemplo', $metadata['canonicalUrl']);
        self::assertSame('https://vitormattos.github.io/articles/example', $metadata['alternateCanonicalUrl']);
        self::assertSame('https://vitormattos.github.io/articles/example', $metadata['englishCanonicalUrl']);
        self::assertSame('article', $metadata['ogType']);
        self::assertSame('summary_large_image', $metadata['socialImage']['twitterCard']);
        self::assertSame('https://vitormattos.github.io/images/article.png', $metadata['socialImage']['url']);
        self::assertSame(1200, $metadata['socialImage']['width']);
        self::assertSame(630, $metadata['socialImage']['height']);
        self::assertNotNull($metadata['publishedTime']);
        self::assertNotNull($metadata['modifiedTime']);

        $graph = $metadata['structuredData']['@graph'];
        self::assertSame('Article', $graph[3]['@type']);
        self::assertSame('BreadcrumbList', $graph[4]['@type']);
        self::assertSame('Artigos', $graph[4]['itemListElement'][1]['name']);
    }

    public function testAcademicImageIsUsedWhenArticleHasNoTopLevelImage(): void
    {
        $page = $this->page('/pt-BR/artigos/academico');
        $page->locale = 'pt-BR';
        $page->title = 'Artigo acadêmico';
        $page->schemaType = 'ScholarlyArticle';
        $page->academic = [
            'author' => 'Vitor Mattos de Souza',
            'image' => 'https://example.com/paper-cover.jpg',
            'institution' => 'Universidade de Exemplo',
            'keywords' => ['software livre'],
        ];

        $metadata = $this->builder->build($page);

        self::assertSame('https://example.com/paper-cover.jpg', $metadata['socialImage']['url']);
        self::assertSame('summary_large_image', $metadata['socialImage']['twitterCard']);
        self::assertSame('Vitor Mattos de Souza', $metadata['authorName']);
        self::assertSame('ScholarlyArticle', $metadata['structuredData']['@graph'][3]['@type']);
    }

    public function testTalkUsesArchivedPresentationThumbnail(): void
    {
        $this->writeOnePixelPng('presentations/slides.com/1659891/thumbnail.png');
        $page = $this->page('/talks/bdd');
        $page->title = 'BDD + PHP = Behat';
        $page->schemaType = 'CreativeWork';
        $page->slidesId = 1659891;
        $page->presentation = [
            'type' => 'slides.com',
            'thumbnail' => 'https://example.com/remote-thumbnail.png',
        ];

        $metadata = $this->builder->build($page);

        self::assertSame(
            'https://vitormattos.github.io/presentations/slides.com/1659891/thumbnail.png',
            $metadata['socialImage']['url'],
        );
        self::assertSame(1, $metadata['socialImage']['width']);
        self::assertSame(1, $metadata['socialImage']['height']);
        self::assertSame('summary_large_image', $metadata['socialImage']['twitterCard']);
        self::assertSame('CreativeWork', $metadata['structuredData']['@graph'][3]['@type']);
        self::assertSame('Talks', $metadata['structuredData']['@graph'][4]['itemListElement'][1]['name']);
    }

    public function testInvalidUpdatedDateDoesNotEmitModifiedTime(): void
    {
        $page = $this->page('/articles/example');
        $page->title = 'Example';
        $page->schemaType = 'Article';
        $page->updated = 'not-a-date';

        $metadata = $this->builder->build($page);

        self::assertNull($metadata['modifiedTime']);
        self::assertArrayNotHasKey('dateModified', $metadata['structuredData']['@graph'][3]);
    }

    private function page(string $path): SeoTestPage
    {
        return new SeoTestPage($path, [
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
        ]);
    }

    private function writeOnePixelPng(string $relativePath): void
    {
        $path = $this->projectRoot . '/' . $relativePath;
        mkdir(dirname($path), 0777, true);
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($bytes);
        file_put_contents($path, $bytes);
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
            if (is_dir($entryPath)) {
                $this->deleteDirectory($entryPath);
            } else {
                unlink($entryPath);
            }
        }

        rmdir($path);
    }
}

#[\AllowDynamicProperties]
final class SeoTestPage
{
    public function __construct(private readonly string $path, array $properties)
    {
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
