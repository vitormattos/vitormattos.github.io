{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
---
locale: pt-BR
alternateUrl: /en/
---
@extends('_layouts.main')

@section('body')
<section class="hero">
    <p class="eyebrow">Software livre · PHP · pesquisa · comunidade</p>
    <h1>Vitor Mattos</h1>
    <p>Desenvolvedor de software, CTO e cooperado da LibreCode, mantenedor do LibreSign e participante de comunidades de software livre.</p>
</section>

<section id="palestras">
    <h2>Palestras</h2>
    <div class="grid">
        @foreach ($talks as $talk)
            <article class="card">
                <p class="meta">{{ date('d/m/Y', $talk->date) }}</p>
                <h3><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h3>
                <p>{{ $talk->description }}</p>
            </article>
        @endforeach
    </div>
</section>

<section id="artigos">
    <h2>Artigos</h2>
    <div class="grid">
        @foreach ($articles as $article)
            <article class="card">
                <p class="meta">{{ date('d/m/Y', $article->date) }}</p>
                <h3><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h3>
                <p>{{ $article->description }}</p>
            </article>
        @endforeach
    </div>
</section>
@endsection
