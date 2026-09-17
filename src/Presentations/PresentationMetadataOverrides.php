<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class PresentationMetadataOverrides
{
    /**
     * Resolve the effective tags for a presentation.
     *
     * Source metadata remains untouched in the provider archive. When a
     * curated tags field exists, it replaces the source list instead of being
     * merged with it. This makes editorial precedence explicit and prevents a
     * later provider synchronization from reintroducing obsolete tags.
     *
     * @param array<string, array<string, array<string, mixed>>> $overrides
     * @param list<mixed> $sourceTags
     * @return list<string>
     */
    public static function tags(array $overrides, string $source, string|int $id, array $sourceTags): array
    {
        $override = (array) ($overrides[$source][(string) $id] ?? []);
        $tags = array_key_exists('tags', $override) ? (array) $override['tags'] : $sourceTags;

        return self::normalizeTags($tags);
    }

    /**
     * Apply arbitrary curated metadata using replacement semantics for string
     * keys. In particular, a curated tags array replaces the provider array;
     * it is never appended to it as array_merge() would do for numeric keys.
     *
     * @param array<string, mixed> $sourceMetadata
     * @param array<string, mixed> $curatedMetadata
     * @return array<string, mixed>
     */
    public static function apply(array $sourceMetadata, array $curatedMetadata): array
    {
        if (array_key_exists('tags', $curatedMetadata)) {
            $curatedMetadata['tags'] = self::normalizeTags((array) $curatedMetadata['tags']);
        }

        return array_replace($sourceMetadata, $curatedMetadata);
    }

    /** @param array<mixed> $tags
     *  @return list<string>
     */
    private static function normalizeTags(array $tags): array
    {
        $normalized = [];
        $seen = [];

        foreach ($tags as $tag) {
            if (!is_scalar($tag)) {
                continue;
            }

            $label = preg_replace('/\s+/u', ' ', trim((string) $tag));
            if (!is_string($label) || $label === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $label;
        }

        return $normalized;
    }
}
