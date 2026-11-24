<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Media;

use App\Presentations\TalkMetadata;
use App\Presentations\TalkTopics;
use InvalidArgumentException;

final class MediaCatalog
{
    private const TYPES = [
        'photo' => ['en' => 'Photographs', 'pt-BR' => 'Fotografias'],
        'video' => ['en' => 'Videos', 'pt-BR' => 'Vídeos'],
        'audio' => ['en' => 'Audio', 'pt-BR' => 'Áudios'],
    ];

    private const ROLES = [
        'speaker' => ['en' => 'Speaker', 'pt-BR' => 'Palestrante'],
        'interviewee' => ['en' => 'Interviewee', 'pt-BR' => 'Entrevistado'],
        'participant' => ['en' => 'Participant', 'pt-BR' => 'Participante'],
        'organizer' => ['en' => 'Organizer', 'pt-BR' => 'Organizador'],
    ];

    public static function fromDirectory(string $root, array $metadata, iterable ...$collections): array
    {
        $standalone = [];
        foreach (glob($root . '/data/media/*.json') ?: [] as $path) {
            $record = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($record)) {
                throw new InvalidArgumentException('Invalid media sidecar: ' . basename($path));
            }
            $record['id'] = pathinfo($path, PATHINFO_FILENAME);
            foreach (['image', 'thumbnail'] as $field) {
                if (isset($record[$field]) && is_string($record[$field]) && !is_file($root . '/source' . $record[$field])) {
                    throw new InvalidArgumentException('Missing published media asset: ' . $record['id']);
                }
            }
            $standalone[] = $record;
        }
        $cities = json_decode((string) file_get_contents($root . '/data/media-cities.json'), true, flags: JSON_THROW_ON_ERROR);

