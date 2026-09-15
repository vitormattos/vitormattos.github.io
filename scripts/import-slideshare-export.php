<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/import-slideshare-export.php <export.json> <export.csv>\n");
    exit(2);
}

[$script, $jsonPath, $csvPath] = $argv;
unset($script);

$json = json_decode((string) file_get_contents($jsonPath), true, flags: JSON_THROW_ON_ERROR);
$slideshows = (array) ($json['slideshows_uploaded'] ?? []);
$csvRows = readCsv($csvPath);

$jsonById = [];
foreach ($slideshows as $slideshow) {
    if (($slideshow['privacy'] ?? null) !== 'public') {
        continue;
    }
    $id = sourceId((string) ($slideshow['url'] ?? ''));
    if ($id !== null) {
        $jsonById[$id] = $slideshow;
    }
}

$imported = 0;
$routes = [];
foreach ($csvRows as $row) {
    if (($row['privacy'] ?? null) !== 'public') {
        continue;
    }

    $sourceUrl = (string) ($row['document_url'] ?? '');
    $id = sourceId($sourceUrl);
    if ($id === null || !isset($jsonById[$id])) {
        fwrite(STDERR, "::warning::Skipping CSV row without matching public JSON slideshow: {$sourceUrl}\n");
        continue;
    }

    $source = $jsonById[$id];
    $slug = sourceSlug((string) ($source['url'] ?? $sourceUrl), (string) ($source['title'] ?? $row['title'] ?? $id));
    $language = trim((string) ($source['language'] ?? $row['language'] ?? ''));
    $locale = str_starts_with(strtolower($language), 'pt') ? 'pt-BR' : 'en';
    $route = ($locale === 'pt-BR' ? '/pt-BR/palestras/' : '/talks/') . $slug;
    if (isset($routes[$route])) {
        $slug .= '-' . $id;
        $route .= '-' . $id;
    }
    $routes[$route] = true;

    $publishedAt = normalizeDate((string) ($row['date_uploaded'] ?? ''));
    $tags = tags((string) ($source['tag'] ?? $row['tag'] ?? ''));
    $metadata = [
        'schema' => 1,
        'source' => 'slideshare',
        'id' => $id,
        'slug' => $slug,
        'title' => trim((string) ($source['title'] ?? $row['title'] ?? '')),
        'description' => trim((string) ($source['description'] ?? $row['description'] ?? '')),
        'language' => $language,
        'visibility' => 'public',
        'source_url' => (string) ($source['url'] ?? $sourceUrl),
        'source_download_url' => (string) ($source['download_url'] ?? $row['download_url'] ?? ''),
        'published_at' => $publishedAt,
        'tags' => $tags,
        'statistics' => [
            'total_views' => integer($row['total_views'] ?? null),
            'slideshare_views' => integer($row['slideshare_views'] ?? null),
            'embed_views' => integer($row['embed_views'] ?? null),
            'likes' => integer($row['total_likes'] ?? null),
            'comments' => integer($row['total_comments'] ?? null),
            'downloads' => integer($row['total_downloads'] ?? null),
        ],
    ];

    $directory = 'presentations/slideshare/' . $id;
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Could not create {$directory}");
    }
    file_put_contents(
        $directory . '/metadata.json',
        json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
    );

    $collection = $locale === 'pt-BR' ? 'source/_talks' : 'source/_talksEn';
    $talkPath = $collection . '/slideshare-' . $id . '-' . $slug . '.md';
    file_put_contents($talkPath, talkMarkdown($metadata, $locale));
    ++$imported;
}

$manifest = [
    'schema' => 1,
    'source' => 'slideshare-data-export',
    'presentations_found' => count($slideshows),
    'presentations_imported' => $imported,
    'privacy_filter' => 'allowlist-v1',
    'included_fields' => [
        'id', 'title', 'description', 'language', 'visibility', 'source_url', 'source_download_url',
        'published_at', 'tags', 'statistics',
    ],
    'excluded_sections' => ['account_registration', 'contact_details', 'following_users', 'comments'],
    'note' => 'The original SlideShare export is intentionally not committed because it contains account and contact data unrelated to the presentation archive.',
];
file_put_contents(
    'presentations/slideshare/import.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
);

fwrite(STDOUT, "Imported {$imported} public SlideShare presentations.\n");

function readCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Could not open {$path}");
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        return [];
    }

    $rows = [];
    while (($values = fgetcsv($handle)) !== false) {
        if (count($values) !== count($header)) {
            continue;
        }
        $rows[] = array_combine($header, $values);
    }
    fclose($handle);

    return $rows;
}

function sourceId(string $url): ?string
{
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    if (preg_match('~/(\d+)$~', '/' . $path, $matches) !== 1) {
        return null;
    }

    return $matches[1];
}

function sourceSlug(string $url, string $title): string
{
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $segments = explode('/', $path);
    $slideshowIndex = array_search('slideshow', $segments, true);
    if ($slideshowIndex !== false && isset($segments[$slideshowIndex + 1])) {
        $candidate = trim($segments[$slideshowIndex + 1]);
        if ($candidate !== '') {
            return safeSlug($candidate);
        }
    }

    return safeSlug($title);
}

function safeSlug(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $ascii = $ascii === false ? $value : $ascii;
    $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii));
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'presentation';
}

function normalizeDate(string $value): string
{
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        throw new RuntimeException("Invalid SlideShare upload date: {$value}");
    }

    return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
}

function tags(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    return array_values(array_filter(
        array_map('trim', explode(',', $value)),
        static fn(string $tag): bool => $tag !== '',
    ));
}

function integer(mixed $value): int
{
    return is_numeric($value) ? (int) $value : 0;
}

function talkMarkdown(array $metadata, string $locale): string
{
    $date = substr((string) $metadata['published_at'], 0, 10);
    $tags = '[' . implode(', ', array_map(
        static fn(string $tag): string => json_encode($tag, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $metadata['tags'],
    )) . ']';
    $title = json_encode($metadata['title'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $description = json_encode($metadata['description'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $url = json_encode($metadata['source_url'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return <<<MARKDOWN
---
extends: _layouts.talk
section: content
locale: {$locale}
schemaType: CreativeWork
indexable: true
showAbout: false
slug: "{$metadata['slug']}"
title: {$title}
description: {$description}
date: {$date}
updated: {$date}
managed: slideshare
slidesId: {$metadata['id']}
tags: {$tags}
presentation:
  type: slideshare
  source: slideshare
  url: {$url}
  metadata: /presentations/slideshare/{$metadata['id']}/metadata.json
  language: "{$metadata['language']}"
---
<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: CC-BY-SA-4.0 -->

MARKDOWN;
}
