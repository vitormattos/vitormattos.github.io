<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\TalkHistory;
use PHPUnit\Framework\TestCase;

final class TalkHistoryTest extends TestCase
{
    public function testItResolvesStableTalkSlug(): void
    {
        $history = [
            'libresign' => [
                'aliases' => ['https://slides.com/vitormattos/libresign'],
                'sources' => [
                    ['type' => 'cfp', 'label' => 'PHPRio CFP', 'url' => 'https://github.com/PHPRio/CFP/issues/131'],
                ],
            ],
        ];

        self::assertSame(
            $history['libresign'],
            TalkHistory::resolve($history, '', 'libresign'),
        );
    }

    public function testItResolvesExactProviderAlias(): void
    {
        $history = [
            'libresign' => [
                'aliases' => ['https://slides.com/vitormattos/libresign'],
            ],
        ];

        self::assertSame(
            $history['libresign'],
            TalkHistory::resolve($history, 'https://slides.com/vitormattos/libresign/', 'generated-provider-slug'),
        );
    }

    public function testItNormalizesUnderscoreAliasesWhenMatchingTalkSlug(): void
    {
        $history = [
            'celular-floss' => [
                'aliases' => ['https://slides.com/vitormattos/celular_floss'],
            ],
        ];

        self::assertSame(
            $history['celular-floss'],
            TalkHistory::resolve($history, '', 'celular-floss'),
        );
    }

    public function testUnknownTalkHasNoCuratedHistory(): void
    {
        self::assertSame([], TalkHistory::resolve([], 'https://example.test/talk', 'talk'));
    }
}
