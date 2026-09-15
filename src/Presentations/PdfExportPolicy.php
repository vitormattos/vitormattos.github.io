<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class PdfExportPolicy
{
    public const MINIMUM_PDF_BYTES = 10_000;
    public const DECKTAPE_VERSION = '3.16.1';
    public const MANIFEST_SCHEMA_VERSION = 4;
    public const EXPORT_MANIFEST = 'export.json';

    private const RENDER_METADATA_KEYS = [
        'width',
        'height',
        'margin',
        'transition',
        'background_transition',
        'rtl',
        'loop',
        'theme_font',
        'theme_color',
    ];

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

    public static function releaseTag(array $metadata): string
    {
        $deckId = self::safeDeckId($metadata);

        return 'slides-com-' . $deckId;
    }

    public static function releaseAssetName(string $sourceFingerprint, array $metadata): string
    {
        return self::releaseTag($metadata) . '-' . substr($sourceFingerprint, 0, 12) . '.pdf';
    }

    public static function releaseAssetUrl(string $repository, string $sourceFingerprint, array $metadata): string
    {
        return sprintf(
            'https://github.com/%s/releases/download/%s/%s',
            trim($repository, '/'),
            rawurlencode(self::releaseTag($metadata)),
            rawurlencode(self::releaseAssetName($sourceFingerprint, $metadata)),
        );
    }

    public static function generatorFingerprint(): string
    {
        $files = [
            __FILE__,
            __DIR__ . '/SlidesDeckPolicy.php',
            dirname(__DIR__, 2) . '/scripts/generate-slides-pdfs.php',
            dirname(__DIR__, 2) . '/scripts/slides/decktape-reveal.js',
        ];
        $hash = hash_init('sha256');
        hash_update($hash, 'decktape:' . self::DECKTAPE_VERSION . "\n");
        foreach ($files as $file) {
            hash_update($hash, basename($file) . ':');
            if (is_file($file)) {
                hash_update_file($hash, $file);
            }
            hash_update($hash, "\n");
        }

        return hash_final($hash);
    }

    public static function sourceArtifacts(string $deckDirectory): array
    {
        $htmlPath = rtrim($deckDirectory, '/') . '/deck.html';
        if (!is_file($htmlPath)) {
            throw new \RuntimeException('Required presentation artifact is missing: deck.html');
        }

        $artifacts = ['deck.html' => hash_file('sha256', $htmlPath)];
        $cssPath = rtrim($deckDirectory, '/') . '/deck.css';
        if (is_file($cssPath)) {
            $artifacts['deck.css'] = hash_file('sha256', $cssPath);
        }

        return $artifacts;
    }

    public static function renderMetadata(array $metadata): array
    {
        $renderMetadata = [];
        foreach (self::RENDER_METADATA_KEYS as $key) {
            $renderMetadata[$key] = $metadata[$key] ?? null;
        }

        return $renderMetadata;
    }

    public static function sourceFingerprint(string $deckDirectory, array $metadata): string
    {
        return hash('sha256', json_encode([
            'artifacts' => self::sourceArtifacts($deckDirectory),
            'render_metadata' => self::renderMetadata($metadata),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function cachePath(string $cacheDirectory, string $deckDirectory, array $metadata): string
    {
        return rtrim($cacheDirectory, '/') . '/'
            . self::releaseAssetName(self::sourceFingerprint($deckDirectory, $metadata), $metadata);
    }

    public static function exportManifest(
        string $deckDirectory,
        array $metadata,
        string $pdfPath,
        string $repository,
        ?string $generatorFingerprint = null,
    ): array {
        $sourceFingerprint = self::sourceFingerprint($deckDirectory, $metadata);
        $generator = $generatorFingerprint ?? self::generatorFingerprint();

        return [
            'schema' => self::MANIFEST_SCHEMA_VERSION,
            'source' => [
                'sha256' => $sourceFingerprint,
                'artifacts' => self::sourceArtifacts($deckDirectory),
                'render_metadata' => self::renderMetadata($metadata),
            ],
            'generator' => [
                'sha256' => $generator,
                'decktape' => self::DECKTAPE_VERSION,
                'profile' => 'slides.com-v1',
            ],
            'pdf' => [
                'sha256' => hash_file('sha256', $pdfPath),
                'bytes' => filesize($pdfPath),
                'release_tag' => self::releaseTag($metadata),
                'asset' => self::releaseAssetName($sourceFingerprint, $metadata),
                'url' => self::releaseAssetUrl($repository, $sourceFingerprint, $metadata),
            ],
        ];
    }

    public static function manifestMatchesSource(string $manifestPath, string $deckDirectory, array $metadata): bool
    {
        if (!is_file($manifestPath)) {
            return false;
        }

        try {
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }

        $sourceFingerprint = self::sourceFingerprint($deckDirectory, $metadata);

        return ($manifest['schema'] ?? null) === self::MANIFEST_SCHEMA_VERSION
            && ($manifest['source']['sha256'] ?? null) === $sourceFingerprint
            && ($manifest['pdf']['release_tag'] ?? null) === self::releaseTag($metadata)
            && ($manifest['pdf']['asset'] ?? null) === self::releaseAssetName($sourceFingerprint, $metadata);
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
            'node_modules/.bin/decktape', 'reveal',
            '--size', SlidesDeckPolicy::viewport($metadata),
            '--load-pause', '0', '--pause', '0',
            '--url-load-timeout', '60000', '--page-load-timeout', '30000',
            '--chrome-arg=--no-sandbox', '--chrome-arg=--disable-dev-shm-usage',
            $embedUrl, $output,
        ];
    }

    private static function safeDeckId(array $metadata): string
    {
        $deckId = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) ($metadata['id'] ?? 'deck'));

        return $deckId !== '' ? $deckId : 'deck';
    }
}
