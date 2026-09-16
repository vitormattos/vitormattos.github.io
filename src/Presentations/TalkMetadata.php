<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

use DateTimeImmutable;

final class TalkMetadata
{
    public static function resolve(array $metadata, string $presentationUrl, string $talkSlug): array
    {
        $talkSlug = self::normalizeSlug($talkSlug);
        if ($talkSlug !== '' && isset($metadata[$talkSlug])) {
            return (array) $metadata[$talkSlug];
        }

        $presentationUrl = self::normalizeHref($presentationUrl);
        foreach ($metadata as $key => $candidate) {
            $candidate = (array) $candidate;

            if ($talkSlug !== '' && self::normalizeSlug((string) $key) === $talkSlug) {
                return $candidate;
            }

            if ($presentationUrl === '') {
                continue;
            }

            foreach (self::presentationHrefs($candidate) as $candidateHref) {
                if (self::normalizeHref($candidateHref) === $presentationUrl) {
                    return $candidate;
                }
            }
        }

        return [];
    }

    /** @return list<string> */
    public static function validationErrors(array $metadata): array
    {
        $errors = [];
        $seenAliases = [];

        foreach ($metadata as $slug => $candidate) {
            $slug = (string) $slug;
            $candidate = (array) $candidate;

            if ($slug === '' || self::normalizeSlug($slug) !== $slug) {
                $errors[] = sprintf('Talk key "%s" must be a normalized slug.', $slug);
            }

            foreach ((array) ($candidate['aliases'] ?? []) as $index => $alias) {
                $alias = (string) $alias;
                if (!self::isValidHref($alias)) {
                    $errors[] = sprintf('%s.aliases[%d] must be a valid URL or root-relative path.', $slug, $index);
                    continue;
                }

                $normalized = self::normalizeHref($alias);
                if (isset($seenAliases[$normalized]) && $seenAliases[$normalized] !== $slug) {
                    $errors[] = sprintf('%s.aliases[%d] duplicates an alias already used by %s.', $slug, $index, $seenAliases[$normalized]);
                }
                $seenAliases[$normalized] = $slug;
            }

            $errors = [...$errors, ...self::validateResources($slug . '.resources', (array) ($candidate['resources'] ?? []))];
            $errors = [...$errors, ...self::validateResources($slug . '.sources', (array) ($candidate['sources'] ?? []))];

            foreach ((array) ($candidate['appearances'] ?? []) as $index => $appearance) {
                $appearance = (array) $appearance;
                $path = sprintf('%s.appearances[%d]', $slug, $index);

                if (trim((string) ($appearance['event'] ?? '')) === '') {
                    $errors[] = $path . '.event is required.';
                }
                if (isset($appearance['date']) && !self::isValidDate((string) $appearance['date'])) {
                    $errors[] = $path . '.date must use YYYY-MM-DD and be a real calendar date.';
                }
                if (isset($appearance['href']) && !self::isValidHref((string) $appearance['href'])) {
                    $errors[] = $path . '.href must be a valid URL or root-relative path.';
                }
                if (isset($appearance['mode']) && !in_array($appearance['mode'], ['in-person', 'online', 'hybrid'], true)) {
                    $errors[] = $path . '.mode must be in-person, online or hybrid.';
                }
                if (isset($appearance['country']) && !preg_match('/^[A-Z]{2}$/', (string) $appearance['country'])) {
                    $errors[] = $path . '.country must be a two-letter ISO 3166-1 alpha-2 code.';
                }

                $errors = [...$errors, ...self::validateResources($path . '.resources', (array) ($appearance['resources'] ?? []))];
            }
        }

        return $errors;
    }

    public static function publicHref(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, '/')) {
            return rtrim($baseUrl, '/') . $href;
        }

        return $href;
    }

    /** @return list<string> */
    private static function validateResources(string $path, array $resources): array
    {
        $errors = [];
        foreach ($resources as $index => $resource) {
            $resource = (array) $resource;
            $itemPath = sprintf('%s[%d]', $path, $index);

            if (trim((string) ($resource['type'] ?? '')) === '') {
                $errors[] = $itemPath . '.type is required.';
            }
            if (trim((string) ($resource['label'] ?? '')) === '') {
                $errors[] = $itemPath . '.label is required.';
            }
            if (!self::isValidHref((string) ($resource['href'] ?? ''))) {
                $errors[] = $itemPath . '.href must be a valid URL or root-relative path.';
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private static function presentationHrefs(array $candidate): array
    {
        $hrefs = array_map('strval', (array) ($candidate['aliases'] ?? []));
        foreach ((array) ($candidate['resources'] ?? []) as $resource) {
            $resource = (array) $resource;
            if (in_array($resource['type'] ?? null, ['slides', 'pdf'], true) && isset($resource['href'])) {
                $hrefs[] = (string) $resource['href'];
            }
        }

        return array_values(array_unique($hrefs));
    }

    private static function isValidHref(string $href): bool
    {
        return str_starts_with($href, '/') || filter_var($href, FILTER_VALIDATE_URL) !== false;
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private static function normalizeHref(string $href): string
    {
        $href = trim($href);
        $fragmentPosition = strpos($href, '#');
        if ($fragmentPosition !== false) {
            $href = substr($href, 0, $fragmentPosition);
        }

        return rtrim($href, '/');
    }

    private static function normalizeSlug(string $value): string
    {
        $value = str_replace('_', '-', strtolower($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }
}
