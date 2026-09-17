<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\TalkTopics;
use PHPUnit\Framework\TestCase;

final class TalkTopicsLocalizationTest extends TestCase
{
    public function testLocalizesLabelsWithoutChangingCanonicalKeys(): void
    {
        $topics = TalkTopics::resolve((object) ['tags' => ['Software Livre', 'Privacidade', 'PHP']]);

        self::assertSame(
            ['Free Software', 'Privacy', 'PHP'],
            array_values(TalkTopics::localized($topics, 'en')),
        );
        self::assertSame(array_keys($topics), array_keys(TalkTopics::localized($topics, 'en')));
    }

    public function testPortugueseLabelsRemainPortuguese(): void
    {
        $topics = TalkTopics::resolve((object) ['tags' => ['Assinatura Eletrônica', 'Segurança']]);
        $localized = TalkTopics::localized($topics, 'pt-BR');

        self::assertSame('Assinatura Eletrônica', $localized['assinatura eletrônica']);
        self::assertSame('Segurança', $localized['segurança']);
    }

    public function testUnknownTopicsFallBackToOriginalLabel(): void
    {
        self::assertSame(
            ['custom-topic' => 'Custom Topic'],
            TalkTopics::localized(['custom-topic' => 'Custom Topic'], 'en'),
        );
    }

    public function testEnglishTaxonomyUsesLocalizedLabels(): void
    {
        $catalog = TalkTopics::taxonomy([
            (object) ['tags' => ['Software Livre']],
            (object) ['tags' => ['Software Livre']],
        ], 'en');

        self::assertSame('Free Software', $catalog['topics']['software livre']['label']);
        self::assertSame(2, $catalog['topics']['software livre']['count']);
    }
}
