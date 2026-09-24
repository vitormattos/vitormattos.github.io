<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\PresentationAssetResolver;
use App\Presentations\PresentationThumbnailResolver;
use App\Seo\PageUrlResolver;
use App\Seo\SeoMetadataBuilder;
use App\Seo\SocialImageResolver;
use App\Seo\StructuredDataBuilder;

$assetResolver = new PresentationAssetResolver(__DIR__);
$thumbnailResolver = new PresentationThumbnailResolver(__DIR__, $assetResolver);
$seoMetadataBuilder = new SeoMetadataBuilder(
    new PageUrlResolver(),
    new SocialImageResolver($thumbnailResolver),
    new StructuredDataBuilder(),
);

return [
    'presentationAssets' => static function ($page) use ($assetResolver): array {
        return $assetResolver->resolve(
            $page,
            (string) ($page->environment ?? 'production'),
            (string) ($page->baseUrl ?? ''),
        );
    },
    'presentationThumbnail' => static function ($page, object $item) use ($thumbnailResolver): ?array {
        $thumbnail = $thumbnailResolver->resolve(
            $item,
            (string) ($page->environment ?? 'production'),
            (string) ($page->baseUrl ?? ''),
        );
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
