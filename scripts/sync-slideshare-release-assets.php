<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$repository = getenv('GITHUB_REPOSITORY') ?: 'vitormattos/vitormattos.github.io';
$updated = 0;

foreach (glob('presentations/slideshare/*/metadata.json') ?: [] as $metadataPath) {
    $id = basename(dirname($metadataPath));
    $tag = 'slideshare-' . $id;
    $release = runJson(['gh', 'release', 'view', $tag, '--repo', $repository, '--json', 'assets']);
    $assets = $release['assets'] ?? [];

    $urls = [];
    foreach ($assets as $asset) {
        $name = strtolower((string) ($asset['name'] ?? ''));
        $url = (string) ($asset['url'] ?? '');
        if ($url === '') {
            continue;
        }
        if (str_ends_with($name, '.pdf')) {
            $urls['pdf'] ??= $url;
        } elseif (str_ends_with($name, '.pptx')) {
            $urls['pptx'] ??= $url;
        } elseif (preg_match('/^thumbnail(?:-[0-9a-f]{12})?\.(?:png|jpe?g|webp)$/', $name) === 1) {
            $urls['thumbnail'] ??= $url;
        }
    }

    foreach (['source/_talks', 'source/_talksEn'] as $collection) {
        foreach (glob($collection . '/slideshare-' . $id . '-*.md') ?: [] as $path) {
            $content = (string) file_get_contents($path);
            $content = preg_replace('/^  (thumbnail|pdf|pptx): .*\R/m', '', $content) ?? $content;
            $lines = [];
            foreach (['thumbnail', 'pdf', 'pptx'] as $type) {
                if (isset($urls[$type])) {
                    $lines[] = '  ' . $type . ': ' . json_encode($urls[$type], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
            if ($lines === []) {
                continue;
            }
            $next = preg_replace('/(^  metadata: .*\R)/m', '$1' . implode("\n", $lines) . "\n", $content, 1);
            if (is_string($next) && $next !== $content) {
                file_put_contents($path, $next);
                ++$updated;
            }
        }
    }
}

fwrite(STDOUT, "Synchronized {$updated} managed SlideShare talk files from release assets.\n");

/** @param list<string> $command */
function runJson(array $command): array
{
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    exec($escaped . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException(implode("\n", $output));
    }
    $decoded = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected JSON object from gh.');
    }
    return $decoded;
}
