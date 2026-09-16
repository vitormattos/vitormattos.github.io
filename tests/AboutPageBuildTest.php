<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AboutPageBuildTest extends TestCase
{
    private const SITE_URL = 'https://vitormattos.github.io';

    private function buildDirectory(): string
    {
        return __DIR__ . '/../' . (getenv('SITE_BUILD_DIR') ?: 'build_production');
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->buildDirectory() . '/' . ltrim($path, '/'));
        self::assertIsString($contents);

        return $contents;
    }

    private function assertLinkExists(string $html, string $href, string $label): void
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertTrue($loaded);

        foreach ($document->getElementsByTagName('a') as $link) {
            if ($link->getAttribute('href') === $href && trim($link->textContent) === $label) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf('Failed asserting that link "%s" points to "%s".', $label, $href));
    }

    public function testLocalizedAboutPagesAreBuiltAndLinkedFromNavigation(): void
    {
        $build = $this->buildDirectory();

        self::assertFileExists($build . '/about/index.html');
        self::assertFileExists($build . '/pt-BR/sobre/index.html');

        $englishHome = $this->read('index.html');
        $portugueseHome = $this->read('pt-BR/index.html');
        $baseUrl = rtrim(getenv('EXPECTED_BASE_URL') ?: self::SITE_URL, '/');

        $this->assertLinkExists($englishHome, $baseUrl . '/about', 'About');
        $this->assertLinkExists($portugueseHome, $baseUrl . '/pt-BR/sobre', 'Sobre');
    }

    public function testAboutPagesExposeReciprocalLanguageAlternatesAndCanonicalUrls(): void
    {
        $english = $this->read('about/index.html');
        $portuguese = $this->read('pt-BR/sobre/index.html');

        self::assertStringContainsString('<link rel="canonical" href="' . self::SITE_URL . '/about">', $english);
        self::assertStringContainsString('hreflang="pt-BR" href="' . self::SITE_URL . '/pt-BR/sobre"', $english);
        self::assertStringContainsString('<link rel="canonical" href="' . self::SITE_URL . '/pt-BR/sobre">', $portuguese);
        self::assertStringContainsString('hreflang="en" href="' . self::SITE_URL . '/about"', $portuguese);
    }

    public function testAboutCopyDescribesPublicProfessionalWorkWithoutApplicationLanguage(): void
    {
        $english = $this->read('about/index.html');
        $portuguese = $this->read('pt-BR/sobre/index.html');

        self::assertStringContainsString('LibreCode', $english);
        self::assertStringContainsString('LibreSign', $english);
        self::assertStringContainsString('PHPRio', $english);
        self::assertStringContainsString('Digital Public Good', $english);
        self::assertStringContainsString('LibreCode', $portuguese);
        self::assertStringContainsString('LibreSign', $portuguese);
        self::assertStringNotContainsString('PHPWomenBR', $english);
        self::assertStringNotContainsString('PHPWomenBR', $portuguese);
        self::assertStringNotContainsString('Global Talent', $english);
        self::assertStringNotContainsString('Global Talent', $portuguese);
    }
}
