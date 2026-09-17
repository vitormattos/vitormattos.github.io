{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}

@if (($page->environment ?? null) === 'preview')
    <div class="preview-badge" aria-hidden="true">
        <span>{{ $page->previewLabel ?? 'PR Preview' }}</span>
    </div>
@endif
