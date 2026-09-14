<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SiteBuildTest extends TestCase
{
    private function buildDirectory(): string
    {
        return __DIR__ . '/../' . (getenv('SITE_BUILD_DIR') ?: 'build_production');
    }

    public function testBuildContainsExpectedPages(): void
    {
        $build = $this->buildDirectory();

        self::assertFileExists($build . '/index.html');
        self::assertFileExists($build . '/articles/from-code-to-infrastructure/index.html');
        self::assertFileExists($build . '/talks/free-software/index.html');
        self::assertFileExists($build . '/pt-BR/index.html');
        self::assertFileExists($build . '/pt-BR/artigos/do-codigo-a-infraestrutura/index.html');
        self::assertFileExists($build . '/pt-BR/palestras/software-livre/index.html');
    }

    public function testLocalizedPagesExposeCorrectLanguage(): void
    {
        $build = $this->buildDirectory();
        $english = file_get_contents($build . '/index.html');
        $portuguese = file_get_contents($build . '/pt-BR/index.html');

        self::assertIsString($english);
        self::assertIsString($portuguese);
        self::assertStringContainsString('<html lang="en">', $english);
        self::assertStringContainsString('<html lang="pt-BR">', $portuguese);
        self::assertStringContainsString('hreflang="pt-BR"', $english);
        self::assertStringContainsString('hreflang="en"', $portuguese);
    }

    public function testFirstArticleContainsSubstantiveContent(): void
    {
        $article = file_get_contents($this->buildDirectory() . '/articles/from-code-to-infrastructure/index.html');

        self::assertIsString($article);
        self::assertStringContainsString('From Code to Infrastructure', $article);
        self::assertStringContainsString('Free software does not mean that development and maintenance have no cost.', $article);
    }

    public function testAssetsUseConfiguredBaseUrl(): void
    {
        $index = file_get_contents($this->buildDirectory() . '/index.html');
        $baseUrl = rtrim((string) (getenv('EXPECTED_BASE_URL') ?: 'https://vitormattos.github.io'), '/');

        self::assertIsString($index);
        self::assertMatchesRegularExpression(
            '#' . preg_quote($baseUrl, '#') . '/assets/build/assets/main-[^"\']+\.css#',
            $index,
        );
    }
}
