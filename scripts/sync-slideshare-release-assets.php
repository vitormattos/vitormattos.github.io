<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__ . '/../src/Presentations/PresentationReleaseMetadata.php';

use App\Presentations\PresentationReleaseMetadata;

$repository = getenv('GITHUB_REPOSITORY') ?: 'vitormattos/vitormattos.github.io';
$updated = 0;

foreach (glob('presentations/slideshare/*/metadata.json') ?: [] as $metadataPath) {
    $metadata = json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
    $id = basename(dirname($metadataPath));
    $tag = 'slideshare-' . $id;
    $release = runJson(['gh', 'release', 'view', $tag, '--repo', $repository, '--json', 'assets']);
    $assets = $release['assets'] ?? [];
    $urls = [];
    $thumbnailAssetName = null;

    foreach ($assets as $asset) {
        $assetName = (string) ($asset['name'] ?? '');
        $name = strtolower($assetName);
        if ($assetName === '') continue;
        $url = sprintf('https://github.com/%s/releases/download/%s/%s', trim($repository, '/'), rawurlencode($tag), rawurlencode($assetName));
        if (str_ends_with($name, '.pdf')) $urls['pdf'] ??= $url;
        elseif (str_ends_with($name, '.pptx')) $urls['pptx'] ??= $url;
        elseif (preg_match('/^thumbnail(?:-[0-9a-f]{12})?\.(?:png|jpe?g|webp)$/', $name) === 1) { $urls['thumbnail'] ??= $url; $thumbnailAssetName ??= $assetName; }
    }

    $thumbnailDimensions = thumbnailDimensions($tag, $thumbnailAssetName, $repository);
    if ($thumbnailDimensions !== null) {
        $metadata['thumbnail_width'] = $thumbnailDimensions[0];
        $metadata['thumbnail_height'] = $thumbnailDimensions[1];
    }

    $notes = PresentationReleaseMetadata::body($metadata, $repository, $urls['thumbnail'] ?? null, $urls['pdf'] ?? null, $urls['pptx'] ?? null);
    run(['gh', 'release', 'edit', $tag, '--repo', $repository, '--title', PresentationReleaseMetadata::title($metadata), '--notes', $notes]);

    foreach (['source/_talks', 'source/_talksEn'] as $collection) {
        foreach (glob($collection . '/slideshare-' . $id . '-*.md') ?: [] as $path) {
            $content = (string) file_get_contents($path);
            $content = preg_replace('/^  (thumbnail|pdf|pptx|thumbnailWidth|thumbnailHeight): .*\R/m', '', $content) ?? $content;
            $lines = [];
            foreach (['thumbnail', 'pdf', 'pptx'] as $type) if (isset($urls[$type])) $lines[] = '  ' . $type . ': ' . json_encode($urls[$type], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($thumbnailDimensions !== null) {
                $lines[] = '  thumbnailWidth: ' . $thumbnailDimensions[0];
                $lines[] = '  thumbnailHeight: ' . $thumbnailDimensions[1];
            }
            if ($lines === []) continue;
            $next = preg_replace('/(^  metadata: .*\R)/m', '$1' . implode("\n", $lines) . "\n", $content, 1);
            if (is_string($next) && $next !== $content) { file_put_contents($path, $next); ++$updated; }
        }
    }
}

fwrite(STDOUT, "Synchronized {$updated} managed SlideShare talk files and refreshed release descriptions.\n");

function thumbnailDimensions(string $tag, ?string $assetName, string $repository): ?array
{
    if ($assetName === null) return null;
    $directory = sys_get_temp_dir() . '/slideshare-thumb-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $tag);
    if (!is_dir($directory)) mkdir($directory, 0777, true);
    run(['gh', 'release', 'download', $tag, '--repo', $repository, '--pattern', $assetName, '--dir', $directory, '--clobber']);
    $size = @getimagesize($directory . '/' . $assetName);
    return is_array($size) ? [(int) $size[0], (int) $size[1]] : null;
}

function run(array $command): void
{
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    exec($escaped . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) throw new RuntimeException(implode("\n", $output));
}

function runJson(array $command): array
{
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    exec($escaped . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) throw new RuntimeException(implode("\n", $output));
    $decoded = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Expected JSON object from gh.');
    return $decoded;
}
