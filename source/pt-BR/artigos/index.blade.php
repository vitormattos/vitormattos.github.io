---
title: Artigos
locale: pt-BR
pageType: CollectionPage
alternateUrl: /articles/
description: Artigos de Vitor Mattos sobre software livre, PHP, Linux, LibreSign, engenharia de software e comunidades sustentáveis.
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="content" aria-labelledby="page-title">
    <header>
        <p class="eyebrow">Textos</p>
        <h1 id="page-title">Artigos</h1>
        <p class="lead">Textos sobre software livre, PHP, Linux, LibreSign, engenharia de software e comunidades sustentáveis.</p>
    </header>

    @foreach ($articles as $article)
        <article>
            <p class="meta"><time datetime="{{ date('Y-m-d', $article->date) }}">{{ date('d/m/Y', $article->date) }}</time></p>
            <h2><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h2>
            <p>{{ $article->description }}</p>
        </article>
    @endforeach
</section>
@endsection
