<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\PresentationAssetResolver;
use PHPUnit\Framework\TestCase;

final class PresentationAssetResolverTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/presentation-assets-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);
    }

    public function testResolvesManagedLatexPreviewFromGeneratedAssets(): void
    {
        $item = (object) [
            'managed' => 'latex',
            'slug' => 'example',
            'presentation' => ['type' => 'pdf'],
        ];

        $assets = (new PresentationAssetResolver($this->projectRoot))->resolve(
            $item,
            'preview',
            'https://example.test/pr-preview/pr-1',
        );

        self::assertSame(
            'https://example.test/pr-preview/pr-1/presentations/latex/example/example.pdf',
            $assets['url'],
        );
        self::assertSame($assets['url'], $assets['pdf']);
        self::assertSame(
            'https://example.test/pr-preview/pr-1/presentations/latex/example/thumbnail.png',
            $assets['thumbnail'],
        );
    }

    public function testResolvesManagedLatexProductionFromReleaseManifest(): void
    {
        $directory = $this->projectRoot . '/presentations/latex/example';
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/export.json', json_encode([
            'release' => [
                'assets' => [
                    'pdf' => ['url' => 'https://example.test/example-source.pdf'],
                    'thumbnail' => ['url' => 'https://example.test/thumbnail-source.png'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $item = (object) [
            'managed' => 'latex',
            'slug' => 'example',
            'presentation' => ['type' => 'pdf'],
        ];

        $assets = (new PresentationAssetResolver($this->projectRoot))->resolve($item);

        self::assertSame('https://example.test/example-source.pdf', $assets['url']);
        self::assertSame('https://example.test/example-source.pdf', $assets['pdf']);
        self::assertSame('https://example.test/thumbnail-source.png', $assets['thumbnail']);
    }

    public function testResolvesArchivedSlidesAssetsFromManifest(): void
    {
        $directory = $this->projectRoot . '/presentations/slideshare/42';
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/export.json', json_encode([
            'assets' => [
                'pdf' => ['url' => 'https://example.test/archive.pdf'],
                'pptx' => ['url' => 'https://example.test/archive.pptx'],
                'original' => ['url' => 'https://example.test/archive.key'],
                'thumbnail' => ['url' => 'https://example.test/archive.png'],
            ],
        ], JSON_THROW_ON_ERROR));

        $item = (object) [
            'slidesId' => '42',
            'presentation' => ['type' => 'slideshare', 'url' => 'https://slideshare.example/original'],
        ];

        $assets = (new PresentationAssetResolver($this->projectRoot))->resolve($item);

        self::assertSame('https://slideshare.example/original', $assets['url']);
        self::assertSame('https://example.test/archive.pdf', $assets['pdf']);
        self::assertSame('https://example.test/archive.pptx', $assets['pptx']);
        self::assertSame('https://example.test/archive.key', $assets['original']);
        self::assertSame('https://example.test/archive.png', $assets['thumbnail']);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
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
