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

    public function testBothCatalogsRenderImportedSlideShareTalks(): void
    {
        $portuguese = file_get_contents($this->buildDirectory() . '/pt-BR/palestras/index.html');
        $english = file_get_contents($this->buildDirectory() . '/talks/index.html');
        self::assertIsString($portuguese);
        self::assertIsString($english);

        foreach ([$portuguese, $english] as $html) {
            self::assertStringContainsString('JasperReports', $html);
            self::assertStringContainsString('Seja subversivo, faça testes', $html);
        }
    }

    public function testCatalogCardsDoNotExposePresentationFormatsOrDownloadActions(): void
    {
        $html = file_get_contents($this->buildDirectory() . '/pt-BR/palestras/index.html');
        self::assertIsString($html);

        self::assertStringNotContainsString('class="format-badge"', $html);
        self::assertStringNotContainsString('class="talk-card__formats"', $html);
        self::assertStringNotContainsString('<span class="format-badge">slideshare</span>', $html);
        self::assertStringNotContainsString('<span class="format-badge">slides.com</span>', $html);
        self::assertStringNotContainsString('<span class="format-badge">HTML</span>', $html);
        self::assertStringNotContainsString('<span class="format-badge">PPTX</span>', $html);
        self::assertStringNotContainsString(' slides</span>', $html);
        self::assertStringNotContainsString('>pt-BR</span>', $html);
    }

    public function testTalkDetailKeepsSlideCountButOmitsLanguageLabel(): void
    {
        $html = file_get_contents($this->buildDirectory() . '/talks/bdd/index.html');
        self::assertIsString($html);

        self::assertStringContainsString('<dt>Slides</dt>', $html);
        self::assertStringNotContainsString('<dt>Language</dt>', $html);
        self::assertStringNotContainsString('<dt>Idioma</dt>', $html);
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

    public function testTalkDetailRendersCuratedHistoryOutsideProviderMetadata(): void
    {
        $html = file_get_contents($this->buildDirectory() . '/talks/cloud-privacity/index.html');
        self::assertIsString($html);

        self::assertStringContainsString('Presentation history', $html);
        self::assertStringContainsString('2022-08-03', $html);
        self::assertStringContainsString('CICC - Centro Integrado de Comando e Controle', $html);
        self::assertStringContainsString('https://www.youtube.com/watch?v=h2UF0h70NTA', $html);
        self::assertStringContainsString('https://github.com/PHPRio/CFP/issues/146', $html);
    }
}
