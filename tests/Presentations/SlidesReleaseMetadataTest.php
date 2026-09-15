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

        $body = SlidesReleaseMetadata::body($metadata, 'vitormattos/vitormattos.github.io');
        self::assertStringContainsString('Uma apresentação sobre autonomia tecnológica.', $body);
        self::assertStringContainsString('https://slides.com/vitormattos/example', $body);
        self::assertStringContainsString('**Language:** pt-BR', $body);
        self::assertStringContainsString('**Slides:** 76', $body);
        self::assertStringContainsString('**Tags:** software livre, privacidade', $body);
        self::assertStringContainsString('thumbnail.jpg', $body);
    }

    public function testReleaseMetadataWorksWithoutOptionalPortfolioFields(): void
    {
        $metadata = ['id' => 123];

        self::assertSame('Slides.com presentation 123', SlidesReleaseMetadata::title($metadata));
        $body = SlidesReleaseMetadata::body($metadata, 'vitormattos/vitormattos.github.io');
        self::assertStringNotContainsString('Presentation thumbnail', $body);
        self::assertStringContainsString('immutable Slides.com deck ID', $body);
    }
}
