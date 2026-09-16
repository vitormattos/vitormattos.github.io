<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

use DateTimeImmutable;

final class TalkHistory
{
    public static function resolve(array $history, string $presentationUrl, string $talkSlug): array
    {
        $talkSlug = self::normalizeSlug($talkSlug);
        if ($talkSlug !== '' && isset($history[$talkSlug])) {
            return (array) $history[$talkSlug];
        }

        $presentationUrl = self::normalizeUrl($presentationUrl);

        foreach ($history as $key => $candidate) {
            $candidate = (array) $candidate;

            if ($talkSlug !== '' && self::normalizeSlug((string) $key) === $talkSlug) {
                return $candidate;
            }

            if ($presentationUrl === '') {
                continue;
            }

            foreach (self::presentationUrls($candidate) as $candidateUrl) {
                if (self::normalizeUrl($candidateUrl) === $presentationUrl) {
                    return $candidate;
                }
            }
        }

        return [];
    }

    /**
     * Validate curated talk metadata so editorial mistakes fail in CI instead of
     * silently producing broken links or ambiguous records in the generated site.
     *
     * @return list<string>
     */
    public static function validationErrors(array $history): array
    {
        $errors = [];
        $seenPresentationUrls = [];

        foreach ($history as $slug => $candidate) {
            $slug = (string) $slug;
            $candidate = (array) $candidate;

            if ($slug === '' || self::normalizeSlug($slug) !== $slug) {
                $errors[] = sprintf('Talk key "%s" must be a normalized slug.', $slug);
            }

            foreach ((array) ($candidate['aliases'] ?? []) as $index => $alias) {
                $alias = (string) $alias;
                if (!self::isValidUrl($alias)) {
                    $errors[] = sprintf('%s.aliases[%d] must be a valid URL.', $slug, $index);
                    continue;
                }

                $normalizedAlias = self::normalizeUrl($alias);
                if (isset($seenPresentationUrls[$normalizedAlias]) && $seenPresentationUrls[$normalizedAlias] !== $slug) {
                    $errors[] = sprintf(
                        '%s.aliases[%d] duplicates a presentation URL already used by %s.',
                        $slug,
                        $index,
                        $seenPresentationUrls[$normalizedAlias],
                    );
                }
                $seenPresentationUrls[$normalizedAlias] = $slug;
            }

            $errors = [...$errors, ...self::validateLinks($slug . '.links', (array) ($candidate['links'] ?? []))];
            $errors = [...$errors, ...self::validateLinks($slug . '.sources', (array) ($candidate['sources'] ?? []))];

            foreach ((array) ($candidate['appearances'] ?? []) as $index => $appearance) {
                $appearance = (array) $appearance;
                $path = sprintf('%s.appearances[%d]', $slug, $index);

                if (trim((string) ($appearance['event'] ?? '')) === '') {
                    $errors[] = $path . '.event is required.';
                }

                if (isset($appearance['date']) && !self::isValidDate((string) $appearance['date'])) {
                    $errors[] = $path . '.date must use YYYY-MM-DD and be a real calendar date.';
                }

                if (isset($appearance['url']) && !self::isValidUrl((string) $appearance['url'])) {
                    $errors[] = $path . '.url must be a valid URL.';
                }

                $errors = [...$errors, ...self::validateLinks($path . '.links', (array) ($appearance['links'] ?? []))];
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private static function validateLinks(string $path, array $links): array
    {
        $errors = [];

        foreach ($links as $index => $link) {
            $link = (array) $link;
            $itemPath = sprintf('%s[%d]', $path, $index);

            if (trim((string) ($link['type'] ?? '')) === '') {
                $errors[] = $itemPath . '.type is required.';
            }
            if (trim((string) ($link['label'] ?? '')) === '') {
                $errors[] = $itemPath . '.label is required.';
            }
            if (!self::isValidUrl((string) ($link['url'] ?? ''))) {
                $errors[] = $itemPath . '.url must be a valid URL.';
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private static function presentationUrls(array $candidate): array
    {
        $urls = array_map('strval', (array) ($candidate['aliases'] ?? []));

        foreach ((array) ($candidate['links'] ?? []) as $link) {
            $link = (array) $link;
            if (($link['type'] ?? null) === 'slides' && isset($link['url'])) {
                $urls[] = (string) $link['url'];
            }
        }

        return array_values(array_unique($urls));
    }

    private static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $url = substr($url, 0, $fragmentPosition);
        }

        return rtrim($url, '/');
    }

    private static function normalizeSlug(string $value): string
    {
        $value = str_replace('_', '-', strtolower($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }
}
