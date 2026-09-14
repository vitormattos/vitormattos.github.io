{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@php
    $presentation = $page->presentation ?? [];
    $needsReveal = ($presentation['type'] ?? null) === 'reveal';
    $isEnglish = ($page->locale ?? 'en') === 'en';
@endphp

@push('head')
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/presentations.scss') }}">
@endpush

@if ($needsReveal)
    @push('scripts')
        <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/presentations.js') }}"></script>
    @endpush
@endif

@section('body')
<article class="content talk-detail">
    <header>
        <p class="eyebrow">{{ $isEnglish ? 'Talk' : 'Palestra' }}</p>
        @if ($page->date ?? false)
            <p class="meta"><time datetime="{{ date('Y-m-d', $page->date) }}">{{ date($isEnglish ? 'Y-m-d' : 'd/m/Y', $page->date) }}</time></p>
        @endif
        <h1>{{ $page->title }}</h1>
        @if ($page->description ?? false)
            <p class="lead">{{ $page->description }}</p>
        @endif
    </header>

    @include('_partials.talk.presentation')

    <section aria-labelledby="talk-about-title">
        <h2 id="talk-about-title">{{ $isEnglish ? 'About this talk' : 'Sobre esta palestra' }}</h2>
        @yield('content')
    </section>
</article>
@endsection
