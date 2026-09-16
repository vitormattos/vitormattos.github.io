<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\TalkMetadata;
use PHPUnit\Framework\TestCase;

final class TalkMetadataTest extends TestCase
{
    public function testItResolvesStableTalkSlug(): void
    {
        $metadata = [
            'libresign' => [
                'aliases' => ['https://slides.com/vitormattos/libresign'],
            ],
        ];

        self::assertSame($metadata['libresign'], TalkMetadata::resolve($metadata, '', 'libresign'));
    }

    public function testItResolvesPresentationResourceAsAlias(): void
    {
        $metadata = [
            'academic-talk' => [
                'resources' => [
                    ['type' => 'pdf', 'label' => 'Slides (PDF)', 'href' => '/presentations/academic-talk/slides.pdf'],
                ],
            ],
        ];

        self::assertSame(
            $metadata['academic-talk'],
            TalkMetadata::resolve($metadata, '/presentations/academic-talk/slides.pdf', 'generated-slug'),
        );
    }

    public function testItResolvesAlternateSlidesUrlIgnoringFragment(): void
    {
        $metadata = [
            'senhas' => [
                'resources' => [
                    ['type' => 'slides', 'label' => 'Webinar slides', 'href' => 'https://slides.com/vitormattos/webinar-senhas#/'],
                ],
            ],
        ];

        self::assertSame(
            $metadata['senhas'],
            TalkMetadata::resolve($metadata, 'https://slides.com/vitormattos/webinar-senhas#/', 'generated-slug'),
        );
    }

    public function testRepositoryMetadataIsValid(): void
    {
        $metadata = require dirname(__DIR__, 2) . '/data/talks.php';

        self::assertSame([], TalkMetadata::validationErrors($metadata));
    }

    public function testItAcceptsAcademicAppearanceAndLocalPdf(): void
    {
        $metadata = [
            'empirical-software-engineering' => [
                'resources' => [
                    ['type' => 'pdf', 'label' => 'Slides (PDF)', 'href' => '/presentations/empirical-software-engineering/slides.pdf'],
                    ['type' => 'source', 'label' => 'LaTeX source', 'href' => 'https://github.com/example/slides'],
                ],
                'appearances' => [
                    [
                        'event' => 'Academic seminar',
                        'date' => '2026-09-17',
                        'venue' => 'UTFPR',
                        'city' => 'Curitiba',
                        'country' => 'BR',
                        'mode' => 'hybrid',
                    ],
                ],
            ],
        ];

        self::assertSame([], TalkMetadata::validationErrors($metadata));
        self::assertSame(
            '/pr-preview/pr-21/presentations/empirical-software-engineering/slides.pdf',
            TalkMetadata::publicHref('/presentations/empirical-software-engineering/slides.pdf', '/pr-preview/pr-21'),
        );
    }

    public function testItReportsInvalidMetadata(): void
    {
        $metadata = [
            'Bad Slug' => [
                'resources' => [
                    ['type' => '', 'label' => '', 'href' => 'invalid'],
                ],
                'appearances' => [
                    ['event' => '', 'date' => '2022-02-31', 'country' => 'Brazil', 'mode' => 'physical'],
                ],
            ],
        ];

        $errors = TalkMetadata::validationErrors($metadata);

        self::assertNotSame([], $errors);
        self::assertTrue((bool) array_filter($errors, static fn(string $error): bool => str_contains($error, 'normalized slug')));
        self::assertTrue((bool) array_filter($errors, static fn(string $error): bool => str_contains($error, 'real calendar date')));
        self::assertTrue((bool) array_filter($errors, static fn(string $error): bool => str_contains($error, 'ISO 3166-1')));
    }
}
