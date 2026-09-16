---
title: About
description: About Vitor Mattos, a software developer, technology leader and free software contributor working on open technologies, communities and research.
locale: en
alternateUrl: /pt-BR/sobre
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: CC-BY-SA-4.0 --}}
@extends('_layouts.main')

@section('body')
<article class="content">
    <header>
        <p class="eyebrow">About</p>
        <h1>Vitor Mattos</h1>
        <p class="lead">I am a software developer and technology leader focused on free software, open technologies and the communities that sustain them.</p>
    </header>

    <p>I have worked with web technologies for more than two decades, with a strong background in PHP and Linux. Today I am CTO and worker-owner at <a href="https://librecode.coop/" target="_blank" rel="external noopener noreferrer">LibreCode</a>, a Brazilian technology cooperative, where I work on products, infrastructure, technical strategy and the long-term sustainability of free software.</p>

    <h2>Building free software</h2>
    <p>A central part of my work is <a href="https://libresign.coop/" target="_blank" rel="external noopener noreferrer">LibreSign</a>, an open source electronic signature platform that I maintain and help develop with its community. LibreSign is recognized as a <a href="https://www.digitalpublicgoods.net/r/libresign" target="_blank" rel="external noopener noreferrer">Digital Public Good</a> and is part of a broader effort to create digital infrastructure that organizations can operate, inspect and improve without depending on proprietary platforms. Its source code and development activity are public on <a href="https://github.com/LibreSign/libresign" target="_blank" rel="external noopener noreferrer">GitHub</a>.</p>

    <p>My work also involves technologies such as <a href="https://nextcloud.com/" target="_blank" rel="external noopener noreferrer">Nextcloud</a> and other free software used by organizations that need control over their data and infrastructure. I am especially interested in projects where technical quality, privacy, interoperability and sustainable business models need to work together.</p>

    <h2>Communities and knowledge sharing</h2>
    <p>Free software has shaped much of my professional path. I organize <a href="https://github.com/PHPRio" target="_blank" rel="external noopener noreferrer">PHPRio</a> and contribute to technical communities through events, discussions, code and public documentation. I see community building as part of engineering work: software becomes more resilient when knowledge, decisions and opportunities to contribute are shared.</p>

    <p>I also speak about software development, free software, digital autonomy and the practical challenges of maintaining open projects. You can find part of this work in my <a href="{{ rtrim($page->baseUrl, '/') }}/talks">talks</a>.</p>

    <h2>Research and public work</h2>
    <p>Alongside industry work, I maintain an academic and research track connected to software development and free software. This site is also a public archive of that work, including <a href="{{ rtrim($page->baseUrl, '/') }}/articles">articles and research</a>, presentations and technical material.</p>

    <p>I try to keep the same principle across these different areas: important technical work should be understandable, verifiable and reusable by other people.</p>

    <h2>Profiles</h2>
    <p>Public profiles and records related to my professional, technical and academic work.</p>
    <p>@include('_partials.author.profile-links')</p>
</article>
@endsection
