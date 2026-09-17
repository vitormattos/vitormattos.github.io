<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\LatexPresentation;

require dirname(__DIR__) . '/vendor/autoload.php';

$repository = getenv('GITHUB_REPOSITORY') ?: '';
$token = getenv('GH_TOKEN') ?: '';
if ($repository === '' || $token === '') {
    fwrite(STDOUT, "LaTeX release publication deferred: GITHUB_REPOSITORY and GH_TOKEN are required.\n");
    exit(0);
}

foreach (glob('presentations/latex/*/metadata.json') ?: [] as $metadataPath) {
    $presentation = LatexPresentation::fromMetadataFile($metadataPath);
    $pdfPath = $presentation->pdfPath();
    $thumbnailPath = $presentation->thumbnailPath();
    $sourcePath = $presentation->sourcePath();

    foreach ([$pdfPath, $thumbnailPath, $sourcePath, $metadataPath] as $required) {
        if (!is_file($required)) {
            throw new RuntimeException("Required release asset not found: {$required}");
        }
    }

    $sourceSha = hash_file('sha256', $sourcePath) ?: throw new RuntimeException('Could not hash LaTeX source.');
    $pdfSha = hash_file('sha256', $pdfPath) ?: throw new RuntimeException('Could not hash PDF.');
    $thumbSha = hash_file('sha256', $thumbnailPath) ?: throw new RuntimeException('Could not hash thumbnail.');
    $metadataSha = hash_file('sha256', $metadataPath) ?: throw new RuntimeException('Could not hash metadata.');

    $assets = [
        'pdf' => [$pdfPath, $presentation->assetName('pdf', $pdfSha)],
        'source' => [$sourcePath, $presentation->assetName('source', $sourceSha)],
        'thumbnail' => [$thumbnailPath, $presentation->assetName('thumbnail', $thumbSha)],
        'metadata' => [$metadataPath, $presentation->assetName('metadata', $metadataSha)],
    ];

    $urls = [
        'pdf_url' => $presentation->releaseAssetUrl($repository, $assets['pdf'][1]),
        'source_url' => $presentation->releaseAssetUrl($repository, $assets['source'][1]),
        'thumbnail_url' => $presentation->releaseAssetUrl($repository, $assets['thumbnail'][1]),
        'metadata_url' => $presentation->releaseAssetUrl($repository, $assets['metadata'][1]),
        'source_sha' => $sourceSha,
        'pdf_sha' => $pdfSha,
    ];

    synchronizeRelease($repository, $presentation, $urls);

    foreach ($assets as [$path, $name]) {
        if (!releaseAssetExists($repository, $presentation->releaseTag(), $name)) {
            run(['gh', 'release', 'upload', $presentation->releaseTag(), $path . '#' . $name, '--repo', $repository]);
        }
    }

    synchronizeRelease($repository, $presentation, $urls);
    fwrite(STDOUT, "Published LaTeX presentation release: {$presentation->releaseTag()}\n");
}

function synchronizeRelease(string $repository, LatexPresentation $presentation, array $urls): void
{
    $title = (string) $presentation->metadata['title'];
    $body = $presentation->releaseBody($repository, $urls);
    $tag = $presentation->releaseTag();

    exec(sprintf('gh release view %s --repo %s >/dev/null 2>&1', escapeshellarg($tag), escapeshellarg($repository)), $output, $exitCode);
    if ($exitCode !== 0) {
        run(['gh', 'release', 'create', $tag, '--repo', $repository, '--target', 'main', '--title', $title, '--notes', $body]);
        return;
    }

    run(['gh', 'release', 'edit', $tag, '--repo', $repository, '--title', $title, '--notes', $body]);
}

function releaseAssetExists(string $repository, string $tag, string $asset): bool
{
    exec(sprintf(
        'gh release view %s --repo %s --json assets --jq %s 2>/dev/null',
        escapeshellarg($tag),
        escapeshellarg($repository),
        escapeshellarg('.assets[].name'),
    ), $output, $exitCode);

    return $exitCode === 0 && in_array($asset, $output, true);
}

function run(array $arguments): void
{
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed ({$exitCode}): {$command}");
    }
}
