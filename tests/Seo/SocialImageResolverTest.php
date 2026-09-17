<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Seo;

use App\Presentations\PresentationThumbnailResolver;
use App\Seo\SocialImageResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocialImageResolverTest extends TestCase
{
    private string $projectRoot;
    private SocialImageResolver $resolver;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/social-image-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot, 0777, true);
        $this->resolver = new SocialImageResolver(new PresentationThumbnailResolver($this->projectRoot));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);
    }

    #[DataProvider('imageProvider')]
    public function testResolvesImagePriority(array $properties, string $expectedUrl, string $expectedCard): void
    {
        $page = $this->page($properties);

        $result = $this->resolver->resolve($page, 'https://example.com', false, 'Example page');

        self::assertSame($expectedUrl, $result['url']);
        self::assertSame($expectedCard, $result['twitterCard']);
    }

    public static function imageProvider(): iterable
    {
        yield 'explicit social image' => [
            ['socialImage' => '/images/social.png', 'image' => '/images/fallback.png'],
            'https://example.com/images/social.png',
            'summary_large_image',
        ];

        yield 'page image fallback' => [
            ['image' => '/images/page.png'],
            'https://example.com/images/page.png',
            'summary_large_image',
        ];

        yield 'academic image fallback' => [
            ['academic' => ['image' => 'https://cdn.example.com/paper.jpg']],
            'https://cdn.example.com/paper.jpg',
            'summary_large_image',
        ];

        yield 'author fallback' => [
            [],
            'https://cdn.example.com/avatar-512.png',
            'summary',
        ];
    }

    public function testArchivedThumbnailTakesPriorityOverRemotePresentationThumbnail(): void
    {
        $this->writeOnePixelPng('presentations/slides.com/42/thumbnail.png');
        $page = $this->page([
            'slidesId' => 42,
            'presentation' => [
                'type' => 'slides.com',
                'thumbnail' => 'https://cdn.example.com/remote.png',
            ],
        ]);

        $result = $this->resolver->resolve($page, 'https://example.com', false, 'Talk');

        self::assertSame('https://example.com/presentations/slides.com/42/thumbnail.png', $result['url']);
        self::assertSame(1, $result['width']);
        self::assertSame(1, $result['height']);
    }

    #[DataProvider('altProvider')]
    public function testResolvesAltText(bool $isProfilePage, ?string $explicitAlt, string $expected): void
    {
        $page = $this->page(['socialImageAlt' => $explicitAlt]);

        $result = $this->resolver->resolve($page, 'https://example.com', $isProfilePage, 'Page title');

        self::assertSame($expected, $result['alt']);
    }

    public static function altProvider(): iterable
    {
        yield 'explicit alt' => [false, 'Custom preview', 'Custom preview'];
        yield 'profile fallback' => [true, null, 'Vitor Mattos'];
        yield 'page title fallback' => [false, null, 'Page title'];
    }

    private function page(array $properties): SeoPageStub
    {
        return new SeoPageStub('/', array_replace_recursive([
            'author' => [
                'name' => 'Vitor Mattos',
                'avatar' => 'https://cdn.example.com/avatar.png',
                'socialImage' => 'https://cdn.example.com/avatar-512.png',
            ],
        ], $properties));
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

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;
            is_dir($entryPath) ? $this->deleteDirectory($entryPath) : unlink($entryPath);
        }

        rmdir($path);
    }
}
