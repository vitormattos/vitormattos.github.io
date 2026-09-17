{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $page->presentation ?? [];
    $type = $presentation['type'] ?? 'external';
    $isEnglish = ($page->locale ?? 'en') === 'en';
    $archivedPdf = $presentation['pdf'] ?? null;
    $archivedOriginal = $presentation['original'] ?? null;
    $archivedPptx = $presentation['pptx'] ?? null;
    $archivedThumbnail = $presentation['thumbnail'] ?? null;

    if ($page->slidesId ?? false) {
        $sourceDirectory = $type === 'slideshare' ? 'slideshare' : 'slides.com';
        $manifestPath = 'presentations/' . $sourceDirectory . '/' . $page->slidesId . '/export.json';
        if (is_file($manifestPath)) {
            try {
                $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
                $archivedPdf ??= $manifest['assets']['pdf']['url'] ?? ($manifest['pdf']['url'] ?? null);
                $archivedOriginal ??= $manifest['assets']['original']['url'] ?? null;
                $archivedPptx ??= $manifest['assets']['pptx']['url'] ?? null;
                $archivedThumbnail ??= $manifest['assets']['thumbnail']['url'] ?? null;
                if (!$archivedThumbnail) {
                    $candidate = 'presentations/' . $sourceDirectory . '/' . $page->slidesId . '/thumbnail.';
                    foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
                        if (is_file($candidate . $extension)) {
                            $archivedThumbnail = '/' . $candidate . $extension;
                            break;
                        }
                    }
                }
            } catch (Throwable) {
                // Keep front matter values when an archive manifest is unavailable or malformed.
            }
        }
    }

    $slideShareDownloads = [];
    if ($archivedPdf) {
        $slideShareDownloads[$archivedPdf] = [
            'href' => $archivedPdf,
            'label' => 'PDF',
            'description' => $isEnglish ? 'Archived PDF' : 'PDF arquivado',
        ];
    }
    if ($archivedPptx) {
        $slideShareDownloads[$archivedPptx] = [
            'href' => $archivedPptx,
            'label' => 'PPTX',
            'description' => $isEnglish ? 'Archived PowerPoint' : 'PowerPoint arquivado',
        ];
    }
    if ($archivedOriginal) {
        $originalPath = parse_url($archivedOriginal, PHP_URL_PATH) ?: '';
        $originalExtension = strtoupper(pathinfo($originalPath, PATHINFO_EXTENSION));
        $slideShareDownloads[$archivedOriginal] ??= [
            'href' => $archivedOriginal,
            'label' => $originalExtension ?: ($isEnglish ? 'Original' : 'Original'),
            'description' => $isEnglish ? 'Original SlideShare file' : 'Arquivo original do SlideShare',
        ];
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
                    <a class="presentation-action presentation-action--primary" href="{{ $presentation['url'] }}"
                        target="_blank" rel="external noopener noreferrer">
                        <span aria-hidden="true">↗</span>
                        <span>{{ $isEnglish ? 'Open original' : 'Abrir original' }}</span>
                    </a>
                @endif

                @if ($archivedPdf)
                    <a class="presentation-action" href="{{ $archivedPdf }}" target="_blank"
                        rel="external noopener noreferrer"><span aria-hidden="true">↓</span><span>{{ $isEnglish ? 'Download PDF' : 'Baixar PDF' }}</span></a>
                @endif

                @if ($presentation['video'] ?? false)
                    <a class="presentation-action" href="{{ $presentation['video'] }}" target="_blank"
                        rel="external noopener noreferrer"><span aria-hidden="true">▶</span><span>{{ $isEnglish ? 'Video' : 'Vídeo' }}</span></a>
                @endif

                <details class="presentation-action-menu">
                    <summary class="presentation-action presentation-action--secondary"><span
                            aria-hidden="true">&lt;/&gt;</span><span>{{ $isEnglish ? 'Source' : 'Fonte' }}</span><span
                            aria-hidden="true">⌄</span></summary>
                    <div class="presentation-action-menu__panel">
                        <a href="{{ $page->baseUrl }}{{ $presentation['localHtml'] }}"
                            download><strong>HTML</strong><small>{{ $isEnglish ? 'Archived slide markup' : 'Marcação arquivada dos slides' }}</small></a>
                        @if ($presentation['localCss'] ?? false)
                            <a href="{{ $page->baseUrl }}{{ $presentation['localCss'] }}"
                                download><strong>CSS</strong><small>{{ $isEnglish ? 'Presentation styles' : 'Estilos da apresentação' }}</small></a>
                        @endif
                        @if ($presentation['metadata'] ?? false)
                            <a href="{{ $page->baseUrl }}{{ $presentation['metadata'] }}"
                                download><strong>JSON</strong><small>{{ $isEnglish ? 'Synced metadata' : 'Metadados sincronizados' }}</small></a>
                        @endif
                    </div>
                </details>
            </div>
        </div>
        <div class="presentation-stage"><iframe src="{{ $presentation['embed'] }}" title="{{ $page->title }}"
                loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
    </div>
@elseif ($type === 'slideshare')
    <div class="presentation-frame">
        <div class="presentation-toolbar presentation-toolbar--archive">
            <div class="presentation-toolbar__context">
                <span class="presentation-toolbar__context-icon" aria-hidden="true">◫</span>
                <span>{{ $isEnglish ? 'Archived presentation' : 'Apresentação arquivada' }}</span>
                <small>SlideShare</small>
            </div>
            <div class="presentation-toolbar__actions">
                @if ($presentation['url'] ?? false)
                    <a class="presentation-action presentation-action--primary" href="{{ $presentation['url'] }}"
                        target="_blank" rel="external noopener noreferrer"><span
                            aria-hidden="true">↗</span><span>{{ $isEnglish ? 'Open original' : 'Abrir original' }}</span></a>
                @endif
                @if (count($slideShareDownloads) === 1)
                    @php($download = reset($slideShareDownloads))
                    <a class="presentation-action" href="{{ $download['href'] }}" target="_blank"
                        rel="external noopener noreferrer"><span aria-hidden="true">↓</span><span>{{ $isEnglish ? 'Download ' : 'Baixar ' }}{{ $download['label'] }}</span></a>
                @elseif (count($slideShareDownloads) > 1)
                    <details class="presentation-action-menu">
                        <summary class="presentation-action"><span
                                aria-hidden="true">↓</span><span>{{ $isEnglish ? 'Download' : 'Baixar' }}</span><span
                                aria-hidden="true">⌄</span></summary>
                        <div class="presentation-action-menu__panel">
                            @foreach ($slideShareDownloads as $download)
                                <a href="{{ $download['href'] }}" target="_blank"
                                    rel="external noopener noreferrer"><strong>{{ $download['label'] }}</strong><small>{{ $download['description'] }}</small></a>
                            @endforeach
                        </div>
                    </details>
                @endif
                @if ($presentation['metadata'] ?? false)
                    <a class="presentation-action presentation-action--secondary"
                        href="{{ $page->baseUrl }}{{ $presentation['metadata'] }}" download>JSON</a>
                @endif
            </div>
        </div>
        <div class="presentation-stage">
            @if ($archivedThumbnail)
                <img src="{{ str_starts_with($archivedThumbnail, 'http') ? $archivedThumbnail : $page->baseUrl . $archivedThumbnail }}"
                    alt="{{ $page->title }}" loading="lazy">
            @else
                <div class="presentation-fallback">
                    <p><a href="{{ $presentation['url'] }}" target="_blank"
                            rel="external noopener noreferrer">{{ $isEnglish ? 'Open presentation on SlideShare' : 'Abrir apresentação no SlideShare' }}</a>
                    </p>
                </div>
            @endif
        </div>
    </div>
@elseif (in_array($type, ['slides.com', 'iframe'], true) && ($presentation['embed'] ?? false))
    <div class="presentation-frame">
        <div class="presentation-toolbar"><span>{{ $isEnglish ? 'Presentation' : 'Apresentação' }}</span>
            <div class="presentation-toolbar__actions">
                @if ($presentation['url'] ?? false)
                    <a href="{{ $presentation['url'] }}" target="_blank"
                        rel="external noopener noreferrer">{{ $isEnglish ? 'Open original' : 'Abrir original' }}</a>
                    @endif @if ($presentation['video'] ?? false)
                        <a href="{{ $presentation['video'] }}" target="_blank"
                            rel="external noopener noreferrer">{{ $isEnglish ? 'Video' : 'Vídeo' }}</a>
                    @endif
            </div>
        </div>
        <div class="presentation-stage"><iframe src="{{ $presentation['embed'] }}" title="{{ $page->title }}"
                loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
    </div>
@elseif ($type === 'pdf' && ($presentation['url'] ?? false))
    <div class="presentation-frame">
        <div class="presentation-fallback">
            <p><a href="{{ $presentation['url'] }}" target="_blank"
                    rel="external noopener noreferrer">{{ $isEnglish ? 'Open presentation PDF' : 'Abrir PDF da apresentação' }}</a>
            </p>
        </div>
    </div>
@elseif ($presentation['url'] ?? false)
    <div class="presentation-frame">
        <div class="presentation-fallback">
            <p><a href="{{ $presentation['url'] }}" target="_blank"
                    rel="external noopener noreferrer">{{ $isEnglish ? 'Open presentation' : 'Abrir apresentação' }}</a>
            </p>
        </div>
    </div>
@endif
