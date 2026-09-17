<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$repository = getenv('GITHUB_REPOSITORY') ?: '';
$releaseTag = trim((string) (getenv('RELEASE_TAG') ?: ''));
$releasePrefix = trim((string) (getenv('RELEASE_PREFIX') ?: ''));

if ($repository === '') {
    fwrite(STDERR, "GITHUB_REPOSITORY is required.\n");
    exit(1);
}

$releases = $releaseTag !== ''
    ? [['tagName' => $releaseTag, 'isDraft' => false]]
    : runJson(['gh', 'release', 'list', '--repo', $repository, '--limit', '1000', '--json', 'tagName,isDraft']);

$generated = 0;
$skipped = 0;

foreach ($releases as $release) {
    if (($release['isDraft'] ?? false) === true) {
        continue;
    }

    $tag = trim((string) ($release['tagName'] ?? ''));
    if ($tag === '' || ($releasePrefix !== '' && !str_starts_with($tag, $releasePrefix))) {
        continue;
    }

    $details = runJson(['gh', 'release', 'view', $tag, '--repo', $repository, '--json', 'assets']);
    $assets = $details['assets'] ?? [];

    if (hasThumbnail($assets)) {
        ++$skipped;
        continue;
    }

    $pdf = preferredPdf($assets);
    if ($pdf === null) {
        ++$skipped;
        continue;
    }

    $workDirectory = sys_get_temp_dir() . '/release-thumbnail-' . preg_replace('/[^0-9A-Za-z._-]+/', '-', $tag) . '-' . bin2hex(random_bytes(4));
    if (!mkdir($workDirectory, 0700, true) && !is_dir($workDirectory)) {
        throw new RuntimeException("Could not create temporary directory for {$tag}");
    }

    try {
        runCommand([
            'gh', 'release', 'download', $tag,
            '--repo', $repository,
            '--pattern', (string) $pdf['name'],
            '--dir', $workDirectory,
        ]);

        $pdfPath = $workDirectory . '/' . $pdf['name'];
        if (!is_file($pdfPath)) {
            throw new RuntimeException("PDF asset was not downloaded for {$tag}");
        }

        $outputPrefix = $workDirectory . '/thumbnail';
        runCommand([
            'pdftoppm',
            '-f', '1',
            '-singlefile',
            '-png',
            '-scale-to-x', '1280',
            '-scale-to-y', '-1',
            $pdfPath,
            $outputPrefix,
        ]);

        $thumbnailPath = $outputPrefix . '.png';
        if (!is_file($thumbnailPath) || filesize($thumbnailPath) === 0) {
            throw new RuntimeException("Thumbnail generation failed for {$tag}");
        }

        runCommand([
            'gh', 'release', 'upload', $tag,
            $thumbnailPath . '#thumbnail.png',
            '--repo', $repository,
        ]);

        fwrite(STDOUT, "Generated thumbnail for release {$tag} from {$pdf['name']}.\n");
        ++$generated;
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Could not backfill thumbnail for {$tag}: {$exception->getMessage()}\n");
    } finally {
        removeDirectory($workDirectory);
    }
}

fwrite(STDOUT, "Release thumbnail backfill finished: {$generated} generated, {$skipped} already complete or without PDF.\n");

/** @param list<array<string, mixed>> $assets */
function hasThumbnail(array $assets): bool
{
    foreach ($assets as $asset) {
        $name = strtolower((string) ($asset['name'] ?? ''));
        if (preg_match('/^thumbnail(?:-[0-9a-f]{12})?\.(?:png|jpe?g|webp)$/', $name) === 1) {
            return true;
        }
    }

    return false;
}

/** @param list<array<string, mixed>> $assets */
function preferredPdf(array $assets): ?array
{
    $pdfs = array_values(array_filter(
        $assets,
        static fn(array $asset): bool => str_ends_with(strtolower((string) ($asset['name'] ?? '')), '.pdf'),
    ));

    if ($pdfs === []) {
        return null;
    }

    usort($pdfs, static function (array $left, array $right): int {
        $leftOriginal = str_contains(strtolower((string) ($left['name'] ?? '')), 'original');
        $rightOriginal = str_contains(strtolower((string) ($right['name'] ?? '')), 'original');

        return $leftOriginal <=> $rightOriginal;
    });

    return $pdfs[0];
}

/** @param list<string> $command */
function runJson(array $command): array
{
    $output = runCommand($command);
    $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException('Command did not return a JSON object or array.');
    }

    return $decoded;
}

/** @param list<string> $command */
function runCommand(array $command): string
{
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    $output = [];
    $exitCode = 0;
    exec($escaped . ' 2>&1', $output, $exitCode);
    $text = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException($text !== '' ? $text : "Command failed with exit code {$exitCode}");
    }

    return $text;
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($directory);
}
