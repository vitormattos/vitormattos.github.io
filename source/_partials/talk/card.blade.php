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
    <a class="talk-card__preview" href="{{ $talk->getUrl() }}" aria-label="{{ $talk->title }}">
        @if ($presentation['thumbnail'] ?? false)
            <img src="{{ $presentation['thumbnail'] }}" alt="" loading="lazy">
        @elseif ($type === 'reveal' && $sourcePath)
            <div class="presentation-thumbnail" aria-hidden="true"><div class="reveal js-reveal-deck" id="{{ $thumbnailId }}" data-presentation-mode="thumbnail"><div class="slides"><section data-markdown="{{ $page->baseUrl }}{{ $sourcePath }}" data-separator="^\r?\n---\r?\n$" data-separator-vertical="^\r?\n--\r?\n$" data-separator-notes="^Notes?:"></section></div></div></div>
        @elseif (in_array($type, ['slides.com', 'iframe'], true) && ($presentation['embed'] ?? false))
            <iframe src="{{ $presentation['embed'] }}" title="" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
        @else
            <div class="presentation-fallback"><strong>{{ $talk->title }}</strong></div>
        @endif
    </a>

    <div class="talk-card__body">
        <h2 class="talk-card__title"><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h2>
        <p class="talk-card__description">{{ $talk->description }}</p>

        <div class="talk-card__meta-line">
            @if ($talk->date ?? false)
                <span><time datetime="{{ date('Y-m-d', $talk->date) }}">{{ date($isEnglish ? 'M d, Y' : 'd/m/Y', $talk->date) }}</time></span>
            @endif
            @if ($presentation['slideCount'] ?? 0)
                <span>{{ $presentation['slideCount'] }} {{ $isEnglish ? 'slides' : 'slides' }}</span>
            @endif
            @if ($presentation['language'] ?? false)
                <span>{{ $presentation['language'] }}</span>
            @endif
        </div>

        <div class="talk-card__footer">
            <div class="talk-card__formats" aria-label="{{ $isEnglish ? 'Available formats' : 'Formatos disponíveis' }}">
                <span class="format-badge">{{ $type }}</span>
                @if ($presentation['localHtml'] ?? false)<span class="format-badge">HTML</span>@endif
                @if (($presentation['localPdf'] ?? false) || ($presentation['pdf'] ?? false))<span class="format-badge">PDF</span>@endif
                @if ($presentation['video'] ?? false)<span class="format-badge">{{ $isEnglish ? 'video' : 'vídeo' }}</span>@endif
            </div>
            @if ($presentation['themeColor'] ?? false)
                <span class="talk-card__theme">{{ $presentation['themeColor'] }}</span>
            @endif
        </div>
    </div>
</article>
