<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use PHPUnit\Framework\TestCase;

final class TalkCatalogBuildTest extends TestCase
{
    private function buildDirectory(): string
    {
        return dirname(__DIR__, 2) . '/' . (getenv('SITE_BUILD_DIR') ?: 'build_production');
    }

    public function testPortugueseCatalogRendersImportedSlideShareTalks(): void
    {
        $html = file_get_contents($this->buildDirectory() . '/pt-BR/palestras/index.html');
        self::assertIsString($html);

        self::assertStringContainsString('JasperReports', $html);
        self::assertStringContainsString('Seja subversivo, faça testes', $html);
        self::assertGreaterThanOrEqual(18, substr_count($html, 'class="talk-card"'));
    }

    public function testTagTaxonomyIsCaseInsensitive(): void
    {
        $html = file_get_contents($this->buildDirectory() . '/pt-BR/palestras/index.html');
        self::assertIsString($html);

        self::assertSame(1, substr_count($html, 'data-talk-tag="php"'));
        self::assertSame(0, substr_count($html, 'data-talk-tag="PHP"'));
        self::assertMatchesRegularExpression('/data-talk-tag="php"><span>PHP<\/span><span>10<\/span>/', $html);
    }
}
