{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@if (($page->author['profiles'] ?? []) !== [])
    @foreach ($page->author['profiles'] as $profile)
        <a href="{{ $profile['url'] }}" target="_blank" rel="me external noopener noreferrer">{{ $profile['label'] }}</a>@if (! $loop->last){{ $separator ?? ' · ' }}@endif
    @endforeach
@endif
