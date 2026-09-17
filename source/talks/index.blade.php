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
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/talks-catalog.scss') }}">
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/talks-list-flow.scss') }}">
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/talk-preview.scss') }}">
@endpush
@push('scripts')
    <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/presentations.js') }}"></script>
    <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/talk-preview.js') }}"></script>
@endpush

@section('body')
    @php
        $mergedTalks = \App\Presentations\TalkTopics::mergeCatalog($talksEn, $talks);
        $catalog = \App\Presentations\TalkTopics::taxonomy($mergedTalks, 'en');
        $talkItems = $catalog['items'];
        $topics = $catalog['topics'];
    @endphp
    <div class="talks-browser">
        <aside class="talks-sidebar" aria-label="Speaker and topics">
            @include('_partials.author.profile', ['locale' => 'en'])

            @if ($topics !== [])
                <button class="talk-tag-toggle" type="button" data-talk-tag-toggle aria-controls="talk-topic-filter"
                    aria-expanded="false">
                    <span>Tags</span><span class="talk-tag-toggle__chevron" aria-hidden="true">⌄</span>
                </button>
                <nav id="talk-topic-filter" class="talk-tag-filter" data-talk-tag-filter aria-label="Filter talks by topic">
                    <p class="talk-tag-filter__label">Topics</p>
                    <a class="talk-tag talk-tag--filter" href="{{ $page->baseUrl }}/talks/" data-talk-tag=""
                        aria-current="true"><span>All talks</span><span>{{ count($talkItems) }}</span></a>
                    @foreach ($topics as $topic => $data)
                        <a class="talk-tag talk-tag--filter"
                            href="{{ $page->baseUrl }}/talks/?tag={{ rawurlencode($topic) }}"
                            data-talk-tag="{{ $topic }}"><span>{{ $data['label'] }}</span><span>{{ $data['count'] }}</span></a>
                    @endforeach
                </nav>
            @endif
        </aside>

        <section class="content talks-catalog" aria-labelledby="page-title">
            <header class="talks-catalog__header">
                <div>
                    <p class="eyebrow">Speaking</p>
                    <h1 id="page-title">Talks</h1>
                    <p class="lead">Talks and presentations about free software, PHP, LibreSign, software engineering and
                        community building.</p>
                </div>
                <div class="gallery-switcher" data-gallery-switcher>
                    <button class="gallery-switcher__trigger" type="button" data-gallery-menu-trigger aria-expanded="false"
                        aria-haspopup="true">
                        <span>Layout:</span> <strong data-gallery-current>Grid</strong><span aria-hidden="true">⌄</span>
                    </button>
                    <div class="gallery-switcher__menu" data-gallery-menu hidden role="group" aria-label="Select layout">
                        <strong class="gallery-switcher__title">Select layout</strong>
                        <button type="button" data-gallery-view="grid" aria-pressed="true">
                            <span><strong>Grid</strong><small>Big images, concise deck information</small></span><span
                                class="gallery-switcher__check" aria-hidden="true">✓</span>
                        </button>
                        <button type="button" data-gallery-view="list" aria-pressed="false">
                            <span><strong>List</strong><small>Small images, includes deck descriptions</small></span><span
                                class="gallery-switcher__check" aria-hidden="true">✓</span>
                        </button>
                    </div>
                </div>
            </header>
            <div class="talks-catalog__toolbar">
                <p class="talk-filter-status" data-talk-filter-status aria-live="polite"></p>
            </div>
            <div class="talk-list" data-talk-gallery data-view="grid">
                @foreach ($talkItems as $talk)
                    @include('_partials.talk.card', ['talk' => $talk, 'tagIndexPath' => '/talks/'])
                @endforeach
            </div>
            <p class="talk-filter-empty" data-talk-filter-empty hidden>No talks were found for this topic.</p>
        </section>
    </div>
@endsection
