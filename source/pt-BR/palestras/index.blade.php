---
title: Palestras
locale: pt-BR
pageType: CollectionPage
alternateUrl: /talks/
description: Palestras e apresentações de Vitor Mattos sobre software livre, PHP, LibreSign, engenharia de software e construção de comunidades.
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="content" aria-labelledby="page-title">
    <header>
        <p class="eyebrow">Apresentações</p>
        <h1 id="page-title">Palestras</h1>
        <p class="lead">Palestras e apresentações sobre software livre, PHP, LibreSign, engenharia de software e construção de comunidades.</p>
    </header>

    @foreach ($talks as $talk)
        <article>
            <p class="meta"><time datetime="{{ date('Y-m-d', $talk->date) }}">{{ date('d/m/Y', $talk->date) }}</time></p>
            <h2><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h2>
            <p>{{ $talk->description }}</p>
        </article>
    @endforeach
</section>
@endsection
