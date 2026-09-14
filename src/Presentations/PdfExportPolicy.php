<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

final class PdfExportPolicy
{
    public const MINIMUM_PDF_BYTES = 10_000;

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

    public static function deckTapeArguments(array $metadata, string $output): array
    {
        if (!SlidesDeckPolicy::isPublic($metadata)) {
            throw new \InvalidArgumentException('Only public Slides.com decks may be exported.');
        }

        $url = $metadata['url'] ?? null;
        if (!SlidesDeckPolicy::isAllowedPublicUrl($url)) {
            throw new \InvalidArgumentException('Deck URL is not an allowed public Slides.com URL.');
        }

        return [
            'npx',
            '--yes',
            'decktape@3.16.1',
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
            $url,
            $output,
        ];
    }
}
