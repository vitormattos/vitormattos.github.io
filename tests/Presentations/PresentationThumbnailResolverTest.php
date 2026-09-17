<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\PresentationThumbnailResolver;
use PHPUnit\Framework\TestCase;

final class PresentationThumbnailResolverTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/presentation-thumbnail-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);
    }

    public function testUsesPresentationThumbnailWhenNoArchivedThumbnailExists(): void
    {
        $item = (object) [
            'presentation' => [
                'thumbnail' => '/images/talk.png',
                'thumbnailWidth' => 1200,
                'thumbnailHeight' => 630,
            ],
        ];

        $thumbnail = (new PresentationThumbnailResolver($this->projectRoot))->resolve($item);

        self::assertSame([
            'path' => '/images/talk.png',
            'width' => 1200,
            'height' => 630,
        ], $thumbnail);
    }

    public function testArchivedSlidesComThumbnailOverridesFrontMatterAndReadsDimensions(): void
    {
        $this->writeOnePixelPng('presentations/slides.com/42/thumbnail.png');
        $item = (object) [
            'slidesId' => 42,
            'presentation' => [
                'type' => 'slides.com',
                'thumbnail' => 'https://example.com/remote.png',
                'width' => 1024,
                'height' => 576,
            ],
        ];

        $thumbnail = (new PresentationThumbnailResolver($this->projectRoot))->resolve($item);

        self::assertSame('/presentations/slides.com/42/thumbnail.png', $thumbnail['path']);
        self::assertSame(1, $thumbnail['width']);
        self::assertSame(1, $thumbnail['height']);
    }

    public function testResolvesArchivedSlideShareThumbnailFromItsOwnDirectory(): void
    {
        $this->writeOnePixelPng('presentations/slideshare/99/thumbnail.png');
        $item = (object) [
            'slidesId' => '99',
            'presentation' => ['type' => 'slideshare'],
        ];

        $thumbnail = (new PresentationThumbnailResolver($this->projectRoot))->resolve($item);

        self::assertSame('/presentations/slideshare/99/thumbnail.png', $thumbnail['path']);
        self::assertSame(1, $thumbnail['width']);
        self::assertSame(1, $thumbnail['height']);
    }

    public function testReturnsNullWhenNoThumbnailIsAvailable(): void
    {
        $thumbnail = (new PresentationThumbnailResolver($this->projectRoot))->resolve((object) []);

        self::assertNull($thumbnail);
    }

    private function writeOnePixelPng(string $relativePath): void
    {
        $path = $this->projectRoot . '/' . $relativePath;
        mkdir(dirname($path), 0777, true);
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($bytes);
        file_put_contents($path, $bytes);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;
            if (is_dir($entryPath)) {
                $this->deleteDirectory($entryPath);
            } else {
                unlink($entryPath);
            }
        }

        rmdir($path);
    }
}
