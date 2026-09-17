<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\TalkTopics;
use PHPUnit\Framework\TestCase;

final class TalkTopicsTest extends TestCase
{
    public function testResolvesTopicsFromFrontMatterAndPresentationMetadata(): void
    {
        $talk = (object) [
            'tags' => ['PHP', 'php', 'custom topic'],
            'presentation' => [
                'metadata' => '/presentations/slideshare/64103619/metadata.json',
            ],
        ];

        $topics = TalkTopics::resolve($talk);

        self::assertArrayHasKey('php', $topics);
        self::assertSame('PHP', $topics['php']);
        self::assertArrayHasKey('custom topic', $topics);
        self::assertArrayHasKey('bdd', $topics);
        self::assertArrayHasKey('tdd', $topics);
        self::assertArrayHasKey('software testing', $topics);
    }

    public function testTaxonomyCountsEachTalkOncePerNormalizedTopicAndExposesOnlyRecurringTopics(): void
    {
        $talks = [
            (object) ['tags' => ['PHP', 'php']],
            (object) ['tags' => ['php']],
            (object) ['tags' => ['testing']],
        ];

        $catalog = TalkTopics::taxonomy($talks);

        self::assertCount(3, $catalog['items']);
        self::assertSame(2, $catalog['topics']['php']['count']);
        self::assertSame('PHP', $catalog['topics']['php']['label']);
        self::assertArrayNotHasKey('testing', $catalog['topics']);
        self::assertSame(['testing' => 'testing'], TalkTopics::resolve($talks[2]));
    }

    public function testMergeCatalogIncludesFallbackLocaleAndPrefersCurrentLocale(): void
    {
        $preferred = [
            (object) [
                'title' => 'English variant',
                'date' => 100,
                'updated' => 300,
                'presentation' => ['metadata' => '/presentations/example/1/metadata.json'],
            ],
        ];
        $fallback = [
            (object) [
                'title' => 'Portuguese variant',
                'date' => 100,
                'updated' => 300,
                'presentation' => ['metadata' => '/presentations/example/1/metadata.json'],
            ],
            (object) [
                'title' => 'SlideShare only',
                'date' => 200,
                'updated' => 200,
                'presentation' => ['metadata' => '/presentations/slideshare/2/metadata.json'],
            ],
        ];

        $merged = TalkTopics::mergeCatalog($preferred, $fallback);

        self::assertCount(2, $merged);
        self::assertSame('English variant', $merged[0]->title);
        self::assertSame('SlideShare only', $merged[1]->title);
    }

    public function testActivityTimestampUsesMostRecentRecordedPresentationDate(): void
    {
        $updatedAfterPublication = (object) [
            'date' => '2020-04-23',
            'updated' => '2023-08-10',
        ];
        $publicationOnly = (object) [
            'date' => '2024-03-26',
        ];

        self::assertSame(strtotime('2023-08-10'), TalkTopics::activityTimestamp($updatedAfterPublication));
        self::assertSame(strtotime('2024-03-26'), TalkTopics::activityTimestamp($publicationOnly));
    }
}
