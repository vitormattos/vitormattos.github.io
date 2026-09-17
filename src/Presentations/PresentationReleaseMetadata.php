<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

class PresentationReleaseMetadata
{
    private const SITE_URL = 'https://vitormattos.github.io';

    public static function title(array $metadata): string
    {
        $title = trim((string) ($metadata['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return ucfirst((string) ($metadata['source'] ?? 'presentation'))
            . ' presentation '
            . (string) ($metadata['id'] ?? '');
    }

    public static function body(
        array $metadata,
        string $repository,
        ?string $thumbnailAssetUrl = null,
        ?string $pdfAssetUrl = null,
        ?string $originalAssetUrl = null,
    ): string {
        $source = trim((string) ($metadata['source'] ?? 'slides.com'));
        $sourceLabel = $source === 'slideshare' ? 'SlideShare' : 'Slides.com';
        $description = trim((string) ($metadata['description'] ?? ''));
        $url = trim((string) ($metadata['source_url'] ?? $metadata['url'] ?? ''));
        $slideCount = (int) ($metadata['slide_count'] ?? 0);
        $publishedAt = self::date((string) ($metadata['published_at'] ?? $metadata['created_at'] ?? ''));
        $updatedAt = self::date((string) ($metadata['updated_at'] ?? ''));
        $tags = self::tags($metadata, $source);

        $lines = [];
        if ($description !== '') {
            $lines[] = $description;
            $lines[] = '';
        }

        $thumbnailUrl = $thumbnailAssetUrl ?? self::thumbnailUrl($metadata, $repository);
        if ($thumbnailUrl !== null) {
            $lines[] = self::thumbnailHtml($metadata, $thumbnailUrl);
            $lines[] = '';
        }

        $lines[] = 'Archived presentation from ' . $sourceLabel . '. Release assets preserve the presentation independently from the original hosting service.';
        $lines[] = '';
        $lines[] = '### Presentation';

        $portfolioUrl = self::portfolioUrl($metadata);
        if ($portfolioUrl !== null) {
            $lines[] = '- **Presentation page:** ' . $portfolioUrl;
        }
        if ($pdfAssetUrl !== null) {
            $lines[] = '- **Archived PDF:** ' . $pdfAssetUrl;
        }
        if ($originalAssetUrl !== null) {
            $lines[] = '- **Original archived file:** ' . $originalAssetUrl;
        }

        $lines[] = '- **Website:** ' . self::SITE_URL;

        if ($url !== '') {
            $lines[] = '- **' . $sourceLabel . ':** ' . $url;
        }
        if ($slideCount > 0) {
            $lines[] = '- **Slides:** ' . $slideCount;
        }
        if ($publishedAt !== '') {
            $lines[] = '- **Published:** ' . $publishedAt;
        }
        if ($updatedAt !== '') {
            $lines[] = '- **Last updated:** ' . $updatedAt;
        }
        if ($tags !== []) {
            $lines[] = '- **Tags:** ' . implode(', ', $tags);
        }

        $lines[] = '';
        $lines[] = 'The release is keyed by the immutable source presentation ID. Historical assets are preserved when a new archived snapshot is published.';

        return rtrim(implode("\n", $lines)) . "\n";
    }

    private static function tags(array $metadata, string $source): array
    {
        $tags = $metadata['tags'] ?? [];
        $tagKey = $source === 'slideshare' ? 'slideshare' : 'slides_com';
        if (isset($tags[$tagKey])) {
            $tags = $tags[$tagKey];
        }

        return array_values(array_filter(
            array_map(static fn($tag): string => trim((string) $tag), (array) $tags),
            static fn(string $tag): bool => $tag !== '',
        ));
    }

    private static function thumbnailHtml(array $metadata, string $thumbnailUrl): string
    {
        $width = (int) ($metadata['thumbnail_width'] ?? $metadata['width'] ?? 0);
        $height = (int) ($metadata['thumbnail_height'] ?? $metadata['height'] ?? 0);
        $src = htmlspecialchars($thumbnailUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alt = htmlspecialchars(self::title($metadata), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($width > 0 && $height > 0) {
            return sprintf(
                '<img src="%s" alt="%s" width="%d" height="%d">',
                $src,
                $alt,
                $width,
                $height,
            );
        }

        return sprintf('<img src="%s" alt="%s">', $src, $alt);
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
