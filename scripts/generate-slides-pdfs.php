<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\PdfExportPolicy;
use App\Presentations\SlidesReleaseMetadata;

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
        $pdfAssetUrl = PdfExportPolicy::releaseAssetUrl($repository, $sourceFingerprint, $metadata);
        [$thumbnailPath, $thumbnailAssetName, $thumbnailAssetUrl] = releaseThumbnail($repository, $releaseTag, $deckDir);
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Skipping PDF for deck {$deckId}: {$exception->getMessage()}\n");
        ++$skipped;
        continue;
    }

    synchronizeRelease($repository, $releaseTag, $metadata, $thumbnailAssetUrl, $pdfAssetUrl);

    if ($thumbnailPath !== null && $thumbnailAssetName !== null
        && !releaseAssetExists($repository, $releaseTag, $thumbnailAssetName)) {
        uploadReleaseAsset($repository, $releaseTag, $thumbnailPath, $thumbnailAssetName);
        fwrite(STDOUT, "Published release thumbnail for deck {$deckId}: {$thumbnailAssetName}\n");
    }

    $assetExists = releaseAssetExists($repository, $releaseTag, $assetName);

    if (PdfExportPolicy::manifestMatchesSource($manifestPath, $deckDir, $metadata) && $assetExists) {
        fwrite(STDOUT, "Reused release PDF for deck {$deckId}: {$assetName}\n");
        ++$reused;
        continue;
    }

    if ($assetExists) {
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
            fwrite(STDERR, "::warning::DeckTape did not produce a valid PDF for deck {$deckId}; preserving previous release assets.\n");
            ++$skipped;
            continue;
        }

        if (!rename($temporary, $cachePath)) {
            @unlink($temporary);
            throw new RuntimeException("Could not cache generated PDF for deck {$deckId}.");
        }
        ++$generated;
    }

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

function synchronizeRelease(
    string $repository,
    string $releaseTag,
    array $metadata,
    ?string $thumbnailAssetUrl,
    ?string $pdfAssetUrl,
): void {
    $title = SlidesReleaseMetadata::title($metadata);
    $body = SlidesReleaseMetadata::body($metadata, $repository, $thumbnailAssetUrl, $pdfAssetUrl);

    $view = sprintf(
        'gh release view %s --repo %s --json name,body 2>/dev/null',
        escapeshellarg($releaseTag),
        escapeshellarg($repository),
    );
    exec($view, $output, $exitCode);

    if ($exitCode !== 0) {
        $create = sprintf(
            'gh release create %s --repo %s --title %s --notes %s',
            escapeshellarg($releaseTag),
            escapeshellarg($repository),
            escapeshellarg($title),
            escapeshellarg($body),
        );
        passthru($create, $createExitCode);
        if ($createExitCode !== 0) {
            throw new RuntimeException("Could not create release {$releaseTag}.");
        }
        fwrite(STDOUT, "Created release metadata for {$releaseTag}.\n");

        return;
    }

    $release = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
    if (($release['name'] ?? '') === $title && ($release['body'] ?? '') === $body) {
        return;
    }

    $edit = sprintf(
        'gh release edit %s --repo %s --title %s --notes %s',
        escapeshellarg($releaseTag),
        escapeshellarg($repository),
        escapeshellarg($title),
        escapeshellarg($body),
    );
    passthru($edit, $editExitCode);
    if ($editExitCode !== 0) {
        throw new RuntimeException("Could not update release {$releaseTag} metadata.");
    }
    fwrite(STDOUT, "Updated release metadata for {$releaseTag}.\n");
}

function releaseThumbnail(string $repository, string $releaseTag, string $deckDir): array
{
    $candidates = glob($deckDir . '/thumbnail.*') ?: [];
    if ($candidates === []) {
        return [null, null, null];
    }

    $path = $candidates[0];
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        throw new RuntimeException("Could not hash release thumbnail {$path}.");
    }

    $assetName = 'thumbnail-' . substr($hash, 0, 12) . '.' . $extension;
    $url = sprintf(
        'https://github.com/%s/releases/download/%s/%s',
        trim($repository, '/'),
        rawurlencode($releaseTag),
        rawurlencode($assetName),
    );

    return [$path, $assetName, $url];
}

function uploadReleaseAsset(string $repository, string $releaseTag, string $assetPath, string $assetName): void
{
    $uploadPath = $assetPath;
    $temporaryPath = null;

    if (basename($assetPath) !== $assetName) {
        $temporaryPath = sys_get_temp_dir() . '/' . $assetName;
        if (!copy($assetPath, $temporaryPath)) {
            throw new RuntimeException("Could not prepare release asset {$assetName}.");
        }
        $uploadPath = $temporaryPath;
    }

    $upload = sprintf(
        'gh release upload %s %s --repo %s',
        escapeshellarg($releaseTag),
        escapeshellarg($uploadPath),
        escapeshellarg($repository),
    );
    passthru($upload, $exitCode);

    if ($temporaryPath !== null) {
        @unlink($temporaryPath);
    }

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
