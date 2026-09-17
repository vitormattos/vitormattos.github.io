<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class PresentationThumbnailResolver
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return array{path: string, width: int, height: int}|null
     */
    public function resolve(object $item): ?array
    {
        $presentation = $item->presentation ?? [];
        if (!is_array($presentation)) {
            $presentation = [];
        }

        $archived = $this->archivedThumbnail($item, $presentation);
        if ($archived !== null) {
            return $archived;
        }

        $path = $this->nonEmptyString($presentation['thumbnail'] ?? null);
        if ($path === null) {
            return null;
        }

        $width = $this->positiveInt($presentation['thumbnailWidth'] ?? null);
        if ($width === 0) {
            $width = $this->positiveInt($presentation['width'] ?? null);
        }

        $height = $this->positiveInt($presentation['thumbnailHeight'] ?? null);
        if ($height === 0) {
            $height = $this->positiveInt($presentation['height'] ?? null);
        }

        return [
            'path' => $path,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param array<string, mixed> $presentation
     *
     * @return array{path: string, width: int, height: int}|null
     */
    private function archivedThumbnail(object $item, array $presentation): ?array
    {
        $slidesId = $this->nonEmptyString($item->slidesId ?? null);
        if ($slidesId === null) {
            return null;
        }

        $sourceDirectory = 'slides.com';
        if (($presentation['type'] ?? null) === 'slideshare') {
            $sourceDirectory = 'slideshare';
        }

        $relativeDirectory = 'presentations/' . $sourceDirectory . '/' . $slidesId;
        $absoluteDirectory = rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        $matches = glob($absoluteDirectory . DIRECTORY_SEPARATOR . 'thumbnail.*') ?: [];
        sort($matches, SORT_STRING);

        if ($matches === []) {
            return null;
        }

        $thumbnail = $matches[0];
        $width = 0;
        $height = 0;
        $dimensions = @getimagesize($thumbnail);
        if (is_array($dimensions)) {
            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];
        }

        return [
            'path' => '/' . $relativeDirectory . '/' . basename($thumbnail),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function positiveInt(mixed $value): int
    {
        $value = (int) $value;

        return $value > 0 ? $value : 0;
    }
}
