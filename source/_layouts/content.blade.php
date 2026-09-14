{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<article class="content">
    <header>
        @if ($page->date ?? false)
            <p class="meta">
                <time datetime="{{ date('Y-m-d', $page->date) }}">{{ date(($page->locale ?? 'en') === 'pt-BR' ? 'd/m/Y' : 'Y-m-d', $page->date) }}</time>
            </p>
        @endif
        <h1>{{ $page->title }}</h1>
        @if ($page->description)
            <p class="lead">{{ $page->description }}</p>
        @endif
        <p class="meta">
            {{ ($page->locale ?? 'en') === 'pt-BR' ? 'Por' : 'By' }}
            <a href="{{ $page->baseUrl }}{{ ($page->locale ?? 'en') === 'pt-BR' ? '/pt-BR/' : '/' }}" rel="author">{{ $page->author['name'] }}</a>
        </p>
    </header>
    @yield('content')
</article>
@endsection
