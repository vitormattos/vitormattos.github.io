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
    $rawTalkMetadata = $page->talkMetadata ?? [];
    if (is_object($rawTalkMetadata) && method_exists($rawTalkMetadata, 'toArray')) {
        $rawTalkMetadata = $rawTalkMetadata->toArray();
    }
    $talkMetadata = \App\Presentations\TalkMetadata::resolve(
        is_array($rawTalkMetadata) ? $rawTalkMetadata : [],
        $presentationUrl,
        (string) ($page->slug ?? ''),
    );
    $appearances = (array) ($talkMetadata['appearances'] ?? []);
    $resources = (array) ($talkMetadata['resources'] ?? []);
    $sources = (array) ($talkMetadata['sources'] ?? []);
    $resourceHref = static fn (array $item): string => \App\Presentations\TalkMetadata::publicHref((string) ($item['href'] ?? ''), (string) $page->baseUrl);
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
            @if ($presentation['slideCount'] ?? 0)<div><dt>Slides</dt><dd>{{ $presentation['slideCount'] }}</dd></div>@endif
            @if ($presentation['language'] ?? false)<div><dt>{{ $isEnglish ? 'Language' : 'Idioma' }}</dt><dd>{{ $presentation['language'] }}</dd></div>@endif
            @if ($presentation['themeColor'] ?? false)<div><dt>{{ $isEnglish ? 'Theme' : 'Tema' }}</dt><dd>{{ $presentation['themeColor'] }}</dd></div>@endif
            @if (($presentation['width'] ?? 0) && ($presentation['height'] ?? 0))<div><dt>{{ $isEnglish ? 'Canvas' : 'Tela' }}</dt><dd>{{ $presentation['width'] }} × {{ $presentation['height'] }}</dd></div>@endif
        </dl>
    @endif
    @if ($talkMetadata !== [])
        <section class="talk-history" aria-labelledby="talk-history-title">
            <h2 id="talk-history-title">{{ $isEnglish ? 'Presentation history' : 'Histórico de apresentações' }}</h2>
            @if ($appearances !== [])
                <h3>{{ $isEnglish ? 'Presented at' : 'Apresentada em' }}</h3>
                <ul class="talk-history__list">
                    @foreach ($appearances as $appearance)
                        <li>
                            <div>
                                @if ($appearance['href'] ?? false)<a href="{{ $resourceHref($appearance) }}" target="_blank" rel="external noopener noreferrer">{{ $appearance['event'] ?? ($isEnglish ? 'Event' : 'Evento') }}</a>@else{{ $appearance['event'] ?? ($isEnglish ? 'Event' : 'Evento') }}@endif
                                @if ($appearance['date'] ?? false) · <time datetime="{{ $appearance['date'] }}">{{ $isEnglish ? $appearance['date'] : date('d/m/Y', strtotime($appearance['date'])) }}</time>@endif
                                @if ($appearance['venue'] ?? false) · {{ $appearance['venue'] }}@endif
                                @if ($appearance['city'] ?? false) · {{ $appearance['city'] }}@endif
                                @if ($appearance['country'] ?? false) · {{ $appearance['country'] }}@endif
                                @if ($appearance['mode'] ?? false) · {{ $appearance['mode'] }}</span>@endif
                            </div>
                            @if (($appearance['resources'] ?? []) !== [])
                                <ul class="talk-history__links">
                                    @foreach ((array) $appearance['resources'] as $resource)
                                        <li><a href="{{ $resourceHref((array) $resource) }}" target="_blank" rel="external noopener noreferrer">{{ $resource['label'] ?? ucfirst((string) ($resource['type'] ?? ($isEnglish ? 'resource' : 'recurso'))) }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($resources !== [])
                <h3>{{ $isEnglish ? 'Related resources' : 'Recursos relacionados' }}</h3>
                <ul class="talk-history__links">
                    @foreach ($resources as $resource)
                        <li><a href="{{ $resourceHref((array) $resource) }}" target="_blank" rel="external noopener noreferrer">{{ $resource['label'] ?? ucfirst((string) ($resource['type'] ?? ($isEnglish ? 'resource' : 'recurso'))) }}</a></li>
                    @endforeach
                </ul>
            @endif
            @if ($sources !== [])
                <h3>{{ $isEnglish ? 'Sources' : 'Fontes' }}</h3>
                <ul class="talk-history__links">
                    @foreach ($sources as $source)
                        <li><a href="{{ $resourceHref((array) $source) }}" target="_blank" rel="external noopener noreferrer">{{ $source['label'] ?? strtoupper((string) ($source['type'] ?? ($isEnglish ? 'Source' : 'Fonte'))) }}</a></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
    @if ($showAbout)
        <section aria-labelledby="talk-about-title"><h2 id="talk-about-title">{{ $isEnglish ? 'About this talk' : 'Sobre esta palestra' }}</h2>@yield('content')</section>
    @endif
</article>
@endsection
