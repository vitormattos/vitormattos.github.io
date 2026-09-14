{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $page->presentation ?? [];
    $type = $presentation['type'] ?? 'external';
    $isEnglish = ($page->locale ?? 'en') === 'en';
@endphp
@if ($type === 'reveal')
    @include('_partials.talk.reveal')
@elseif ($type === 'slides.com' && ($presentation['localHtml'] ?? false))
    <div class="presentation-frame">
        <div class="presentation-toolbar">
            <span>{{ $isEnglish ? 'Archived web presentation' : 'Apresentação web arquivada' }}</span>
            <div class="presentation-toolbar__actions">
                @if ($presentation['url'] ?? false)<a href="{{ $presentation['url'] }}" rel="external">{{ $isEnglish ? 'Original' : 'Original' }}</a>@endif
                <a href="{{ $page->baseUrl }}{{ $presentation['localHtml'] }}" download>{{ $isEnglish ? 'Download HTML' : 'Baixar HTML' }}</a>
                @if ($presentation['localCss'] ?? false)<a href="{{ $page->baseUrl }}{{ $presentation['localCss'] }}" download>CSS</a>@endif
                @if ($presentation['metadata'] ?? false)<a href="{{ $page->baseUrl }}{{ $presentation['metadata'] }}" download>JSON</a>@endif
                @if ($presentation['pdf'] ?? false)<a href="{{ $presentation['pdf'] }}" download>PDF</a>@endif
            </div>
        </div>
        <div class="presentation-stage">
            <iframe src="{{ $presentation['embed'] }}" title="{{ $page->title }}" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </div>
@elseif (in_array($type, ['slides.com', 'iframe'], true) && ($presentation['embed'] ?? false))
    <div class="presentation-frame"><div class="presentation-toolbar"><span>{{ $isEnglish ? 'Presentation' : 'Apresentação' }}</span><div class="presentation-toolbar__actions">@if ($presentation['url'] ?? false)<a href="{{ $presentation['url'] }}" rel="external">{{ $isEnglish ? 'Open original' : 'Abrir original' }}</a>@endif @if ($presentation['video'] ?? false)<a href="{{ $presentation['video'] }}" rel="external">{{ $isEnglish ? 'Video' : 'Vídeo' }}</a>@endif</div></div><div class="presentation-stage"><iframe src="{{ $presentation['embed'] }}" title="{{ $page->title }}" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div></div>
@elseif ($type === 'pdf' && ($presentation['url'] ?? false))
    <div class="presentation-frame"><div class="presentation-fallback"><p><a href="{{ $presentation['url'] }}">{{ $isEnglish ? 'Open presentation PDF' : 'Abrir PDF da apresentação' }}</a></p></div></div>
@elseif ($presentation['url'] ?? false)
    <div class="presentation-frame"><div class="presentation-fallback"><p><a href="{{ $presentation['url'] }}">{{ $isEnglish ? 'Open presentation' : 'Abrir apresentação' }}</a></p></div></div>
@endif
