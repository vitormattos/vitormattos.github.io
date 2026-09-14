{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $talk->presentation ?? [];
    $isEnglish = ($talk->locale ?? 'en') === 'en';
    $slug = $talk->slug ?? null;
    $talkPath = $slug
        ? ($isEnglish ? '/talks/' : '/pt-BR/palestras/') . $slug
        : '/' . ltrim($talk->getPath(), '/');
    $talkUrl = rtrim($page->baseUrl, '/') . $talkPath;
@endphp
<article class="home-talk-card">
    <a class="home-talk-card__preview" href="{{ $talkUrl }}" aria-label="{{ $talk->title }}">
        @if ($presentation['thumbnail'] ?? false)
            <img src="{{ $presentation['thumbnail'] }}" alt="" loading="lazy">
        @else
            <div class="home-talk-card__fallback" aria-hidden="true">{{ $talk->title }}</div>
        @endif
    </a>
    <div class="home-talk-card__body">
        <div class="home-talk-card__meta">
            @if ($talk->date ?? false)
                <time datetime="{{ date('Y-m-d', $talk->date) }}">{{ date($isEnglish ? 'M d, Y' : 'd/m/Y', $talk->date) }}</time>
            @endif
            @if ($presentation['slideCount'] ?? 0)
                <span>{{ $presentation['slideCount'] }} slides</span>
            @endif
        </div>
        <h3><a href="{{ $talkUrl }}">{{ $talk->title }}</a></h3>
    </div>
</article>
