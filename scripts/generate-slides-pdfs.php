<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\PdfExportPolicy;

require dirname(__DIR__) . '/vendor/autoload.php';

$success = 0;
$skipped = 0;

foreach (glob('presentations/slides.com/*/metadata.json') ?: [] as $metadataPath) {
    $deckDir = dirname($metadataPath);
    $deckId = basename($deckDir);
    $output = $deckDir . '/deck.pdf';
    $temporary = $deckDir . '/deck.pdf.tmp';

    try {
        $metadata = json_decode(file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        $arguments = PdfExportPolicy::deckTapeArguments($metadata, $temporary);
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Skipping PDF for deck {$deckId}: {$exception->getMessage()}\n");
        ++$skipped;
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

    ++$success;
}

fwrite(STDOUT, "PDF generation finished: {$success} generated, {$skipped} skipped.\n");
