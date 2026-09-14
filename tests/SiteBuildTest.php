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
        self::assertFileExists(__DIR__ . '/../build_production/artigos/primeiro-artigo/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/palestras/software-livre/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/en/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/en/articles/first-article/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/en/talks/free-software/index.html');
    }

    public function testLocalizedPagesExposeCorrectLanguage(): void
    {
        $portuguese = file_get_contents(__DIR__ . '/../build_production/index.html');
        $english = file_get_contents(__DIR__ . '/../build_production/en/index.html');

        self::assertIsString($portuguese);
        self::assertIsString($english);
        self::assertStringContainsString('<html lang="pt-BR">', $portuguese);
        self::assertStringContainsString('<html lang="en">', $english);
        self::assertStringContainsString('hreflang="en"', $portuguese);
        self::assertStringContainsString('hreflang="pt-BR"', $english);
    }
}
