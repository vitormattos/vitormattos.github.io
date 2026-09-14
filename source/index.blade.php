---
locale: en
alternateUrl: /pt-BR/
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="hero">
    <p class="eyebrow">Free software · PHP · research · community</p>
    <h1>Vitor Mattos</h1>
    <p>Software developer, CTO and worker-owner at LibreCode, LibreSign maintainer, and participant in free software communities.</p>
</section>

<section id="talks">
    <h2>Talks</h2>
    <div class="grid">
        @foreach ($talksEn as $talk)
            <article class="card">
                <p class="meta">{{ date('Y-m-d', $talk->date) }}</p>
                <h3><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h3>
                <p>{{ $talk->description }}</p>
            </article>
        @endforeach
    </div>
</section>

<section id="articles">
    <h2>Articles</h2>
    <div class="grid">
        @foreach ($articlesEn as $article)
            <article class="card">
                <p class="meta">{{ date('Y-m-d', $article->date) }}</p>
                <h3><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h3>
                <p>{{ $article->description }}</p>
            </article>
        @endforeach
    </div>
</section>
@endsection
