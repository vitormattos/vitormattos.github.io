{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $locale = $page->locale ?? $page->defaultLocale;
    $isEnglish = $locale === 'en';
    $homePath = $isEnglish ? '/' : '/pt-BR';
    $aboutPath = $isEnglish ? '/about' : '/pt-BR/sobre';
    $articlesPath = $isEnglish ? '/articles' : '/pt-BR/artigos';
    $talksPath = $isEnglish ? '/talks' : '/pt-BR/palestras';
    $alternatePath = null;
    if ($page->alternateUrl ?? false) {
        $rawAlternatePath = '/' . ltrim($page->alternateUrl, '/');
        $alternatePath = $rawAlternatePath === '/' ? '/' : rtrim($rawAlternatePath, '/');
    }

    $currentPath = '/' . trim((string) $page->getPath(), '/');
    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
    $isAbout = $currentPath === $aboutPath;
    $isArticles = $currentPath === $articlesPath || str_starts_with($currentPath, $articlesPath . '/');
    $isTalks = $currentPath === $talksPath || str_starts_with($currentPath, $talksPath . '/');
@endphp
<!doctype html>
<html lang="{{ $locale }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (() => {
            try {
                const storedTheme = localStorage.getItem('theme');
                if (storedTheme === 'light' || storedTheme === 'dark') {
                    document.documentElement.dataset.theme = storedTheme;
                }
            } catch (_) {
                // Fall back to prefers-color-scheme when storage is unavailable.
            }
        })();
    </script>
    <title>{{ $page->title ? $page->title . ' · ' : '' }}{{ $page->siteName }}</title>
    @include('_partials.seo')
    @viteRefresh()
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/main.scss') }}">
    @stack('head')
</head>

<body>
    <a class="skip-link" href="#main-content">{{ $isEnglish ? 'Skip to content' : 'Pular para o conteúdo' }}</a>
    <header class="site-header">
        <div class="site-header__bar">
            <a class="site-identity" href="{{ $page->baseUrl }}{{ $homePath }}"
                aria-label="{{ $page->author['name'] }} — {{ $isEnglish ? 'home' : 'início' }}">
                <span aria-hidden="true">VM</span>
            </a>

            <div class="site-header__actions">
                <nav class="site-nav" aria-label="{{ $isEnglish ? 'Main' : 'Principal' }}">
                    <a @class(['site-nav__link', 'is-active' => $isAbout]) href="{{ $page->baseUrl }}{{ $aboutPath }}"
                        @if ($isAbout) aria-current="page" @endif>{{ $isEnglish ? 'About' : 'Sobre' }}</a>
                    <a @class(['site-nav__link', 'is-active' => $isArticles]) href="{{ $page->baseUrl }}{{ $articlesPath }}"
                        @if ($isArticles) aria-current="page" @endif>{{ $isEnglish ? 'Articles' : 'Artigos' }}</a>
                    <a @class(['site-nav__link', 'is-active' => $isTalks]) href="{{ $page->baseUrl }}{{ $talksPath }}"
                        @if ($isTalks) aria-current="page" @endif>{{ $isEnglish ? 'Talks' : 'Palestras' }}</a>
                </nav>

                <div class="site-controls">
                    @if ($alternatePath !== null)
                        <a class="language-switch" href="{{ $page->baseUrl }}{{ $alternatePath }}"
                            hreflang="{{ $isEnglish ? 'pt-BR' : 'en' }}"
                            aria-label="{{ $isEnglish ? 'Ver esta página em português' : 'View this page in English' }}">
                            {{ $isEnglish ? 'PT' : 'EN' }}
                        </a>
                    @endif
                    @include('_partials.theme-toggle')
                </div>
            </div>
        </div>
    </header>
    <main id="main-content">
        @yield('body')
    </main>
    <footer>
        <p>{{ $isEnglish ? 'Content and code published with transparency and version control.' : 'Conteúdo e código publicados com transparência e controle de versão.' }}
        </p>
        <p><a href="{{ rtrim($page->siteUrl, '/') }}{{ $isEnglish ? '/feed.xml' : '/pt-BR/feed.xml' }}">RSS</a></p>
    </footer>
    @stack('scripts')
</body>

</html>
