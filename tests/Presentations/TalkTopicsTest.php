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

    public function testTaxonomyCountsEachTalkOncePerNormalizedTopic(): void
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
        self::assertSame(1, $catalog['topics']['testing']['count']);
    }
}
