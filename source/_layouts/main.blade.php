{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $locale = $page->locale ?? $page->defaultLocale;
    $isEnglish = $locale === 'en';
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $page->description ?? $page->siteDescription }}">
    <title>{{ $page->title ? $page->title.' · ' : '' }}{{ $page->siteName }}</title>
    @if ($page->alternateUrl ?? false)
        <link rel="alternate" hreflang="{{ $isEnglish ? 'pt-BR' : 'en' }}" href="{{ $page->baseUrl }}{{ $page->alternateUrl }}">
    @endif
    <link rel="stylesheet" href="{{ $page->baseUrl }}/assets/css/main.css">
</head>
<body>
<header class="site-header">
    <a href="{{ $page->baseUrl }}{{ $isEnglish ? '/' : '/pt-BR/' }}">Vitor Mattos</a>
    <nav aria-label="{{ $isEnglish ? 'Main' : 'Principal' }}">
        <a href="{{ $page->baseUrl }}{{ $isEnglish ? '/#articles' : '/pt-BR/#artigos' }}">{{ $isEnglish ? 'Articles' : 'Artigos' }}</a>
        <a href="{{ $page->baseUrl }}{{ $isEnglish ? '/#talks' : '/pt-BR/#palestras' }}">{{ $isEnglish ? 'Talks' : 'Palestras' }}</a>
        <a href="https://github.com/vitormattos">GitHub</a>
        @if ($page->alternateUrl ?? false)
            <a href="{{ $page->baseUrl }}{{ $page->alternateUrl }}" hreflang="{{ $isEnglish ? 'pt-BR' : 'en' }}">
                {{ $isEnglish ? 'Português' : 'English' }}
            </a>
        @endif
    </nav>
</header>
<main>
    @yield('body')
</main>
<footer>
    <p>{{ $isEnglish ? 'Content and code published with transparency and version control.' : 'Conteúdo e código publicados com transparência e controle de versão.' }}</p>
</footer>
</body>
</html>
