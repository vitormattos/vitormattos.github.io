<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests;

use App\Media\MediaCatalog;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MediaArchiveTest extends TestCase
{
    private static function talk(string $slug, string $locale = 'pt-BR', ?string $video = null): object
    {
        return (object) [
            'slug' => $slug,
            'locale' => $locale,
            'title' => $locale === 'en' ? 'Example talk' : 'Palestra de exemplo',
            'date' => '2026-10-01',
            'description' => 'Example description',
            'tags' => ['PHP', 'software livre'],
            'presentation' => ['video' => $video],
        ];
    }

    public function testDerivesRecordingsFromTalkResourcesAppearancesAndNativeMetadata(): void
    {
        $metadata = [
            'example' => [
                'sources' => [['href' => 'https://example.org/event']],
                'resources' => [
                    ['type' => 'video', 'href' => 'https://youtu.be/27ONOPxLxVs', 'date' => '2012-06-02', 'archiveId' => 'historic-video'],
                    ['type' => 'video', 'href' => 'https://www.youtube.com/watch?v=NB7bLwUvceg', 'availability' => 'unverified'],
                    ['type' => 'slides', 'href' => 'https://slides.com/example'],
                ],
                'appearances' => [[
                    'event' => 'Example event',
                    'date' => '2022-08-03',
                    'city' => 'Rio de Janeiro',
                    'resources' => [['type' => 'video', 'href' => 'https://example.org/recording.mp4']],
                ]],
            ],
        ];
        $archive = MediaCatalog::build($metadata, [], [], [
            self::talk('example', 'en', 'https://www.youtube.com/watch?v=27ONOPxLxVs&feature=share'),
            self::talk('example', 'pt-BR', 'https://youtu.be/NB7bLwUvceg'),
            self::talk('another-talk', 'pt-BR', 'https://youtu.be/43hFB-Jw2TE'),
        ]);
        $items = array_column($archive['items'], null, 'url');
        self::assertCount(3, $items);
        $historic = $items['https://www.youtube.com/watch?v=27ONOPxLxVs'];
        self::assertSame('historic-video', $historic['id']);
        self::assertSame(2012, $historic['year']);
        self::assertSame(['en' => 'Example talk', 'pt-BR' => 'Palestra de exemplo'], $historic['title']);
        self::assertSame('https://example.org/event', $historic['reference']);
        self::assertSame('Example event', $items['https://example.org/recording.mp4']['event']);
        self::assertSame(2022, $items['https://example.org/recording.mp4']['year']);
        self::assertSame('Rio de Janeiro', $items['https://example.org/recording.mp4']['city']);
        self::assertSame('https://example.org/recording.mp4', $items['https://example.org/recording.mp4']['video']);
        self::assertNull($items['https://www.youtube.com/watch?v=43hFB-Jw2TE']['year'], 'Deck date is not a recording date');
        self::assertArrayNotHasKey('https://www.youtube.com/watch?v=NB7bLwUvceg', $items);
        self::assertSame(['photo', 'video', 'audio'], array_keys($archive['types']));
        self::assertArrayHasKey('software livre', $archive['topics']);
    }

    public function testYoutubeUrlsAreNormalizedWithoutAcceptingImpostorHosts(): void
    {
        foreach ([
            'https://youtu.be/27ONOPxLxVs?t=2',
            'https://www.youtube.com/watch?v=27ONOPxLxVs',
            'https://m.youtube.com/watch?v=27ONOPxLxVs',
            'https://www.youtube-nocookie.com/embed/27ONOPxLxVs',
            'https://youtube.com/live/27ONOPxLxVs',
        ] as $url) {
            self::assertSame('27ONOPxLxVs', MediaCatalog::youtubeId($url));
        }
        foreach ([
            'https://youtube.com.example.org/watch?v=27ONOPxLxVs',
            'https://user@youtube.com/watch?v=27ONOPxLxVs',
            'https://youtube.com/watch?v[]=27ONOPxLxVs',
            'javascript:alert(1)',
        ] as $url) {
            self::assertNull(MediaCatalog::youtubeId($url));
        }
    }

    public function testStandaloneSidecarsRejectPrivateMetadataAndUnsupportedTypes(): void
    {
        $record = [
            'id' => 'sample', 'type' => 'audio', 'year' => 2026, 'event' => 'Example',
            'title' => ['en' => 'Interview', 'pt-BR' => 'Entrevista'],
            'description' => ['en' => '', 'pt-BR' => ''], 'topics' => ['software livre'],
            'url' => 'https://example.org/episode', 'audio' => 'https://example.org/episode.mp3',
            'source' => 'Example', 'role' => 'interviewee',
        ];
        self::assertCount(1, MediaCatalog::build([], [$record], [])['items']);
        $photo = array_replace($record, [
            'id' => 'sample-photo',
            'type' => 'photo',
            'url' => '/media-assets/sample.webp',
            'image' => '/media-assets/sample.webp',
            'thumbnail' => '/media-assets/sample-thumb.webp',
            'width' => 1200,
            'height' => 800,
            'credit' => 'Example photographer',
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
        ]);
        unset($photo['audio']);
        self::assertSame('photo', MediaCatalog::build([], [$photo], [])['items'][0]['type']);
        foreach ([
            array_replace($record, ['privatePath' => '/personal/photo.jpg']),
            array_replace($record, ['audio' => 'https://cloud.colab.rio/private.mp3']),
            array_replace($record, ['type' => 'presentation']),
            array_replace($record, ['type' => 'photo']),
        ] as $invalid) {
            try {
                MediaCatalog::build([], [$invalid], []);
                self::fail('Invalid sidecar was accepted');
            } catch (InvalidArgumentException $e) {
                self::assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testBuiltArchiveAndBacklinksUseTheGeneratedCatalog(): void
    {
        $build = __DIR__ . '/../' . (getenv('SITE_BUILD_DIR') ?: 'build_production');
        foreach (['media', 'pt-BR/acervo'] as $path) {
            $html = file_get_contents($build . '/' . $path . '/index.html');
            self::assertIsString($html);
            self::assertStringStartsWith('<!doctype html>', ltrim($html));
            self::assertMatchesRegularExpression('/<header class="site-header"[^>]*>.*?<\/header>\s*<main id="main-content">\s*<div class="media-archive"[^>]*data-media-catalog/s', $html);
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($html, LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($document);
            foreach ($xpath->query('//article[@data-record]') as $card) {
                self::assertContains($card->getAttribute('data-type'), ['photo', 'video', 'audio']);
                if ($card->getAttribute('data-type') === 'video' && $card->getAttribute('data-youtube') !== '') {
                    $id = $card->getAttribute('data-youtube');
                    self::assertSame(1, $xpath->query("./a//img[@src='https://i.ytimg.com/vi/" . $id . "/hqdefault.jpg'][@loading='lazy']", $card)->length);
                    self::assertSame(1, $xpath->query('./template//iframe[@allowfullscreen]', $card)->length);
                }
            }
            self::assertSame(1, $xpath->query('//article[@data-youtube="43hFB-Jw2TE"]')->length);
            self::assertSame(1, $xpath->query('//article[@data-youtube="h2UF0h70NTA"]')->length);
            self::assertSame(1, $xpath->query('//article[@id="latinoware-bate-papo-nerd-2026"]//template//audio[@controls]')->length);
            self::assertSame(0, $xpath->query('//iframe[not(ancestor::template)] | //audio[not(ancestor::template)]')->length);
            self::assertStringNotContainsString('artecult-interview-2022', $html);
            self::assertStringNotContainsString('hemingway.softwarelivre.org', $html);
            self::assertStringNotContainsString('data-type-filter="presentation"', $html);
            self::assertStringContainsString('data-fullscreen', $html);
        }
        $talk = file_get_contents($build . '/talks/cloud-privacity/index.html');
        self::assertIsString($talk);
        self::assertStringContainsString('/media#phprio-cloud-privacy-2022', $talk);
        $article = file_get_contents($build . '/articles/communion-of-saints-digital-ethics/index.html');
        self::assertIsString($article);
        self::assertStringContainsString('/media#phprio-cloud-privacy-2022', $article);
        self::assertFileExists($build . '/media-assets/south-america.geojson');
    }

    public function testHistoricalRecordingReferencesAreRetained(): void
    {
        $metadata = require __DIR__ . '/../data/talks.php';
        $resources = $metadata['que-as-oportunidades-me-encontrem']['resources'];
        self::assertStringContainsString('eventials.com', $resources[0]['href']);
        self::assertSame('unavailable', $resources[0]['availability']);
        self::assertStringContainsString('hemingway.softwarelivre.org', $resources[1]['href']);
        self::assertSame('unavailable', $metadata['contribuindo-e-criando-software-livre']['resources'][0]['availability']);
        self::assertSame('https://youtu.be/WJpe_NnmW8o', $metadata['libresign']['resources'][0]['href']);
    }
}
