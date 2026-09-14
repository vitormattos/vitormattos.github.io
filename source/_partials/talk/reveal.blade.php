{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $page->presentation;
    $sourcePath = '/' . ltrim($presentation['source'], '/');
    $sourceUrl = $page->baseUrl . $sourcePath;
    $deckId = 'deck-' . substr(sha1($sourcePath), 0, 10);
    $isEnglish = ($page->locale ?? 'en') === 'en';
    $pdfUrl = $presentation['pdf'] ?? (rtrim($page->getPath(), '/') . '/presentation.pdf');
@endphp

<div class="presentation-frame">
    <div class="presentation-toolbar" data-presentation-for="{{ $deckId }}">
        <span>{{ $isEnglish ? 'Presentation' : 'Apresentação' }}</span>
        <div class="presentation-toolbar__actions">
            <button type="button" data-presentation-action="overview">
                {{ $isEnglish ? 'Overview' : 'Miniaturas' }}
            </button>
            <button type="button" data-presentation-action="search">
                {{ $isEnglish ? 'Search' : 'Buscar' }}
            </button>
            <button type="button" data-presentation-action="reading" aria-pressed="false">
                {{ $isEnglish ? 'Reading view' : 'Modo leitura' }}
            </button>
            <button type="button" data-presentation-action="fullscreen">
                {{ $isEnglish ? 'Fullscreen' : 'Tela cheia' }}
            </button>
            <a href="{{ $sourceUrl }}" rel="nofollow">Markdown</a>
            <a href="{{ $page->baseUrl }}/{{ ltrim($pdfUrl, '/') }}" download>PDF</a>
            @if ($presentation['video'] ?? false)
                <a href="{{ $presentation['video'] }}">{{ $isEnglish ? 'Video' : 'Vídeo' }}</a>
            @endif
        </div>
    </div>
    <div class="presentation-stage">
        <div
            class="reveal presentation-detail js-reveal-deck"
            id="{{ $deckId }}"
            data-presentation-mode="detail"
        >
            <div class="slides">
                <section
                    data-markdown="{{ $sourceUrl }}"
                    data-separator="^\r?\n---\r?\n$"
                    data-separator-vertical="^\r?\n--\r?\n$"
                    data-separator-notes="^Notes?:"
                ></section>
            </div>
        </div>
    </div>
</div>
