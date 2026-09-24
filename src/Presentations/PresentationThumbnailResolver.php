<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class PresentationThumbnailResolver
{
    private readonly PresentationAssetResolver $assetResolver;

    public function __construct(
        private readonly string $projectRoot,
        ?PresentationAssetResolver $assetResolver = null,
    ) {
        $this->assetResolver = $assetResolver ?? new PresentationAssetResolver($projectRoot);
    }

    /**
     * @return array{path: string, width: int, height: int}|null
     */
    public function resolve(object $item, string $environment = 'production', string $baseUrl = ''): ?array
    {
        $assets = $this->assetResolver->resolve($item, $environment, $baseUrl);
        $path = $assets['thumbnail'];
        if ($path === null) {
            return null;
        }

        $presentation = $item->presentation ?? [];
        if (!is_array($presentation)) {
            $presentation = [];
        }

        [$width, $height] = $this->dimensions($path, $presentation);

        return [
            'path' => $path,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param array<string, mixed> $presentation
     *
     * @return array{0: int, 1: int}
     */
    private function dimensions(string $path, array $presentation): array
    {
        $localPath = $this->localPath($path);
        if ($localPath !== null && is_file($localPath)) {
            $dimensions = @getimagesize($localPath);
            if (is_array($dimensions)) {
                return [(int) $dimensions[0], (int) $dimensions[1]];
            }
        }

        $width = $this->positiveInt($presentation['thumbnailWidth'] ?? null);
        if ($width === 0) {
            $width = $this->positiveInt($presentation['width'] ?? null);
        }

        $height = $this->positiveInt($presentation['thumbnailHeight'] ?? null);
        if ($height === 0) {
            $height = $this->positiveInt($presentation['height'] ?? null);
        }

        return [$width, $height];
    }

    private function localPath(string $path): ?string
    {
        $urlPath = parse_url($path, PHP_URL_PATH);
        if (!is_string($urlPath) || $urlPath === '') {
            return null;
        }

        if (str_starts_with($urlPath, '/presentations/latex/')) {
            return rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'source'
                . str_replace('/', DIRECTORY_SEPARATOR, $urlPath);
        }

        if (str_starts_with($urlPath, '/presentations/')) {
            return rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
                . str_replace('/', DIRECTORY_SEPARATOR, $urlPath);
        }

        return null;
    }

    private function positiveInt(mixed $value): int
    {
        $value = (int) $value;

        return $value > 0 ? $value : 0;
    }
}
