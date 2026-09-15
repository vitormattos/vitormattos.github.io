<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class SlidesReleaseMetadata
{
    private const SITE_URL = 'https://vitormattos.github.io';

    public static function title(array $metadata): string
    {
        $title = trim((string) ($metadata['title'] ?? ''));

        return $title !== '' ? $title : 'Slides.com presentation ' . (string) ($metadata['id'] ?? '');
    }

    public static function body(array $metadata, string $repository): string
    {
        $description = trim((string) ($metadata['description'] ?? ''));
        $url = trim((string) ($metadata['url'] ?? ''));
        $language = trim((string) ($metadata['language'] ?? ''));
        $slideCount = (int) ($metadata['slide_count'] ?? 0);
        $createdAt = self::date((string) ($metadata['created_at'] ?? ''));
        $updatedAt = self::date((string) ($metadata['updated_at'] ?? ''));
        $tags = array_values(array_filter(
            array_map('strval', (array) ($metadata['tags']['slides_com'] ?? [])),
            static fn(string $tag): bool => trim($tag) !== '',
        ));

        $lines = [];
        if ($description !== '') {
            $lines[] = $description;
            $lines[] = '';
        }

        $thumbnailUrl = self::thumbnailUrl($metadata, $repository);
        if ($thumbnailUrl !== null) {
            $lines[] = '![Presentation thumbnail](' . $thumbnailUrl . ')';
            $lines[] = '';
        }

        $lines[] = 'Archived presentation from Slides.com. PDF assets are immutable snapshots of the presentation source and are kept here as part of the presentation archive.';
        $lines[] = '';
        $lines[] = '### Presentation';

        $portfolioUrl = self::portfolioUrl($metadata);
        if ($portfolioUrl !== null) {
            $lines[] = '- **Presentation page:** ' . $portfolioUrl;
        }
        $lines[] = '- **Website:** ' . self::SITE_URL;
        if ($url !== '') {
            $lines[] = '- **Slides.com:** ' . $url;
        }
        if ($language !== '') {
            $lines[] = '- **Language:** ' . $language;
        }
        if ($slideCount > 0) {
            $lines[] = '- **Slides:** ' . $slideCount;
        }
        if ($createdAt !== '') {
            $lines[] = '- **Created:** ' . $createdAt;
        }
        if ($updatedAt !== '') {
            $lines[] = '- **Last updated on Slides.com:** ' . $updatedAt;
        }
        if ($tags !== []) {
            $lines[] = '- **Tags:** ' . implode(', ', $tags);
        }

        $lines[] = '';
        $lines[] = 'The release is keyed by the immutable Slides.com deck ID. New PDF snapshots may be added when the archived visual source changes; previous assets are preserved.';

        return rtrim(implode("\n", $lines)) . "\n";
    }

    private static function thumbnailUrl(array $metadata, string $repository): ?string
    {
        $path = trim((string) ($metadata['thumbnail_path'] ?? ''));
        if ($path === '') {
            return null;
        }

        return sprintf(
            'https://raw.githubusercontent.com/%s/main/%s',
            trim($repository, '/'),
            implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/')))),
        );
    }

    private static function portfolioUrl(array $metadata): ?string
    {
        $slug = trim((string) ($metadata['slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        $language = strtolower(trim((string) ($metadata['language'] ?? '')));
        $prefix = str_starts_with($language, 'pt') ? '/pt-BR/palestras/' : '/talks/';

        return self::SITE_URL . $prefix . rawurlencode($slug);
    }

    private static function date(string $value): string
    {
        return $value !== '' ? substr($value, 0, 10) : '';
    }
}