        return self::build($metadata, $standalone, $cities, ...$collections);
    }

    public static function build(array $metadata, array $standalone, array $cities, iterable ...$collections): array
    {
        $talks = [];
        $nativeVideos = [];
        $topicLabels = [];
        foreach ($collections as $collection) {
            foreach ($collection as $talk) {
                $slug = (string) ($talk->slug ?? '');
                if ($slug === '' || ($talk->indexable ?? true) === false) {
                    continue;
                }
                $presentation = (array) ($talk->presentation ?? []);
                $url = (string) ($presentation['url'] ?? '');
                $canonical = self::canonicalSlug($metadata, $slug, $url);
                $locale = ($talk->locale ?? 'en') === 'pt-BR' ? 'pt-BR' : 'en';
                $talks[$canonical]['title'][$locale] = trim((string) ($talk->title ?? $slug));
                $talks[$canonical]['description'][$locale] = trim((string) ($talk->description ?? ''));
                $talks[$canonical]['slugs'][] = $slug;
                $topics = TalkTopics::resolve($talk);
                $talks[$canonical]['topics'] = array_values(array_unique([
                    ...($talks[$canonical]['topics'] ?? []), ...array_keys($topics),
                ]));
                $topicLabels += $topics;
                if (!empty($presentation['video'])) {
                    $nativeVideos[] = [$canonical, $slug, (string) $presentation['video']];
                }
            }
        }

        // Establish exclusions before inspecting native metadata, which may repeat
        // the same unavailable recording in another locale or presentation.
        $blocked = [];
        foreach ($metadata as $definition) {
            foreach (self::resources($definition) as [$resource, $appearance]) {
                if (($resource['type'] ?? '') === 'video' && in_array($resource['availability'] ?? '', ['unavailable', 'unverified'], true)) {
                    $blocked[self::identity((string) $resource['href'])] = true;
                }
            }
        }

        $items = [];
        foreach ($metadata as $slug => $definition) {
            if (!isset($talks[$slug])) {
                continue;
            }
            foreach (self::resources($definition) as [$resource, $appearance]) {
                if (($resource['type'] ?? '') !== 'video') {
                    continue;
                }
                $url = (string) ($resource['href'] ?? '');
                $key = self::identity($url);
                if (isset($blocked[$key]) || !self::isPlayableVideo($url)) {
                    continue;
                }
                $record = self::record($slug, $talks[$slug], $resource, $appearance, $definition);
                self::merge($items, $key, $record);
            }
        }
        foreach ($nativeVideos as [$canonical, $slug, $url]) {
            $key = self::identity($url);
            if (isset($blocked[$key]) || !self::isPlayableVideo($url)) {
                continue;
            }
            $record = self::record($canonical, $talks[$canonical], ['href' => $url], [], $metadata[$canonical] ?? []);
            $record['talks'] = array_values(array_unique([...$record['talks'], $slug]));
            self::merge($items, $key, $record);
        }

        foreach ($standalone as $record) {
            self::validateStandalone($record);
            $record['year'] ??= null;
            $key = self::identity($record['url']);
            if (isset($blocked[$key])) {
                continue;
            }
            if ($record['type'] === 'video' && ($id = self::youtubeId($record['url'])) !== null) {
                $record['youtube'] = $id;
                $record['url'] = 'https://www.youtube.com/watch?v=' . $id;
            }
            self::merge($items, $key, $record);
        }

        $usedTopics = [];
        $ids = [];
        foreach ($items as $record) {
            if (isset($ids[$record['id']])) {
                throw new InvalidArgumentException('Duplicate media id: ' . $record['id']);
            }
            $ids[$record['id']] = true;
            foreach ($record['topics'] as $topic) {
                $usedTopics[$topic] = $topicLabels[$topic] ?? $topic;
            }
        }
        ksort($usedTopics, SORT_NATURAL | SORT_FLAG_CASE);
        $topics = [];
        foreach (['en', 'pt-BR'] as $locale) {
            foreach (TalkTopics::localized($usedTopics, $locale) as $key => $label) {
                $topics[$key][$locale] = $label;
            }
        }
        $items = array_values($items);
        usort($items, static fn(array $a, array $b): int => ($b['year'] ?? 0) <=> ($a['year'] ?? 0) ?: strcmp($a['id'], $b['id']));

        return ['types' => self::TYPES, 'roles' => self::ROLES, 'topics' => $topics, 'cities' => $cities, 'items' => $items];
    }

    public static function youtubeId(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $id = null;
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:embed|shorts|live)/([^/]+)/?$~', $path, $match)) {
                $id = $match[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) ? $id : null;
    }

    private static function isPlayableVideo(string $url): bool
    {
        return self::youtubeId($url) !== null || (self::isPublicHref($url)
            && preg_match('/\.(?:mp4|webm|ogv)$/iD', (string) parse_url($url, PHP_URL_PATH)));
    }

    private static function isPublicHref(string $url): bool
    {
        if (preg_match('~^/(?:media-assets|presentations)/[A-Za-z0-9/_-]+\.[A-Za-z0-9]+$~D', $url)) {
            return true;
        }
        $parts = parse_url($url);

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass'])
            && !in_array(strtolower($parts['host']), ['cloud.colab.rio', 'cloud.librecode.coop', 'localhost'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private static function canonicalSlug(array $metadata, string $slug, string $url): string
    {
        $resolved = TalkMetadata::resolve($metadata, $url, $slug);
        if (isset($metadata[$slug])) {
            return $slug;
        }
        if ($resolved !== []) {
            foreach ($metadata as $key => $definition) {
                if ($definition === $resolved) {
                    return (string) $key;
                }
            }
        }

        return $slug;
    }

    private static function resources(array $definition): iterable
    {
        foreach ($definition['resources'] ?? [] as $resource) {
            yield [$resource, []];
        }
        foreach ($definition['appearances'] ?? [] as $appearance) {
            foreach ($appearance['resources'] ?? [] as $resource) {
                yield [$resource, $appearance];
            }
        }
    }

    private static function identity(string $url): string
    {
        $id = self::youtubeId($url);

        return $id === null ? rtrim($url, '/') : 'youtube:' . $id;
    }

    private static function localized(array $values, string $fallback): array
    {
        $value = $values['pt-BR'] ?? $values['en'] ?? $fallback;

        return ['en' => ($values['en'] ?? '') ?: $value, 'pt-BR' => ($values['pt-BR'] ?? '') ?: $value];
    }

    private static function record(string $slug, array $talk, array $resource, array $appearance, array $definition): array
    {
        $href = (string) $resource['href'];
        $id = self::youtubeId($href);
        $date = (string) ($resource['date'] ?? $appearance['date'] ?? '');
        $title = self::localized($talk['title'], $slug);
        $record = [
            'id' => $resource['archiveId'] ?? 'video-' . substr(hash('sha256', $id ?? $href), 0, 16),
            'type' => 'video',
            'year' => preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) ? (int) substr($date, 0, 4) : null,
            'event' => $resource['event'] ?? $appearance['event'] ?? $title['pt-BR'],
            'title' => $title,
            'description' => self::localized($talk['description'], ''),
            'topics' => $talk['topics'],
            'url' => $id !== null ? 'https://www.youtube.com/watch?v=' . $id : $href,
            'source' => $resource['source'] ?? ($id !== null ? 'YouTube' : (parse_url($href, PHP_URL_HOST) ?: 'Archive')),
            'talks' => array_values(array_unique([$slug, ...$talk['slugs']])),
            'role' => 'speaker',
            'articles' => $resource['articles'] ?? [],
        ];
        if ($id !== null) {
            $record['youtube'] = $id;
        } else {
            $record['video'] = $href;
        }
        $city = $resource['city'] ?? $appearance['city'] ?? null;
        if (is_string($city) && $city !== '') {
            $record['city'] = $city;
        }
        $reference = $resource['reference'] ?? $appearance['href'] ?? $definition['sources'][0]['href'] ?? null;
        if ($reference) {
            $record['reference'] = $reference;
        }

        return $record;
    }

    private static function merge(array &$items, string $key, array $record): void
    {
        if (!isset($items[$key])) {
            $items[$key] = $record;
            return;
        }
        foreach (['talks', 'articles', 'topics'] as $field) {
            $items[$key][$field] = array_values(array_unique([
                ...($items[$key][$field] ?? []), ...($record[$field] ?? []),
            ]));
        }
    }

    private static function validateStandalone(array $record): void
    {
        $allowed = ['id', 'type', 'year', 'event', 'city', 'title', 'description', 'topics', 'url', 'source', 'talks', 'articles', 'role', 'image', 'thumbnail', 'width', 'height', 'credit', 'license', 'rights', 'audio', 'video', 'reference'];
        if (array_diff(array_keys($record), $allowed) || !isset(self::TYPES[$record['type'] ?? '']) || !isset(self::ROLES[$record['role'] ?? ''])) {
            throw new InvalidArgumentException('Invalid or unsupported media fields: ' . ($record['id'] ?? 'unknown'));
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', (string) ($record['id'] ?? '')) || empty($record['source']) || empty($record['event']) || !is_array($record['topics'] ?? null)) {
            throw new InvalidArgumentException('Incomplete media sidecar: ' . ($record['id'] ?? 'unknown'));
        }
        foreach (['title', 'description'] as $field) {
            foreach (['en', 'pt-BR'] as $locale) {
                if (!is_string($record[$field][$locale] ?? null)) {
                    throw new InvalidArgumentException('Missing localized media metadata: ' . $field);
                }
            }
        }
        foreach (['url', 'audio', 'video', 'reference', 'license'] as $field) {
            if (!isset($record[$field])) {
                continue;
            }
            if (!is_string($record[$field]) || !self::isPublicHref($record[$field])) {
                throw new InvalidArgumentException('Media URLs must reference public HTTPS sources.');
            }
        }
        if (isset($record['year']) && (!is_int($record['year']) || $record['year'] < 1900 || $record['year'] > 2100)) {
            throw new InvalidArgumentException('Invalid media year.');
        }
        $recordTopics = $record['topics'];
        if (array_filter($recordTopics, static fn($topic) => !is_string($topic) || trim($topic) === '')) {
            throw new InvalidArgumentException('Media topics must be nonempty strings.');
        }
        if (empty($record['url'])) {
            throw new InvalidArgumentException('Media source URL is required.');
        }
        if ($record['type'] === 'photo') {
            foreach (['image', 'thumbnail'] as $field) {
                if (!preg_match('~^/media-assets/[A-Za-z0-9/_-]+\.(?:webp|avif|jpe?g|png)$~iD', (string) ($record[$field] ?? ''))) {
                    throw new InvalidArgumentException('Photos need a public optimized image and thumbnail.');
                }
            }
            if (empty($record['credit']) || empty($record['license']) || ($record['width'] ?? 0) <= 0 || ($record['height'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Photos need credit, license and dimensions.');
            }
        } elseif ($record['type'] === 'audio' && empty($record['audio'])) {
            throw new InvalidArgumentException('Audio needs a playable source.');
        } elseif ($record['type'] === 'video' && self::youtubeId($record['url']) === null && empty($record['video'])) {
            throw new InvalidArgumentException('Video needs a supported player.');
        }
    }
}
