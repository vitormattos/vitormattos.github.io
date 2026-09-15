<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\PresentationReleaseMetadata;
use PHPUnit\Framework\TestCase;

final class SlideShareArchiveTest extends TestCase
{
    public function testImportedMetadataUsesExplicitPrivacyAllowlist(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/presentations/slideshare/*/metadata.json') ?: [];
        self::assertCount(16, $files);

        $allowed = [
            'schema', 'source', 'id', 'slug', 'title', 'description', 'language', 'visibility',
            'source_url', 'source_download_url', 'published_at', 'tags', 'statistics',
        ];
        $forbidden = [
            'email', 'first_name', 'last_name', 'occupation', 'organization', 'city', 'region',
            'country', 'facebook', 'linkedin', 'twitter', 'following_users', 'contact_details',
            'account_registration',
        ];

        foreach ($files as $file) {
            $metadata = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('slideshare', $metadata['source'] ?? null, $file);
            self::assertSame('public', $metadata['visibility'] ?? null, $file);
            self::assertNotEmpty($metadata['published_at'] ?? null, $file);
            self::assertSame([], array_values(array_diff(array_keys($metadata), $allowed)), $file);

            $serialized = json_encode($metadata, JSON_THROW_ON_ERROR);
            foreach ($forbidden as $field) {
                self::assertStringNotContainsString('"' . $field . '"', $serialized, $file);
            }
        }
    }

    public function testReleaseMetadataSupportsSlideShareAssetsAndHistoricalDate(): void
    {
        $metadata = [
            'source' => 'slideshare',
            'id' => '10406513',
            'slug' => 'jasperreports',
            'title' => 'JasperReports',
            'description' => 'Geração de relatórios no PHP.',
            'language' => 'pt',
            'source_url' => 'https://pt.slideshare.net/slideshow/jasperreports/10406513',
            'published_at' => '2011-11-30T17:55:49Z',
            'tags' => ['PHP', 'JasperReports'],
            'width' => 1024,
            'height' => 768,
        ];

        $body = PresentationReleaseMetadata::body(
            $metadata,
            'vitormattos/vitormattos.github.io',
            'https://example.invalid/thumbnail.jpg',
            'https://example.invalid/archive.pdf',
            'https://example.invalid/original.ppt',
        );

        self::assertStringContainsString('<img src="https://example.invalid/thumbnail.jpg"', $body);
        self::assertStringContainsString('width="1024" height="768"', $body);
        self::assertStringContainsString('**Published:** 2011-11-30', $body);
        self::assertStringContainsString('**SlideShare:** https://pt.slideshare.net/slideshow/jasperreports/10406513', $body);
        self::assertStringContainsString('**Archived PDF:** https://example.invalid/archive.pdf', $body);
        self::assertStringContainsString('**Original archived file:** https://example.invalid/original.ppt', $body);
    }
}
