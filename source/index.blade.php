---
locale: en
alternateUrl: /pt-BR/
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="hero" aria-labelledby="page-title">
    <p class="eyebrow">Free software · PHP · research · community</p>
    <h1 id="page-title">Vitor Mattos</h1>
    <p>Software developer, CTO and worker-owner at LibreCode, LibreSign maintainer, and participant in free software communities.</p>
</section>

<section id="talks" class="home-talks" aria-labelledby="talks-title">
    <div class="section-heading">
        <h2 id="talks-title">Talks</h2>
        <a href="{{ rtrim($page->baseUrl, '/') }}/talks/">View all talks →</a>
    </div>
    <div class="home-talks__grid">
        @foreach ($talksEn->take(4) as $talk)
            @include('_partials.talk.home-card', ['talk' => $talk])
        @endforeach
    </div>
</section>

<section id="articles" aria-labelledby="articles-title">
    <h2 id="articles-title">Articles</h2>
    <div class="grid">
        @foreach ($articlesEn as $article)
            <article class="card">
                <p class="meta"><time datetime="{{ date('Y-m-d', $article->date) }}">{{ date('Y-m-d', $article->date) }}</time></p>
                <h3><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h3>
                <p>{{ $article->description }}</p>
            </article>
        @endforeach
    </div>
</section>
@endsection
