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

$token = getenv('SLIDES_API_TOKEN');
if (!$token) {
    fwrite(STDERR, "SLIDES_API_TOKEN is required.\n");
    exit(1);
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

function spdxHtmlHeader(): string
{
    return '<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->' . "\n"
        . '<!-- SPDX-' . 'License-Identifier: CC-BY-SA-4.0 -->' . "\n";
}

function spdxCssHeader(): string
{
    return '/* SPDX-FileCopyrightText: 2026 Vitor Mattos */' . "\n"
        . '/* SPDX-' . 'License-Identifier: CC-BY-SA-4.0 */' . "\n";
}

$list = request('/v1/decks?per_page=100&page=1');
$total = (int) ($list['meta']['total'] ?? count($list['data'] ?? []));
$summaries = $list['data'] ?? [];
$pages = max(1, (int) ceil($total / 100));

for ($page = 2; $page <= $pages; ++$page) {
    usleep(REQUEST_DELAY_MICROSECONDS);
    array_push($summaries, ...(request("/v1/decks?per_page=100&page={$page}")['data'] ?? []));
}

$publicDecks = [];
foreach ($summaries as $summary) {
    if (array_key_exists('visibility', $summary) && ($summary['visibility'] ?? null) !== 'all') {
        continue;
    }

    usleep(REQUEST_DELAY_MICROSECONDS);
    $detail = request('/v1/decks/' . rawurlencode((string) $summary['id']) . '?include_deck_html=true')['data'];
    if (($detail['visibility'] ?? null) === 'all') {
        $publicDecks[] = $detail;
    }
}

$publicUrls = array_values(array_filter(array_map(
    static fn(array $deck): string => rtrim((string) ($deck['url'] ?? ''), '/'),
    $publicDecks,
)));
$tagsByUrl = [];
try {
    $tagsByUrl = (new SlidesPublicTagsScraper('vitormattos'))->scrape($publicUrls);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Slides.com tag scraping failed; continuing without tags: ' . $exception->getMessage() . "\n");
}

$expectedPaths = [];
foreach ($publicDecks as $detail) {
    $slug = safeSlug((string) ($detail['slug'] ?? $detail['id']));
    $managedName = MANAGED_PREFIX . $detail['id'] . '-' . $slug;
    $locale = str_starts_with((string) ($detail['language'] ?? ''), 'pt') ? 'pt-BR' : 'en';
    $collection = $locale === 'pt-BR' ? 'source/_talks' : 'source/_talksEn';
    $managedPath = "{$collection}/{$managedName}.md";
    $expectedPaths[$managedPath] = true;
    $publicUrl = rtrim((string) $detail['url'], '/');
    $embed = $publicUrl . '/embed';
    $tags = $tagsByUrl[$publicUrl] ?? [];
    $created = substr((string) ($detail['created_at'] ?? ''), 0, 10);
    $updated = substr((string) ($detail['updated_at'] ?? ''), 0, 10);
    $description = trim((string) ($detail['description'] ?? '')) ?: (string) $detail['title'];
    $deckDir = "presentations/slides.com/{$detail['id']}";

    $meta = [
        '_spdx' => [
            'copyright' => '2026 Vitor Mattos',
            'license' => 'CC-BY-SA-4.0',
        ],
        'id' => $detail['id'],
        'slug' => $slug,
        'title' => $detail['title'] ?? null,
        'visibility' => 'all',
        'url' => $publicUrl,
        'embed_url' => $embed,
        'tags' => [
            'slides_com' => $tags,
        ],
        'thumbnail_url' => $detail['thumbnail_url'] ?? null,
        'slide_count' => $detail['slide_count'] ?? null,
        'width' => $detail['width'] ?? null,
        'height' => $detail['height'] ?? null,
        'margin' => $detail['margin'] ?? null,
        'transition' => $detail['transition'] ?? null,
        'background_transition' => $detail['background_transition'] ?? null,
        'rtl' => $detail['rtl'] ?? false,
        'loop' => $detail['loop'] ?? false,
        'theme_font' => $detail['theme_font'] ?? null,
        'theme_color' => $detail['theme_color'] ?? null,
        'language' => $detail['language'] ?? null,
        'created_at' => $detail['created_at'] ?? null,
        'updated_at' => $detail['updated_at'] ?? null,
        'urls' => $detail['urls'] ?? [],
    ];

    writeIfChanged("{$deckDir}/deck.html", spdxHtmlHeader() . ($detail['deck_html'] ?? '') . "\n");
    writeIfChanged("{$deckDir}/deck.css", spdxCssHeader() . ($detail['css'] ?? '') . "\n");
    writeIfChanged(
        "{$deckDir}/metadata.json",
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
    );

    $frontMatter = "---\n"
        . "extends: _layouts.talk\n"
        . "section: content\n"
        . "locale: {$locale}\n"
        . "schemaType: CreativeWork\n"
        . "indexable: true\n"
        . "showAbout: false\n"
        . 'slug: ' . yamlString($slug) . "\n"
        . 'title: ' . yamlString((string) $detail['title']) . "\n"
        . 'description: ' . yamlString($description) . "\n"
        . "date: {$created}\n"
        . "updated: {$updated}\n"
        . "managed: slides.com\n"
        . "slidesId: {$detail['id']}\n"
        . "tags:\n"
        . yamlList($tags, '  ')
        . "presentation:\n"
        . "  type: slides.com\n"
        . '  url: ' . yamlString($publicUrl) . "\n"
        . '  embed: ' . yamlString($embed) . "\n"
        . '  thumbnail: ' . yamlString((string) ($detail['thumbnail_url'] ?? '')) . "\n"
        . "  localHtml: /{$deckDir}/deck.html\n"
        . "  localCss: /{$deckDir}/deck.css\n"
        . "  metadata: /{$deckDir}/metadata.json\n"
        . '  language: ' . yamlString((string) ($detail['language'] ?? '')) . "\n"
        . '  slideCount: ' . (int) ($detail['slide_count'] ?? 0) . "\n"
        . '  width: ' . (int) ($detail['width'] ?? 0) . "\n"
        . '  height: ' . (int) ($detail['height'] ?? 0) . "\n"
        . '  transition: ' . yamlString((string) ($detail['transition'] ?? 'slide')) . "\n"
        . '  themeFont: ' . yamlString((string) ($detail['theme_font'] ?? '')) . "\n"
        . '  themeColor: ' . yamlString((string) ($detail['theme_color'] ?? '')) . "\n"
        . "---\n"
        . spdxHtmlHeader();

    writeIfChanged($managedPath, $frontMatter);
}

foreach (['source/_talks', 'source/_talksEn'] as $collection) {
    foreach (glob("{$collection}/" . MANAGED_PREFIX . '*.md') ?: [] as $file) {
        if (!isset($expectedPaths[$file])) {
            unlink($file);
        }
    }
}

fwrite(STDOUT, 'Synchronized ' . count($expectedPaths) . " public Slides.com decks with public tag metadata.\n");
