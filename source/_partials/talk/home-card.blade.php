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

    $thumbnail = $presentation['thumbnail'] ?? null;
    $thumbnailWidth = (int) ($presentation['thumbnailWidth'] ?? $presentation['width'] ?? 0);
    $thumbnailHeight = (int) ($presentation['thumbnailHeight'] ?? $presentation['height'] ?? 0);
    if ($talk->slidesId ?? false) {
        $sourceDirectory = ($presentation['type'] ?? null) === 'slideshare' ? 'slideshare' : 'slides.com';
        $localThumbnails = glob('presentations/' . $sourceDirectory . '/' . $talk->slidesId . '/thumbnail.*') ?: [];
        if ($localThumbnails !== []) {
            $thumbnail = '/' . $localThumbnails[0];
            $imageSize = @getimagesize($localThumbnails[0]);
            if (is_array($imageSize)) {
                $thumbnailWidth = (int) $imageSize[0];
                $thumbnailHeight = (int) $imageSize[1];
            }
        }
    }
@endphp
<article class="home-talk-card">
    <a class="home-talk-card__preview" href="{{ $talkUrl }}" aria-label="{{ $talk->title }}">
        @if ($thumbnail)
            <img src="{{ str_starts_with($thumbnail, '/') ? $page->baseUrl . $thumbnail : $thumbnail }}" alt="" loading="lazy" @if ($thumbnailWidth > 0 && $thumbnailHeight > 0) width="{{ $thumbnailWidth }}" height="{{ $thumbnailHeight }}" @endif>
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
