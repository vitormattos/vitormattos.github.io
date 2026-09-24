<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Presentations;

use JsonException;
use RuntimeException;

final class LatexPresentation
{
    public function __construct(
        public readonly string $directory,
        public readonly array $metadata,
    ) {}

    public static function fromMetadataFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("LaTeX presentation metadata not found: {$path}");
        }

        try {
            $metadata = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid LaTeX presentation metadata: {$path}", 0, $exception);
        }

        foreach (['slug', 'title', 'description', 'language', 'date', 'source', 'slide_count'] as $required) {
            if (!isset($metadata[$required]) || trim((string) $metadata[$required]) === '') {
                throw new RuntimeException("Missing required LaTeX presentation metadata: {$required}");
            }
        }

        return new self(dirname($path), $metadata);
    }

    public function slug(): string
    {
        return (string) $this->metadata['slug'];
    }

    public function sourcePath(): string
    {
        return $this->directory . '/' . (string) $this->metadata['source'];
    }

    public function generatedDirectory(string $sourceRoot = 'source/presentations/latex'): string
    {
        return rtrim($sourceRoot, '/') . '/' . $this->slug();
    }

    public function pdfPath(string $sourceRoot = 'source/presentations/latex'): string
    {
        return $this->generatedDirectory($sourceRoot) . '/' . $this->slug() . '.pdf';
    }

    public function thumbnailPath(string $sourceRoot = 'source/presentations/latex'): string
    {
        return $this->generatedDirectory($sourceRoot) . '/thumbnail.png';
    }

    public function generatedMetadataPath(string $sourceRoot = 'source/presentations/latex'): string
    {
        return $this->generatedDirectory($sourceRoot) . '/metadata.json';
    }

    public function releaseTag(): string
    {
        return 'latex-' . $this->slug();
    }

    public function assetName(string $kind, string $hash): string
    {
        $short = substr($hash, 0, 12);

        return match ($kind) {
            'pdf' => $this->slug() . '-' . $short . '.pdf',
            'source' => $this->slug() . '-' . $short . '.tex',
            'thumbnail' => 'thumbnail-' . $short . '.png',
            'metadata' => 'metadata-' . $short . '.json',
            default => throw new RuntimeException("Unknown LaTeX release asset kind: {$kind}"),
        };
    }

    public function releaseAssetUrl(string $repository, string $asset): string
    {
        return sprintf(
            'https://github.com/%s/releases/download/%s/%s',
            trim($repository, '/'),
            rawurlencode($this->releaseTag()),
            rawurlencode($asset),
        );
    }

    public function portfolioUrl(string $siteUrl = 'https://vitormattos.github.io'): string
    {
        return rtrim($siteUrl, '/') . '/pt-BR/palestras/' . rawurlencode($this->slug());
    }

    public function releaseBody(string $repository, array $assets): string
    {
        $reviewed = (array) ($this->metadata['reviewed_work'] ?? []);
        $authors = implode(', ', array_map('strval', (array) ($reviewed['authors'] ?? [])));
        $lines = [
            trim((string) $this->metadata['description']),
            '',
            sprintf(
                '<img src="%s" alt="%s" width="1280" height="720">',
                $assets['thumbnail_url'],
                htmlspecialchars((string) $this->metadata['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ),
            '',
            'Seminário acadêmico produzido em LaTeX/Beamer. Esta release publica o PDF, a fonte LaTeX, a primeira página renderizada e os metadados correspondentes à versão atual da apresentação.',
            '',
            '### Apresentação',
            '- **Página no portfólio:** ' . $this->portfolioUrl(),
            '- **PDF desta versão:** ' . $assets['pdf_url'],
            '- **Fonte LaTeX desta versão:** ' . $assets['source_url'],
            '- **Metadados:** ' . $assets['metadata_url'],
            '- **Idioma:** ' . (string) $this->metadata['language'],
            '- **Slides:** ' . (string) $this->metadata['slide_count'],
            '- **Data:** ' . (string) $this->metadata['date'],
            '- **SHA-256 da fonte:** ' . $assets['source_sha'],
            '- **SHA-256 do PDF:** ' . $assets['pdf_sha'],
        ];

        if (($reviewed['title'] ?? '') !== '') {
            $lines = [...$lines, '', '### Trabalho analisado', '- **Título:** ' . $reviewed['title']];
            if ($authors !== '') {
                $lines[] = '- **Autores:** ' . $authors;
            }
            if (($reviewed['year'] ?? '') !== '') {
                $lines[] = '- **Ano:** ' . (string) $reviewed['year'];
            }
            if (($reviewed['doi'] ?? '') !== '') {
                $lines[] = '- **DOI:** https://doi.org/' . $reviewed['doi'];
            }
            if (($reviewed['url'] ?? '') !== '') {
                $lines[] = '- **Fonte:** ' . $reviewed['url'];
            }
        }

        $lines = [...$lines, '', 'A tag da release identifica a apresentação e mantém apenas os ativos correspondentes à versão atual da fonte.'];

        return rtrim(implode("\n", $lines)) . "\n";
    }
}
