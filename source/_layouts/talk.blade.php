{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')
@php
    $presentation = $page->presentation ?? [];
    $needsReveal = ($presentation['type'] ?? null) === 'reveal' || (bool) ($presentation['localHtml'] ?? false);
    $isEnglish = ($page->locale ?? 'en') === 'en';
    $showAbout = $page->showAbout ?? true;
    $tags = array_values(array_filter(array_map('strval', (array) ($page->tags ?? []))));
    $tagIndexPath = $isEnglish ? '/talks/' : '/pt-BR/palestras/';
    $presentationUrl = rtrim((string) ($presentation['url'] ?? ''), '/');
    $allTalkHistory = (array) ($page->talkHistory ?? []);
    $talkHistory = (array) ($allTalkHistory[$presentationUrl] ?? []);

    if ($talkHistory === []) {
        $normalizeTalkSlug = static fn (string $value): string => trim(
            strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', str_replace('_', '-', $value))),
            '-',
        );
        $pageSlug = $normalizeTalkSlug((string) ($page->slug ?? ''));
        foreach ($allTalkHistory as $sourceUrl => $candidateHistory) {
            $path = (string) parse_url((string) $sourceUrl, PHP_URL_PATH);
            $sourceSlug = $normalizeTalkSlug((string) basename($path));
            if ($sourceSlug === $pageSlug) {
                $talkHistory = (array) $candidateHistory;
                break;
            }
        }
    }

    $appearances = (array) ($talkHistory['appearances'] ?? []);
    $recordings = (array) ($talkHistory['recordings'] ?? []);
    $alternateSlides = (array) ($talkHistory['alternateSlides'] ?? []);
    $resources = (array) ($talkHistory['resources'] ?? []);
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
        @if ($tags !== [])
            <nav class="talk-detail__tags" aria-label="{{ $isEnglish ? 'Topics' : 'Tópicos' }}">
                @foreach ($tags as $tag)
                    <a class="talk-tag" href="{{ $page->baseUrl }}{{ $tagIndexPath }}?tag={{ rawurlencode($tag) }}">{{ $tag }}</a>
                @endforeach
            </nav>
        @endif
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
    @if ($talkHistory !== [])
        <section class="talk-history" aria-labelledby="talk-history-title">
            <h2 id="talk-history-title">{{ $isEnglish ? 'Presentation history' : 'Histórico de apresentações' }}</h2>
            @if ($appearances !== [])
                <h3>{{ $isEnglish ? 'Presented at' : 'Apresentada em' }}</h3>
                <ul class="talk-history__list">
                    @foreach ($appearances as $appearance)
                        <li>
                            @if ($appearance['url'] ?? false)<a href="{{ $appearance['url'] }}" target="_blank" rel="external noopener noreferrer">{{ $appearance['event'] ?? ($isEnglish ? 'Event' : 'Evento') }}</a>@else{{ $appearance['event'] ?? ($isEnglish ? 'Event' : 'Evento') }}@endif
                            @if ($appearance['date'] ?? false) · <time datetime="{{ $appearance['date'] }}">{{ $isEnglish ? $appearance['date'] : date('d/m/Y', strtotime($appearance['date'])) }}</time>@endif
                            @if ($appearance['venue'] ?? false) · {{ $appearance['venue'] }}@endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($recordings !== [] || $alternateSlides !== [] || $resources !== [] || ($talkHistory['cfp'] ?? false))
                <h3>{{ $isEnglish ? 'Related resources' : 'Recursos relacionados' }}</h3>
                <ul class="talk-history__links">
                    @foreach ($recordings as $recording)<li><a href="{{ $recording['url'] }}" target="_blank" rel="external noopener noreferrer">{{ $recording['label'] ?? ($isEnglish ? 'Recording' : 'Gravação') }}</a></li>@endforeach
                    @foreach ($alternateSlides as $slides)<li><a href="{{ $slides['url'] }}" target="_blank" rel="external noopener noreferrer">{{ $slides['label'] ?? ($isEnglish ? 'Alternate slides' : 'Slides alternativos') }}</a></li>@endforeach
                    @foreach ($resources as $resource)<li><a href="{{ $resource['url'] }}" target="_blank" rel="external noopener noreferrer">{{ $resource['label'] ?? ($isEnglish ? 'Resource' : 'Recurso') }}</a></li>@endforeach
                    @if ($talkHistory['cfp'] ?? false)<li><a href="{{ $talkHistory['cfp'] }}" target="_blank" rel="external noopener noreferrer">PHPRio CFP</a></li>@endif
                </ul>
            @endif
        </section>
    @endif
    @if ($showAbout)
        <section aria-labelledby="talk-about-title"><h2 id="talk-about-title">{{ $isEnglish ? 'About this talk' : 'Sobre esta palestra' }}</h2>@yield('content')</section>
    @endif
</article>
@endsection
