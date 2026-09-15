<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\SlidesReleaseMetadata;
use PHPUnit\Framework\TestCase;

final class SlidesReleaseMetadataTest extends TestCase
{
    public function testReleaseUsesPresentationMetadataAsPortfolioContent(): void
    {
        $metadata = [
            'id' => 3629052,
            'slug' => 'quem-controla-sua-tecnologia-controla-seu-futuro',
            'title' => 'Quem controla sua tecnologia, controla seu futuro',
            'description' => 'Uma apresentação sobre autonomia tecnológica.',
            'url' => 'https://slides.com/vitormattos/example',
            'language' => 'pt-BR',
            'slide_count' => 76,
            'created_at' => '2026-01-02T10:00:00Z',
            'updated_at' => '2026-09-10T11:00:00Z',
            'tags' => ['slides_com' => ['software livre', 'privacidade']],
            'thumbnail_path' => '/presentations/slides.com/3629052/thumbnail.jpg',
        ];

        self::assertSame(
            'Quem controla sua tecnologia, controla seu futuro',
            SlidesReleaseMetadata::title($metadata),
        );

        $thumbnailAsset = 'https://github.com/vitormattos/vitormattos.github.io/releases/download/slides-com-3629052/thumbnail-deadbeef1234.jpg';
        $pdfAsset = 'https://github.com/vitormattos/vitormattos.github.io/releases/download/slides-com-3629052/slides-com-3629052-cafebabe1234.pdf';
        $body = SlidesReleaseMetadata::body(
            $metadata,
            'vitormattos/vitormattos.github.io',
            $thumbnailAsset,
            $pdfAsset,
        );

        self::assertStringContainsString('Uma apresentação sobre autonomia tecnológica.', $body);
        self::assertStringContainsString('![Presentation thumbnail](' . $thumbnailAsset . ')', $body);
        self::assertStringContainsString('**Latest archived PDF:** ' . $pdfAsset, $body);
        self::assertStringContainsString('https://slides.com/vitormattos/example', $body);
        self::assertStringContainsString('https://vitormattos.github.io/pt-BR/palestras/quem-controla-sua-tecnologia-controla-seu-futuro', $body);
        self::assertStringContainsString('**Website:** https://vitormattos.github.io', $body);
        self::assertStringContainsString('**Language:** pt-BR', $body);
        self::assertStringContainsString('**Slides:** 76', $body);
        self::assertStringContainsString('**Tags:** software livre, privacidade', $body);
    }

    public function testEnglishPresentationUsesEnglishPortfolioPath(): void
    {
        $metadata = [
            'id' => 123,
            'slug' => 'example-talk',
            'language' => 'en',
        ];

        $body = SlidesReleaseMetadata::body($metadata, 'vitormattos/vitormattos.github.io');
        self::assertStringContainsString('https://vitormattos.github.io/talks/example-talk', $body);
    }

    public function testReleaseMetadataWorksWithoutOptionalPortfolioFields(): void
    {
        $metadata = ['id' => 123];

        self::assertSame('Slides.com presentation 123', SlidesReleaseMetadata::title($metadata));
        $body = SlidesReleaseMetadata::body($metadata, 'vitormattos/vitormattos.github.io');
        self::assertStringNotContainsString('Presentation thumbnail', $body);
        self::assertStringNotContainsString('**Presentation page:**', $body);
        self::assertStringContainsString('immutable Slides.com deck ID', $body);
    }
}
