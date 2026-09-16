<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\TalkHistory;
use PHPUnit\Framework\TestCase;

final class TalkHistoryTest extends TestCase
{
    public function testItResolvesExactPresentationUrl(): void
    {
        $history = [
            'https://slides.com/vitormattos/libresign' => ['cfp' => 'https://github.com/PHPRio/CFP/issues/131'],
        ];

        self::assertSame(
            $history['https://slides.com/vitormattos/libresign'],
            TalkHistory::resolve($history, 'https://slides.com/vitormattos/libresign/', 'libresign'),
        );
    }

    public function testItFallsBackToNormalizedSlugAcrossPresentationSources(): void
    {
        $history = [
            'https://slides.com/vitormattos/celular_floss' => ['cfp' => 'https://github.com/PHPRio/CFP/issues/126'],
        ];

        self::assertSame(
            $history['https://slides.com/vitormattos/celular_floss'],
            TalkHistory::resolve(
                $history,
                'https://pt.slideshare.net/slideshow/tenha-um-celular-100-floss/123456',
                'celular-floss',
            ),
        );
    }

    public function testUnknownTalkHasNoCuratedHistory(): void
    {
        self::assertSame([], TalkHistory::resolve([], 'https://example.test/talk', 'talk'));
    }
}
