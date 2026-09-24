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

    <section class="home-hero" aria-labelledby="page-title">
        <div class="home-hero__copy">
            <p class="eyebrow">Vitor Mattos · engenheiro de software · tecnologia aberta</p>
            <h1 id="page-title">Construindo tecnologia aberta, comunidades e infraestrutura digital.</h1>
            <p class="home-hero__lead">Sou engenheiro de software e CTO com mais de duas décadas de experiência em
                tecnologias web, com foco em software livre, liderança técnica, privacidade e comunidades abertas
                sustentáveis.</p>
            <div class="home-hero__actions" aria-label="Links principais">
                <a class="button-link" href="#trabalho-selecionado">Trabalho selecionado</a>
                <a class="text-link" href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/sobre">Sobre mim →</a>
            </div>
            <p class="home-hero__profiles">@include('_partials.author.profile-links', ['separator' => ' · '])</p>
        </div>

        <aside class="home-signal" aria-label="Atuação atual">
            <p class="home-signal__label">Atuação atual</p>
            <ul>
                <li><strong>CTO e cooperado</strong><span>LibreCode</span></li>
                <li><strong>Mantenedor</strong><span>LibreSign</span></li>
                <li><strong>Pesquisa e trabalho público</strong><span>Engenharia de software e software livre</span></li>
            </ul>
        </aside>
    </section>

    <section id="trabalho-selecionado" class="home-section" aria-labelledby="selected-work-title">
        <div class="section-heading section-heading--home">
            <div>
                <p class="eyebrow">Trabalho selecionado</p>
                <h2 id="selected-work-title">Trabalho que pode ser inspecionado, usado e verificado</h2>
            </div>
        </div>

        <div class="work-grid">
            <article class="work-card">
                <p class="work-card__kicker">Produto de software livre</p>
                <h3>LibreSign</h3>
                <p>Mantenho e ajudo a liderar uma plataforma de assinatura eletrônica de código aberto reconhecida como
                    Digital Public Good.</p>
                <p class="work-card__links">
                    <a href="https://libresign.coop/" target="_blank" rel="external noopener noreferrer">Projeto</a>
                    <a href="https://github.com/LibreSign/libresign" target="_blank"
                        rel="external noopener noreferrer">Código-fonte</a>
                    <a href="https://www.digitalpublicgoods.net/r/libresign" target="_blank"
                        rel="external noopener noreferrer">Registro DPG</a>
                </p>
            </article>

            <article class="work-card">
                <p class="work-card__kicker">Liderança técnica</p>
                <h3>LibreCode</h3>
                <p>Como CTO e cooperado de uma cooperativa de tecnologia, atuo em arquitetura, infraestrutura, produtos,
                    práticas de engenharia e sustentabilidade de software livre.</p>
                <p class="work-card__links">
                    <a href="https://librecode.coop/" target="_blank" rel="external noopener noreferrer">LibreCode</a>
                    <a href="https://github.com/LibreCodeCoop" target="_blank" rel="external noopener noreferrer">GitHub</a>
                </p>
            </article>

            <article class="work-card">
                <p class="work-card__kicker">Comunidade e trabalho público</p>
                <h3>Conhecimento em público</h3>
                <p>Organizo comunidades técnicas, falo sobre engenharia de software e autonomia digital e publico materiais
                    que tornam o trabalho técnico mais fácil de inspecionar e reutilizar.</p>
                <p class="work-card__links">
                    <a href="https://github.com/PHPRio" target="_blank" rel="external noopener noreferrer">PHPRio</a>
                    <a href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/palestras">Palestras</a>
                    <a href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/artigos">Artigos</a>
                </p>
            </article>
        </div>
    </section>

    <section id="palestras" class="home-section home-talks" aria-labelledby="talks-title">
        <div class="section-heading section-heading--home">
            <div>
                <p class="eyebrow">Palestras</p>
                <h2 id="talks-title">Palestras selecionadas</h2>
            </div>
            <a href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/palestras/">Ver todas as palestras →</a>
        </div>
        <div class="home-talks__grid">
            @foreach (array_slice($homeTalks, 0, 3) as $talk)
                @include('_partials.talk.home-card', ['talk' => $talk])
            @endforeach
        </div>
    </section>

    <section id="artigos" class="home-section" aria-labelledby="articles-title">
        <div class="section-heading section-heading--home">
            <div>
                <p class="eyebrow">Escrita e pesquisa</p>
                <h2 id="articles-title">Artigos recentes</h2>
            </div>
            <a href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/artigos/">Ver todos os artigos →</a>
        </div>
        <div class="home-articles">
            @foreach (array_slice($articles->values()->all(), 0, 3) as $article)
                <article class="home-article">
                    <p class="meta"><time
                            datetime="{{ date('Y-m-d', $article->date) }}">{{ date('d/m/Y', $article->date) }}</time></p>
                    <h3><a href="{{ $article->getUrl() }}">{{ $article->title }}</a></h3>
                    <p>{{ $article->description }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection
