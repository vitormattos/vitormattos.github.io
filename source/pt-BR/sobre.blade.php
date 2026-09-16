---
title: Sobre
description: Sobre Vitor Mattos, desenvolvedor de software, líder de tecnologia e contribuidor de software livre atuando com tecnologias abertas, comunidades e pesquisa.
locale: pt-BR
alternateUrl: /about
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: CC-BY-SA-4.0 --}}
@extends('_layouts.main')

@section('body')
<article class="content">
    <header>
        <p class="eyebrow">Sobre</p>
        <h1>Vitor Mattos</h1>
        <p class="lead">Sou desenvolvedor de software e líder de tecnologia, com atuação concentrada em software livre, tecnologias abertas e nas comunidades que as sustentam.</p>
    </header>

    <p>Trabalho com tecnologias web há mais de duas décadas, com forte experiência em PHP e Linux. Hoje sou CTO e cooperado da <a href="https://librecode.coop/" rel="external">LibreCode</a>, uma cooperativa brasileira de tecnologia, onde atuo em produtos, infraestrutura, estratégia técnica e na sustentabilidade de longo prazo de software livre.</p>

    <h2>Construindo software livre</h2>
    <p>Uma parte central do meu trabalho é o <a href="https://libresign.coop/" rel="external">LibreSign</a>, uma plataforma de assinatura eletrônica de código aberto que mantenho e ajudo a desenvolver junto com sua comunidade. O LibreSign é reconhecido como Digital Public Good e faz parte de um esforço mais amplo para construir infraestrutura digital que organizações possam operar, inspecionar e melhorar sem depender de plataformas proprietárias.</p>

    <p>Meu trabalho também envolve tecnologias como Nextcloud e outros softwares livres usados por organizações que precisam manter controle sobre seus dados e sua infraestrutura. Tenho especial interesse em projetos nos quais qualidade técnica, privacidade, interoperabilidade e modelos de negócio sustentáveis precisam funcionar em conjunto.</p>

    <h2>Comunidades e compartilhamento de conhecimento</h2>
    <p>O software livre moldou grande parte da minha trajetória profissional. Organizo o PHPRio e coordeno o PHPWomenBR, além de contribuir com comunidades técnicas por meio de eventos, mentoria, discussões, código e documentação pública. Vejo a construção de comunidades como parte do trabalho de engenharia: o software se torna mais resiliente quando conhecimento, decisões e oportunidades de contribuição são compartilhados.</p>

    <p>Também realizo palestras sobre desenvolvimento de software, software livre, autonomia digital e os desafios práticos de manter projetos abertos. Parte desse trabalho está disponível na seção de <a href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/palestras">palestras</a>.</p>

    <h2>Pesquisa e trabalho público</h2>
    <p>Em paralelo à atuação profissional, mantenho uma trajetória acadêmica e de pesquisa relacionada ao desenvolvimento de software e software livre. Este site também funciona como um arquivo público desse trabalho, reunindo <a href="{{ rtrim($page->baseUrl, '/') }}/pt-BR/artigos">artigos e pesquisas</a>, apresentações e materiais técnicos.</p>

    <p>Procuro manter o mesmo princípio entre essas diferentes áreas: trabalho técnico relevante deve ser compreensível, verificável e reutilizável por outras pessoas.</p>

    <h2>Outros canais</h2>
    <p>Meu código e minha atividade técnica pública estão no <a href="{{ $page->author['github'] }}" rel="me external">GitHub</a>, e meu perfil profissional está no <a href="{{ $page->author['linkedin'] }}" rel="me external">LinkedIn</a>.</p>
</article>
@endsection
