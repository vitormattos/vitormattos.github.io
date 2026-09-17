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

    public function testEveryArchivedPresentationHasAnEditorialTagDecision(): void
    {
        $root = dirname(__DIR__, 2);
        $overrides = require $root . '/data/presentation-overrides.php';

        foreach (['slides.com', 'slideshare'] as $source) {
            $metadataFiles = glob($root . '/presentations/' . $source . '/*/metadata.json') ?: [];
            self::assertNotEmpty($metadataFiles, 'Expected archived presentations for ' . $source);

            foreach ($metadataFiles as $metadataFile) {
                $id = basename(dirname($metadataFile));
                self::assertArrayHasKey($id, $overrides[$source] ?? [], $source . '/' . $id . ' has no curation');

                $tags = $overrides[$source][$id]['tags'] ?? null;
                self::assertIsArray($tags, $source . '/' . $id . ' must define tags');
                self::assertGreaterThanOrEqual(2, count($tags), $source . '/' . $id . ' has too few tags');
                self::assertLessThanOrEqual(5, count($tags), $source . '/' . $id . ' has too many tags');
            }
        }
    }
}
