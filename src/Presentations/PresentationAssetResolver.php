<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

use JsonException;

final class PresentationAssetResolver
{
    public function __construct(private readonly string $projectRoot) {}

    /**
     * @return array{
     *     url: ?string,
     *     pdf: ?string,
     *     original: ?string,
     *     pptx: ?string,
     *     thumbnail: ?string
     * }
     */
    public function resolve(object $item, string $environment = 'production', string $baseUrl = ''): array
    {
        $presentation = $item->presentation ?? [];
        if (!is_array($presentation)) {
            $presentation = [];
        }

        $assets = [
            'url' => $this->stringOrNull($presentation['url'] ?? null),
            'pdf' => $this->stringOrNull($presentation['pdf'] ?? null),
            'original' => $this->stringOrNull($presentation['original'] ?? null),
            'pptx' => $this->stringOrNull($presentation['pptx'] ?? null),
            'thumbnail' => $this->stringOrNull($presentation['thumbnail'] ?? null),
        ];

        if (($item->managed ?? null) === 'latex') {
            return $this->resolveLatex($item, $assets, $environment, $baseUrl);
        }

        if (($item->slidesId ?? null) !== null) {
            return $this->resolveArchivedSlides($item, $presentation, $assets);
        }

        return $assets;
    }

    /**
     * @param array{url: ?string, pdf: ?string, original: ?string, pptx: ?string, thumbnail: ?string} $assets
     *
     * @return array{url: ?string, pdf: ?string, original: ?string, pptx: ?string, thumbnail: ?string}
     */
    private function resolveLatex(object $item, array $assets, string $environment, string $baseUrl): array
    {
        $slug = $this->stringOrNull($item->slug ?? null);
        if ($slug === null) {
            return $assets;
        }

        if ($environment === 'preview') {
            $prefix = rtrim($baseUrl, '/') . '/presentations/latex/' . $slug . '/';
            $assets['url'] = $prefix . $slug . '.pdf';
            $assets['pdf'] = $assets['url'];
            $assets['thumbnail'] = $prefix . 'thumbnail.png';

            return $assets;
        }

        $manifest = $this->readJson('presentations/latex/' . $slug . '/export.json');
        if ($manifest === null) {
            return $assets;
        }

        $assets['url'] = $this->stringOrNull($manifest['release']['assets']['pdf']['url'] ?? null) ?? $assets['url'];
        $assets['pdf'] = $assets['url'] ?? $assets['pdf'];
        $assets['thumbnail'] =
            $this->stringOrNull($manifest['release']['assets']['thumbnail']['url'] ?? null) ?? $assets['thumbnail'];

        return $assets;
    }

    /**
     * @param array<string, mixed> $presentation
     * @param array{url: ?string, pdf: ?string, original: ?string, pptx: ?string, thumbnail: ?string} $assets
     *
     * @return array{url: ?string, pdf: ?string, original: ?string, pptx: ?string, thumbnail: ?string}
     */
    private function resolveArchivedSlides(object $item, array $presentation, array $assets): array
    {
        $slidesId = $this->stringOrNull($item->slidesId ?? null);
        if ($slidesId === null) {
            return $assets;
        }

        $sourceDirectory = ($presentation['type'] ?? null) === 'slideshare' ? 'slideshare' : 'slides.com';
        $relativeDirectory = 'presentations/' . $sourceDirectory . '/' . $slidesId;
        $manifest = $this->readJson($relativeDirectory . '/export.json');

        $manifestThumbnail = null;
        if ($manifest !== null) {
            $assets['pdf'] ??=
                $this->stringOrNull($manifest['assets']['pdf']['url'] ?? null)
                ?? $this->stringOrNull($manifest['pdf']['url'] ?? null);
            $assets['original'] ??= $this->stringOrNull($manifest['assets']['original']['url'] ?? null);
            $assets['pptx'] ??= $this->stringOrNull($manifest['assets']['pptx']['url'] ?? null);
            $manifestThumbnail = $this->stringOrNull($manifest['assets']['thumbnail']['url'] ?? null);
        }

        $assets['thumbnail'] =
            $this->findLocalThumbnail($relativeDirectory)
            ?? $manifestThumbnail
            ?? $assets['thumbnail'];

        return $assets;
    }

    private function findLocalThumbnail(string $relativeDirectory): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $relativePath = $relativeDirectory . '/thumbnail.' . $extension;
            if (is_file($this->projectPath($relativePath))) {
                return '/' . $relativePath;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $relativePath): ?array
    {
        $path = $this->projectPath($relativePath);
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function projectPath(string $relativePath): string
    {
        return rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
