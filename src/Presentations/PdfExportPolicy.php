<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class PdfExportPolicy
{
    public const MINIMUM_PDF_BYTES = 10_000;
    public const DECKTAPE_VERSION = '3.16.1';
    public const CACHE_SCHEMA_VERSION = 2;

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
