<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TalkCatalogBuildTest extends TestCase
{
    private function buildDirectory(): string
    {
        return __DIR__ . '/../' . (getenv('SITE_BUILD_DIR') ?: 'build_production');
    }

    public function testPortugueseCatalogRendersImportedSlideShareTalks(): void
    {
        $html = file_get_contents($this->buildDirectory() . '/pt-BR/palestras/index.html');
        self::assertIsString($html);

        self::assertStringContainsString('JasperReports', $html);
        self::assertStringContainsString('Seja subversivo, faça testes', $html);
        self::assertGreaterThanOrEqual(18, substr_count($html, 'class="talk-card"'));
    }

    public function testTopicTaxonomyIsCaseInsensitiveAndCountsRenderedCards(): void
    {
        $html = file_get_contents($this->buildDirectory() . '/pt-BR/palestras/index.html');
        self::assertIsString($html);

        self::assertSame(1, substr_count($html, 'data-talk-tag="php"'));
        self::assertSame(0, substr_count($html, 'data-talk-tag="PHP"'));

        preg_match('/data-talk-tag="php"><span>[^<]+<\/span><span>(\d+)<\/span>/', $html, $counterMatch);
        self::assertArrayHasKey(1, $counterMatch);
        $displayedCount = (int) $counterMatch[1];

        preg_match_all('/<article class="talk-card" data-talk-tags="([^"]*)">/', $html, $cardMatches);
        $cardsWithPhp = 0;
        foreach ($cardMatches[1] as $encodedTopics) {
            $topics = json_decode(html_entity_decode($encodedTopics, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (is_array($topics) && in_array('php', $topics, true)) {
                ++$cardsWithPhp;
            }
        }

        self::assertSame($cardsWithPhp, $displayedCount);
        self::assertGreaterThan(0, $displayedCount);
    }
}
