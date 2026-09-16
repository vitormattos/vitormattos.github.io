<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class TalkHistory
{
    public static function resolve(array $history, string $presentationUrl, string $talkSlug): array
    {
        $talkSlug = self::normalizeSlug($talkSlug);
        if ($talkSlug !== '' && isset($history[$talkSlug])) {
            return (array) $history[$talkSlug];
        }

        $presentationUrl = rtrim($presentationUrl, '/');

        foreach ($history as $key => $candidate) {
            $candidate = (array) $candidate;

            if ($talkSlug !== '' && self::normalizeSlug((string) $key) === $talkSlug) {
                return $candidate;
            }

            foreach ((array) ($candidate['aliases'] ?? []) as $alias) {
                $alias = rtrim((string) $alias, '/');

                if ($presentationUrl !== '' && $alias === $presentationUrl) {
                    return $candidate;
                }

                if ($talkSlug === '') {
                    continue;
                }

                $path = (string) parse_url($alias, PHP_URL_PATH);
                if (self::normalizeSlug((string) basename($path)) === $talkSlug) {
                    return $candidate;
                }
            }
        }

        return [];
    }

    private static function normalizeSlug(string $value): string
    {
        $value = str_replace('_', '-', strtolower($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }
}
