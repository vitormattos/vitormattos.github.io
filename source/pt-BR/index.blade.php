---
locale: pt-BR
alternateUrl: /
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
    @php
        $homeTalks = \App\Presentations\TalkTopics::mergeCatalog($talksEn, $talks);
    @endphp
    <section class="hero" aria-labelledby="page-title">
        <p class="eyebrow">Software livre · PHP · pesquisa · comunidade</p>
        <h1 id="page-title">Vitor Mattos</h1>
        <p>Desenvolvedor de software, CTO e cooperado da LibreCode, mantenedor do LibreSign e participante de comunidades de
            software livre.</p>
    </section>

    <section id="palestras" class="home-talks" aria-labelledby="talks-title">
        <div class="section-heading">
            <h2 id="talks-title">Palestras</h2>
            <a href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/palestras/">Ver todas as palestras →</a>
        </div>
        <div class="home-talks__grid">
            @foreach (array_slice($homeTalks, 0, 4) as $talk)
                @include('_partials.talk.home-card', ['talk' => $talk])
            @endforeach
        </div>
    </section>

    <section id="artigos" aria-labelledby="articles-title">
        <h2 id="articles-title">Artigos</h2>
        <div class="grid">
            @foreach ($articles as $article)
                <article class="card">
                    <p class="meta"><time
                            datetime="{{ date('Y-m-d', $article->date) }}">{{ date('d/m/Y', $article->date) }}</time></p>
                    <h3><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h3>
                    <p>{{ $article->description }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection
