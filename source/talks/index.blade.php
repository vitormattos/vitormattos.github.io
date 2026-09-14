---
title: Talks
locale: en
pageType: CollectionPage
alternateUrl: /pt-BR/palestras/
description: Talks and presentations by Vitor Mattos about free software, PHP, LibreSign, software engineering and community building.
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@push('head')
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/presentations.scss') }}">
@endpush

@push('scripts')
    <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/presentations.js') }}"></script>
@endpush

@section('body')
<section class="content" aria-labelledby="page-title">
    <header>
        <p class="eyebrow">Speaking</p>
        <h1 id="page-title">Talks</h1>
        <p class="lead">Talks and presentations about free software, PHP, LibreSign, software engineering and community building.</p>
    </header>

    <div class="talk-list">
        @foreach ($talksEn as $talk)
            @include('_partials.talk.card', ['talk' => $talk])
        @endforeach
    </div>
</section>
@endsection
