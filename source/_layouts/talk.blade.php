{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')
@php
    $presentation = $page->presentation ?? [];
    $needsReveal = ($presentation['type'] ?? null) === 'reveal' || (bool) ($presentation['localHtml'] ?? false);
    $isEnglish = ($page->locale ?? 'en') === 'en';
@endphp
@push('head')
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/presentations.scss') }}">
    @if ($presentation['localCss'] ?? false)<link rel="stylesheet" href="{{ $page->baseUrl }}{{ $presentation['localCss'] }}">@endif
@endpush
@if ($needsReveal)
    @push('scripts')<script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/presentations.js') }}"></script>@endpush
@endif
@section('body')
<article class="content talk-detail">
    <header>
        <p class="eyebrow">{{ $isEnglish ? 'Talk' : 'Palestra' }}</p>
        @if ($page->date ?? false)<p class="meta"><time datetime="{{ date('Y-m-d', $page->date) }}">{{ date($isEnglish ? 'Y-m-d' : 'd/m/Y', $page->date) }}</time></p>@endif
        <h1>{{ $page->title }}</h1>
        @if ($page->description ?? false)<p class="lead">{{ $page->description }}</p>@endif
    </header>
    @include('_partials.talk.presentation')
    @if (($presentation['slideCount'] ?? 0) || ($presentation['language'] ?? false) || ($presentation['themeColor'] ?? false))
        <dl class="presentation-metadata">
            @if ($presentation['slideCount'] ?? 0)<div><dt>{{ $isEnglish ? 'Slides' : 'Slides' }}</dt><dd>{{ $presentation['slideCount'] }}</dd></div>@endif
            @if ($presentation['language'] ?? false)<div><dt>{{ $isEnglish ? 'Language' : 'Idioma' }}</dt><dd>{{ $presentation['language'] }}</dd></div>@endif
            @if ($presentation['themeColor'] ?? false)<div><dt>{{ $isEnglish ? 'Theme' : 'Tema' }}</dt><dd>{{ $presentation['themeColor'] }}</dd></div>@endif
            @if (($presentation['width'] ?? 0) && ($presentation['height'] ?? 0))<div><dt>{{ $isEnglish ? 'Canvas' : 'Tela' }}</dt><dd>{{ $presentation['width'] }} × {{ $presentation['height'] }}</dd></div>@endif
        </dl>
    @endif
    <section aria-labelledby="talk-about-title"><h2 id="talk-about-title">{{ $isEnglish ? 'About this talk' : 'Sobre esta palestra' }}</h2>@yield('content')</section>
</article>
@endsection
