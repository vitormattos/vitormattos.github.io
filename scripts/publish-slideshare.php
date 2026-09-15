<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\PresentationReleaseMetadata;

require dirname(__DIR__) . '/vendor/autoload.php';

$repository = getenv('GITHUB_REPOSITORY') ?: '';
$ghToken = getenv('GH_TOKEN') ?: '';
$cacheDirectory = getenv('SLIDESHARE_CACHE_DIR') ?: '.cache/slideshare';

if ($repository === '' || $ghToken === '') {
    fwrite(STDOUT, "SlideShare publication deferred: GITHUB_REPOSITORY and GH_TOKEN are required.\n");
    exit(0);
}

if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0777, true) && !is_dir($cacheDirectory)) {
    throw new RuntimeException("Could not create SlideShare cache directory: {$cacheDirectory}");
}

$published = 0;
$skipped = 0;

foreach (glob('presentations/slideshare/*/metadata.json') ?: [] as $metadataPath) {
    $directory = dirname($metadataPath);
    $id = basename($directory);

    try {
        $metadata = json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        validateMetadata($metadata);

        $tag = 'slideshare-' . safeId((string) $metadata['id']);
        $releaseUrl = "https://github.com/{$repository}/releases/tag/{$tag}";
        $pageHtml = downloadText((string) $metadata['source_url']);
        $thumbnail = archiveThumbnail($directory, $pageHtml);
        $original = downloadOriginal($metadata, $cacheDirectory);
        $pdf = buildPdf($original, $cacheDirectory, $id);

        $thumbnailAsset = $thumbnail ? contentAddressedAsset($repository, $tag, $thumbnail, 'thumbnail') : null;
        $originalAsset = $original ? contentAddressedAsset($repository, $tag, $original, 'slideshare-' . $id . '-original') : null;
        $pdfAsset = $pdf ? contentAddressedAsset($repository, $tag, $pdf, 'slideshare-' . $id) : null;

        $releaseMetadata = $metadata;
        if ($thumbnail !== null) {
            $size = @getimagesize($thumbnail);
            if (is_array($size)) {
                $releaseMetadata['width'] = (int) $size[0];
                $releaseMetadata['height'] = (int) $size[1];
            }
        }

        ensureRelease(
            $repository,
            $tag,
            PresentationReleaseMetadata::title($releaseMetadata),
            PresentationReleaseMetadata::body(
                $releaseMetadata,
                $repository,
                $thumbnailAsset['url'] ?? null,
                $pdfAsset['url'] ?? null,
                $originalAsset['url'] ?? null,
            ),
        );

        foreach ([$thumbnailAsset, $originalAsset, $pdfAsset] as $asset) {
            if ($asset !== null) {
                uploadAssetIfMissing($repository, $tag, $asset['path'], $asset['name']);
            }
        }

        $manifest = [
            'schema' => 1,
            'source' => [
                'provider' => 'slideshare',
                'id' => (string) $metadata['id'],
                'published_at' => $metadata['published_at'] ?? null,
                'url' => $metadata['source_url'],
            ],
            'release' => [
                'tag' => $tag,
                'url' => $releaseUrl,
                'make_latest' => false,
                'published_at_note' => 'GitHub does not support backdating a release publication timestamp; the original SlideShare publication date is preserved in metadata and release notes.',
            ],
            'assets' => array_filter([
                'thumbnail' => manifestAsset($thumbnailAsset),
                'original' => manifestAsset($originalAsset),
                'pdf' => manifestAsset($pdfAsset),
            ]),
        ];

        file_put_contents(
            $directory . '/export.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        );

        updateManagedTalk(
            (string) $metadata['id'],
            $thumbnail ? '/' . $thumbnail : null,
            $pdfAsset['url'] ?? null,
            $originalAsset['url'] ?? null,
        );

        fwrite(STDOUT, "Published SlideShare archive {$id}.\n");
        ++$published;
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Skipping SlideShare presentation {$id}: {$exception->getMessage()}\n");
        ++$skipped;
    }
}

fwrite(STDOUT, "SlideShare publication finished: {$published} published, {$skipped} skipped.\n");

function validateMetadata(array $metadata): void
{
    foreach (['id', 'title', 'source_url', 'published_at'] as $required) {
        if (trim((string) ($metadata[$required] ?? '')) === '') {
            throw new RuntimeException("Required SlideShare metadata is missing: {$required}");
        }
    }

    validateUrl((string) $metadata['source_url'], ['slideshare.net']);
    if (!empty($metadata['source_download_url'])) {
        validateUrl((string) $metadata['source_download_url'], ['slideshare.net']);
    }
}

function validateUrl(string $url, array $allowedSuffixes): void
{
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($scheme !== 'https') {
        throw new RuntimeException('SlideShare archive URLs must use HTTPS.');
    }

    foreach ($allowedSuffixes as $suffix) {
        if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
            return;
        }
    }

    throw new RuntimeException("Unexpected SlideShare archive host: {$host}");
}

