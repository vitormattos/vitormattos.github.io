<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class TalkTopics
{
    private const CATALOG_MINIMUM_OCCURRENCES = 2;

    public static function resolve(object $talk): array
    {
        $topics = [];
        $curatedTags = self::curatedTags($talk);

        if ($curatedTags !== null) {
            self::collect($topics, $curatedTags);
        } else {
            self::collect($topics, $talk->tags ?? []);
            $presentation = $talk->presentation ?? [];
            if (is_array($presentation)) {
                $metadataPath = trim((string) ($presentation['metadata'] ?? ''));
                if ($metadataPath !== '') {
                    self::collectFromMetadata($topics, $metadataPath);
                }
            }
        }

        self::expandRelatedTopics($topics);
        ksort($topics, SORT_NATURAL | SORT_FLAG_CASE);

        return $topics;
    }

    public static function localized(array $topics, string $locale): array
    {
        $translations = self::translations();
        $locale = $locale === 'pt-BR' ? 'pt-BR' : 'en';

        foreach ($topics as $key => $label) {
            $topics[$key] = $translations[$key][$locale] ?? $label;
        }

        return $topics;
    }

    public static function activityTimestamp(object $talk): int
    {
        return max(self::timestamp($talk->date ?? null), self::timestamp($talk->updated ?? null));
    }

    public static function mergeCatalog(iterable $preferred, iterable $fallback): array
    {
        $items = [];
        $seen = [];

        foreach ([$preferred, $fallback] as $collection) {
            foreach ($collection as $talk) {
                if (!self::isCatalogVisible($talk)) {
                    continue;
                }

                $identity = self::identity($talk);
                if (isset($seen[$identity])) {
                    continue;
                }
                $seen[$identity] = true;
                $items[] = $talk;
            }
        }

        usort($items, static function (object $left, object $right): int {
            $byActivity = self::activityTimestamp($right) <=> self::activityTimestamp($left);
            return $byActivity !== 0
                ? $byActivity
                : strnatcasecmp((string) ($left->title ?? ''), (string) ($right->title ?? ''));
        });

        return $items;
    }

    /** @return array{items: list<object>, topics: array<string, array{label: string, count: int}>} */
    public static function taxonomy(iterable $talks, string $locale = 'pt-BR'): array
    {
        $items = [];
        $taxonomy = [];

        foreach ($talks as $talk) {
            $items[] = $talk;
            foreach (self::localized(self::resolve($talk), $locale) as $key => $label) {
                if (!isset($taxonomy[$key])) {
                    $taxonomy[$key] = ['label' => $label, 'count' => 0];
                } elseif (self::preferLabel($label, $taxonomy[$key]['label'])) {
                    $taxonomy[$key]['label'] = $label;
                }
                ++$taxonomy[$key]['count'];
            }
        }

        $taxonomy = array_filter(
            $taxonomy,
            static fn(array $topic): bool => $topic['count'] >= self::CATALOG_MINIMUM_OCCURRENCES,
        );
        uasort($taxonomy, static function (array $left, array $right): int {
            $byCount = $right['count'] <=> $left['count'];
            return $byCount !== 0 ? $byCount : strnatcasecmp($left['label'], $right['label']);
        });

        return ['items' => $items, 'topics' => $taxonomy];
    }

    private static function translations(): array
    {
        $path = dirname(__DIR__, 2) . '/data/topic-translations.php';
        if (!is_file($path)) {
            return [];
        }
        $translations = require $path;
        return is_array($translations) ? $translations : [];
    }

    private static function identity(object $talk): string
    {
        $presentation = $talk->presentation ?? [];
        if (is_array($presentation)) {
            $metadata = trim((string) ($presentation['metadata'] ?? ''));
            if ($metadata !== '') {
                return 'metadata:' . $metadata;
            }
            $url = trim((string) ($presentation['url'] ?? ''));
            if ($url !== '') {
                return 'url:' . rtrim($url, '/');
            }
        }
        if ($talk->slidesId ?? false) {
            return 'slides:' . (string) $talk->slidesId;
        }
        return 'slug:' . (string) ($talk->slug ?? $talk->title ?? spl_object_id($talk));
    }

    private static function isCatalogVisible(object $talk): bool
    {
        $override = self::presentationOverride($talk);

        return !is_array($override) || ($override['catalog'] ?? true) !== false;
    }

    /** @return list<mixed>|null */
    private static function curatedTags(object $talk): ?array
    {
        $override = self::presentationOverride($talk);
        if (!is_array($override) || !array_key_exists('tags', $override)) {
            return null;
        }

        return (array) $override['tags'];
    }

    /** @return array<string, mixed>|null */
    private static function presentationOverride(object $talk): ?array
    {
        $presentation = $talk->presentation ?? [];
        if (!is_array($presentation)) {
            return null;
        }
        $source = trim((string) ($presentation['type'] ?? $presentation['source'] ?? ''));
        $id = trim((string) ($talk->slidesId ?? ''));
        if ($source === '' || $id === '') {
            return null;
        }
        $path = dirname(__DIR__, 2) . '/data/presentation-overrides.php';
        if (!is_file($path)) {
            return null;
        }
        $overrides = require $path;
        if (!is_array($overrides)) {
            return null;
        }
        $override = $overrides[$source][$id] ?? null;

        return is_array($override) ? $override : null;
    }

    private static function expandRelatedTopics(array &$topics): void
    {
        $path = dirname(__DIR__, 2) . '/data/topic-relations.php';
        if (!is_file($path)) {
            return;
        }
        $relations = require $path;
        if (!is_array($relations)) {
            return;
        }
        $queue = array_keys($topics);
        $processed = [];
        while ($queue !== []) {
            $key = array_shift($queue);
            if (!is_string($key) || isset($processed[$key])) {
                continue;
            }
            $processed[$key] = true;
            $related = $relations[$key] ?? [];
            if (!is_array($related)) {
                continue;
            }
            $before = array_keys($topics);
            self::collect($topics, $related);
            foreach (array_diff(array_keys($topics), $before) as $addedKey) {
                if (!isset($processed[$addedKey])) {
                    $queue[] = $addedKey;
                }
            }
        }
    }

    private static function collect(array &$topics, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::collect($topics, $item);
            }
            return;
        }
        if (!is_scalar($value)) {
            return;
        }
        $label = preg_replace('/\s+/u', ' ', trim((string) $value));
        if (!is_string($label) || $label === '') {
            return;
        }
        $key = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
        if (!isset($topics[$key]) || self::preferLabel($label, $topics[$key])) {
            $topics[$key] = $label;
        }
    }

    private static function collectFromMetadata(array &$topics, string $metadataPath): void
    {
        $path = ltrim($metadataPath, '/');
        if (!is_file($path)) {
            return;
        }
        try {
            $metadata = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return;
        }
        if (is_array($metadata) && array_key_exists('tags', $metadata)) {
            self::collect($topics, $metadata['tags']);
        }
    }

    private static function preferLabel(string $candidate, string $current): bool
    {
        if ($candidate === $current) {
            return false;
        }
        $candidateLower = function_exists('mb_strtolower') ? mb_strtolower($candidate, 'UTF-8') : strtolower($candidate);
        $currentLower = function_exists('mb_strtolower') ? mb_strtolower($current, 'UTF-8') : strtolower($current);
        return $current === $currentLower && $candidate !== $candidateLower;
    }

    private static function timestamp(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value) && trim($value) !== '') {
            return strtotime($value) ?: 0;
        }
        return 0;
    }
}
