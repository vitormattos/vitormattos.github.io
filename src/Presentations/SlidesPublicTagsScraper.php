<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;

final class SlidesPublicTagsScraper
{
    private const BASE_URL = 'https://slides.com';
    private const MAX_CANDIDATE_TAG_PAGES = 50;

    /** @var callable(string): string */
    private $fetchHtml;

    public function __construct(
        private readonly string $username,
        ?callable $fetchHtml = null,
    ) {
        $this->fetchHtml = $fetchHtml ?? self::defaultFetcher(...);
    }

    /**
     * @param list<string> $deckUrls
     *
     * @return array<string, list<string>> Map of canonical deck URL to public Slides.com tags.
     */
    public function scrape(array $deckUrls): array
    {
        $knownDecks = [];
        foreach ($deckUrls as $deckUrl) {
            $canonical = $this->canonicalOwnedUrl($deckUrl);
            if ($canonical !== null) {
                $knownDecks[$canonical] = [];
            }
        }

        if ($knownDecks === []) {
            return [];
        }

        $profileUrl = self::BASE_URL . '/' . rawurlencode($this->username);
        $profileHtml = ($this->fetchHtml)($profileUrl);
        $candidates = $this->discoverTagCandidates($profileHtml, array_keys($knownDecks));

        foreach (array_slice($candidates, 0, self::MAX_CANDIDATE_TAG_PAGES, true) as $candidateUrl => $tagName) {
            try {
                $html = ($this->fetchHtml)($candidateUrl);
            } catch (Throwable) {
                continue;
            }

            $matchedDecks = $this->extractKnownDeckUrls($html, array_keys($knownDecks));
            if ($matchedDecks === []) {
                continue;
            }

            foreach ($matchedDecks as $deckUrl) {
                $knownDecks[$deckUrl][] = $tagName;
            }
        }

        foreach ($knownDecks as &$tags) {
            $tags = array_values(array_unique(array_filter(array_map('trim', $tags))));
            natcasesort($tags);
            $tags = array_values($tags);
        }
        unset($tags);

        return $knownDecks;
    }

    /**
     * Candidate detection deliberately does not depend on Slides.com CSS classes.
     * Public deck URLs returned by the API are excluded. Remaining owned links
     * are fetched and only promoted to tags if their page contains known decks.
     * This confines the fragile HTML dependency to a single adapter.
     *
     * @param list<string> $knownDeckUrls
     *
     * @return array<string, string>
     */
    private function discoverTagCandidates(string $html, array $knownDeckUrls): array
    {
        $known = array_fill_keys($knownDeckUrls, true);
        $profileUrl = self::BASE_URL . '/' . $this->username;
        $candidates = [];

        foreach ($this->anchors($html) as [$href, $text]) {
            $url = $this->resolveUrl($href);
            $canonical = $this->canonicalOwnedUrl($url);

            if ($canonical === null
                || $canonical === $profileUrl
                || isset($known[$canonical])
                || str_ends_with($canonical, '/embed')
                || trim($text) === ''
                || strcasecmp(trim($text), 'All decks') === 0
            ) {
                continue;
            }

            $candidates[$canonical] = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        }

        return $candidates;
    }

    /** @param list<string> $knownDeckUrls
     *  @return list<string>
     */
    private function extractKnownDeckUrls(string $html, array $knownDeckUrls): array
    {
        $known = array_fill_keys($knownDeckUrls, true);
        $matches = [];

        foreach ($this->anchors($html) as [$href]) {
            $canonical = $this->canonicalOwnedUrl($this->resolveUrl($href));
            if ($canonical !== null && isset($known[$canonical])) {
                $matches[$canonical] = true;
            }
        }

        return array_keys($matches);
    }

    /** @return list<array{0: string, 1: string}> */
    private function anchors(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            return [];
        }

        $anchors = [];
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $anchors[] = [$node->getAttribute('href'), trim($node->textContent)];
        }

        return $anchors;
    }

    private function resolveUrl(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }
        if (str_starts_with($href, '/')) {
            return self::BASE_URL . $href;
        }

        return $href;
    }

    private function canonicalOwnedUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'slides.com'
        ) {
            return null;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $prefix = '/' . $this->username;
        if ($path !== $prefix && !str_starts_with($path, $prefix . '/')) {
            return null;
        }

        $canonical = self::BASE_URL . $path;
        if (isset($parts['query']) && $parts['query'] !== '') {
            $canonical .= '?' . $parts['query'];
        }

        return $canonical;
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
