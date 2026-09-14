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

        self::assertSame('decktape@3.16.1', $arguments[2]);
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
}
