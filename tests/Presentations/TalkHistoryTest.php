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

    public function testItResolvesAlternateSlidesLink(): void
    {
        $history = [
            'senhas' => [
                'aliases' => ['https://slides.com/vitormattos/senhas'],
                'links' => [
                    ['type' => 'slides', 'label' => 'Webinar slides', 'url' => 'https://slides.com/vitormattos/webinar-senhas#/'],
                ],
            ],
        ];

        self::assertSame(
            $history['senhas'],
            TalkHistory::resolve($history, 'https://slides.com/vitormattos/webinar-senhas#/', 'generated-provider-slug'),
        );
    }

    public function testItValidatesCuratedMetadata(): void
    {
        $history = [
            'cloud-privacity' => [
                'aliases' => ['https://slides.com/vitormattos/cloud-privacity'],
                'sources' => [
                    ['type' => 'cfp', 'label' => 'PHPRio CFP', 'url' => 'https://github.com/PHPRio/CFP/issues/146'],
                ],
                'appearances' => [
                    [
                        'event' => 'PHPRio',
                        'date' => '2022-08-03',
                        'url' => 'https://www.meetup.com/pt-BR/php-rio/events/287427112/',
                        'links' => [
                            ['type' => 'video', 'label' => 'Recording', 'url' => 'https://www.youtube.com/watch?v=h2UF0h70NTA'],
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame([], TalkHistory::validationErrors($history));
    }

    public function testItReportsInvalidMetadataAndDuplicateAliases(): void
    {
        $history = [
            'Bad Slug' => [
                'aliases' => ['not-a-url'],
                'links' => [
                    ['type' => '', 'label' => '', 'url' => 'invalid'],
                ],
                'appearances' => [
                    ['event' => '', 'date' => '2022-02-31', 'url' => 'invalid'],
                ],
            ],
            'second-talk' => [
                'aliases' => ['https://slides.com/vitormattos/talk'],
            ],
            'third-talk' => [
                'aliases' => ['https://slides.com/vitormattos/talk/'],
            ],
        ];

        $errors = TalkHistory::validationErrors($history);

        self::assertNotSame([], $errors);
        self::assertTrue((bool) array_filter($errors, static fn (string $error): bool => str_contains($error, 'normalized slug')));
        self::assertTrue((bool) array_filter($errors, static fn (string $error): bool => str_contains($error, 'duplicates a presentation URL')));
        self::assertTrue((bool) array_filter($errors, static fn (string $error): bool => str_contains($error, 'real calendar date')));
    }

    public function testUnknownTalkHasNoCuratedHistory(): void
    {
        self::assertSame([], TalkHistory::resolve([], 'https://example.test/talk', 'talk'));
    }
}
