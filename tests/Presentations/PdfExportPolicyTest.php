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
        PdfExportPolicy::deckTapeArguments(['visibility' => 'self', 'embed_url' => 'https://slides.com/vitormattos/private-deck/embed'], '/tmp/deck.pdf');
    }

    public function testTeamDeckCanNeverProduceExportCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PdfExportPolicy::deckTapeArguments(['visibility' => 'team', 'embed_url' => 'https://slides.com/vitormattos/team-deck/embed'], '/tmp/deck.pdf');
    }

    public function testExportAcceptsOnlyOwnedPublicSlidesEmbedUrl(): void
    {
        foreach (['http://slides.com/vitormattos/deck/embed', 'https://example.com/vitormattos/deck/embed', 'https://slides.com/another-user/deck/embed', 'https://slides.com/vitormattos/deck'] as $url) {
            try {
                PdfExportPolicy::deckTapeArguments(['visibility' => 'all', 'embed_url' => $url], '/tmp/deck.pdf');
                self::fail("Embed URL should have been rejected: {$url}");
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testExportCommandUsesInstalledDeckTapeWithoutArtificialPause(): void
    {
        $arguments = PdfExportPolicy::deckTapeArguments([
            'visibility' => 'all',
            'embed_url' => 'https://slides.com/vitormattos/public-deck/embed',
            'width' => 960,
            'height' => 540,
        ], '/tmp/deck.pdf');

        self::assertSame('node_modules/.bin/decktape', $arguments[0]);
        self::assertSame('reveal', $arguments[1]);
        self::assertContains('960x540', $arguments);
        self::assertContains('0', $arguments);
        self::assertSame('https://slides.com/vitormattos/public-deck/embed', $arguments[count($arguments) - 2]);
        self::assertSame('/tmp/deck.pdf', $arguments[count($arguments) - 1]);
    }

    public function testInvalidDimensionsCannotCreateAnUnusableViewport(): void
    {
        $arguments = PdfExportPolicy::deckTapeArguments(['visibility' => 'all', 'embed_url' => 'https://slides.com/vitormattos/public-deck/embed', 'width' => 0, 'height' => -1], '/tmp/deck.pdf');
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

    public function testReleaseIdentityUsesStableSlidesDeckId(): void
    {
        $metadata = ['id' => 3629052, 'slug' => 'title-may-change'];
        self::assertSame('slides-com-3629052', PdfExportPolicy::releaseTag($metadata));
        self::assertSame('slides-com-3629052-abcdef123456.pdf', PdfExportPolicy::releaseAssetName('abcdef1234567890', $metadata));
    }

    public function testSourceFingerprintChangesOnlyForRenderRelevantSource(): void
    {
        $directory = $this->createDeckDirectory();
        $metadata = $this->metadata();
        try {
            $original = PdfExportPolicy::sourceFingerprint($directory, $metadata);

            foreach (['deck.html', 'deck.css'] as $filename) {
                $before = file_get_contents($directory . '/' . $filename);
                file_put_contents($directory . '/' . $filename, $before . "\nchanged");
                self::assertNotSame($original, PdfExportPolicy::sourceFingerprint($directory, $metadata));
                file_put_contents($directory . '/' . $filename, $before);
            }

            $changedRenderMetadata = $metadata;
            $changedRenderMetadata['width'] = 1280;
            self::assertNotSame($original, PdfExportPolicy::sourceFingerprint($directory, $changedRenderMetadata));

            $changedNonRenderMetadata = $metadata;
            $changedNonRenderMetadata['title'] = 'A new title';
            $changedNonRenderMetadata['tags'] = ['slides_com' => ['new-tag']];
            self::assertSame($original, PdfExportPolicy::sourceFingerprint($directory, $changedNonRenderMetadata));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testSourceArtifactsAllowMissingOptionalCss(): void
    {
        $directory = $this->createDeckDirectory();
        try {
            unlink($directory . '/deck.css');
            $artifacts = PdfExportPolicy::sourceArtifacts($directory);
            self::assertArrayHasKey('deck.html', $artifacts);
            self::assertArrayNotHasKey('deck.css', $artifacts);
            self::assertNotSame('', PdfExportPolicy::sourceFingerprint($directory, $this->metadata()));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testGeneratorVersionDoesNotInvalidateSourceFingerprint(): void
    {
        $directory = $this->createDeckDirectory();
        try {
            $metadata = $this->metadata();
            $fingerprint = PdfExportPolicy::sourceFingerprint($directory, $metadata);
            self::assertSame($fingerprint, PdfExportPolicy::sourceFingerprint($directory, $metadata));
            self::assertNotSame('', PdfExportPolicy::generatorFingerprint());
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testCachePathIsContentAddressedByPresentationSource(): void
    {
        $directory = $this->createDeckDirectory();
        try {
            $metadata = $this->metadata();
            $fingerprint = PdfExportPolicy::sourceFingerprint($directory, $metadata);
            self::assertSame(
                '/tmp/slides-cache/slides-com-123-' . substr($fingerprint, 0, 12) . '.pdf',
                PdfExportPolicy::cachePath('/tmp/slides-cache', $directory, $metadata),
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testManifestMatchesSourceWithoutDependingOnGeneratorVersionOrLocalPdf(): void
    {
        $directory = $this->createDeckDirectory();
        $metadata = $this->metadata();
        $pdf = $directory . '/generated.pdf';
        $manifest = $directory . '/' . PdfExportPolicy::EXPORT_MANIFEST;

        try {
            file_put_contents($pdf, '%PDF-' . str_repeat('x', PdfExportPolicy::MINIMUM_PDF_BYTES));
            file_put_contents($manifest, json_encode(PdfExportPolicy::exportManifest(
                $directory,
                $metadata,
                $pdf,
                'vitormattos/vitormattos.github.io',
                'generator-a',
            ), JSON_THROW_ON_ERROR));

            @unlink($pdf);
            self::assertTrue(PdfExportPolicy::manifestMatchesSource($manifest, $directory, $metadata));

            file_put_contents($directory . '/deck.css', 'changed');
            self::assertFalse(PdfExportPolicy::manifestMatchesSource($manifest, $directory, $metadata));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function metadata(): array
    {
        return [
            'id' => 123,
            'slug' => 'public-deck',
            'width' => 960,
            'height' => 540,
            'margin' => 0.04,
            'transition' => 'slide',
            'background_transition' => 'fade',
            'rtl' => false,
            'loop' => false,
            'theme_font' => 'montserrat',
            'theme_color' => 'black',
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
