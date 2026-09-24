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
        self::assertFileExists($build . '/articles/communion-of-saints-digital-ethics/index.html');
        self::assertFileExists($build . '/talks/index.html');
        self::assertFileExists($build . '/talks/libresign-integrations/index.html');
        self::assertFileExists($build . '/pt-BR/index.html');
        self::assertFileExists($build . '/pt-BR/artigos/index.html');
        self::assertFileExists($build . '/pt-BR/artigos/comunhao-crista-etica-digital/index.html');
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

        self::assertStringContainsString('<html lang="en"', $english);
        self::assertStringContainsString('<html lang="pt-BR"', $portuguese);
        self::assertStringContainsString('hreflang="en" href="' . self::SITE_URL . '/"', $english);
        self::assertStringContainsString('hreflang="pt-BR" href="' . self::SITE_URL . '/pt-BR"', $english);
        self::assertStringContainsString('hreflang="x-default" href="' . self::SITE_URL . '/"', $english);
        self::assertStringContainsString('hreflang="en" href="' . self::SITE_URL . '/"', $portuguese);
        self::assertStringContainsString('hreflang="pt-BR" href="' . self::SITE_URL . '/pt-BR"', $portuguese);
        self::assertStringContainsString('hreflang="x-default" href="' . self::SITE_URL . '/"', $portuguese);
    }

    public function testHomeShowsSameTalksInBothSiteLanguages(): void
    {
        $english = $this->read('index.html');
        $portuguese = $this->read('pt-BR/index.html');

        preg_match_all('/<article class="home-talk-card">.*?<h3><a href="([^"]+)">([^<]+)<\/a><\/h3>.*?<\/article>/s', $english, $englishTalks, PREG_SET_ORDER);
        preg_match_all('/<article class="home-talk-card">.*?<h3><a href="([^"]+)">([^<]+)<\/a><\/h3>.*?<\/article>/s', $portuguese, $portugueseTalks, PREG_SET_ORDER);

        self::assertCount(3, $englishTalks);
        self::assertCount(3, $portugueseTalks);

        $englishItems = array_map(static fn(array $match): array => [$match[1], $match[2]], $englishTalks);
        $portugueseItems = array_map(static fn(array $match): array => [$match[1], $match[2]], $portugueseTalks);

        self::assertSame($englishItems, $portugueseItems);
    }

    public function testHomePrioritizesVerifiableProfessionalEvidence(): void
    {
        $english = $this->read('index.html');
        $portuguese = $this->read('pt-BR/index.html');

        foreach ([$english, $portuguese] as $html) {
            self::assertSame(1, preg_match_all('/<h1\\b/', $html));
            self::assertStringContainsString('https://libresign.coop/', $html);
            self::assertStringContainsString('https://github.com/LibreSign/libresign', $html);
            self::assertStringContainsString('https://www.digitalpublicgoods.net/r/libresign', $html);
            self::assertStringContainsString('https://librecode.coop/', $html);
            self::assertStringContainsString('https://github.com/LibreCodeCoop', $html);
            self::assertStringContainsString('https://github.com/PHPRio', $html);
            self::assertStringNotContainsString('Global Talent', $html);
        }

        self::assertStringContainsString('Building open technology, communities, and digital infrastructure.', $english);
        self::assertStringContainsString('Construindo tecnologia aberta, comunidades e infraestrutura digital.', $portuguese);
    }

    public function testThemeToggleIsAvailableInBothLanguages(): void
    {
        $english = $this->read('index.html');
        $portuguese = $this->read('pt-BR/index.html');

        self::assertStringContainsString('data-theme-toggle', $english);
        self::assertStringContainsString('data-label-light="Use light theme"', $english);
        self::assertStringContainsString('data-label-dark="Use dark theme"', $english);
        self::assertStringContainsString("localStorage.getItem('theme')", $english);
        self::assertStringContainsString('data-theme-toggle', $portuguese);
        self::assertStringContainsString('data-label-light="Usar tema claro"', $portuguese);
        self::assertStringContainsString('data-label-dark="Usar tema escuro"', $portuguese);
    }

    public function testLanguagePreferenceUsesBrowserLocaleUntilManuallySelected(): void
    {
        $english = $this->read('index.html');
        $portuguese = $this->read('pt-BR/index.html');

        foreach ([$english, $portuguese] as $html) {
            self::assertStringContainsString("const storageKey = 'site-locale'", $html);
            self::assertStringContainsString('navigator.languages?.[0]', $html);
            self::assertStringContainsString("browserLocale.startsWith('pt') ? 'pt-BR' : 'en'", $html);
            self::assertStringContainsString('window.location.replace(alternateUrl)', $html);
            self::assertStringContainsString('localStorage.getItem(storageKey)', $html);
            self::assertStringContainsString('localStorage.setItem(storageKey, selectedLocale)', $html);
            self::assertStringContainsString('data-language-switch', $html);
        }

        self::assertStringContainsString('data-alternate-locale="pt-BR"', $english);
        self::assertStringContainsString('data-locale="pt-BR"', $english);
        self::assertStringContainsString('data-alternate-locale="en"', $portuguese);
        self::assertStringContainsString('data-locale="en"', $portuguese);
    }

    public function testSimontonMonographUsesSourceRepositoryMetadata(): void
    {
        $monograph = $this->read('pt-BR/artigos/comunhao-crista-etica-digital/index.html');

        self::assertStringContainsString('A comunhão dos santos frente aos dilemas da ética digital', $monograph);
        self::assertStringContainsString('Seminário Teológico Presbiteriano Rev. Ashbel Green Simonton', $monograph);
        self::assertStringContainsString('Bacharel em Teologia', $monograph);
        self::assertStringContainsString('Rev. André Luís Barros Monteiro', $monograph);
        self::assertStringContainsString('Vitor Mattos de Souza', $monograph);
        self::assertStringContainsString('https://github.com/vitormattos/monografia-teologia', $monograph);
        self::assertStringContainsString('https://vitormattos.github.io/monografia-teologia/monografia.pdf', $monograph);
        self::assertStringContainsString('centralidade da comunhão dos santos', $monograph);
        self::assertStringContainsString('"@type":"ScholarlyArticle"', $monograph);
        self::assertStringNotContainsString('Do código à infraestrutura', $monograph);
    }

    public function testAssetsUseConfiguredBaseUrl(): void
    {
        $index = $this->read('index.html');
        $talk = $this->read('talks/libresign-integrations/index.html');
        $baseUrl = rtrim((string) (getenv('EXPECTED_BASE_URL') ?: self::SITE_URL), '/');

        self::assertMatchesRegularExpression('#' . preg_quote($baseUrl, '#') . '/assets/build/assets/main-[^"\']+\.css#', $index);
        self::assertMatchesRegularExpression('#' . preg_quote($baseUrl, '#') . '/assets/build/assets/presentations-[^"\']+\.css#', $talk);
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
        $article = $this->read('articles/communion-of-saints-digital-ethics/index.html');
        $canonical = self::SITE_URL . '/articles/communion-of-saints-digital-ethics';

        self::assertStringContainsString('<link rel="canonical" href="' . $canonical . '">', $article);
        self::assertStringContainsString('"@type":"ScholarlyArticle"', $article);
        self::assertStringContainsString('"@type":"Person"', $article);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $article);
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
        self::assertStringContainsString(self::SITE_URL . '/articles/communion-of-saints-digital-ethics', $sitemap);
        self::assertStringContainsString(self::SITE_URL . '/pt-BR/artigos/comunhao-crista-etica-digital', $sitemap);
        self::assertStringContainsString(self::SITE_URL . '/talks/libresign-integrations', $sitemap);
        self::assertStringContainsString(self::SITE_URL . '/pt-BR/palestras/libresign-integracoes', $sitemap);
        self::assertStringNotContainsString('/articles/from-code-to-infrastructure', $sitemap);
        self::assertStringNotContainsString('/pt-BR/artigos/do-codigo-a-infraestrutura', $sitemap);
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

        self::assertStringContainsString(self::SITE_URL . '/articles/communion-of-saints-digital-ethics', $feed);
        self::assertStringContainsString('The communion of saints in the face of digital ethics dilemmas', $llms);
        self::assertStringContainsString('# Vitor Mattos', $llms);
        self::assertStringContainsString('Sitemap: ' . self::SITE_URL . '/sitemap.xml', $llms);
    }

    public function testPublicBuildDoesNotExposeInternalApplicationPurpose(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->buildDirectory(), FilesystemIterator::SKIP_DOTS));

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
