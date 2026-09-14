<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class PdfExportPolicy
{
    public const MINIMUM_PDF_BYTES = 10_000;
    public const DECKTAPE_VERSION = '3.16.1';
    public const CACHE_SCHEMA_VERSION = 3;
    public const EXPORT_MANIFEST = 'export.json';

    public static function isValidPdf(string $path): bool
    {
        if (!is_file($path) || filesize($path) < self::MINIMUM_PDF_BYTES) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    public static function pdfFilename(array $metadata): string
    {
        $slug = strtolower((string) ($metadata['slug'] ?? ''));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

        if ($slug === '') {
            $id = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) ($metadata['id'] ?? 'deck'));
            $slug = 'deck-' . $id;
        }

        return $slug . '.pdf';
    }

    public static function generatorFingerprint(): string
    {
        $files = [
            __FILE__,
            __DIR__ . '/SlidesDeckPolicy.php',
            dirname(__DIR__, 2) . '/scripts/generate-slides-pdfs.php',
        ];
        $hash = hash_init('sha256');
        hash_update($hash, 'cache-schema:' . self::CACHE_SCHEMA_VERSION . "\n");
        hash_update($hash, 'decktape:' . self::DECKTAPE_VERSION . "\n");

        foreach ($files as $file) {
            hash_update($hash, basename($file) . ':');
            hash_update_file($hash, $file);
            hash_update($hash, "\n");
        }

        return hash_final($hash);
    }

    public static function sourceArtifacts(string $deckDirectory): array
    {
        $artifacts = [];
        foreach (['deck.html', 'deck.css', 'metadata.json'] as $filename) {
            $path = rtrim($deckDirectory, '/') . '/' . $filename;
            if (!is_file($path)) {
                throw new \RuntimeException("Required presentation artifact is missing: {$filename}");
            }
            $artifacts[$filename] = hash_file('sha256', $path);
        }

        return $artifacts;
    }

    public static function sourceFingerprint(string $deckDirectory, ?string $generatorFingerprint = null): string
    {
        $payload = [
            'artifacts' => self::sourceArtifacts($deckDirectory),
            'generator' => $generatorFingerprint ?? self::generatorFingerprint(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function exportManifest(
        string $deckDirectory,
        array $metadata,
        string $pdfPath,
        ?string $generatorFingerprint = null,
    ): array {
        $generator = $generatorFingerprint ?? self::generatorFingerprint();

        return [
            '_spdx' => [
                'copyright' => '2026 Vitor Mattos',
                'license' => 'CC-BY-SA-4.0',
            ],
            'schema' => self::CACHE_SCHEMA_VERSION,
            'source' => [
                'sha256' => self::sourceFingerprint($deckDirectory, $generator),
                'artifacts' => self::sourceArtifacts($deckDirectory),
            ],
            'generator' => [
                'sha256' => $generator,
                'decktape' => self::DECKTAPE_VERSION,
            ],
            'pdf' => [
                'file' => basename($pdfPath),
                'sha256' => hash_file('sha256', $pdfPath),
                'bytes' => filesize($pdfPath),
            ],
        ];
    }

    public static function manifestMatches(
        string $manifestPath,
        string $deckDirectory,
        array $metadata,
        ?string $generatorFingerprint = null,
    ): bool {
        if (!is_file($manifestPath)) {
            return false;
        }

        try {
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }

        $pdfPath = rtrim($deckDirectory, '/') . '/' . self::pdfFilename($metadata);
        if (!self::isValidPdf($pdfPath)) {
            return false;
        }

        $generator = $generatorFingerprint ?? self::generatorFingerprint();

        return ($manifest['schema'] ?? null) === self::CACHE_SCHEMA_VERSION
            && ($manifest['source']['sha256'] ?? null) === self::sourceFingerprint($deckDirectory, $generator)
            && ($manifest['generator']['sha256'] ?? null) === $generator
            && ($manifest['pdf']['file'] ?? null) === basename($pdfPath)
            && ($manifest['pdf']['sha256'] ?? null) === hash_file('sha256', $pdfPath);
    }

    public static function deckFingerprint(array $metadata, ?string $generatorFingerprint = null): string
    {
        $relevant = [
            'id' => (string) ($metadata['id'] ?? ''),
            'url' => (string) ($metadata['url'] ?? ''),
            'embed_url' => (string) ($metadata['embed_url'] ?? ''),
            'visibility' => (string) ($metadata['visibility'] ?? ''),
            'width' => (int) ($metadata['width'] ?? 0),
            'height' => (int) ($metadata['height'] ?? 0),
            'slide_count' => (int) ($metadata['slide_count'] ?? 0),
            'updated_at' => (string) ($metadata['updated_at'] ?? ''),
            'generator' => $generatorFingerprint ?? self::generatorFingerprint(),
        ];

        return hash('sha256', json_encode($relevant, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function cachePath(string $cacheDirectory, array $metadata, ?string $generatorFingerprint = null): string
    {
        $deckId = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) ($metadata['id'] ?? 'deck'));

        return rtrim($cacheDirectory, '/') . '/' . $deckId . '-' . self::deckFingerprint($metadata, $generatorFingerprint) . '.pdf';
    }

    public static function deckTapeArguments(array $metadata, string $output): array
    {
        if (!SlidesDeckPolicy::isPublic($metadata)) {
            throw new \InvalidArgumentException('Only public Slides.com decks may be exported.');
        }

        $embedUrl = $metadata['embed_url'] ?? null;
        if (!SlidesDeckPolicy::isAllowedEmbedUrl($embedUrl)) {
            throw new \InvalidArgumentException('Deck embed URL is not an allowed public Slides.com embed URL.');
        }

        return [
            'npx',
            '--yes',
            'decktape@' . self::DECKTAPE_VERSION,
            'reveal',
            '--size',
            SlidesDeckPolicy::viewport($metadata),
            '--load-pause',
            '3000',
            '--pause',
            '250',
            '--url-load-timeout',
            '60000',
            '--page-load-timeout',
            '30000',
            '--chrome-arg=--no-sandbox',
            '--chrome-arg=--disable-dev-shm-usage',
            $embedUrl,
            $output,
        ];
    }
}
