<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class TalkHistory
{
    public static function resolve(array $history, string $presentationUrl, string $talkSlug): array
    {
        $presentationUrl = rtrim($presentationUrl, '/');
        if ($presentationUrl !== '' && isset($history[$presentationUrl])) {
            return (array) $history[$presentationUrl];
        }

        $talkSlug = self::normalizeSlug($talkSlug);
        if ($talkSlug === '') {
            return [];
        }

        foreach ($history as $sourceUrl => $candidate) {
            $path = (string) parse_url((string) $sourceUrl, PHP_URL_PATH);
            if (self::normalizeSlug((string) basename($path)) === $talkSlug) {
                return (array) $candidate;
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
