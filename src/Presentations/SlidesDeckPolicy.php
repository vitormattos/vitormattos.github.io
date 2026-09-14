<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class SlidesDeckPolicy
{
    public static function isPublic(array $deck): bool
    {
        return ($deck['visibility'] ?? null) === 'all';
    }

    public static function isAllowedPublicUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'slides.com'
            && isset($parts['path'])
            && str_starts_with($parts['path'], '/vitormattos/');
    }

    public static function isAllowedEmbedUrl(?string $url): bool
    {
        if (!self::isAllowedPublicUrl($url)) {
            return false;
        }

        $path = (string) (parse_url((string) $url, PHP_URL_PATH) ?? '');

        return preg_match('#^/vitormattos/[^/]+/embed/?$#', $path) === 1;
    }

    public static function viewport(array $deck): string
    {
        $width = max(320, (int) ($deck['width'] ?? 1280));
        $height = max(180, (int) ($deck['height'] ?? 720));

        return $width . 'x' . $height;
    }
}
