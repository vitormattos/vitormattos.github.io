<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\SlidesPublicTagsScraper;

require __DIR__ . '/../vendor/autoload.php';

const API_BASE = 'https://api.slides.com';
const MANAGED_PREFIX = 'slides-com-';
const MAX_RETRIES = 6;
const REQUEST_DELAY_MICROSECONDS = 750000;

$requireToken = in_array('--require-token', $argv, true);
$token = getenv('SLIDES_API_TOKEN');
if (!$token) {
    if ($requireToken) {
        fwrite(STDERR, "SLIDES_API_TOKEN is required.\n");
        exit(1);
    }

    fwrite(
        STDOUT,
        "SLIDES_API_TOKEN is unavailable; skipping Slides.com synchronization and using versioned content.\n",
    );
    exit(0);
}

function responseStatusCode(array $headers): int
{
    $status = $headers[0] ?? '';
    if (preg_match('/\s(\d{3})\s/', $status, $matches) !== 1) {
        return 0;
    }

    return (int) $matches[1];
}

function retryAfterSeconds(array $headers, int $attempt): int
{
    foreach ($headers as $header) {
        if (stripos($header, 'Retry-After:') !== 0) {
            continue;
        }

        $value = trim(substr($header, strlen('Retry-After:')));
        if (ctype_digit($value)) {
            return max(1, (int) $value);
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return max(1, $timestamp - time());
        }
    }

    return min(60, 2 ** $attempt);
}

function request(string $path): array
{
    global $token;

    for ($attempt = 1; $attempt <= MAX_RETRIES; ++$attempt) {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\nUser-Agent: vitormattos.github.io-slides-sync\r\n",
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $body = file_get_contents(API_BASE . $path, false, $context);
        $headers = $http_response_header ?? [];
        $statusCode = responseStatusCode($headers);

        if ($body !== false && $statusCode >= 200 && $statusCode < 300) {
            return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        }

        if ($statusCode !== 429 || $attempt === MAX_RETRIES) {
            $status = $headers[0] ?? 'unknown status';
            throw new RuntimeException("Slides API request failed: {$path} ({$status})");
        }

        $wait = retryAfterSeconds($headers, $attempt);
        fwrite(STDERR, "Slides API rate limit reached; retrying {$path} in {$wait}s.\n");
        sleep($wait);
    }

    throw new RuntimeException("Slides API request exhausted retries: {$path}");
}

function yamlString(?string $value): string
{
    return json_encode($value ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function yamlList(array $values, string $indent = ''): string
{
    if ($values === []) {
        return $indent . "[]\n";
    }

    return implode('', array_map(
        static fn(string $value): string => $indent . '- ' . yamlString($value) . "\n",
        $values,
    ));
}

function safeSlug(string $slug): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $slug), '-'));

    return $slug !== '' ? $slug : 'deck';
}

function writeIfChanged(string $path, string $content): void
{
    if (is_file($path) && file_get_contents($path) === $content) {
        return;
    }

    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    file_put_contents($path, $content);
}

function deleteManagedTalks(array $expectedPaths): void
{
    $expected = array_fill_keys($expectedPaths, true);
    foreach (['source/_talks', 'source/_talksEn'] as $directory) {
        foreach (glob($directory . '/' . MANAGED_PREFIX . '*.md') ?: [] as $path) {
            if (!isset($expected[$path])) {
                unlink($path);
            }
        }
    }
}

function clearDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

function normalizeTags(array $deck, string $deckUrl): array
{
    $tags = [];
    foreach ((array) ($deck['tags'] ?? []) as $tag) {
        if (is_string($tag)) {
            $tag = trim($tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        } elseif (is_array($tag) && isset($tag['name']) && is_string($tag['name'])) {
            $name = trim($tag['name']);
            if ($name !== '') {
                $tags[] = $name;
            }
        }
    }

    if ($tags === []) {
        $tags = SlidesPublicTagsScraper::fetch($deckUrl);
    }

    $tags = array_values(array_unique($tags));
    sort($tags, SORT_NATURAL | SORT_FLAG_CASE);

    return $tags;
}

function normalizeTimestamp(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format(DATE_ATOM);
    } catch (Throwable) {
        return $value;
    }
}

function fetchDeckDetails(array $deck): array
{
    $id = (string) ($deck['id'] ?? '');
    if ($id === '') {
        return $deck;
    }

    foreach (["/v1/decks/{$id}", "/v1/decks/{$id}/"] as $path) {
        try {
            $detail = request($path);
            if (is_array($detail)) {
                return array_replace($deck, $detail);
            }
        } catch (RuntimeException) {
            // Fall back to the list payload when a detail endpoint is unavailable.
        }
    }

    return $deck;
}

