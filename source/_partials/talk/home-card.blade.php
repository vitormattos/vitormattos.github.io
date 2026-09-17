{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $talk->presentation ?? [];
    $talkIsEnglish = ($talk->locale ?? 'en') === 'en';
    $pageIsEnglish = ($page->locale ?? $page->defaultLocale ?? 'en') === 'en';
    $slug = $talk->slug ?? null;
    $talkPath = $slug ? ($talkIsEnglish ? '/talks/' : '/pt-BR/palestras/') . $slug : '/' . ltrim($talk->getPath(), '/');
    $talkUrl = rtrim($page->baseUrl, '/') . $talkPath;
    $thumbnail = $page->presentationThumbnail($talk);
@endphp
<article class="home-talk-card">
    <a class="home-talk-card__preview" href="{{ $talkUrl }}" aria-label="{{ $talk->title }}">
        @if ($thumbnail)
            <img src="{{ $thumbnail['url'] }}" alt="" loading="lazy"
                @if ($thumbnail['width'] > 0 && $thumbnail['height'] > 0) width="{{ $thumbnail['width'] }}" height="{{ $thumbnail['height'] }}" @endif>
        @else
            <div class="home-talk-card__fallback" aria-hidden="true">{{ $talk->title }}</div>
        @endif
    </a>
    <div class="home-talk-card__body">
        <div class="home-talk-card__meta">
            @if ($talk->date ?? false)
                <time
                    datetime="{{ date('Y-m-d', $talk->date) }}">{{ date($pageIsEnglish ? 'M d, Y' : 'd/m/Y', $talk->date) }}</time>
            @endif
            @if ($presentation['slideCount'] ?? 0)
                <span>{{ $presentation['slideCount'] }} slides</span>
            @endif
        </div>
        <h3><a href="{{ $talkUrl }}">{{ $talk->title }}</a></h3>
    </div>
</article>
