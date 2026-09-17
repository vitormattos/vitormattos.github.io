<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\PresentationMetadataOverrides;
use PHPUnit\Framework\TestCase;

final class PresentationMetadataOverridesTest extends TestCase
{
    public function testFallsBackToSourceTagsWhenThereIsNoCuratedEntry(): void
    {
        $tags = PresentationMetadataOverrides::tags([], 'slides.com', 123, ['PHP', 'php', ' Composer ']);

        self::assertSame(['PHP', 'Composer'], $tags);
    }

    public function testCuratedTagsReplaceSourceTagsInsteadOfBeingMerged(): void
    {
        $overrides = [
            'slides.com' => [
                '123' => ['tags' => ['Composer', 'Dependency Management']],
            ],
        ];

        $tags = PresentationMetadataOverrides::tags($overrides, 'slides.com', 123, ['opensource', 'php']);

        self::assertSame(['Composer', 'Dependency Management'], $tags);
        self::assertNotContains('opensource', $tags);
        self::assertNotContains('php', $tags);
    }

    public function testEmptyCuratedTagListCanIntentionallyClearSourceTags(): void
    {
        $overrides = ['slideshare' => ['42' => ['tags' => []]]];

        self::assertSame([], PresentationMetadataOverrides::tags($overrides, 'slideshare', 42, ['legacy']));
    }

    public function testApplyKeepsUnrelatedSourceMetadataAndReplacesTags(): void
    {
        $source = ['title' => 'Deck', 'tags' => ['legacy'], 'language' => 'pt'];
        $curated = ['tags' => ['PHP', ' php ', 'Composer']];

        self::assertSame(
            ['title' => 'Deck', 'tags' => ['PHP', 'Composer'], 'language' => 'pt'],
            PresentationMetadataOverrides::apply($source, $curated),
        );
    }
}
