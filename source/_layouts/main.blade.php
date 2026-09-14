{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $page->description ?? $page->siteDescription }}">
    <title>{{ $page->title ? $page->title.' · ' : '' }}{{ $page->siteName }}</title>
    <link rel="stylesheet" href="{{ $page->baseUrl }}/assets/css/main.css">
</head>
<body>
<header class="site-header">
    <a href="{{ $page->baseUrl }}/">Vitor Mattos</a>
    <nav aria-label="Principal">
        <a href="{{ $page->baseUrl }}/#artigos">Artigos</a>
        <a href="{{ $page->baseUrl }}/#palestras">Palestras</a>
        <a href="https://github.com/vitormattos">GitHub</a>
    </nav>
</header>
<main>
    @yield('body')
</main>
<footer>
    <p>Conteúdo e código publicados com transparência e controle de versão.</p>
</footer>
</body>
</html>
