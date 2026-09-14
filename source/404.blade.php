---
permalink: /404.html
locale: en
indexable: false
title: Page not found
description: The requested page could not be found on Vitor Mattos's site.
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
<section class="content" aria-labelledby="page-title">
    <h1 id="page-title">Page not found</h1>
    <p>The requested page could not be found.</p>
    <p><a href="{{ $page->baseUrl }}/">Return to the homepage</a> · <a href="{{ $page->baseUrl }}/pt-BR/">Ir para a página em português</a></p>
</section>
@endsection
