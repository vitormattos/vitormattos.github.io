<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Listeners;

use samdark\sitemap\Sitemap;
use TightenCo\Jigsaw\Jigsaw;

final class GenerateSitemap
{
    public function handle(Jigsaw $jigsaw): void
    {
        if (! (bool) $jigsaw->getConfig('indexable')) {
            return;
        }

        $baseUrl = rtrim((string) $jigsaw->getConfig('baseUrl'), '/');
        $sitemap = new Sitemap($jigsaw->getDestinationPath() . '/sitemap.xml');

        $jigsaw->getPages()->each(function ($pageData, $path) use ($baseUrl, $sitemap): void {
            $normalizedPath = $this->normalizePath((string) $path);

            if ($this->isAsset($normalizedPath) || $normalizedPath === '/robots.txt') {
                return;
            }

            $page = is_object($pageData) ? ($pageData->page ?? $pageData) : null;
            $images = $this->resolveImages($baseUrl, $page);

            $sitemap->addItem(
                $baseUrl . ($normalizedPath === '/' ? '/' : $normalizedPath),
                time(),
                Sitemap::WEEKLY,
                null,
                $images,
            );
        });

        $sitemap->write();
    }

    /** @return list<string> */
    private function resolveImages(string $baseUrl, mixed $page): array
    {
        if (! is_object($page)) {
            return [];
        }

        foreach (['banner', 'cover_image'] as $property) {
            $value = $page->{$property} ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return [$value];
            }

            return [$baseUrl . '/' . ltrim($value, '/')];
        }

        return [];
    }

    private function normalizePath(string $path): string
    {
        $urlPath = parse_url($path, PHP_URL_PATH);

        if (! is_string($urlPath) || $urlPath === '') {
            return '/';
        }

        return $urlPath === '/' ? '/' : '/' . ltrim($urlPath, '/');
    }

    private function isAsset(string $path): bool
    {
        return str_starts_with($path, '/assets/');
    }
}