function safeId(string $id): string
{
    $safe = preg_replace('/[^0-9A-Za-z._-]/', '-', $id);

    return $safe !== '' ? $safe : 'presentation';
}

function downloadText(string $url): string
{
    [$bytes, $status] = httpGet($url, 'text/html,application/xhtml+xml');
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("SlideShare page returned HTTP {$status}.");
    }

    return $bytes;
}

function httpGet(string $url, string $accept = '*/*'): array
{
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Accept: {$accept}\r\nUser-Agent: Mozilla/5.0 presentation-archive/1.0\r\n",
        'ignore_errors' => true,
        'timeout' => 45,
        'follow_location' => 1,
        'max_redirects' => 8,
    ]]);

    $bytes = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }

    if ($bytes === false) {
        throw new RuntimeException("Could not download {$url}");
    }

    return [$bytes, $status];
}

function archiveThumbnail(string $directory, string $html): ?string
{
    $url = null;
    foreach ([
        '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
    ] as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            break;
        }
    }

    if ($url === null) {
        return existingThumbnail($directory);
    }

    validateUrl($url, ['slidesharecdn.com', 'slideshare.net', 'licdn.com']);
    [$bytes, $status] = httpGet($url, 'image/*');
    if ($status < 200 || $status >= 300 || $bytes === '') {
        return existingThumbnail($directory);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->buffer($bytes);
    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    if ($extension === null) {
        return existingThumbnail($directory);
    }

    $target = $directory . '/thumbnail.' . $extension;
    file_put_contents($target, $bytes);
    foreach (glob($directory . '/thumbnail.*') ?: [] as $candidate) {
        if ($candidate !== $target && is_file($candidate)) {
            unlink($candidate);
        }
    }

    return $target;
}

function existingThumbnail(string $directory): ?string
{
    $matches = glob($directory . '/thumbnail.*') ?: [];

    return $matches[0] ?? null;
}

function downloadOriginal(array $metadata, string $cacheDirectory): ?string
{
    $url = trim((string) ($metadata['source_download_url'] ?? ''));
    if ($url === '') {
        return null;
    }

    [$bytes, $status] = httpGet($url, 'application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.presentation,*/*;q=0.5');
    if ($status < 200 || $status >= 300 || $bytes === '') {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->buffer($bytes);
    $extension = match ($mime) {
        'application/pdf' => 'pdf',
        'application/vnd.ms-powerpoint', 'application/mspowerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
        default => null,
    };

    if ($extension === null) {
        fwrite(STDERR, "::warning::SlideShare download for {$metadata['id']} returned unsupported MIME {$mime}; original file was not archived.\n");
        return null;
    }

    $path = rtrim($cacheDirectory, '/') . '/slideshare-' . safeId((string) $metadata['id']) . '-original.' . $extension;
    file_put_contents($path, $bytes);

    return $path;
}

function buildPdf(?string $original, string $cacheDirectory, string $id): ?string
{
    if ($original === null) {
        return null;
    }

    if (strtolower((string) pathinfo($original, PATHINFO_EXTENSION)) === 'pdf') {
        return $original;
    }

    $outputDirectory = rtrim($cacheDirectory, '/') . '/converted-' . safeId($id);
    if (!is_dir($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }

    $command = sprintf(
        'libreoffice --headless --convert-to pdf --outdir %s %s >/dev/null 2>&1',
        escapeshellarg($outputDirectory),
        escapeshellarg($original),
    );
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        return null;
    }

    $candidate = $outputDirectory . '/' . pathinfo($original, PATHINFO_FILENAME) . '.pdf';

    return is_file($candidate) && filesize($candidate) > 1000 ? $candidate : null;
}

function contentAddressedAsset(string $repository, string $tag, string $path, string $prefix): array
{
    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        throw new RuntimeException("Could not hash {$path}");
    }

    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $name = $prefix . '-' . substr($hash, 0, 12) . ($extension !== '' ? '.' . $extension : '');

    return [
        'path' => $path,
        'name' => $name,
        'sha256' => $hash,
        'bytes' => filesize($path),
        'url' => sprintf(
            'https://github.com/%s/releases/download/%s/%s',
            trim($repository, '/'),
            rawurlencode($tag),
            rawurlencode($name),
        ),
    ];
}

