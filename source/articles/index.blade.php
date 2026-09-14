---
title: Articles
locale: en
pageType: CollectionPage
alternateUrl: /pt-BR/artigos/
description: Articles by Vitor Mattos about free software, PHP, Linux, LibreSign, software engineering and sustainable software communities.
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="content" aria-labelledby="page-title">
    <header>
        <p class="eyebrow">Writing</p>
        <h1 id="page-title">Articles</h1>
        <p class="lead">Writing about free software, PHP, Linux, LibreSign, software engineering and sustainable software communities.</p>
    </header>

    @foreach ($articlesEn as $article)
        <article>
            <p class="meta"><time datetime="{{ date('Y-m-d', $article->date) }}">{{ date('Y-m-d', $article->date) }}</time></p>
            <h2><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h2>
            <p>{{ $article->description }}</p>
        </article>
    @endforeach
</section>
@endsection
