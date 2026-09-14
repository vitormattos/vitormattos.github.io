---
title: Talks
locale: en
pageType: CollectionPage
alternateUrl: /pt-BR/palestras/
description: Talks and presentations by Vitor Mattos about free software, PHP, LibreSign, software engineering and community building.
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="content" aria-labelledby="page-title">
    <header>
        <p class="eyebrow">Speaking</p>
        <h1 id="page-title">Talks</h1>
        <p class="lead">Talks and presentations about free software, PHP, LibreSign, software engineering and community building.</p>
    </header>

    @foreach ($talksEn as $talk)
        <article>
            <p class="meta"><time datetime="{{ date('Y-m-d', $talk->date) }}">{{ date('Y-m-d', $talk->date) }}</time></p>
            <h2><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h2>
            <p>{{ $talk->description }}</p>
        </article>
    @endforeach
</section>
@endsection