function ensureRelease(string $repository, string $tag, string $title, string $body): void
{
    $view = sprintf('gh release view %s --repo %s --json name,body 2>/dev/null', escapeshellarg($tag), escapeshellarg($repository));
    exec($view, $output, $exitCode);

    if ($exitCode !== 0) {
        $command = sprintf(
            'gh api %s -X POST -f tag_name=%s -f target_commitish=main -f name=%s -f body=%s -F make_latest=false >/dev/null',
            escapeshellarg('repos/' . $repository . '/releases'),
            escapeshellarg($tag),
            escapeshellarg($title),
            escapeshellarg($body),
        );
        passthru($command, $createExitCode);
        if ($createExitCode !== 0) {
            throw new RuntimeException("Could not create release {$tag}.");
        }

        return;
    }

    $release = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
    if (($release['name'] ?? '') === $title
        && rtrim((string) ($release['body'] ?? ''), "\r\n") === rtrim($body, "\r\n")) {
        return;
    }

    $command = sprintf(
        'gh release edit %s --repo %s --title %s --notes %s >/dev/null',
        escapeshellarg($tag),
        escapeshellarg($repository),
        escapeshellarg($title),
        escapeshellarg($body),
    );
    passthru($command, $editExitCode);
    if ($editExitCode !== 0) {
        throw new RuntimeException("Could not update release {$tag}.");
    }
}

function uploadAssetIfMissing(string $repository, string $tag, string $path, string $assetName): void
{
    $command = sprintf(
        'gh release view %s --repo %s --json assets --jq %s 2>/dev/null',
        escapeshellarg($tag),
        escapeshellarg($repository),
        escapeshellarg('.assets[].name'),
    );
    exec($command, $assets, $exitCode);
    if ($exitCode === 0 && in_array($assetName, $assets, true)) {
        return;
    }

    $uploadPath = $path;
    $temporary = null;
    if (basename($path) !== $assetName) {
        $temporary = sys_get_temp_dir() . '/' . $assetName;
        copy($path, $temporary);
        $uploadPath = $temporary;
    }

    $upload = sprintf('gh release upload %s %s --repo %s', escapeshellarg($tag), escapeshellarg($uploadPath), escapeshellarg($repository));
    passthru($upload, $uploadExitCode);
    if ($temporary !== null) {
        @unlink($temporary);
    }
    if ($uploadExitCode !== 0) {
        throw new RuntimeException("Could not upload {$assetName} to {$tag}.");
    }
}

function manifestAsset(?array $asset): ?array
{
    if ($asset === null) {
        return null;
    }

    return [
        'sha256' => $asset['sha256'],
        'bytes' => $asset['bytes'],
        'asset' => $asset['name'],
        'url' => $asset['url'],
    ];
}

function updateManagedTalk(string $id, ?string $thumbnail, ?string $pdf, ?string $original): void
{
    foreach (['source/_talks', 'source/_talksEn'] as $collection) {
        foreach (glob($collection . '/slideshare-' . safeId($id) . '-*.md') ?: [] as $path) {
            $content = (string) file_get_contents($path);
            $lines = [];
            if ($thumbnail !== null) {
                $lines[] = '  thumbnail: ' . json_encode($thumbnail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if ($pdf !== null) {
                $lines[] = '  pdf: ' . json_encode($pdf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if ($original !== null) {
                $lines[] = '  original: ' . json_encode($original, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if ($lines === []) {
                continue;
            }

            $content = preg_replace('/^  (thumbnail|pdf|original): .*\R/m', '', $content);
            $anchor = '/(^  metadata: .*\R)/m';
            $updated = preg_replace($anchor, '$1' . implode("\n", $lines) . "\n", $content, 1);
            if (is_string($updated) && $updated !== $content) {
                file_put_contents($path, $updated);
            }
        }
    }
}
