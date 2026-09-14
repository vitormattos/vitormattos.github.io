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
    <title>{{ $page->title ? $page->title.' · ' : '' }}{{ $page->siteName }}</title>
    @include('_partials.seo')
    @viteRefresh()
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/main.scss') }}">
</head>
<body>
<a class="skip-link" href="#main-content">{{ $isEnglish ? 'Skip to content' : 'Pular para o conteúdo' }}</a>
<header class="site-header">
    <a href="{{ $page->baseUrl }}{{ $isEnglish ? '/' : '/pt-BR/' }}">Vitor Mattos</a>
    <nav aria-label="{{ $isEnglish ? 'Main' : 'Principal' }}">
        <a href="{{ $page->baseUrl }}{{ $isEnglish ? '/articles/' : '/pt-BR/artigos/' }}">{{ $isEnglish ? 'Articles' : 'Artigos' }}</a>
        <a href="{{ $page->baseUrl }}{{ $isEnglish ? '/talks/' : '/pt-BR/palestras/' }}">{{ $isEnglish ? 'Talks' : 'Palestras' }}</a>
        <a href="{{ $page->author['github'] }}" rel="me">GitHub</a>
        <a href="{{ $page->author['linkedin'] }}" rel="me">LinkedIn</a>
        @if ($page->alternateUrl ?? false)
            <a href="{{ $page->baseUrl }}{{ $page->alternateUrl }}" hreflang="{{ $isEnglish ? 'pt-BR' : 'en' }}">
                {{ $isEnglish ? 'Português' : 'English' }}
            </a>
        @endif
    </nav>
</header>
<main id="main-content">
    @yield('body')
</main>
<footer>
    <p>{{ $isEnglish ? 'Content and code published with transparency and version control.' : 'Conteúdo e código publicados com transparência e controle de versão.' }}</p>
    <p><a href="{{ rtrim($page->siteUrl, '/') }}{{ $isEnglish ? '/feed.xml' : '/pt-BR/feed.xml' }}">RSS</a></p>
</footer>
</body>
</html>
