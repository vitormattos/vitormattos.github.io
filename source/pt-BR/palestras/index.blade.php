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
<section class="content talks-catalog" aria-labelledby="page-title">
    <header class="talks-catalog__header">
        <div>
            <p class="eyebrow">Apresentações</p>
            <h1 id="page-title">Palestras</h1>
            <p class="lead">Palestras e apresentações sobre software livre, PHP, LibreSign, engenharia de software e construção de comunidades.</p>
        </div>
        <div class="gallery-switcher" data-gallery-switcher>
            <button class="gallery-switcher__trigger" type="button" data-gallery-menu-trigger aria-expanded="false" aria-haspopup="true">
                <span>Layout:</span> <strong data-gallery-current>Grade</strong><span aria-hidden="true">⌄</span>
            </button>
            <div class="gallery-switcher__menu" data-gallery-menu hidden role="group" aria-label="Selecionar layout">
                <strong class="gallery-switcher__title">Selecionar layout</strong>
                <button type="button" data-gallery-view="grid" aria-pressed="true">
                    <span><strong>Grade</strong><small>Imagens grandes e informações concisas</small></span><span class="gallery-switcher__check" aria-hidden="true">✓</span>
                </button>
                <button type="button" data-gallery-view="list" aria-pressed="false">
                    <span><strong>Lista</strong><small>Imagens pequenas e descrição da apresentação</small></span><span class="gallery-switcher__check" aria-hidden="true">✓</span>
                </button>
            </div>
        </div>
    </header>
    <div class="talk-list" data-talk-gallery data-view="grid">
        @foreach ($talks as $talk)
            @include('_partials.talk.card', ['talk' => $talk])
        @endforeach
    </div>
</section>
@endsection
