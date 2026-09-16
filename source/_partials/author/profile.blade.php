{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $profileLocale = $locale ?? ($page->locale ?? $page->defaultLocale);
    $profileSummary = $page->author['summary'][$profileLocale]
        ?? $page->author['summary'][$page->defaultLocale]
        ?? null;
@endphp
<div class="talks-profile">
    <div>
        <p class="talks-profile__name">{{ $page->author['name'] }}</p>
        @if ($profileSummary)
            <p class="talks-profile__summary">{{ $profileSummary }}</p>
        @endif
    </div>
    <img class="talks-profile__avatar" src="{{ $page->author['avatar'] }}" alt="" width="56" height="56">
    @if (($page->author['profiles'] ?? []) !== [])
        <div class="talks-profile__links">
            @include('_partials.author.profile-links', ['separator' => ' '])
        </div>
    @endif
</div>
