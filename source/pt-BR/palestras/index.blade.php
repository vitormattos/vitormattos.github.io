---
title: Palestras
locale: pt-BR
pageType: CollectionPage
alternateUrl: /talks/
description: Palestras e apresentações de Vitor Mattos sobre software livre, PHP, LibreSign, engenharia de software e construção de comunidades.
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
        <p class="eyebrow">Apresentações</p>
        <h1 id="page-title">Palestras</h1>
        <p class="lead">Palestras e apresentações sobre software livre, PHP, LibreSign, engenharia de software e construção de comunidades.</p>
    </header>

    <div class="talk-list">
        @foreach ($talks as $talk)
            @include('_partials.talk.card', ['talk' => $talk])
        @endforeach
    </div>
</section>
@endsection
