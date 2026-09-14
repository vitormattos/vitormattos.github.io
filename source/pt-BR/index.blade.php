---
locale: pt-BR
alternateUrl: /
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="hero" aria-labelledby="page-title">
    <p class="eyebrow">Software livre · PHP · pesquisa · comunidade</p>
    <h1 id="page-title">Vitor Mattos</h1>
    <p>Desenvolvedor de software, CTO e cooperado da LibreCode, mantenedor do LibreSign e participante de comunidades de software livre.</p>
</section>

<section id="palestras" aria-labelledby="talks-title">
    <h2 id="talks-title">Palestras</h2>
    <div class="grid">
        @foreach ($talks as $talk)
            <article class="card">
                <p class="meta"><time datetime="{{ date('Y-m-d', $talk->date) }}">{{ date('d/m/Y', $talk->date) }}</time></p>
                <h3><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h3>
                <p>{{ $talk->description }}</p>
            </article>
        @endforeach
    </div>
</section>

<section id="artigos" aria-labelledby="articles-title">
    <h2 id="articles-title">Artigos</h2>
    <div class="grid">
        @foreach ($articles as $article)
            <article class="card">
                <p class="meta"><time datetime="{{ date('Y-m-d', $article->date) }}">{{ date('d/m/Y', $article->date) }}</time></p>
                <h3><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h3>
                <p>{{ $article->description }}</p>
            </article>
        @endforeach
    </div>
</section>
@endsection
