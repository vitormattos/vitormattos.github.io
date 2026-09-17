<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\PresentationThumbnailResolver;
use App\Seo\SeoMetadataBuilder;

$thumbnailResolver = new PresentationThumbnailResolver(__DIR__);
$seoMetadataBuilder = new SeoMetadataBuilder($thumbnailResolver);

return [
    'presentationThumbnail' => static function ($page, object $item) use ($thumbnailResolver): ?array {
        $thumbnail = $thumbnailResolver->resolve($item);
        if ($thumbnail === null) {
            return null;
        }

        $thumbnail['url'] = $thumbnail['path'];
        if (str_starts_with($thumbnail['path'], '/')) {
            $thumbnail['url'] = rtrim((string) $page->baseUrl, '/') . $thumbnail['path'];
        }

        return $thumbnail;
    },
    'seoMetadata' => static function ($page) use ($seoMetadataBuilder): array {
        return $seoMetadataBuilder->build($page);
    },
];
