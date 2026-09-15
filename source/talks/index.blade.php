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
@endpush
@push('scripts')
    <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/presentations.js') }}"></script>
@endpush

@section('body')
@php
    $talkItems = [];
    $tagCounts = [];
    $tagLabels = [];

    foreach ($talksEn as $talk) {
        $talkItems[] = $talk;

        foreach ((array) ($talk->tags ?? []) as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }

            $key = strtolower($tag);
            $tagCounts[$key] = ($tagCounts[$key] ?? 0) + 1;

            if (!isset($tagLabels[$key])
                || ($tagLabels[$key] === strtolower($tagLabels[$key]) && $tag !== strtolower($tag))) {
                $tagLabels[$key] = $tag;
            }
        }
    }

    ksort($tagCounts, SORT_NATURAL | SORT_FLAG_CASE);
@endphp
<div class="talks-browser">
    <aside class="talks-sidebar" aria-label="Speaker and topics">
        <div class="talks-profile">
            <div>
                <p class="talks-profile__name">Vitor Mattos</p>
                <p class="talks-profile__summary">CTO at LibreCode. Free software, privacy, PHP, digital signatures and sustainable software communities.</p>
            </div>
            <img class="talks-profile__avatar" src="https://github.com/vitormattos.png?size=112" alt="" width="56" height="56">
            <div class="talks-profile__links">
                <a href="https://github.com/vitormattos" rel="me">GitHub</a>
                <a href="https://www.linkedin.com/in/vitormattos/" rel="me">LinkedIn</a>
            </div>
        </div>

        @if ($tagCounts !== [])
            <nav class="talk-tag-filter" data-talk-tag-filter aria-label="Filter talks by topic">
                <p class="talk-tag-filter__label">Topics</p>
                <a class="talk-tag talk-tag--filter" href="{{ $page->baseUrl }}/talks/" data-talk-tag="" aria-current="true"><span>All talks</span><span>{{ count($talkItems) }}</span></a>
                @foreach ($tagCounts as $tag => $count)
                    <a class="talk-tag talk-tag--filter" href="{{ $page->baseUrl }}/talks/?tag={{ rawurlencode($tag) }}" data-talk-tag="{{ $tag }}"><span>{{ $tagLabels[$tag] }}</span><span>{{ $count }}</span></a>
                @endforeach
            </nav>
        @endif
    </aside>

    <section class="content talks-catalog" aria-labelledby="page-title">
        <header class="talks-catalog__header">
            <div>
                <p class="eyebrow">Speaking</p>
                <h1 id="page-title">Talks</h1>
                <p class="lead">Talks and presentations about free software, PHP, LibreSign, software engineering and community building.</p>
            </div>
            <div class="gallery-switcher" data-gallery-switcher>
                <button class="gallery-switcher__trigger" type="button" data-gallery-menu-trigger aria-expanded="false" aria-haspopup="true">
                    <span>Layout:</span> <strong data-gallery-current>Grid</strong><span aria-hidden="true">⌄</span>
                </button>
                <div class="gallery-switcher__menu" data-gallery-menu hidden role="group" aria-label="Select layout">
                    <strong class="gallery-switcher__title">Select layout</strong>
                    <button type="button" data-gallery-view="grid" aria-pressed="true">
                        <span><strong>Grid</strong><small>Big images, concise deck information</small></span><span class="gallery-switcher__check" aria-hidden="true">✓</span>
                    </button>
                    <button type="button" data-gallery-view="list" aria-pressed="false">
                        <span><strong>List</strong><small>Small images, includes deck descriptions</small></span><span class="gallery-switcher__check" aria-hidden="true">✓</span>
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
        <p class="talk-filter-empty" data-talk-filter-empty hidden>No talks were found for this topic.</p>
    </section>
</div>
@endsection
