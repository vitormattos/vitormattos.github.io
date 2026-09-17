<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Seo;

use App\Seo\PageUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageUrlResolverTest extends TestCase
{
    #[DataProvider('canonicalProvider')]
    public function testCanonicalResolution(
        string $path,
        string $locale,
        ?string $alternateUrl,
        string $expectedCanonical,
        ?string $expectedAlternate,
        string $expectedEnglish,
        string $expectedPageType,
    ): void {
        $page = new SeoPageStub($path, [
            'siteUrl' => 'https://example.com',
            'defaultLocale' => 'en',
            'locale' => $locale,
            'alternateUrl' => $alternateUrl,
        ]);

        $result = (new PageUrlResolver())->resolve($page);

        self::assertSame($expectedCanonical, $result['canonicalUrl']);
        self::assertSame($expectedAlternate, $result['alternateCanonicalUrl']);
        self::assertSame($expectedEnglish, $result['englishCanonicalUrl']);
        self::assertSame($expectedPageType, $result['pageType']);
    }

    public static function canonicalProvider(): iterable
    {
        yield 'english home' => [
            '/',
            'en',
            '/pt-BR',
            'https://example.com/',
            'https://example.com/pt-BR',
            'https://example.com/',
            'ProfilePage',
        ];

        yield 'portuguese home' => [
            '/pt-BR/',
            'pt-BR',
            '/',
            'https://example.com/pt-BR',
            'https://example.com/',
            'https://example.com/',
            'ProfilePage',
        ];

        yield 'portuguese article' => [
            '/pt-BR/artigos/exemplo/',
            'pt-BR',
            '/articles/example/',
            'https://example.com/pt-BR/artigos/exemplo',
            'https://example.com/articles/example',
            'https://example.com/articles/example',
            'WebPage',
        ];

        yield 'portuguese page without translation' => [
            '/pt-BR/sobre/',
            'pt-BR',
            null,
            'https://example.com/pt-BR/sobre',
            null,
            'https://example.com/',
            'WebPage',
        ];
    }

    public function testExplicitPageTypeOverridesProfileDefault(): void
    {
        $page = new SeoPageStub('/', [
            'siteUrl' => 'https://example.com',
            'defaultLocale' => 'en',
            'locale' => 'en',
            'pageType' => 'AboutPage',
        ]);

        $result = (new PageUrlResolver())->resolve($page);

        self::assertSame('AboutPage', $result['pageType']);
    }
}
