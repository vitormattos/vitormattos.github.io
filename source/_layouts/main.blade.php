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

    $alternateLocale = $isEnglish ? 'pt-BR' : 'en';
    $alternateHref = $alternatePath !== null ? rtrim((string) $page->baseUrl, '/') . $alternatePath : null;
    $currentPath = '/' . trim((string) $page->getPath(), '/');
    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
    $isAbout = $currentPath === $aboutPath;
    $isArticles = $currentPath === $articlesPath || str_starts_with($currentPath, $articlesPath . '/');
    $isTalks = $currentPath === $talksPath || str_starts_with($currentPath, $talksPath . '/');
    $isTalksCatalog = $currentPath === $talksPath;
@endphp
<!doctype html>
<html lang="{{ $locale }}"
    @if ($alternateHref !== null) data-alternate-locale="{{ $alternateLocale }}" data-alternate-url="{{ $alternateHref }}" @endif>

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
    <script>
        (() => {
            const storageKey = 'site-locale';
            const root = document.documentElement;
            const currentLocale = root.lang === 'pt-BR' ? 'pt-BR' : 'en';
            const alternateLocale = root.dataset.alternateLocale;
            const alternateUrl = root.dataset.alternateUrl;
            let preferredLocale = null;

            try {
                const storedLocale = localStorage.getItem(storageKey);
                if (storedLocale === 'en' || storedLocale === 'pt-BR') {
                    preferredLocale = storedLocale;
                }
            } catch (_) {
                // Fall back to the browser language when storage is unavailable.
            }

            if (preferredLocale === null) {
                const browserLocale = (navigator.languages?.[0] ?? navigator.language ?? 'en').toLowerCase();
                preferredLocale = browserLocale.startsWith('pt') ? 'pt-BR' : 'en';
            }

            if (alternateUrl && alternateLocale === preferredLocale && currentLocale !== preferredLocale) {
                window.location.replace(alternateUrl);
            }

            window.addEventListener('DOMContentLoaded', () => {
                const languageSwitch = document.querySelector('[data-language-switch]');
                languageSwitch?.addEventListener('click', () => {
                    const selectedLocale = languageSwitch.dataset.locale;
                    if (selectedLocale !== 'en' && selectedLocale !== 'pt-BR') return;

                    try {
                        localStorage.setItem(storageKey, selectedLocale);
                    } catch (_) {
                        // Navigation still works when storage is unavailable.
                    }
                });
            });
        })();
    </script>
    <title>{{ $page->title ? $page->title . ' · ' : '' }}{{ $page->siteName }}</title>
    @include('_partials.seo')
    @viteRefresh()
    <link rel="stylesheet" href="{{ $page->baseUrl }}{{ vite('source/_assets/scss/main.scss') }}">
    @stack('head')
</head>

<body>
    @include('_partials.preview-badge')
    <a class="skip-link" href="#main-content">{{ $isEnglish ? 'Skip to content' : 'Pular para o conteúdo' }}</a>
    <header @class(['site-header', 'site-header--talks' => $isTalksCatalog])>
        <div class="site-header__bar">
            <a class="site-identity" href="{{ $page->baseUrl }}{{ $homePath }}"
                aria-label="{{ $page->author['name'] }}">
                <img class="site-identity__avatar" src="{{ $page->author['avatar'] }}" alt="" width="56"
                    height="56">
                <span class="site-identity__copy">
                    <strong>{{ $page->author['name'] }}</strong>
                    <span>{{ $isEnglish ? 'free software · technology' : 'software livre · tecnologia' }}</span>
                </span>
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
                            hreflang="{{ $alternateLocale }}" data-language-switch
                            data-locale="{{ $alternateLocale }}"
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
        <p>
            <a class="rss-link"
                href="{{ rtrim($page->siteUrl, '/') }}{{ $isEnglish ? '/feed.xml' : '/pt-BR/feed.xml' }}">
                <span class="rss-link__icon" data-ui-icon="rss" aria-hidden="true"></span>
                <span>RSS</span>
            </a>
        </p>
    </footer>
    <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/social-icons.js') }}"></script>
    @stack('scripts')
</body>

</html>
