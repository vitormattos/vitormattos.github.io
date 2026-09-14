<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\PdfExportPolicy;
use PHPUnit\Framework\TestCase;

final class PdfExportPolicyTest extends TestCase
{
    public function testPrivateDeckCanNeverProduceExportCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PdfExportPolicy::deckTapeArguments([
            'visibility' => 'self',
            'url' => 'https://slides.com/vitormattos/private-deck',
        ], '/tmp/deck.pdf');
    }

    public function testTeamDeckCanNeverProduceExportCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PdfExportPolicy::deckTapeArguments([
            'visibility' => 'team',
            'url' => 'https://slides.com/vitormattos/team-deck',
        ], '/tmp/deck.pdf');
    }

    public function testExportAcceptsOnlyOwnedPublicSlidesUrl(): void
    {
        foreach ([
            'http://slides.com/vitormattos/deck',
            'https://example.com/vitormattos/deck',
            'https://slides.com/another-user/deck',
        ] as $url) {
            try {
                PdfExportPolicy::deckTapeArguments(['visibility' => 'all', 'url' => $url], '/tmp/deck.pdf');
                self::fail("URL should have been rejected: {$url}");
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testExportCommandPinsDeckTapeAndPreservesDeckDimensions(): void
    {
        $arguments = PdfExportPolicy::deckTapeArguments([
            'visibility' => 'all',
            'url' => 'https://slides.com/vitormattos/public-deck',
            'width' => 960,
            'height' => 540,
        ], '/tmp/deck.pdf');

        self::assertSame('decktape@' . PdfExportPolicy::DECKTAPE_VERSION, $arguments[2]);
        self::assertSame('reveal', $arguments[3]);
        self::assertContains('960x540', $arguments);
        self::assertSame('https://slides.com/vitormattos/public-deck', $arguments[count($arguments) - 2]);
        self::assertSame('/tmp/deck.pdf', $arguments[count($arguments) - 1]);
    }

    public function testInvalidDimensionsCannotCreateAnUnusableViewport(): void
    {
        $arguments = PdfExportPolicy::deckTapeArguments([
            'visibility' => 'all',
            'url' => 'https://slides.com/vitormattos/public-deck',
            'width' => 0,
            'height' => -1,
        ], '/tmp/deck.pdf');

        self::assertContains('320x180', $arguments);
    }

    public function testPdfMustHaveSignatureAndMinimumUsefulSize(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'slides-pdf-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, '%PDF-' . str_repeat('x', PdfExportPolicy::MINIMUM_PDF_BYTES));
            self::assertTrue(PdfExportPolicy::isValidPdf($path));

            file_put_contents($path, 'not-a-pdf' . str_repeat('x', PdfExportPolicy::MINIMUM_PDF_BYTES));
            self::assertFalse(PdfExportPolicy::isValidPdf($path));

            file_put_contents($path, '%PDF-tiny');
            self::assertFalse(PdfExportPolicy::isValidPdf($path));
        } finally {
            @unlink($path);
        }
    }

    public function testUnchangedDeckAndGeneratorReuseTheSameCacheKey(): void
    {
        $metadata = $this->publicDeckMetadata();

        self::assertSame(
            PdfExportPolicy::deckFingerprint($metadata, 'generator-a'),
            PdfExportPolicy::deckFingerprint($metadata, 'generator-a'),
        );
    }

    public function testDeckChangesThatAffectRenderingInvalidateTheCache(): void
    {
        $metadata = $this->publicDeckMetadata();
        $original = PdfExportPolicy::deckFingerprint($metadata, 'generator-a');

        foreach ([
            ['updated_at' => '2026-09-15T00:00:00Z'],
            ['url' => 'https://slides.com/vitormattos/renamed-deck'],
            ['width' => 1280],
            ['height' => 720],
            ['slide_count' => 25],
        ] as $change) {
            self::assertNotSame(
                $original,
                PdfExportPolicy::deckFingerprint(array_replace($metadata, $change), 'generator-a'),
            );
        }
    }

    public function testGeneratorChangeInvalidatesEveryDeckCacheKey(): void
    {
        $metadata = $this->publicDeckMetadata();

        self::assertNotSame(
            PdfExportPolicy::deckFingerprint($metadata, 'generator-a'),
            PdfExportPolicy::deckFingerprint($metadata, 'generator-b'),
        );
    }

    public function testCachePathIsScopedByDeckAndFingerprint(): void
    {
        $path = PdfExportPolicy::cachePath('/tmp/slides-cache', $this->publicDeckMetadata(), 'generator-a');

        self::assertStringStartsWith('/tmp/slides-cache/123-', $path);
        self::assertStringEndsWith('.pdf', $path);
    }

    private function publicDeckMetadata(): array
    {
        return [
            'id' => 123,
            'visibility' => 'all',
            'url' => 'https://slides.com/vitormattos/public-deck',
            'width' => 960,
            'height' => 540,
            'slide_count' => 24,
            'updated_at' => '2026-09-14T12:00:00Z',
        ];
    }
}
