<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\SlidesPublicTagsScraper;
use PHPUnit\Framework\TestCase;

final class SlidesPublicTagsScraperTest extends TestCase
{
    public function testItAssociatesProfileTagsWithKnownPublicDeckIds(): void
    {
        $html = <<<'HTML'
            <script>
            var SLDeckTags = [
                {"id":285656,"name":"testes","slug":"testes","tag_type":"deck","decks":[2401021,1772784,1507067]},
                {"id":285655,"name":"Carreira","slug":"carreira","tag_type":"deck","decks":[1931499,1442266]},
                {"id":137010,"name":"php","slug":"php","tag_type":"deck","decks":[2401021,1772784,1442266]}
            ];
            </script>
            HTML;

        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => $html);

        self::assertSame([
            'https://slides.com/vitormattos/deck-a' => ['php', 'testes'],
            'https://slides.com/vitormattos/deck-b' => ['Carreira', 'php'],
        ], $scraper->scrape([
            'https://slides.com/vitormattos/deck-a' => 2401021,
            'https://slides.com/vitormattos/deck-b' => 1442266,
        ]));
    }

    public function testItNeverImportsUnknownDeckIds(): void
    {
        $html = <<<'HTML'
            <script>
            var SLDeckTags = [{"id":1,"name":"php","tag_type":"deck","decks":[9999999]}];
            </script>
            HTML;

        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => $html);

        self::assertSame([
            'https://slides.com/vitormattos/public-deck' => [],
        ], $scraper->scrape([
            'https://slides.com/vitormattos/public-deck' => 123,
        ]));
    }

    public function testItIgnoresNonDeckTags(): void
    {
        $html = <<<'HTML'
            <script>
            var SLDeckTags = [
                {"id":1,"name":"team-tag","tag_type":"team","decks":[123]},
                {"id":2,"name":"php","tag_type":"deck","decks":[123]}
            ];
            </script>
            HTML;

        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => $html);

        self::assertSame([
            'https://slides.com/vitormattos/public-deck' => ['php'],
        ], $scraper->scrape([
            'https://slides.com/vitormattos/public-deck' => 123,
        ]));
    }

    public function testItReturnsEmptyTagsWhenProfileHasNoSLDeckTags(): void
    {
        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => '<html></html>');

        self::assertSame([
            'https://slides.com/vitormattos/public-deck' => [],
        ], $scraper->scrape([
            'https://slides.com/vitormattos/public-deck' => 123,
        ]));
    }

    public function testItHandlesBracketsInsideJsonStrings(): void
    {
        $html = <<<'HTML'
            <script>
            var SLDeckTags = [{"id":1,"name":"PHP [legacy]","tag_type":"deck","decks":[123]}];
            var anotherVariable = [1,2,3];
            </script>
            HTML;

        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => $html);

        self::assertSame([
            'https://slides.com/vitormattos/public-deck' => ['PHP [legacy]'],
        ], $scraper->scrape([
            'https://slides.com/vitormattos/public-deck' => 123,
        ]));
    }
}
