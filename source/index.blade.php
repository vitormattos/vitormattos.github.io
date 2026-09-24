---
locale: en
alternateUrl: /pt-BR/
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
@php
    $homeTalks = \App\Presentations\TalkTopics::mergeCatalog($talksEn, $talks);
@endphp

<section class="home-hero" aria-labelledby="page-title">
    <div class="home-hero__copy">
        <p class="eyebrow">Vitor Mattos</p>
        <h1 id="page-title">Software engineer and open-source maintainer.</h1>
        <p class="home-hero__positioning">CTO at LibreCode · LibreSign maintainer · free software community builder</p>
        <p class="home-hero__lead">More than two decades working with web technologies, technical leadership, privacy, and sustainable open-source communities.</p>
        <div class="home-hero__actions" aria-label="Primary links">
            <a class="button-link" href="#selected-work">Selected work</a>
            <a class="text-link" href="{{ rtrim($page->baseUrl, '/') }}/about">About me →</a>
        </div>
        <p class="home-hero__profiles">@include('_partials.author.profile-links', ['separator' => ' · '])</p>
    </div>

    <aside class="home-signal" aria-label="Current focus">
        <p class="home-signal__label">Current focus</p>
        <ul>
            <li><strong>CTO & worker-owner</strong><span>LibreCode</span></li>
            <li><strong>Maintainer</strong><span>LibreSign</span></li>
            <li><strong>Research & public work</strong><span>Software engineering and free software</span></li>
        </ul>
    </aside>
</section>

<section id="selected-work" class="home-section" aria-labelledby="selected-work-title">
    <div class="section-heading section-heading--home">
        <div>
            <h2 id="selected-work-title">Selected work</h2>
            <p class="section-intro">Projects, leadership, and public work with links to the work itself.</p>
        </div>
    </div>

    <div class="work-grid">
        <article class="work-card">
            <p class="work-card__kicker">Free software product</p>
            <h3><a class="work-card__title-link" href="https://libresign.coop/" target="_blank" rel="external noopener noreferrer">LibreSign <span aria-hidden="true">↗</span></a></h3>
            <p>I maintain and help lead a free and open-source electronic signature platform recognized as a Digital Public Good.</p>
            <p class="work-card__links">
                <a href="https://libresign.coop/" target="_blank" rel="external noopener noreferrer">Visit project</a>
                <a href="https://github.com/LibreSign/libresign" target="_blank" rel="external noopener noreferrer">Source code</a>
                <a href="https://www.digitalpublicgoods.net/r/libresign" target="_blank" rel="external noopener noreferrer">Digital Public Goods record</a>
            </p>
        </article>

        <article class="work-card">
            <p class="work-card__kicker">Technical leadership</p>
            <h3><a class="work-card__title-link" href="https://librecode.coop/" target="_blank" rel="external noopener noreferrer">LibreCode <span aria-hidden="true">↗</span></a></h3>
            <p>As CTO and worker-owner of a technology cooperative, I work across architecture, infrastructure, products, engineering practice, and the sustainability of free software.</p>
            <p class="work-card__links">
                <a href="https://librecode.coop/" target="_blank" rel="external noopener noreferrer">Visit LibreCode</a>
                <a href="https://github.com/LibreCodeCoop" target="_blank" rel="external noopener noreferrer">Organization on GitHub</a>
            </p>
        </article>

        <article class="work-card">
            <p class="work-card__kicker">Community and public work</p>
            <h3><a class="work-card__title-link" href="{{ rtrim($page->baseUrl, '/') }}/about">Knowledge in public</a></h3>
            <p>I organize technical communities, speak about software engineering and digital autonomy, and publish material that makes technical work easier to understand, share, and reuse.</p>
            <p class="work-card__links">
                <a href="https://github.com/PHPRio" target="_blank" rel="external noopener noreferrer">PHPRio on GitHub</a>
                <a href="{{ rtrim($page->baseUrl, '/') }}/talks">Talks</a>
                <a href="{{ rtrim($page->baseUrl, '/') }}/articles">Articles</a>
            </p>
        </article>
    </div>
</section>

<section id="talks" class="home-section home-talks" aria-labelledby="talks-title">
    <div class="section-heading section-heading--home">
        <div>
            <p class="eyebrow">Speaking</p>
            <h2 id="talks-title">Selected talks</h2>
        </div>
        <a href="{{ rtrim($page->baseUrl, '/') }}/talks/">View all talks →</a>
    </div>
    <div class="home-talks__grid">
        @foreach (array_slice($homeTalks, 0, 3) as $talk)
            @include('_partials.talk.home-card', ['talk' => $talk])
        @endforeach
    </div>
</section>

<section id="articles" class="home-section" aria-labelledby="articles-title">
    <div class="section-heading section-heading--home">
        <div>
            <p class="eyebrow">Writing & research</p>
            <h2 id="articles-title">Recent articles</h2>
        </div>
        <a href="{{ rtrim($page->baseUrl, '/') }}/articles/">View all articles →</a>
    </div>
    <div class="home-articles">
        @foreach (array_slice($articlesEn->values()->all(), 0, 3) as $article)
            <article class="home-article">
                <p class="meta"><time datetime="{{ date('Y-m-d', $article->date) }}">{{ date('Y-m-d', $article->date) }}</time></p>
                <h3><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h3>
                <p>{{ $article->description }}</p>
            </article>
        @endforeach
    </div>
</section>
@endsection
