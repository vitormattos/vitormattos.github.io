{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@if (($page->author['profiles'] ?? []) !== [])
    @foreach ($page->author['profiles'] as $profileKey => $profile)
        <a class="profile-link" href="{{ $profile['url'] }}" target="_blank"
            rel="me external noopener noreferrer">
            <span class="profile-link__icon" data-brand-icon="{{ $profileKey }}" aria-hidden="true"></span>
            <span>{{ $profile['label'] }}</span>
        </a>@if (! $loop->last){{ $separator ?? ' · ' }}@endif
    @endforeach
@endif
