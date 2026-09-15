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

        $pageHtml = tryDownloadText((string) $metadata['source_url'], $id);
        $original = downloadOriginal($metadata, $cacheDirectory);
        $pdf = buildPdf($original, $cacheDirectory, $id);
        $thumbnail = archiveThumbnail($directory, $pageHtml);
        if ($thumbnail === null && $pdf !== null) {
            $thumbnail = thumbnailFromPdf($directory, $pdf);
        }

        $thumbnailAsset = $thumbnail !== null
            ? contentAddressedAsset($repository, $tag, $thumbnail, 'thumbnail')
            : null;
        $originalAsset = $original !== null
            ? contentAddressedAsset($repository, $tag, $original, 'slideshare-' . $id . '-original')
            : null;
        $pdfAsset = null;
        if ($pdf !== null) {
            $pdfAsset = $pdf === $original && $originalAsset !== null
                ? $originalAsset
                : contentAddressedAsset($repository, $tag, $pdf, 'slideshare-' . $id);
        }

        $releaseMetadata = $metadata;
        if ($thumbnail !== null) {
            $size = @getimagesize($thumbnail);
            if (is_array($size)) {
                $releaseMetadata['width'] = (int) $size[0];
                $releaseMetadata['height'] = (int) $size[1];
            }
        }

        $body = PresentationReleaseMetadata::body(
            $releaseMetadata,
            $repository,
            $thumbnailAsset['url'] ?? null,
            $pdfAsset['url'] ?? null,
            $originalAsset['url'] ?? null,
        );
        ensureRelease($repository, $tag, PresentationReleaseMetadata::title($releaseMetadata), $body);

        $seenAssets = [];
        foreach ([$thumbnailAsset, $originalAsset, $pdfAsset] as $asset) {
            if ($asset === null || isset($seenAssets[$asset['name']])) {
                continue;
            }
            uploadAssetIfMissing($repository, $tag, $asset['path'], $asset['name']);
            $seenAssets[$asset['name']] = true;
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
            $thumbnail !== null ? '/' . $thumbnail : null,
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

function tryDownloadText(string $url, string $id): ?string
{
    try {
        [$bytes, $status] = httpGet($url, 'text/html,application/xhtml+xml');
        if ($status >= 200 && $status < 300) {
            return $bytes;
        }
        fwrite(STDERR, "::warning::SlideShare page {$id} returned HTTP {$status}; continuing without page metadata.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Could not fetch SlideShare page {$id}: {$exception->getMessage()}; continuing with exported metadata.\n");
    }

    return null;
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

function archiveThumbnail(string $directory, ?string $html): ?string
{
    if ($html === null) {
        return existingThumbnail($directory);
    }

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

    try {
        validateUrl($url, ['slidesharecdn.com', 'slideshare.net', 'licdn.com']);
        [$bytes, $status] = httpGet($url, 'image/*');
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Could not archive SlideShare thumbnail: {$exception->getMessage()}\n");
        return existingThumbnail($directory);
    }

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

    return writeThumbnail($directory, $bytes, $extension);
}

function thumbnailFromPdf(string $directory, string $pdf): ?string
{
    $prefix = sys_get_temp_dir() . '/slideshare-thumbnail-' . basename($directory);
    $command = sprintf(
        'pdftoppm -f 1 -singlefile -png -scale-to-x 1280 -scale-to-y -1 %s %s >/dev/null 2>&1',
        escapeshellarg($pdf),
        escapeshellarg($prefix),
    );
    exec($command, $output, $exitCode);
    $generated = $prefix . '.png';
    if ($exitCode !== 0 || !is_file($generated)) {
        return existingThumbnail($directory);
    }

    $bytes = (string) file_get_contents($generated);
    @unlink($generated);

    return $bytes !== '' ? writeThumbnail($directory, $bytes, 'png') : existingThumbnail($directory);
}

function writeThumbnail(string $directory, string $bytes, string $extension): string
{
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

    try {
        [$bytes, $status] = httpGet($url, 'application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.presentation,*/*;q=0.5');
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Could not download original SlideShare file {$metadata['id']}: {$exception->getMessage()}\n");
        return null;
    }

    if ($status < 200 || $status >= 300 || $bytes === '') {
        fwrite(STDERR, "::warning::SlideShare original download {$metadata['id']} returned HTTP {$status}.\n");
        return null;
    }

    $extension = detectPresentationExtension($bytes);
    if ($extension === null) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($bytes);
        fwrite(STDERR, "::warning::SlideShare download {$metadata['id']} returned unsupported MIME {$mime}; original file was not archived.\n");
        return null;
    }

    $path = rtrim($cacheDirectory, '/') . '/slideshare-' . safeId((string) $metadata['id']) . '-original.' . $extension;
    file_put_contents($path, $bytes);

    return $path;
}

function detectPresentationExtension(string $bytes): ?string
{
    if (str_starts_with($bytes, '%PDF-')) {
        return 'pdf';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->buffer($bytes);
    $extension = match ($mime) {
        'application/pdf' => 'pdf',
        'application/vnd.ms-powerpoint', 'application/mspowerpoint', 'application/x-mspowerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
        default => null,
    };
    if ($extension !== null) {
        return $extension;
    }

    if (str_starts_with($bytes, "PK\x03\x04")) {
        if (str_contains($bytes, 'ppt/presentation.xml')) {
            return 'pptx';
        }
        if (str_contains($bytes, 'application/vnd.oasis.opendocument.presentation')) {
            return 'odp';
        }
    }

    return null;
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
    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
        return null;
    }

    $command = sprintf(
        'libreoffice --headless --convert-to pdf --outdir %s %s >/dev/null 2>&1',
        escapeshellarg($outputDirectory),
        escapeshellarg($original),
    );
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "::warning::LibreOffice could not convert SlideShare presentation {$id} to PDF.\n");
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
        if (!copy($path, $temporary)) {
            throw new RuntimeException("Could not prepare release asset {$assetName}.");
        }
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

            $content = preg_replace('/^  (thumbnail|pdf|original): .*\R/m', '', $content) ?? $content;
            $updated = preg_replace('/(^  metadata: .*\R)/m', '$1' . implode("\n", $lines) . "\n", $content, 1);
            if (is_string($updated) && $updated !== $content) {
                file_put_contents($path, $updated);
            }
        }
    }
}
