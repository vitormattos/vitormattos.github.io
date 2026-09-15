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
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/talks-catalog.scss') }}">
@endpush
@push('scripts')
    <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/presentations.js') }}"></script>
@endpush

@section('body')
@php
    $catalog = \App\Presentations\TalkTopics::taxonomy($talks);
    $talkItems = $catalog['items'];
    $topics = $catalog['topics'];
@endphp
<div class="talks-browser">
    <aside class="talks-sidebar" aria-label="Palestrante e tópicos">
        <div class="talks-profile">
            <div>
                <p class="talks-profile__name">Vitor Mattos</p>
                <p class="talks-profile__summary">CTO da LibreCode. Software livre, privacidade, PHP, assinaturas digitais e comunidades sustentáveis de software.</p>
            </div>
            <img class="talks-profile__avatar" src="https://github.com/vitormattos.png?size=112" alt="" width="56" height="56">
            <div class="talks-profile__links">
                <a href="https://github.com/vitormattos" rel="me">GitHub</a>
                <a href="https://www.linkedin.com/in/vitormattos/" rel="me">LinkedIn</a>
            </div>
        </div>

        @if ($topics !== [])
            <nav class="talk-tag-filter" data-talk-tag-filter aria-label="Filtrar palestras por tópico">
                <p class="talk-tag-filter__label">Tópicos</p>
                <a class="talk-tag talk-tag--filter" href="{{ $page->baseUrl }}/pt-BR/palestras/" data-talk-tag="" aria-current="true"><span>Todas</span><span>{{ count($talkItems) }}</span></a>
                @foreach ($topics as $topic => $data)
                    <a class="talk-tag talk-tag--filter" href="{{ $page->baseUrl }}/pt-BR/palestras/?tag={{ rawurlencode($topic) }}" data-talk-tag="{{ $topic }}"><span>{{ $data['label'] }}</span><span>{{ $data['count'] }}</span></a>
                @endforeach
            </nav>
        @endif
    </aside>

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
        <div class="talks-catalog__toolbar">
            <p class="talk-filter-status" data-talk-filter-status aria-live="polite"></p>
        </div>
        <div class="talk-list" data-talk-gallery data-view="grid">
            @foreach ($talkItems as $talk)
                @include('_partials.talk.card', ['talk' => $talk])
            @endforeach
        </div>
        <p class="talk-filter-empty" data-talk-filter-empty hidden>Nenhuma palestra foi encontrada para este tópico.</p>
    </section>
</div>
@endsection
