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
            <p class="eyebrow">Vitor Mattos</p>
            <h1 id="page-title">Desenvolvedor de software e mantenedor de software livre.</h1>
            <p class="home-hero__positioning">CTO na LibreCode · mantenedor do LibreSign · construtor de comunidades de
                software livre</p>
            <p class="home-hero__lead">Mais de duas décadas trabalhando com tecnologias web, liderança técnica, privacidade e
                comunidades abertas sustentáveis.</p>
            <div class="home-hero__actions" aria-label="Links principais">
                <a class="button-link" href="#trabalho-selecionado">Ver minha atuação</a>
                <a class="text-link" href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/sobre">Sobre mim →</a>
            </div>
            <p class="home-hero__profiles">@include('_partials.author.profile-links', ['separator' => ' · '])</p>
        </div>

        <aside class="home-signal" aria-label="Atuação atual">
            <p class="home-signal__label">Atuação atual</p>
            <ul>
                <li>
                    <a class="home-signal__link" href="https://librecode.coop/" target="_blank"
                        rel="external noopener noreferrer">
                        <span><strong>CTO e cooperado</strong><small>LibreCode</small></span>
                        <span class="home-signal__arrow" aria-hidden="true">↗</span>
                    </a>
                </li>
                <li>
                    <a class="home-signal__link" href="https://libresign.coop/" target="_blank"
                        rel="external noopener noreferrer">
                        <span><strong>Mantenedor</strong><small>LibreSign</small></span>
                        <span class="home-signal__arrow" aria-hidden="true">↗</span>
                    </a>
                </li>
                <li>
                    <a class="home-signal__link" href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/artigos/">
                        <span><strong>Pesquisa e escrita</strong><small>Artigos e trabalho acadêmico</small></span>
                        <span class="home-signal__arrow" aria-hidden="true">→</span>
                    </a>
                </li>
                <li>
                    <a class="home-signal__link" href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/palestras/">
                        <span><strong>Palestras e comunidade</strong><small>Palestras e compartilhamento de
                                conhecimento</small></span>
                        <span class="home-signal__arrow" aria-hidden="true">→</span>
                    </a>
                </li>
            </ul>
        </aside>
    </section>

    <section id="trabalho-selecionado" class="home-section" aria-labelledby="selected-work-title">
        <div class="section-heading section-heading--home">
            <div>
                <h2 id="selected-work-title">Projetos e atuação pública</h2>
                <p class="section-intro">Projetos, liderança e trabalho público com links para o próprio trabalho.</p>
            </div>
        </div>

        <div class="work-grid">
            <article class="work-card">
                <p class="work-card__kicker">Produto de software livre</p>
                <h3><a class="work-card__title-link" href="https://libresign.coop/" target="_blank"
                        rel="external noopener noreferrer">LibreSign <span aria-hidden="true">↗</span></a></h3>
                <p>Mantenho e ajudo a liderar uma plataforma livre de assinatura eletrônica reconhecida como Digital Public
                    Good.</p>
                <p class="work-card__links">
                    <a href="https://libresign.coop/" target="_blank" rel="external noopener noreferrer">Visitar projeto</a>
                    <a href="https://github.com/LibreSign/libresign" target="_blank"
                        rel="external noopener noreferrer">Código-fonte</a>
                    <a href="https://www.digitalpublicgoods.net/r/libresign" target="_blank"
                        rel="external noopener noreferrer">Registro Digital Public Goods</a>
                </p>
            </article>

            <article class="work-card">
                <p class="work-card__kicker">Liderança técnica</p>
                <h3><a class="work-card__title-link" href="https://librecode.coop/" target="_blank"
                        rel="external noopener noreferrer">LibreCode <span aria-hidden="true">↗</span></a></h3>
                <p>Como CTO e cooperado de uma cooperativa de tecnologia, atuo em arquitetura, infraestrutura, produtos,
                    práticas de engenharia e sustentabilidade de software livre.</p>
                <p class="work-card__links">
                    <a href="https://librecode.coop/" target="_blank" rel="external noopener noreferrer">Visitar
                        LibreCode</a>
                    <a href="https://github.com/LibreCodeCoop" target="_blank"
                        rel="external noopener noreferrer">Organização no GitHub</a>
                </p>
            </article>

            <article class="work-card">
                <p class="work-card__kicker">Comunidade e trabalho público</p>
                <h3><a class="work-card__title-link" href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/sobre">Conhecimento em
                        público</a></h3>
                <p>Organizo comunidades técnicas, falo sobre engenharia de software e autonomia digital e publico materiais
                    que tornam o trabalho técnico mais fácil de compreender, compartilhar e reutilizar.</p>
                <p class="work-card__links">
                    <a href="https://github.com/PHPRio" target="_blank" rel="external noopener noreferrer">PHPRio no
                        GitHub</a>
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
                <h2 id="talks-title">Palestras recentes</h2>
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
