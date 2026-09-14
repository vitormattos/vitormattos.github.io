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
            'embed_url' => 'https://slides.com/vitormattos/private-deck/embed',
        ], '/tmp/deck.pdf');
    }

    public function testTeamDeckCanNeverProduceExportCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PdfExportPolicy::deckTapeArguments([
            'visibility' => 'team',
            'embed_url' => 'https://slides.com/vitormattos/team-deck/embed',
        ], '/tmp/deck.pdf');
    }

    public function testExportAcceptsOnlyOwnedPublicSlidesEmbedUrl(): void
    {
        foreach ([
            'http://slides.com/vitormattos/deck/embed',
            'https://example.com/vitormattos/deck/embed',
            'https://slides.com/another-user/deck/embed',
            'https://slides.com/vitormattos/deck',
        ] as $url) {
            try {
                PdfExportPolicy::deckTapeArguments(['visibility' => 'all', 'embed_url' => $url], '/tmp/deck.pdf');
                self::fail("Embed URL should have been rejected: {$url}");
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testExportCommandUsesEmbedAndPreservesDeckDimensions(): void
    {
        $arguments = PdfExportPolicy::deckTapeArguments([
            'visibility' => 'all',
            'url' => 'https://slides.com/vitormattos/public-deck',
            'embed_url' => 'https://slides.com/vitormattos/public-deck/embed',
            'width' => 960,
            'height' => 540,
        ], '/tmp/deck.pdf');

        self::assertSame('decktape@' . PdfExportPolicy::DECKTAPE_VERSION, $arguments[2]);
        self::assertSame('reveal', $arguments[3]);
        self::assertContains('960x540', $arguments);
        self::assertSame('https://slides.com/vitormattos/public-deck/embed', $arguments[count($arguments) - 2]);
        self::assertSame('/tmp/deck.pdf', $arguments[count($arguments) - 1]);
    }

    public function testInvalidDimensionsCannotCreateAnUnusableViewport(): void
    {
        $arguments = PdfExportPolicy::deckTapeArguments([
            'visibility' => 'all',
            'embed_url' => 'https://slides.com/vitormattos/public-deck/embed',
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

    public function testPdfFilenameUsesDeckSlug(): void
    {
        self::assertSame(
            'quem-controla-sua-tecnologia-controla-seu-futuro-ufpb.pdf',
            PdfExportPolicy::pdfFilename(['id' => 123, 'slug' => 'Quem controla sua tecnologia controla seu futuro - UFPB']),
        );
        self::assertSame('deck-123.pdf', PdfExportPolicy::pdfFilename(['id' => 123]));
    }

    public function testSourceFingerprintChangesWhenAnyVersionedSourceArtifactChanges(): void
    {
        $directory = $this->createDeckDirectory();
        try {
            $original = PdfExportPolicy::sourceFingerprint($directory, 'generator-a');
            foreach (['deck.html', 'deck.css', 'metadata.json'] as $filename) {
                $before = file_get_contents($directory . '/' . $filename);
                file_put_contents($directory . '/' . $filename, $before . "\nchanged");
                self::assertNotSame($original, PdfExportPolicy::sourceFingerprint($directory, 'generator-a'));
                file_put_contents($directory . '/' . $filename, $before);
            }
            self::assertNotSame($original, PdfExportPolicy::sourceFingerprint($directory, 'generator-b'));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testVersionedManifestAllowsReuseOnlyWhileSourcesGeneratorAndPdfMatch(): void
    {
        $directory = $this->createDeckDirectory();
        $metadata = $this->publicDeckMetadata();
        $metadata['slug'] = 'public-deck';
        $pdf = $directory . '/public-deck.pdf';
        $manifest = $directory . '/' . PdfExportPolicy::EXPORT_MANIFEST;

        try {
            file_put_contents($pdf, '%PDF-' . str_repeat('x', PdfExportPolicy::MINIMUM_PDF_BYTES));
            file_put_contents(
                $manifest,
                json_encode(PdfExportPolicy::exportManifest($directory, $metadata, $pdf, 'generator-a'), JSON_THROW_ON_ERROR),
            );
            self::assertTrue(PdfExportPolicy::manifestMatches($manifest, $directory, $metadata, 'generator-a'));

            file_put_contents($directory . '/deck.css', 'changed');
            self::assertFalse(PdfExportPolicy::manifestMatches($manifest, $directory, $metadata, 'generator-a'));
            file_put_contents($directory . '/deck.css', 'css');
            self::assertFalse(PdfExportPolicy::manifestMatches($manifest, $directory, $metadata, 'generator-b'));
            file_put_contents($pdf, '%PDF-' . str_repeat('y', PdfExportPolicy::MINIMUM_PDF_BYTES));
            self::assertFalse(PdfExportPolicy::manifestMatches($manifest, $directory, $metadata, 'generator-a'));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testUnchangedDeckAndGeneratorReuseTheSameCacheKey(): void
    {
        $metadata = $this->publicDeckMetadata();
        self::assertSame(PdfExportPolicy::deckFingerprint($metadata, 'generator-a'), PdfExportPolicy::deckFingerprint($metadata, 'generator-a'));
    }

    public function testDeckChangesThatAffectRenderingInvalidateTheCache(): void
    {
        $metadata = $this->publicDeckMetadata();
        $original = PdfExportPolicy::deckFingerprint($metadata, 'generator-a');
        foreach ([
            ['updated_at' => '2026-09-15T00:00:00Z'],
            ['url' => 'https://slides.com/vitormattos/renamed-deck'],
            ['embed_url' => 'https://slides.com/vitormattos/renamed-deck/embed'],
            ['width' => 1280],
            ['height' => 720],
            ['slide_count' => 25],
        ] as $change) {
            self::assertNotSame($original, PdfExportPolicy::deckFingerprint(array_replace($metadata, $change), 'generator-a'));
        }
    }

    public function testGeneratorChangeInvalidatesEveryDeckCacheKey(): void
    {
        $metadata = $this->publicDeckMetadata();
        self::assertNotSame(PdfExportPolicy::deckFingerprint($metadata, 'generator-a'), PdfExportPolicy::deckFingerprint($metadata, 'generator-b'));
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
            'embed_url' => 'https://slides.com/vitormattos/public-deck/embed',
            'width' => 960,
            'height' => 540,
            'slide_count' => 24,
            'updated_at' => '2026-09-14T12:00:00Z',
        ];
    }

    private function createDeckDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/slides-contract-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/deck.html', '<section>slide</section>');
        file_put_contents($directory . '/deck.css', 'css');
        file_put_contents($directory . '/metadata.json', '{}');

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}
