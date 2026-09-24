<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Seo;

use App\Presentations\PresentationThumbnailResolver;

final class SocialImageResolver
{
    public function __construct(private readonly PresentationThumbnailResolver $thumbnailResolver) {}

    /**
     * @return array{url: string, width: int, height: int, alt: string, twitterCard: string}
     */
    public function resolve(object $page, string $siteUrl, bool $isProfilePage, string $pageTitle): array
    {
        $image = $this->pageImage($page);
        $thumbnail = null;
        if ($image === null) {
            $thumbnail = $this->thumbnailResolver->resolve(
                $page,
                (string) ($page->environment ?? 'production'),
                (string) ($page->baseUrl ?? ''),
            );
            $image = $thumbnail['path'] ?? null;
        }

        $hasPageImage = $image !== null;
        if (!$hasPageImage) {
            $image = $this->authorImage($page);
        }

        if ($image === null) {
            throw new \RuntimeException('No social image or author avatar is configured.');
        }

        [$width, $height] = $this->dimensions($page, $thumbnail, $hasPageImage);

        return [
            'url' => $this->absoluteUrl($siteUrl, $image),
            'width' => $width,
            'height' => $height,
            'alt' => $this->alt($page, $isProfilePage, $pageTitle),
            'twitterCard' => $hasPageImage ? 'summary_large_image' : 'summary',
        ];
    }

    private function pageImage(object $page): ?string
    {
        $academic = is_array($page->academic ?? null) ? $page->academic : [];

        return $this->firstNonEmptyString([
            $page->socialImage ?? null,
            $page->image ?? null,
            $page->thumbnail ?? null,
            $academic['socialImage'] ?? null,
            $academic['image'] ?? null,
            $academic['thumbnail'] ?? null,
        ]);
    }

    private function authorImage(object $page): ?string
    {
        return $this->firstNonEmptyString([
            $page->author['socialImage'] ?? null,
            $page->author['avatar'] ?? null,
        ]);
    }

    /**
     * @param array{path: string, width: int, height: int}|null $thumbnail
     *
     * @return array{0: int, 1: int}
     */
    private function dimensions(object $page, ?array $thumbnail, bool $hasPageImage): array
    {
        if ($thumbnail !== null) {
            return [$thumbnail['width'], $thumbnail['height']];
        }

        if (!$hasPageImage) {
            return [512, 512];
        }

        return [
            $this->firstPositiveInt([$page->socialImageWidth ?? null, $page->imageWidth ?? null]),
            $this->firstPositiveInt([$page->socialImageHeight ?? null, $page->imageHeight ?? null]),
        ];
    }

    private function alt(object $page, bool $isProfilePage, string $pageTitle): string
    {
        $alt = $this->firstNonEmptyString([$page->socialImageAlt ?? null]);
        if ($alt !== null) {
            return $alt;
        }

        return $isProfilePage ? (string) $page->author['name'] : $pageTitle;
    }

    private function absoluteUrl(string $siteUrl, string $image): string
    {
        if (preg_match('#^https?://#i', $image) === 1) {
            return $image;
        }

        if (str_starts_with($image, '//')) {
            return 'https:' . $image;
        }

        return rtrim($siteUrl, '/') . '/' . ltrim($image, '/');
    }

    /**
     * @param list<mixed> $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $candidate = trim((string) $value);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     */
    private function firstPositiveInt(array $values): int
    {
        foreach ($values as $value) {
            $candidate = (int) $value;
            if ($candidate > 0) {
                return $candidate;
            }
        }

        return 0;
    }
}
