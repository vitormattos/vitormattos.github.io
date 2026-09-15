<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

use JsonException;
use RuntimeException;

final class SlidesPublicTagsScraper
{
    private const BASE_URL = 'https://slides.com';
    private const TAGS_VARIABLE = 'SLDeckTags';

    /** @var callable(string): string */
    private $fetchHtml;

    public function __construct(
        private readonly string $username,
        ?callable $fetchHtml = null,
    ) {
        $this->fetchHtml = $fetchHtml ?? self::defaultFetcher(...);
    }

    /**
     * @param array<string, int|string> $deckIdsByUrl Map of public deck URL to Slides.com deck ID.
     *
     * @return array<string, list<string>> Map of canonical deck URL to public Slides.com tags.
     */
    public function scrape(array $deckIdsByUrl): array
    {
        $knownDecksById = [];
        $tagsByUrl = [];

        foreach ($deckIdsByUrl as $deckUrl => $deckId) {
            $canonical = $this->canonicalOwnedUrl($deckUrl);
            if ($canonical === null) {
                continue;
            }

            $knownDecksById[(string) $deckId] = $canonical;
            $tagsByUrl[$canonical] = [];
        }

        if ($knownDecksById === []) {
            return [];
        }

        $profileUrl = self::BASE_URL . '/' . rawurlencode($this->username);
        $profileHtml = ($this->fetchHtml)($profileUrl);
        $deckTags = $this->extractDeckTags($profileHtml);

        $this->debug('known decks: ' . count($knownDecksById));
        $this->debug('profile tags: ' . count($deckTags));

        foreach ($deckTags as $tag) {
            if (($tag['tag_type'] ?? 'deck') !== 'deck') {
                continue;
            }

            $tagName = trim((string) ($tag['name'] ?? ''));
            if ($tagName === '') {
                continue;
            }

            $deckIds = $tag['decks'] ?? [];
            if (!is_array($deckIds)) {
                continue;
            }

            $matched = 0;
            foreach ($deckIds as $deckId) {
                $deckUrl = $knownDecksById[(string) $deckId] ?? null;
                if ($deckUrl === null) {
                    continue;
                }

                $tagsByUrl[$deckUrl][] = $tagName;
                ++$matched;
            }

            $this->debug(sprintf('tag: %s (%d known decks)', $tagName, $matched));
        }

        foreach ($tagsByUrl as &$tags) {
            $tags = array_values(array_unique(array_filter(array_map('trim', $tags))));
            natcasesort($tags);
            $tags = array_values($tags);
        }
        unset($tags);

        return $tagsByUrl;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractDeckTags(string $html): array
    {
        $markerPosition = strpos($html, self::TAGS_VARIABLE);
        if ($markerPosition === false) {
            $this->debug(self::TAGS_VARIABLE . ' not found in profile HTML');

            return [];
        }

        $assignmentPosition = strpos($html, '=', $markerPosition + strlen(self::TAGS_VARIABLE));
        if ($assignmentPosition === false) {
            return [];
        }

        $arrayStart = strpos($html, '[', $assignmentPosition + 1);
        if ($arrayStart === false) {
            return [];
        }

        $json = $this->extractJsonArray($html, $arrayStart);
        if ($json === null) {
            throw new RuntimeException(self::TAGS_VARIABLE . ' contains an unterminated JSON array.');
        }

        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(self::TAGS_VARIABLE . ' contains invalid JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function extractJsonArray(string $source, int $start): ?string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($source);

        for ($position = $start; $position < $length; ++$position) {
            $character = $source[$position];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($character === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($character === '"') {
                $inString = true;
                continue;
            }
            if ($character === '[') {
                ++$depth;
                continue;
            }
            if ($character !== ']') {
                continue;
            }

            --$depth;
            if ($depth === 0) {
                return substr($source, $start, $position - $start + 1);
            }
        }

        return null;
    }

    private function canonicalOwnedUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !in_array(($parts['scheme'] ?? null), ['http', 'https'], true)
            || ($parts['host'] ?? null) !== 'slides.com'
        ) {
            return null;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $prefix = '/' . $this->username;
        if ($path === $prefix || !str_starts_with($path, $prefix . '/')) {
            return null;
        }

        return self::BASE_URL . $path;
    }

    private function debug(string $message): void
    {
        if (getenv('SLIDES_TAGS_DEBUG') !== '1') {
            return;
        }

        fwrite(STDERR, '[slides-tags] ' . $message . PHP_EOL);
    }

    private static function defaultFetcher(string $url): string
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept: text/html\r\nUser-Agent: vitormattos.github.io-slides-tags/1.0 (+https://vitormattos.github.io)\r\n",
            'timeout' => 20,
            'follow_location' => 1,
            'max_redirects' => 3,
        ]]);
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            throw new RuntimeException("Could not fetch public Slides.com page: {$url}");
        }

        return $html;
    }
}
