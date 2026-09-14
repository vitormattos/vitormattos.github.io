<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SiteBuildTest extends TestCase
{
    public function testProductionBuildContainsExpectedPages(): void
    {
        self::assertFileExists(__DIR__ . '/../build_production/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/articles/first-article/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/talks/free-software/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/pt-BR/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/pt-BR/artigos/primeiro-artigo/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/pt-BR/palestras/software-livre/index.html');
    }

    public function testLocalizedPagesExposeCorrectLanguage(): void
    {
        $english = file_get_contents(__DIR__ . '/../build_production/index.html');
        $portuguese = file_get_contents(__DIR__ . '/../build_production/pt-BR/index.html');

        self::assertIsString($english);
        self::assertIsString($portuguese);
        self::assertStringContainsString('<html lang="en">', $english);
        self::assertStringContainsString('<html lang="pt-BR">', $portuguese);
        self::assertStringContainsString('hreflang="pt-BR"', $english);
        self::assertStringContainsString('hreflang="en"', $portuguese);
    }

    public function testIntroductoryArticleIsRealContent(): void
    {
        $article = file_get_contents(__DIR__ . '/../build_production/articles/first-article/index.html');

        self::assertIsString($article);
        self::assertStringContainsString('Why this site exists', $article);
        self::assertStringContainsString('English is the canonical language', $article);
    }
}