function fetchDeckSource(string $deckUrl): array
{
    $html = @file_get_contents($deckUrl);
    if (!is_string($html) || $html === '') {
        return ['html' => null, 'css' => null];
    }

    $css = null;
    if (preg_match('/<style[^>]*>(.*?)<\/style>/is', $html, $matches) === 1) {
        $css = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    return ['html' => $html, 'css' => $css];
}

$page = 1;
$decks = [];
do {
    $response = request('/v1/decks?limit=50&page=' . $page);
    $batch = $response['results'] ?? $response['decks'] ?? [];
    if (!is_array($batch) || $batch === []) {
        break;
    }

    foreach ($batch as $deck) {
        if (is_array($deck)) {
            $decks[] = $deck;
        }
    }

    $hasMore = (bool) ($response['has_more'] ?? false);
    ++$page;
} while ($hasMore);

$expectedTalkPaths = [];
foreach ($decks as $summary) {
    $deck = fetchDeckDetails($summary);
    if (($deck['visibility'] ?? null) !== 'all') {
        continue;
    }

    $id = (string) ($deck['id'] ?? '');
    if ($id === '') {
        continue;
    }

    $slug = safeSlug((string) ($deck['slug'] ?? $deck['title'] ?? $id));
    $title = trim((string) ($deck['title'] ?? $slug));
    $description = trim((string) ($deck['description'] ?? ''));
    $deckUrl = 'https://slides.com/vitormattos/' . rawurlencode((string) ($deck['slug'] ?? $slug));
    $tags = normalizeTags($deck, $deckUrl);
    $language = (string) ($deck['language'] ?? 'pt');
    $isEnglish = str_starts_with(strtolower($language), 'en');
    $collection = $isEnglish ? 'source/_talksEn' : 'source/_talks';
    $talkPath = $collection . '/' . MANAGED_PREFIX . $id . '-' . $slug . '.md';
    $expectedTalkPaths[] = $talkPath;
    $archiveDirectory = 'presentations/slides.com/' . $id;
    clearDirectory($archiveDirectory);
    mkdir($archiveDirectory, 0777, true);

    $source = fetchDeckSource($deckUrl);
    if (is_string($source['html'])) {
        file_put_contents($archiveDirectory . '/slides.html', $source['html']);
    }
    if (is_string($source['css']) && trim($source['css']) !== '') {
        file_put_contents($archiveDirectory . '/slides.css', $source['css']);
    }

    $metadata = [
        'id' => $id,
        'source' => 'slides.com',
        'source_url' => $deckUrl,
        'slug' => $slug,
        'title' => $title,
        'description' => $description,
        'language' => $language,
        'visibility' => $deck['visibility'] ?? null,
        'created_at' => normalizeTimestamp($deck['created_at'] ?? null),
        'updated_at' => normalizeTimestamp($deck['updated_at'] ?? null),
        'slide_count' => $deck['slide_count'] ?? $deck['slides_count'] ?? null,
        'width' => $deck['width'] ?? null,
        'height' => $deck['height'] ?? null,
        'tags' => ['slides_com' => $tags],
    ];
    file_put_contents(
        $archiveDirectory . '/metadata.json',
        json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
    );

    $date = substr((string) ($metadata['created_at'] ?? date(DATE_ATOM)), 0, 10);
    $frontMatter = "---\n";
    $frontMatter .= 'extends: _layouts.talk' . "\n";
    $frontMatter .= 'section: content' . "\n";
    $frontMatter .= 'title: ' . yamlString($title) . "\n";
    $frontMatter .= 'description: ' . yamlString($description) . "\n";
    $frontMatter .= 'date: ' . $date . "\n";
    $frontMatter .= 'slidesId: ' . yamlString($id) . "\n";
    $frontMatter .= 'locale: ' . yamlString($isEnglish ? 'en' : 'pt-BR') . "\n";
    $frontMatter .= "tags:\n" . yamlList($tags, '  ');
    $frontMatter .= "presentation:\n";
    $frontMatter .= '  type: slides.com' . "\n";
    $frontMatter .= '  source: slides.com' . "\n";
    $frontMatter .= '  url: ' . yamlString($deckUrl) . "\n";
    $frontMatter .= '  metadata: /' . $archiveDirectory . '/metadata.json' . "\n";
    $frontMatter .= '  language: ' . yamlString($language) . "\n";
    if (is_file($archiveDirectory . '/slides.html')) {
        $frontMatter .= '  localHtml: /' . $archiveDirectory . '/slides.html' . "\n";
        $frontMatter .= '  embed: /' . $archiveDirectory . '/slides.html' . "\n";
    }
    if (is_file($archiveDirectory . '/slides.css')) {
        $frontMatter .= '  localCss: /' . $archiveDirectory . '/slides.css' . "\n";
    }
    if ($metadata['slide_count']) {
        $frontMatter .= '  slideCount: ' . (int) $metadata['slide_count'] . "\n";
    }
    if ($metadata['width']) {
        $frontMatter .= '  width: ' . (int) $metadata['width'] . "\n";
    }
    if ($metadata['height']) {
        $frontMatter .= '  height: ' . (int) $metadata['height'] . "\n";
    }
    $frontMatter .= "---\n";
    $frontMatter .= "<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->\n";
    $frontMatter .= "<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->\n";

    writeIfChanged($talkPath, $frontMatter);
    usleep(REQUEST_DELAY_MICROSECONDS);
}

deleteManagedTalks($expectedTalkPaths);
