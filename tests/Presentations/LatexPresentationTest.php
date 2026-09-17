<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Presentations;

use App\Presentations\LatexPresentation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LatexPresentationTest extends TestCase
{
    public function testRepositoryPresentationMetadataIsValid(): void
    {
        $presentation = LatexPresentation::fromMetadataFile(
            dirname(__DIR__, 2) . '/presentations/latex/open-source-ecosystem-review/metadata.json',
        );

        self::assertSame('open-source-ecosystem-review', $presentation->slug());
        self::assertSame('latex-open-source-ecosystem-review', $presentation->releaseTag());
        self::assertFileExists($presentation->sourcePath());
        self::assertSame(16, $presentation->metadata['slide_count']);
        self::assertSame('pt-BR', $presentation->metadata['language']);
    }

    public function testAssetNamesAreContentAddressed(): void
    {
        $presentation = new LatexPresentation('/tmp/example', [
            'slug' => 'example',
            'title' => 'Example',
            'description' => 'Description',
            'language' => 'pt-BR',
            'date' => '2026-09-17',
            'source' => 'main.tex',
            'slide_count' => 10,
        ]);

        self::assertSame('example-abcdef123456.pdf', $presentation->assetName('pdf', 'abcdef1234567890'));
        self::assertSame('example-abcdef123456.tex', $presentation->assetName('source', 'abcdef1234567890'));
        self::assertSame('thumbnail-abcdef123456.png', $presentation->assetName('thumbnail', 'abcdef1234567890'));
    }

    public function testReleaseBodyContainsPortfolioSourceAndReviewedWork(): void
    {
        $presentation = new LatexPresentation('/tmp/example', [
            'slug' => 'example',
            'title' => 'Example',
            'description' => 'Academic presentation.',
            'language' => 'pt-BR',
            'date' => '2026-09-17',
            'source' => 'main.tex',
            'slide_count' => 10,
            'reviewed_work' => [
                'title' => 'Reviewed paper',
                'authors' => ['A. Author', 'B. Author'],
                'year' => 2012,
                'doi' => '10.1000/example',
            ],
        ]);

        $body = $presentation->releaseBody('owner/repo', [
            'thumbnail_url' => 'https://example.test/thumb.png',
            'pdf_url' => 'https://example.test/deck.pdf',
            'source_url' => 'https://example.test/main.tex',
            'metadata_url' => 'https://example.test/metadata.json',
            'source_sha' => str_repeat('a', 64),
            'pdf_sha' => str_repeat('b', 64),
        ]);

        self::assertStringContainsString('https://vitormattos.github.io/pt-BR/palestras/example', $body);
        self::assertStringContainsString('Fonte LaTeX desta versão', $body);
        self::assertStringContainsString('Reviewed paper', $body);
        self::assertStringContainsString('https://doi.org/10.1000/example', $body);
    }

    public function testInvalidMetadataFailsFast(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'latex-meta-');
        self::assertNotFalse($path);
        file_put_contents($path, '{"slug":"example"}');

        try {
            $this->expectException(RuntimeException::class);
            LatexPresentation::fromMetadataFile($path);
        } finally {
            @unlink($path);
        }
    }
}
