{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $page->presentation ?? [];
    $type = $presentation['type'] ?? 'external';
    $isEnglish = ($page->locale ?? 'en') === 'en';
    $archivedPdf = null;
    $archivedPdfIsLocal = false;

    if ($presentation['pdf'] ?? false) {
        $archivedPdf = $presentation['pdf'];
    } elseif ($page->slidesId ?? false) {
        $candidatePdf = '/presentations/slides.com/' . $page->slidesId . '/deck.pdf';
        if (is_file(ltrim($candidatePdf, '/'))) {
            $archivedPdf = $candidatePdf;
            $archivedPdfIsLocal = true;
        }
    }
@endphp
@if ($type === 'reveal')
    @include('_partials.talk.reveal')
@elseif ($type === 'slides.com' && ($presentation['localHtml'] ?? false))
    <div class="presentation-frame">
        <div class="presentation-toolbar presentation-toolbar--archive">
            <div class="presentation-toolbar__context">
                <span class="presentation-toolbar__context-icon" aria-hidden="true">◫</span>
                <span>{{ $isEnglish ? 'Web presentation' : 'Apresentação web' }}</span>
                <small>{{ $isEnglish ? 'Archived from Slides.com' : 'Arquivada do Slides.com' }}</small>
            </div>
            <div class="presentation-toolbar__actions">
                @if ($presentation['url'] ?? false)
                    <a class="presentation-action presentation-action--primary" href="{{ $presentation['url'] }}" rel="external">
                        <span aria-hidden="true">↗</span>
                        <span>{{ $isEnglish ? 'Open original' : 'Abrir original' }}</span>
                    </a>
                @endif

                @if ($archivedPdf || ($presentation['video'] ?? false))
                    <details class="presentation-action-menu">
                        <summary class="presentation-action">
                            <span aria-hidden="true">↓</span>
                            <span>{{ $isEnglish ? 'Download' : 'Baixar' }}</span>
                            <span aria-hidden="true">⌄</span>
                        </summary>
                        <div class="presentation-action-menu__panel">
                            @if ($archivedPdf)
                                <a href="{{ $archivedPdfIsLocal ? $page->baseUrl . $archivedPdf : $archivedPdf }}" download>
                                    <strong>PDF</strong>
                                    <small>{{ $isEnglish ? 'Portable document' : 'Documento portátil' }}</small>
                                </a>
                            @endif
                            @if ($presentation['video'] ?? false)
                                <a href="{{ $presentation['video'] }}" rel="external">
                                    <strong>{{ $isEnglish ? 'Video' : 'Vídeo' }}</strong>
                                    <small>{{ $isEnglish ? 'Watch recording' : 'Assistir gravação' }}</small>
                                </a>
                            @endif
                        </div>
                    </details>
                @endif

                <details class="presentation-action-menu">
                    <summary class="presentation-action presentation-action--secondary">
                        <span aria-hidden="true">&lt;/&gt;</span>
                        <span>{{ $isEnglish ? 'Source' : 'Fonte' }}</span>
                        <span aria-hidden="true">⌄</span>
                    </summary>
                    <div class="presentation-action-menu__panel">
                        <a href="{{ $page->baseUrl }}{{ $presentation['localHtml'] }}" download>
                            <strong>HTML</strong>
                            <small>{{ $isEnglish ? 'Archived slide markup' : 'Marcação arquivada dos slides' }}</small>
                        </a>
                        @if ($presentation['localCss'] ?? false)
                            <a href="{{ $page->baseUrl }}{{ $presentation['localCss'] }}" download>
                                <strong>CSS</strong>
                                <small>{{ $isEnglish ? 'Presentation styles' : 'Estilos da apresentação' }}</small>
                            </a>
                        @endif
                        @if ($presentation['metadata'] ?? false)
                            <a href="{{ $page->baseUrl }}{{ $presentation['metadata'] }}" download>
                                <strong>JSON</strong>
                                <small>{{ $isEnglish ? 'Synced metadata' : 'Metadados sincronizados' }}</small>
                            </a>
                        @endif
                    </div>
                </details>
            </div>
        </div>
        <div class="presentation-stage">
            <iframe src="{{ $presentation['embed'] }}" title="{{ $page->title }}" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </div>
@elseif (in_array($type, ['slides.com', 'iframe'], true) && ($presentation['embed'] ?? false))
    <div class="presentation-frame"><div class="presentation-toolbar"><span>{{ $isEnglish ? 'Presentation' : 'Apresentação' }}</span><div class="presentation-toolbar__actions">@if ($presentation['url'] ?? false)<a href="{{ $presentation['url'] }}" rel="external">{{ $isEnglish ? 'Open original' : 'Abrir original' }}</a>@endif @if ($presentation['video'] ?? false)<a href="{{ $presentation['video'] }}" rel="external">{{ $isEnglish ? 'Video' : 'Vídeo' }}</a>@endif</div></div><div class="presentation-stage"><iframe src="{{ $presentation['embed'] }}" title="{{ $page->title }}" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div></div>
@elseif ($type === 'pdf' && ($presentation['url'] ?? false))
    <div class="presentation-frame"><div class="presentation-fallback"><p><a href="{{ $presentation['url'] }}">{{ $isEnglish ? 'Open presentation PDF' : 'Abrir PDF da apresentação' }}</a></p></div></div>
@elseif ($presentation['url'] ?? false)
    <div class="presentation-frame"><div class="presentation-fallback"><p><a href="{{ $presentation['url'] }}">{{ $isEnglish ? 'Open presentation' : 'Abrir apresentação' }}</a></p></div></div>
@endif
