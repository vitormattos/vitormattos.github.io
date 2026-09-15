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
    private const MAX_CANDIDATE_TAG_PAGES = 80;

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

            $matchedDecks = $this->extractRenderedKnownDeckUrls($html, array_keys($knownDecks));
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
     * Tag routes can be present in serialized profile data even when they are
     * not rendered as anchors. Serialized data is therefore used only for tag
     * discovery. Deck membership is determined from rendered links on the tag
     * page so profile-wide hydration data cannot inflate tag counts.
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
            $this->addCandidate($candidates, $known, $profileUrl, $href, $text);
        }

        foreach ($this->ownedRoutesInHtml($html) as $route) {
            $this->addCandidate($candidates, $known, $profileUrl, $route, '');
        }

        return $candidates;
    }

    /**
     * @param array<string, string> $candidates
     * @param array<string, true> $known
     */
    private function addCandidate(array &$candidates, array $known, string $profileUrl, string $url, string $label): void
    {
        $canonical = $this->canonicalOwnedUrl($this->resolveUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5)));
        if ($canonical === null
            || $canonical === $profileUrl
            || isset($known[$canonical])
            || $this->isDeckUtilityRoute($canonical)
        ) {
            return;
        }

        $tagName = trim(preg_replace('/\s+/', ' ', $label) ?? $label);
        if ($tagName === '' || strcasecmp($tagName, 'All decks') === 0) {
            $tagName = $this->labelFromUrl($canonical);
        }
        if ($tagName === '') {
            return;
        }

        $candidates[$canonical] ??= $tagName;
    }

    /** @return list<string> */
    private function ownedRoutesInHtml(string $html): array
    {
        $normalizedHtml = html_entity_decode(str_replace('\\/', '/', $html), ENT_QUOTES | ENT_HTML5);
        $username = preg_quote($this->username, '~');
        $patterns = [
            '~https?://slides\.com/' . $username . '/[a-zA-Z0-9][a-zA-Z0-9_-]*~',
            '~/' . $username . '/[a-zA-Z0-9][a-zA-Z0-9_-]*~',
        ];
        $routes = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $normalizedHtml, $matches) === false) {
                continue;
            }

            array_push($routes, ...$matches[0]);
        }

        return array_values(array_unique($routes));
    }

    /**
     * @param list<string> $knownDeckUrls
     *
     * @return list<string>
     */
    private function extractRenderedKnownDeckUrls(string $html, array $knownDeckUrls): array
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
        $href = trim(str_replace('\\/', '/', $href));
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
            || !in_array(($parts['scheme'] ?? null), ['http', 'https'], true)
            || ($parts['host'] ?? null) !== 'slides.com'
        ) {
            return null;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $prefix = '/' . $this->username;
        if ($path !== $prefix && !str_starts_with($path, $prefix . '/')) {
            return null;
        }

        return self::BASE_URL . $path;
    }

    private function isDeckUtilityRoute(string $url): bool
    {
        foreach (['/embed', '/fullscreen', '/live', '/edit'] as $suffix) {
            if (str_ends_with($url, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function labelFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = explode('/', $path);
        $slug = end($segments);
        if (!is_string($slug) || $slug === '') {
            return '';
        }

        return ucwords(str_replace(['-', '_'], ' ', $slug));
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
