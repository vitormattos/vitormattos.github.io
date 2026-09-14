<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\PdfExportPolicy;

require dirname(__DIR__) . '/vendor/autoload.php';

$cacheDirectory = getenv('SLIDES_PDF_CACHE_DIR') ?: '.cache/slides-pdf';
if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0777, true) && !is_dir($cacheDirectory)) {
    throw new RuntimeException("Could not create PDF cache directory: {$cacheDirectory}");
}

$generatorFingerprint = PdfExportPolicy::generatorFingerprint();
$generated = 0;
$cached = 0;
$skipped = 0;

foreach (glob('presentations/slides.com/*/metadata.json') ?: [] as $metadataPath) {
    $deckDir = dirname($metadataPath);
    $deckId = basename($deckDir);
    $output = $deckDir . '/deck.pdf';
    $temporary = $deckDir . '/deck.pdf.tmp';

    try {
        $metadata = json_decode(file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        $cachePath = PdfExportPolicy::cachePath($cacheDirectory, $metadata, $generatorFingerprint);
        $arguments = PdfExportPolicy::deckTapeArguments($metadata, $temporary);
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Skipping PDF for deck {$deckId}: {$exception->getMessage()}\n");
        ++$skipped;
        continue;
    }

    if (PdfExportPolicy::isValidPdf($cachePath)) {
        if (!copy($cachePath, $output)) {
            throw new RuntimeException("Could not restore cached PDF for deck {$deckId}.");
        }
        fwrite(STDOUT, "Reused cached PDF for deck {$deckId}\n");
        ++$cached;
        continue;
    }

    @unlink($temporary);
    fwrite(STDOUT, "Generating {$output} from public Slides.com deck {$deckId}\n");

    $command = implode(' ', array_map('escapeshellarg', $arguments));
    passthru($command, $exitCode);

    if ($exitCode !== 0 || !PdfExportPolicy::isValidPdf($temporary)) {
        @unlink($temporary);
        fwrite(STDERR, "::warning::DeckTape did not produce a valid PDF for deck {$deckId}; preserving any previous valid export.\n");
        ++$skipped;
        continue;
    }

    if (!rename($temporary, $output)) {
        @unlink($temporary);
        throw new RuntimeException("Could not publish generated PDF for deck {$deckId}.");
    }

    if (!copy($output, $cachePath)) {
        throw new RuntimeException("Could not cache generated PDF for deck {$deckId}.");
    }

    ++$generated;
}

fwrite(STDOUT, "PDF generation finished: {$generated} generated, {$cached} cached, {$skipped} skipped.\n");
