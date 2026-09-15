<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

const THUMBNAIL_MIME_EXTENSIONS = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$archived = 0;
$reused = 0;
$skipped = 0;

foreach (glob('presentations/slides.com/*/metadata.json') ?: [] as $metadataPath) {
    $deckDir = dirname($metadataPath);
    $deckId = basename($deckDir);

    try {
        $metadata = json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        $metadata = enrichFromManagedTalk($metadata, $deckId);
        $thumbnailUrl = trim((string) ($metadata['thumbnail_url'] ?? ''));
        if ($thumbnailUrl === '') {
            writeMetadata($metadataPath, $metadata);
            ++$skipped;
            continue;
        }

        assertAllowedThumbnailUrl($thumbnailUrl);

        $existingPath = ltrim((string) ($metadata['thumbnail_path'] ?? ''), '/');
        $existingSource = trim((string) ($metadata['thumbnail_source_url'] ?? ''));
        if ($existingPath !== '' && $existingSource === $thumbnailUrl && is_file($existingPath)) {
            updateTalkThumbnail($deckId, '/' . $existingPath);
            writeMetadata($metadataPath, $metadata);
            ++$reused;
            continue;
        }

        [$bytes, $mime] = downloadThumbnail($thumbnailUrl);
        $extension = THUMBNAIL_MIME_EXTENSIONS[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException("Unsupported thumbnail MIME type: {$mime}");
        }

        $target = "{$deckDir}/thumbnail.{$extension}";
        file_put_contents($target, $bytes);

        foreach (glob("{$deckDir}/thumbnail.*") ?: [] as $candidate) {
            if ($candidate !== $target && is_file($candidate)) {
                unlink($candidate);
            }
        }

        $localPath = '/' . $target;
        $metadata['thumbnail_path'] = $localPath;
        $metadata['thumbnail_source_url'] = $thumbnailUrl;
        writeMetadata($metadataPath, $metadata);

        updateTalkThumbnail($deckId, $localPath);
        ++$archived;
    } catch (Throwable $exception) {
        fwrite(STDERR, "::warning::Could not archive thumbnail for deck {$deckId}: {$exception->getMessage()}\n");
        ++$skipped;
    }
}

fwrite(STDOUT, "Thumbnail archive finished: {$archived} archived, {$reused} reused, {$skipped} skipped.\n");

function enrichFromManagedTalk(array $metadata, string $deckId): array
{
    foreach (['source/_talks', 'source/_talksEn'] as $collection) {
        $matches = glob("{$collection}/slides-com-{$deckId}-*.md") ?: [];
        if ($matches === []) {
            continue;
        }

        $content = (string) file_get_contents($matches[0]);
        foreach (['slug', 'title', 'description'] as $key) {
            if (trim((string) ($metadata[$key] ?? '')) !== '') {
                continue;
            }
            if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+)$/m', $content, $match) === 1) {
                $decoded = json_decode(trim($match[1]), true);
                $metadata[$key] = is_string($decoded) ? $decoded : trim($match[1], " \t\n\r\0\x0B\"");
            }
        }

        $existingTags = (array) ($metadata['tags']['slides_com'] ?? []);
        if ($existingTags === [] && preg_match('/^tags:\R(?<tags>(?:  - .+\R)*)^presentation:/m', $content, $match) === 1) {
            $tags = [];
            foreach (preg_split('/\R/', trim($match['tags'])) ?: [] as $line) {
                if (preg_match('/^\s*-\s*(.+)$/', $line, $tagMatch) !== 1) {
                    continue;
                }
                $decoded = json_decode(trim($tagMatch[1]), true);
                $tags[] = is_string($decoded) ? $decoded : trim($tagMatch[1], " \t\n\r\0\x0B\"");
            }
            $metadata['tags']['slides_com'] = array_values(array_filter($tags));
        }

        break;
    }

    return $metadata;
}

function writeMetadata(string $path, array $metadata): void
{
    $content = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if (!is_file($path) || file_get_contents($path) !== $content) {
        file_put_contents($path, $content);
    }
}

function assertAllowedThumbnailUrl(string $url): void
{
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($scheme !== 'https') {
        throw new RuntimeException('Thumbnail URL must use HTTPS.');
    }

    $allowed = $host === 'slides.com'
        || $host === 's3.amazonaws.com'
        || $host === 'media.slid.es'
        || str_ends_with($host, '.slid.es');

    if (!$allowed) {
        throw new RuntimeException("Thumbnail host is not allowed: {$host}");
    }
}

function downloadThumbnail(string $url): array
{
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Accept: image/*\r\nUser-Agent: vitormattos.github.io-slides-thumbnail-archive\r\n",
        'ignore_errors' => true,
        'timeout' => 30,
        'follow_location' => 1,
        'max_redirects' => 5,
    ]]);

    $bytes = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = $headers[0] ?? '';
    if ($bytes === false || preg_match('/\s2\d\d\s/', $status) !== 1) {
        throw new RuntimeException("Thumbnail download failed ({$status}).");
    }

    if ($bytes === '') {
        throw new RuntimeException('Thumbnail response is empty.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->buffer($bytes);

    return [$bytes, $mime];
}

function updateTalkThumbnail(string $deckId, string $localPath): void
{
    foreach (['source/_talks', 'source/_talksEn'] as $collection) {
        foreach (glob("{$collection}/slides-com-{$deckId}-*.md") ?: [] as $path) {
            $content = (string) file_get_contents($path);
            $replacement = '  thumbnail: ' . json_encode($localPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $updated = preg_replace('/^  thumbnail: .*$/m', $replacement, $content, 1, $count);
            if ($count === 0) {
                continue;
            }
            if ($updated !== $content) {
                file_put_contents($path, $updated);
            }
        }
    }
}
