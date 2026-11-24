---
locale: pt-BR
title: Acervo multimídia
description: Explore gravações, referências fotográficas, entrevistas e registros de eventos públicos.
alternateUrl: /media
indexable: true
---
{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@extends('_layouts.main')

@section('body')
    @include('_partials.media.catalog')
@endsection

@push('scripts')
    <script type="module" src="{{ $page->baseUrl }}{{ vite('source/_assets/js/media-catalog.js') }}"></script>
@endpush
