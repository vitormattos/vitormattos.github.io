{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $talk->presentation ?? [];
    $type = $presentation['type'] ?? 'external';
    $isEnglish = ($talk->locale ?? 'en') === 'en';
    $sourcePath = isset($presentation['source']) ? '/' . ltrim($presentation['source'], '/') : null;
    $thumbnailId = $sourcePath ? 'thumb-' . substr(sha1($sourcePath), 0, 10) : null;
@endphp

<article class="talk-card">
    <div class="talk-card__preview" aria-hidden="true">
        @if ($type === 'reveal' && $sourcePath)
            <div class="presentation-thumbnail">
                <div
                    class="reveal js-reveal-deck"
                    id="{{ $thumbnailId }}"
                    data-presentation-mode="thumbnail"
                >
                    <div class="slides">
                        <section
                            data-markdown="{{ $page->baseUrl }}{{ $sourcePath }}"
                            data-separator="^\r?\n---\r?\n$"
                            data-separator-vertical="^\r?\n--\r?\n$"
                            data-separator-notes="^Notes?:"
                        ></section>
                    </div>
                </div>
            </div>
        @elseif ($presentation['thumbnail'] ?? false)
            <img src="{{ $presentation['thumbnail'] }}" alt="" loading="lazy">
        @else
            <div class="presentation-fallback">
                <strong>{{ $talk->title }}</strong>
            </div>
        @endif
    </div>
    <div class="talk-card__body">
        @if ($talk->date ?? false)
            <p class="meta"><time datetime="{{ date('Y-m-d', $talk->date) }}">{{ date($isEnglish ? 'Y-m-d' : 'd/m/Y', $talk->date) }}</time></p>
        @endif
        <h2 class="talk-card__title"><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h2>
        <p>{{ $talk->description }}</p>
        <div class="talk-card__formats" aria-label="{{ $isEnglish ? 'Available formats' : 'Formatos disponíveis' }}">
            <span class="format-badge">{{ $type }}</span>
            @if ($presentation['pdf'] ?? false)<span class="format-badge">PDF</span>@endif
            @if ($presentation['video'] ?? false)<span class="format-badge">{{ $isEnglish ? 'video' : 'vídeo' }}</span>@endif
        </div>
    </div>
</article>
