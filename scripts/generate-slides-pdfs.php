<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\PdfExportPolicy;

require dirname(__DIR__) . '/vendor/autoload.php';

$cacheDirectory = getenv('SLIDES_PDF_CACHE_DIR') ?: '.cache/slides-pdf';
$repository = getenv('GITHUB_REPOSITORY') ?: '';
$ghToken = getenv('GH_TOKEN') ?: '';

if ($repository === '' || $ghToken === '') {
    fwrite(STDOUT, "PDF publication deferred: GITHUB_REPOSITORY and GH_TOKEN are required.\n");
    exit(0);
}

if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0777, true) && !is_dir($cacheDirectory)) {
    throw new RuntimeException("Could not create PDF cache directory: {$cacheDirectory}");
}

$generatorFingerprint = PdfExportPolicy::generatorFingerprint();
$generated = 0;
$released = 0;
$cached = 0;
$reused = 0;
$skipped = 0;

foreach (glob('presentations/slides.com/*/metadata.json') ?: [] as $metadataPath) {
    $deckDir = dirname($metadataPath);
    $deckId = basename($deckDir);
    $manifestPath = $deckDir . '/' . PdfExportPolicy::EXPORT_MANIFEST;

    try {
        $metadata = json_decode(file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        $sourceFingerprint = PdfExportPolicy::sourceFingerprint($deckDir, $metadata);
        $releaseTag = PdfExportPolicy::releaseTag($metadata);
        $assetName = PdfExportPolicy::releaseAssetName($sourceFingerprint, $metadata);
        $cachePath = PdfExportPolicy::cachePath($cacheDirectory, $deckDir, $metadata);
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Skipping PDF for deck {$deckId}: {$exception->getMessage()}\n");
        ++$skipped;
        continue;
    }

    if (PdfExportPolicy::manifestMatchesSource($manifestPath, $deckDir, $metadata)
        && releaseAssetExists($repository, $releaseTag, $assetName)) {
        fwrite(STDOUT, "Reused release PDF for deck {$deckId}: {$assetName}\n");
        ++$reused;
        continue;
    }

    if (releaseAssetExists($repository, $releaseTag, $assetName)) {
        fwrite(STDOUT, "Recovered existing release PDF for deck {$deckId}: {$assetName}\n");
        if (is_file($cachePath)) {
            writeExportManifest($manifestPath, PdfExportPolicy::exportManifest(
                $deckDir,
                $metadata,
                $cachePath,
                $repository,
                $generatorFingerprint,
            ));
        } else {
            writeReleaseOnlyManifest($manifestPath, $deckDir, $metadata, $repository, $generatorFingerprint);
        }
        ++$reused;
        continue;
    }

    $temporary = $cachePath . '.tmp';
    @unlink($temporary);

    if (PdfExportPolicy::isValidPdf($cachePath)) {
        fwrite(STDOUT, "Reused Actions-cached PDF for deck {$deckId}\n");
        ++$cached;
    } else {
        fwrite(STDOUT, "Generating release PDF for public Slides.com deck {$deckId}\n");
        $arguments = PdfExportPolicy::deckTapeArguments($metadata, $temporary);
        $command = implode(' ', array_map('escapeshellarg', $arguments));
        passthru($command, $exitCode);

        if ($exitCode !== 0 || !PdfExportPolicy::isValidPdf($temporary)) {
            @unlink($temporary);
            fwrite(STDERR, "::warning::DeckTape did not produce a valid PDF for deck {$deckId}; preserving the previous release asset.\n");
            ++$skipped;
            continue;
        }

        if (!rename($temporary, $cachePath)) {
            @unlink($temporary);
            throw new RuntimeException("Could not cache generated PDF for deck {$deckId}.");
        }
        ++$generated;
    }

    ensureRelease($repository, $releaseTag, (string) ($metadata['title'] ?? "Slides.com deck {$deckId}"));
    uploadReleaseAsset($repository, $releaseTag, $cachePath, $assetName);

    writeExportManifest($manifestPath, PdfExportPolicy::exportManifest(
        $deckDir,
        $metadata,
        $cachePath,
        $repository,
        $generatorFingerprint,
    ));
    ++$released;
}

fwrite(STDOUT, "PDF generation finished: {$generated} generated, {$released} published, {$cached} Actions-cached, {$reused} release-reused, {$skipped} skipped.\n");

function releaseAssetExists(string $repository, string $releaseTag, string $assetName): bool
{
    $command = sprintf(
        'gh release view %s --repo %s --json assets --jq %s 2>/dev/null',
        escapeshellarg($releaseTag),
        escapeshellarg($repository),
        escapeshellarg('.assets[].name'),
    );
    exec($command, $output, $exitCode);

    return $exitCode === 0 && in_array($assetName, $output, true);
}

function ensureRelease(string $repository, string $releaseTag, string $title): void
{
    $view = sprintf(
        'gh release view %s --repo %s >/dev/null 2>&1',
        escapeshellarg($releaseTag),
        escapeshellarg($repository),
    );
    exec($view, $_, $exitCode);
    if ($exitCode === 0) {
        return;
    }

    $notes = 'Archived PDF exports for this Slides.com presentation. Assets are append-only and content-addressed by the archived presentation source.';
    $create = sprintf(
        'gh release create %s --repo %s --title %s --notes %s',
        escapeshellarg($releaseTag),
        escapeshellarg($repository),
        escapeshellarg($title),
        escapeshellarg($notes),
    );
    passthru($create, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Could not create release {$releaseTag}.");
    }
}

function uploadReleaseAsset(string $repository, string $releaseTag, string $pdfPath, string $assetName): void
{
    if (basename($pdfPath) !== $assetName) {
        throw new RuntimeException("Release asset path must already use the content-addressed filename {$assetName}.");
    }

    $upload = sprintf(
        'gh release upload %s %s --repo %s',
        escapeshellarg($releaseTag),
        escapeshellarg($pdfPath),
        escapeshellarg($repository),
    );
    passthru($upload, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Could not upload {$assetName} to release {$releaseTag}.");
    }
}

function writeExportManifest(string $path, array $manifest): void
{
    $content = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("Could not write PDF export manifest: {$path}");
    }
}

function writeReleaseOnlyManifest(
    string $path,
    string $deckDir,
    array $metadata,
    string $repository,
    string $generatorFingerprint,
): void {
    $sourceFingerprint = PdfExportPolicy::sourceFingerprint($deckDir, $metadata);
    $manifest = [
        '_spdx' => ['copyright' => '2026 Vitor Mattos', 'license' => 'CC-BY-SA-4.0'],
        'schema' => PdfExportPolicy::MANIFEST_SCHEMA_VERSION,
        'source' => [
            'sha256' => $sourceFingerprint,
            'artifacts' => PdfExportPolicy::sourceArtifacts($deckDir),
            'render_metadata' => PdfExportPolicy::renderMetadata($metadata),
        ],
        'generator' => [
            'sha256' => $generatorFingerprint,
            'decktape' => PdfExportPolicy::DECKTAPE_VERSION,
            'profile' => 'slides.com-v1',
        ],
        'pdf' => [
            'release_tag' => PdfExportPolicy::releaseTag($metadata),
            'asset' => PdfExportPolicy::releaseAssetName($sourceFingerprint, $metadata),
            'url' => PdfExportPolicy::releaseAssetUrl($repository, $sourceFingerprint, $metadata),
        ],
    ];

    writeExportManifest($path, $manifest);
}
