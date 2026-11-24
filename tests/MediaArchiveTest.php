<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests;

use PHPUnit\Framework\TestCase;

final class MediaArchiveTest extends TestCase
{
    public function testPublicMediaMetadataAndProvenance(): void
    {
        $archive = require __DIR__ . '/../data/media.php';
        $ids = [];
        foreach ($archive['items'] as $record) {
            self::assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $record['id']);
            self::assertNotContains($record['id'], $ids);
            $ids[] = $record['id'];
            self::assertArrayHasKey($record['type'], $archive['types']);
            self::assertNotEmpty($record['source']);
            self::assertNotFalse(filter_var($record['url'], FILTER_VALIDATE_URL));
            self::assertContains(parse_url($record['url'], PHP_URL_SCHEME), ['https', 'http']);
            self::assertNotContains(parse_url($record['url'], PHP_URL_HOST), ['cloud.colab.rio', 'cloud.librecode.coop']);
            foreach (['latitude', 'longitude', 'gps', 'exif', 'privatePath', 'fileId', 'faceEmbedding'] as $key) {
                self::assertArrayNotHasKey($key, $record);
            }
            foreach ($record['topics'] as $topic) {
                self::assertArrayHasKey($topic, $archive['topics']);
            }
            if (isset($record['city'])) {
                self::assertArrayHasKey($record['city'], $archive['cities']);
            }
            if (isset($record['cover'])) {
                self::assertFileExists(__DIR__ . '/..' . $record['cover']);
                self::assertGreaterThan(0, $record['coverWidth']);
                self::assertGreaterThan(0, $record['coverHeight']);
            }
            if ($record['type'] === 'photo' && isset($record['image'])) {
                self::assertNotEmpty($record['credit']);
                self::assertNotEmpty($record['license']);
                self::assertFileExists(__DIR__ . '/../source' . $record['image']);
                self::assertFileExists(__DIR__ . '/../source' . $record['thumbnail']);
            }
        }
    }

    public function testLocalizedArchiveAndBacklinksInBuiltSite(): void
    {
        $build = __DIR__ . '/../' . (getenv('SITE_BUILD_DIR') ?: 'build_production');
        foreach (['media', 'pt-BR/acervo'] as $path) {
            $html = file_get_contents($build . '/' . $path . '/index.html');
            self::assertIsString($html);
            self::assertStringContainsString('data-media-catalog', $html);
            self::assertStringContainsString('id="web-summit-rio-2026"', $html);
            self::assertStringContainsString('id="fisl-photo-coverage-2018"', $html);
            self::assertStringContainsString('fisl-2018-kupac-thumb.webp', $html);
            self::assertStringNotContainsString('i.ytimg.com', $html);
            self::assertStringNotContainsString('<iframe', $html);
        }
        $talk = file_get_contents($build . '/talks/controle-tecnologia/index.html');
        self::assertIsString($talk);
        self::assertStringContainsString('/media#web-summit-rio-2026', $talk);
        $article = file_get_contents($build . '/articles/communion-of-saints-digital-ethics/index.html');
        self::assertIsString($article);
        self::assertStringContainsString('/media#phprio-cloud-privacy-2022', $article);
        self::assertFileExists($build . '/media-assets/south-america.geojson');
    }
}
