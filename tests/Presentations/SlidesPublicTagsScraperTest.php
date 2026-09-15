<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\SlidesPublicTagsScraper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SlidesPublicTagsScraperTest extends TestCase
{
    public function testItAssociatesPublicTagsWithKnownPublicDecks(): void
    {
        $pages = [
            'https://slides.com/vitormattos' => <<<'HTML'
                <nav>
                  <a href="/vitormattos">All decks</a>
                  <a href="/vitormattos/php">PHP</a>
                  <a href="/vitormattos/software-livre">Software Livre</a>
                  <a href="/vitormattos/deck-a">Deck A</a>
                </nav>
                HTML,
            'https://slides.com/vitormattos/php' => <<<'HTML'
                <a href="/vitormattos/deck-a">Deck A</a>
                <a href="/vitormattos/deck-b">Deck B</a>
                HTML,
            'https://slides.com/vitormattos/software-livre' => <<<'HTML'
                <a href="https://slides.com/vitormattos/deck-a">Deck A</a>
                HTML,
        ];

        $scraper = new SlidesPublicTagsScraper('vitormattos', static function (string $url) use ($pages): string {
            return $pages[$url] ?? throw new RuntimeException("Unexpected URL: {$url}");
        });

        self::assertSame([
            'https://slides.com/vitormattos/deck-a' => ['PHP', 'Software Livre'],
            'https://slides.com/vitormattos/deck-b' => ['PHP'],
        ], $scraper->scrape([
            'https://slides.com/vitormattos/deck-a',
            'https://slides.com/vitormattos/deck-b',
        ]));
    }

    public function testItDiscoversTagRoutesFromSerializedProfileData(): void
    {
        $pages = [
            'https://slides.com/vitormattos' => <<<'HTML'
                <script>
                window.__PROFILE__ = {"filters":["\/vitormattos\/software-livre"],"decks":["\/vitormattos\/deck-a"]};
                </script>
                HTML,
            'https://slides.com/vitormattos/software-livre' => <<<'HTML'
                <a href="/vitormattos/deck-a">Deck A</a>
                HTML,
        ];

        $scraper = new SlidesPublicTagsScraper('vitormattos', static function (string $url) use ($pages): string {
            return $pages[$url] ?? throw new RuntimeException("Unexpected URL: {$url}");
        });

        self::assertSame([
            'https://slides.com/vitormattos/deck-a' => ['Software Livre'],
        ], $scraper->scrape(['https://slides.com/vitormattos/deck-a']));
    }

    public function testSerializedDeckDataDoesNotInflateTagMembership(): void
    {
        $pages = [
            'https://slides.com/vitormattos' => '<a href="/vitormattos/php">PHP</a>',
            'https://slides.com/vitormattos/php' => <<<'HTML'
                <a href="/vitormattos/deck-a">Deck A</a>
                <script>
                window.__PROFILE__ = {"decks":["\/vitormattos\/deck-a","\/vitormattos\/deck-b"]};
                </script>
                HTML,
        ];

        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => $pages[$url]);

        self::assertSame([
            'https://slides.com/vitormattos/deck-a' => ['PHP'],
            'https://slides.com/vitormattos/deck-b' => [],
        ], $scraper->scrape([
            'https://slides.com/vitormattos/deck-a',
            'https://slides.com/vitormattos/deck-b',
        ]));
    }

    public function testItNormalizesDeckUrlsBeforeMatchingTags(): void
    {
        $pages = [
            'https://slides.com/vitormattos' => '<a href="/vitormattos/php">PHP</a>',
            'https://slides.com/vitormattos/php' => '<a href="http://slides.com/vitormattos/deck-a?foo=bar#slide-1">Deck A</a>',
        ];
        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => $pages[$url]);

        self::assertSame([
            'https://slides.com/vitormattos/deck-a' => ['PHP'],
        ], $scraper->scrape(['https://slides.com/vitormattos/deck-a?utm_source=api']));
    }

    public function testItNeverInventsOrImportsUnknownDecks(): void
    {
        $pages = [
            'https://slides.com/vitormattos' => '<a href="/vitormattos/php">PHP</a>',
            'https://slides.com/vitormattos/php' => '<a href="/vitormattos/private-deck">Private</a><a href="/someone/deck">Other user</a>',
        ];
        $scraper = new SlidesPublicTagsScraper('vitormattos', static fn(string $url): string => $pages[$url]);

        self::assertSame([
            'https://slides.com/vitormattos/public-deck' => [],
        ], $scraper->scrape(['https://slides.com/vitormattos/public-deck']));
    }

    public function testBrokenCandidatePageDoesNotBreakSynchronization(): void
    {
        $scraper = new SlidesPublicTagsScraper('vitormattos', static function (string $url): string {
            if ($url === 'https://slides.com/vitormattos') {
                return '<a href="/vitormattos/php">PHP</a>';
            }

            throw new RuntimeException('temporary failure');
        });

        self::assertSame([
            'https://slides.com/vitormattos/deck-a' => [],
        ], $scraper->scrape(['https://slides.com/vitormattos/deck-a']));
    }

    public function testExternalLinksAreNeverConsideredTagPages(): void
    {
        $requested = [];
        $scraper = new SlidesPublicTagsScraper('vitormattos', static function (string $url) use (&$requested): string {
            $requested[] = $url;

            return '<a href="https://example.com/php">PHP</a>';
        });

        $scraper->scrape(['https://slides.com/vitormattos/deck-a']);

        self::assertSame(['https://slides.com/vitormattos'], $requested);
    }
}
