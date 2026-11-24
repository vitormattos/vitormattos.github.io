{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $mediaLocale = ($page->locale ?? 'en') === 'pt-BR' ? 'pt-BR' : 'en';
    $mediaEnglish = $mediaLocale === 'en';
    $mediaHub = $mediaEnglish ? '/media' : '/pt-BR/acervo';
    $mediaSlug = (string) ($page->slug ?? basename(trim((string) $page->getPath(), '/')));
    $mediaData = $page->mediaCatalog($talksEn, $talks);
    $relatedMedia = array_filter(
        (array) ($mediaData['items'] ?? []),
        static fn(array $item): bool => in_array($mediaSlug, $item[$mediaRelationKind] ?? [], true),
    );
@endphp
@if ($relatedMedia !== [])
    <section class="media-related" aria-labelledby="related-media-title">
        <h2 id="related-media-title">{{ $mediaEnglish ? 'Related media' : 'Mídias relacionadas' }}</h2>
        <ul>
            @foreach ($relatedMedia as $record)
                <li><a
                        href="{{ rtrim((string) $page->baseUrl, '/') }}{{ $mediaHub }}#{{ $record['id'] }}">{{ $record['title'][$mediaLocale] }}</a>
                </li>
            @endforeach
        </ul>
        <a
            href="{{ rtrim((string) $page->baseUrl, '/') }}{{ $mediaHub }}">{{ $mediaEnglish ? 'Explore the media archive' : 'Explorar o acervo' }}</a>
    </section>
@endif
