{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<article class="content">
    <p class="meta">{{ date('d/m/Y', $page->date) }}</p>
    <h1>{{ $page->title }}</h1>
    @if ($page->description)
        <p class="lead">{{ $page->description }}</p>
    @endif
    @yield('content')
</article>
@endsection
