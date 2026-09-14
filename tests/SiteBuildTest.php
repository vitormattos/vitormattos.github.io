<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SiteBuildTest extends TestCase
{
    private const SITE_URL = 'https://vitormattos.github.io';
    private const INDEXABLE_ROBOTS = '<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">';
    private const NOINDEX_ROBOTS = '<meta name="robots" content="noindex,nofollow,noarchive">';

    private function buildDirectory(): string
    {
        return __DIR__ . '/../' . (getenv('SITE_BUILD_DIR') ?: 'build_production');
    }

    private function isPreview(): bool
    {
        return (getenv('SITE_BUILD_DIR') ?: 'build_production') === 'build_preview';
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->buildDirectory() . '/' . ltrim($path, '/'));
        self::assertIsString($contents);

        return $contents;
    }

    public function testBuildContainsExpectedPages(): void
    {
        $build = $this->buildDirectory();

        self::assertFileExists($build . '/index.html');
        self::assertFileExists($build . '/articles/index.html');
        self::assertFileExists($build . '/articles/from-code-to-infrastructure/index.html');
        self::assertFileExists($build . '/talks/index.html');
        self::assertFileExists($build . '/talks/libresign-integrations/index.html');
        self::assertFileExists($build . '/pt-BR/index.html');
        self::assertFileExists($build . '/pt-BR/artigos/index.html');
        self::assertFileExists($build . '/pt-BR/artigos/do-codigo-a-infraestrutura/index.html');
        self::assertFileExists($build . '/pt-BR/palestras/index.html');
        self::assertFileExists($build . '/pt-BR/palestras/libresign-integracoes/index.html');
        self::assertFileExists($build . '/404.html');
        self::assertFileExists($build . '/feed.xml');
        self::assertFileExists($build . '/pt-BR/feed.xml');
        self::assertFileExists($build . '/llms.txt');
        self::assertFileExists($build . '/robots.txt');
    }

    public function testLocalizedPagesExposeCorrectLanguageAndHreflang(): void
    {
        $english = $this->read('index.html');
        $portuguese = $this->read('pt-BR/index.html');

        self::assertStringContainsString('<html lang="en">', $english);
        self::assertStringContainsString('<html lang="pt-BR">', $portuguese);
        self::assertStringContainsString('hreflang="en" href="' . self::SITE_URL . '/"', $english);
        self::assertStringContainsString('hreflang="pt-BR" href="' . self::SITE_URL . '/pt-BR"', $english);
        self::assertStringContainsString('hreflang="x-default" href="' . self::SITE_URL . '/"', $english);
        self::assertStringContainsString('hreflang="en" href="' . self::SITE_URL . '/"', $portuguese);
        self::assertStringContainsString('hreflang="pt-BR" href="' . self::SITE_URL . '/pt-BR"', $portuguese);
        self::assertStringContainsString('hreflang="x-default" href="' . self::SITE_URL . '/"', $portuguese);
    }

    public function testFirstArticleContainsSubstantiveContent(): void
    {
        $article = $this->read('articles/from-code-to-infrastructure/index.html');

        self::assertStringContainsString('From Code to Infrastructure', $article);
        self::assertStringContainsString('Free software does not mean that development and maintenance have no cost.', $article);
        self::assertStringContainsString('Technical implementation is only part of the work', $article);
        self::assertStringContainsString('Community is infrastructure too', $article);
    }

    public function testAssetsUseConfiguredBaseUrl(): void
    {
        $index = $this->read('index.html');
        $talk = $this->read('talks/libresign-integrations/index.html');
        $baseUrl = rtrim((string) (getenv('EXPECTED_BASE_URL') ?: self::SITE_URL), '/');

        self::assertMatchesRegularExpression(
            '#' . preg_quote($baseUrl, '#') . '/assets/build/assets/main-[^"\']+\.css#',
            $index,
        );
        self::assertMatchesRegularExpression(
            '#' . preg_quote($baseUrl, '#') . '/assets/build/assets/presentations-[^"\']+\.css#',
            $talk,
        );
    }

    public function testRealLibreSignTalkUsesSlidesComPresentation(): void
    {
        $index = $this->read('talks/index.html');
        $talk = $this->read('talks/libresign-integrations/index.html');
        $portugueseTalk = $this->read('pt-BR/palestras/libresign-integracoes/index.html');

        self::assertStringContainsString('LibreSign - Integrações', $talk);
        self::assertStringContainsString('https://slides.com/vitormattos/libresign-integracao/embed', $talk);
        self::assertStringContainsString('https://youtu.be/WJpe_NnmW8o', $talk);
        self::assertStringContainsString('slides.com', $index);
        self::assertStringContainsString('LibreSign - Integrações', $portugueseTalk);
        self::assertStringNotContainsString('Initial structure to register talks', $talk);
        self::assertStringNotContainsString('structural example', $talk);
    }

    public function testCanonicalAndStructuredDataAlwaysUseProductionUrl(): void
    {
        $article = $this->read('articles/from-code-to-infrastructure/index.html');
        $canonical = self::SITE_URL . '/articles/from-code-to-infrastructure';

        self::assertStringContainsString('<link rel="canonical" href="' . $canonical . '">', $article);
        self::assertStringContainsString('"@type":"Article"', $article);
        self::assertStringContainsString('"@type":"Person"', $article);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $article);
        self::assertStringContainsString('"name":"Vitor Mattos"', $article);
        self::assertStringContainsString('"url":"' . $canonical . '"', $article);
        self::assertStringContainsString(self::SITE_URL . '/articles', $article);
        self::assertStringNotContainsString('/pr-preview/', $this->extractCanonicalLine($article));
    }

    public function testIndexingDirectivesMatchEnvironment(): void
    {
        $index = $this->read('index.html');
        $robots = $this->read('robots.txt');
        $notFound = $this->read('404.html');
        $realTalk = $this->read('talks/libresign-integrations/index.html');

        self::assertStringContainsString(self::NOINDEX_ROBOTS, $notFound);

        if ($this->isPreview()) {
            // Preview policy overrides the page-level indexable flag for every page.
            self::assertStringContainsString(self::NOINDEX_ROBOTS, $index);
            self::assertStringContainsString(self::NOINDEX_ROBOTS, $realTalk);
            self::assertStringNotContainsString(self::INDEXABLE_ROBOTS, $realTalk);
            self::assertStringContainsString("Disallow: /", $robots);
            self::assertFileDoesNotExist($this->buildDirectory() . '/sitemap.xml');

            return;
        }

        self::assertStringContainsString(self::INDEXABLE_ROBOTS, $index);
        self::assertStringContainsString(self::INDEXABLE_ROBOTS, $realTalk);
        self::assertStringNotContainsString(self::NOINDEX_ROBOTS, $realTalk);
        self::assertStringContainsString('Disallow: /pr-preview/', $robots);
        self::assertStringContainsString('Sitemap: ' . self::SITE_URL . '/sitemap.xml', $robots);
        self::assertFileExists($this->buildDirectory() . '/sitemap.xml');
    }

    public function testSitemapContainsOnlyCanonicalHtmlDocuments(): void
    {
        if ($this->isPreview()) {
            self::markTestSkipped('Preview builds intentionally do not generate a sitemap.');
        }

        $sitemap = $this->read('sitemap.xml');

        self::assertStringContainsString(self::SITE_URL . '/articles', $sitemap);
        self::assertStringContainsString(self::SITE_URL . '/articles/from-code-to-infrastructure', $sitemap);
        self::assertStringContainsString(self::SITE_URL . '/pt-BR/artigos/do-codigo-a-infraestrutura', $sitemap);
        self::assertStringContainsString(self::SITE_URL . '/talks/libresign-integrations', $sitemap);
        self::assertStringContainsString(self::SITE_URL . '/pt-BR/palestras/libresign-integracoes', $sitemap);
        self::assertStringNotContainsString('/404.html', $sitemap);
        self::assertStringNotContainsString('/pr-preview/', $sitemap);
        self::assertStringNotContainsString('/feed.xml', $sitemap);
        self::assertStringNotContainsString('/llms.txt', $sitemap);
        self::assertStringNotContainsString('/presentations/', $sitemap);
    }

    public function testFeedsAndMachineReadableGuideUseCanonicalUrls(): void
    {
        $feed = $this->read('feed.xml');
        $llms = $this->read('llms.txt');

        self::assertStringContainsString(self::SITE_URL . '/articles/from-code-to-infrastructure', $feed);
        self::assertStringContainsString('# Vitor Mattos', $llms);
        self::assertStringContainsString('Sitemap: ' . self::SITE_URL . '/sitemap.xml', $llms);
    }

    public function testPublicBuildDoesNotExposeInternalApplicationPurpose(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->buildDirectory(), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['html', 'txt', 'xml'], true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertStringNotContainsString('Global Talent', $contents, $file->getPathname());
        }
    }

    private function extractCanonicalLine(string $html): string
    {
        preg_match('/<link rel="canonical"[^>]+>/', $html, $matches);

        return $matches[0] ?? '';
    }
}
