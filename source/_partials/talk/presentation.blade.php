{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $page->presentation ?? [];
    $type = $presentation['type'] ?? 'external';
    $isEnglish = ($page->locale ?? 'en') === 'en';
@endphp

@if ($type === 'reveal')
    @include('_partials.talk.reveal')
@elseif (in_array($type, ['slides.com', 'iframe'], true) && ($presentation['embed'] ?? false))
    <div class="presentation-frame">
        <div class="presentation-stage">
            <iframe
                src="{{ $presentation['embed'] }}"
                title="{{ $page->title }}"
                loading="lazy"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
            ></iframe>
        </div>
    </div>
@elseif ($type === 'pdf' && ($presentation['url'] ?? false))
    <div class="presentation-frame">
        <div class="presentation-fallback">
            <p><a href="{{ $presentation['url'] }}">{{ $isEnglish ? 'Open presentation PDF' : 'Abrir PDF da apresentação' }}</a></p>
        </div>
    </div>
@elseif ($presentation['url'] ?? false)
    <div class="presentation-frame">
        <div class="presentation-fallback">
            <p><a href="{{ $presentation['url'] }}">{{ $isEnglish ? 'Open presentation' : 'Abrir apresentação' }}</a></p>
        </div>
    </div>
@endif
