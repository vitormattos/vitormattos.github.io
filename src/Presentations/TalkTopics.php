<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class TalkTopics
{
    /**
     * @return array<string, string> normalized topic key => display label
     */
    public static function resolve(object $talk): array
    {
        $topics = [];

        self::collect($topics, $talk->tags ?? []);

        $presentation = $talk->presentation ?? [];
        if (is_array($presentation)) {
            $metadataPath = trim((string) ($presentation['metadata'] ?? ''));
            if ($metadataPath !== '') {
                self::collectFromMetadata($topics, $metadataPath);
            }
        }

        ksort($topics, SORT_NATURAL | SORT_FLAG_CASE);

        return $topics;
    }

    /**
     * @param iterable<object> $talks
     * @return array{items: list<object>, topics: array<string, array{label: string, count: int}>}
     */
    public static function taxonomy(iterable $talks): array
    {
        $items = [];
        $taxonomy = [];

        foreach ($talks as $talk) {
            $items[] = $talk;

            foreach (self::resolve($talk) as $key => $label) {
                if (!isset($taxonomy[$key])) {
                    $taxonomy[$key] = [
                        'label' => $label,
                        'count' => 0,
                    ];
                } elseif (self::preferLabel($label, $taxonomy[$key]['label'])) {
                    $taxonomy[$key]['label'] = $label;
                }

                ++$taxonomy[$key]['count'];
            }
        }

        ksort($taxonomy, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'items' => $items,
            'topics' => $taxonomy,
        ];
    }

    /**
     * @param array<string, string> $topics
     */
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

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($label, 'UTF-8')
            : strtolower($label);

        if (!isset($topics[$key]) || self::preferLabel($label, $topics[$key])) {
            $topics[$key] = $label;
        }
    }

    /**
     * @param array<string, string> $topics
     */
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

        if (!is_array($metadata) || !array_key_exists('tags', $metadata)) {
            return;
        }

        self::collect($topics, $metadata['tags']);
    }

    private static function preferLabel(string $candidate, string $current): bool
    {
        if ($candidate === $current) {
            return false;
        }

        $candidateLower = function_exists('mb_strtolower')
            ? mb_strtolower($candidate, 'UTF-8')
            : strtolower($candidate);
        $currentLower = function_exists('mb_strtolower')
            ? mb_strtolower($current, 'UTF-8')
            : strtolower($current);

        if ($current === $currentLower && $candidate !== $candidateLower) {
            return true;
        }

        return false;
    }
}
