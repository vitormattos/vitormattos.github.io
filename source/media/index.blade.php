---
locale: en
title: Media archive
description: Explore photographs, video recordings and audio interviews.
alternateUrl: /pt-BR/acervo
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
