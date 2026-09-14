<?php
// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

const API_BASE = 'https://api.slides.com';
const MANAGED_PREFIX = 'slides-com-';

$token = getenv('SLIDES_API_TOKEN');
if (!$token) { fwrite(STDERR, "SLIDES_API_TOKEN is required.\n"); exit(1); }

function request(string $path): array
{
    global $token;
    $context = stream_context_create(['http' => ['method' => 'GET', 'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\nUser-Agent: vitormattos.github.io-slides-sync\r\n", 'ignore_errors' => true, 'timeout' => 30]]);
    $body = file_get_contents(API_BASE . $path, false, $context);
    $status = $http_response_header[0] ?? '';
    if ($body === false || !preg_match('/\s2\d\d\s/', $status)) throw new RuntimeException("Slides API request failed: {$path} ({$status})");
    return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
}
function yamlString(?string $value): string { return json_encode($value ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
function safeSlug(string $slug): string { $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $slug), '-')); return $slug !== '' ? $slug : 'deck'; }
function writeIfChanged(string $path, string $content): void { if (is_file($path) && file_get_contents($path) === $content) return; if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true); file_put_contents($path, $content); }

$list = request('/v1/decks?per_page=100&page=1');
$total = (int) ($list['meta']['total'] ?? count($list['data'] ?? []));
$summaries = $list['data'] ?? [];
for ($page = 2, $pages = max(1, (int) ceil($total / 100)); $page <= $pages; ++$page) array_push($summaries, ...(request("/v1/decks?per_page=100&page={$page}")['data'] ?? []));

$expectedPaths = [];
foreach ($summaries as $summary) {
    $detail = request('/v1/decks/' . rawurlencode((string) $summary['id']) . '?include_deck_html=true')['data'];
    if (($detail['visibility'] ?? null) !== 'all') continue;

    $slug = safeSlug((string) ($detail['slug'] ?? $detail['id']));
    $managedName = MANAGED_PREFIX . $detail['id'] . '-' . $slug;
    $locale = str_starts_with((string) ($detail['language'] ?? ''), 'pt') ? 'pt-BR' : 'en';
    $collection = $locale === 'pt-BR' ? 'source/_talks' : 'source/_talksEn';
    $publicPath = $locale === 'pt-BR' ? '/pt-BR/palestras/' . $slug : '/talks/' . $slug;
    $managedPath = "{$collection}/{$managedName}.md";
    $expectedPaths[$managedPath] = true;
    $embed = rtrim((string) $detail['url'], '/') . '/embed';
    $created = substr((string) ($detail['created_at'] ?? ''), 0, 10);
    $updated = substr((string) ($detail['updated_at'] ?? ''), 0, 10);
    $description = trim((string) ($detail['description'] ?? '')) ?: (string) $detail['title'];
    $deckDir = "presentations/slides.com/{$detail['id']}";

    $meta = [
        '_spdx' => ['copyright' => '2026 Vitor Mattos', 'license' => 'CC-BY-SA-4.0'],
        'id' => $detail['id'], 'slug' => $slug, 'visibility' => $detail['visibility'], 'thumbnail_url' => $detail['thumbnail_url'] ?? null,
        'slide_count' => $detail['slide_count'] ?? null, 'width' => $detail['width'] ?? null, 'height' => $detail['height'] ?? null, 'margin' => $detail['margin'] ?? null,
        'transition' => $detail['transition'] ?? null, 'background_transition' => $detail['background_transition'] ?? null, 'rtl' => $detail['rtl'] ?? false, 'loop' => $detail['loop'] ?? false,
        'theme_font' => $detail['theme_font'] ?? null, 'theme_color' => $detail['theme_color'] ?? null, 'language' => $detail['language'] ?? null,
        'created_at' => $detail['created_at'] ?? null, 'updated_at' => $detail['updated_at'] ?? null, 'urls' => $detail['urls'] ?? [],
    ];
    writeIfChanged("{$deckDir}/deck.html", "<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->\n<!-- SPDX-License-Identifier: CC-BY-SA-4.0 -->\n" . ($detail['deck_html'] ?? '') . "\n");
    writeIfChanged("{$deckDir}/deck.css", "/* SPDX-FileCopyrightText: 2026 Vitor Mattos */\n/* SPDX-License-Identifier: CC-BY-SA-4.0 */\n" . ($detail['css'] ?? '') . "\n");
    writeIfChanged("{$deckDir}/metadata.json", json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

    $frontMatter = "---\n" . 'title: ' . yamlString((string) $detail['title']) . "\n" . 'description: ' . yamlString($description) . "\n"
        . "date: {$created}\nupdated: {$updated}\nlocale: {$locale}\n" . 'permalink: ' . yamlString($publicPath) . "\nlayout: talk\nschemaType: CreativeWork\nindexable: true\n"
        . "managed: slides.com\nslidesId: {$detail['id']}\npresentation:\n  type: slides.com\n  url: " . yamlString((string) $detail['url']) . "\n"
        . '  embed: ' . yamlString($embed) . "\n  thumbnail: " . yamlString((string) ($detail['thumbnail_url'] ?? '')) . "\n"
        . "  localHtml: /{$deckDir}/deck.html\n  localCss: /{$deckDir}/deck.css\n  metadata: /{$deckDir}/metadata.json\n"
        . '  language: ' . yamlString((string) ($detail['language'] ?? '')) . "\n  slideCount: " . (int) ($detail['slide_count'] ?? 0) . "\n"
        . '  width: ' . (int) ($detail['width'] ?? 0) . "\n  height: " . (int) ($detail['height'] ?? 0) . "\n  transition: " . yamlString((string) ($detail['transition'] ?? 'slide')) . "\n"
        . '  themeFont: ' . yamlString((string) ($detail['theme_font'] ?? '')) . "\n  themeColor: " . yamlString((string) ($detail['theme_color'] ?? '')) . "\n---\n"
        . "<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->\n<!-- SPDX-License-Identifier: CC-BY-SA-4.0 -->\n\n{$description}\n\nSynced from the public Slides.com deck. The archived HTML, CSS and metadata are kept in this repository so the presentation remains independently inspectable and reusable.\n";
    writeIfChanged($managedPath, $frontMatter);
}
foreach (['source/_talks', 'source/_talksEn'] as $collection) foreach (glob("{$collection}/" . MANAGED_PREFIX . '*.md') ?: [] as $file) if (!isset($expectedPaths[$file])) unlink($file);
fwrite(STDOUT, 'Synchronized ' . count($expectedPaths) . " public Slides.com decks.\n");
