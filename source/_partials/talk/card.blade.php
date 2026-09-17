{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $talk->presentation ?? [];
    $type = $presentation['type'] ?? 'external';
    $isEnglish = ($talk->locale ?? 'en') === 'en';
    $sourcePath = isset($presentation['source']) ? '/' . ltrim($presentation['source'], '/') : null;
    $thumbnailId = $sourcePath ? 'thumb-' . substr(sha1($sourcePath), 0, 10) : null;
    $topics = \App\Presentations\TalkTopics::resolve($talk);
    $topicKeys = array_keys($topics);
    $tagIndexPath = $tagIndexPath ?? ($isEnglish ? '/talks/' : '/pt-BR/palestras/');
    $encodedTags = htmlspecialchars(
        json_encode($topicKeys, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8',
    );

    $thumbnail = $page->presentationThumbnail($talk);
    $archivedPdf = $presentation['localPdf'] ?? ($presentation['pdf'] ?? null);
    if ($talk->slidesId ?? false) {
        $sourceDirectory = $type === 'slideshare' ? 'slideshare' : 'slides.com';
        $deckDir = 'presentations/' . $sourceDirectory . '/' . $talk->slidesId;
        $manifestPath = $deckDir . '/export.json';
        if (is_file($manifestPath)) {
            try {
                $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
                $archivedPdf ??= $manifest['assets']['pdf']['url'] ?? ($manifest['pdf']['url'] ?? null);
            } catch (Throwable) {
                // Keep front matter values when the archive manifest is unavailable or malformed.
            }
        }
    }
    $pdfHref = null;
    if (is_string($archivedPdf) && $archivedPdf !== '') {
        $pdfHref = str_starts_with($archivedPdf, 'http')
            ? $archivedPdf
            : rtrim((string) $page->baseUrl, '/') . '/' . ltrim($archivedPdf, '/');
    }
@endphp
<article class="talk-card" data-talk-tags="{!! $encodedTags !!}">
    <a class="talk-card__preview" href="{{ $talk->getUrl() }}" aria-label="{{ $talk->title }}">
        @if ($thumbnail)
            <img src="{{ $thumbnail['url'] }}" alt="" loading="lazy"
                @if ($thumbnail['width'] > 0 && $thumbnail['height'] > 0) width="{{ $thumbnail['width'] }}" height="{{ $thumbnail['height'] }}" @endif>
        @elseif ($type === 'reveal' && $sourcePath)
            <div class="presentation-thumbnail" aria-hidden="true">
                <div class="reveal js-reveal-deck" id="{{ $thumbnailId }}" data-presentation-mode="thumbnail">
                    <div class="slides">
                        <section data-markdown="{{ $page->baseUrl }}{{ $sourcePath }}"
                            data-separator="^\r?\n---\r?\n$" data-separator-vertical="^\r?\n--\r?\n$"
                            data-separator-notes="^Notes?:"></section>
                    </div>
                </div>
            </div>
        @elseif (in_array($type, ['slides.com', 'iframe'], true) && ($presentation['embed'] ?? false))
            <iframe src="{{ $presentation['embed'] }}" title="" loading="lazy" tabindex="-1"
                aria-hidden="true"></iframe>
        @else
            <div class="presentation-fallback"><strong>{{ $talk->title }}</strong></div>
        @endif
    </a>

    <div class="talk-card__body">
        <h2 class="talk-card__title"><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h2>
        <p class="talk-card__description">{{ $talk->description }}</p>

        @if ($talk->date ?? false)
            <div class="talk-card__meta-line">
                <span><time
                        datetime="{{ date('Y-m-d', $talk->date) }}">{{ date($isEnglish ? 'M d, Y' : 'd/m/Y', $talk->date) }}</time></span>
            </div>
        @endif

        @if ($topics !== [])
            <nav class="talk-card__tags" aria-label="{{ $isEnglish ? 'Topics' : 'Tópicos' }}">
                @foreach ($topics as $topic => $label)
                    <a class="talk-tag"
                        href="{{ $page->baseUrl }}{{ $tagIndexPath }}?tag={{ rawurlencode($topic) }}">{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        @if ($pdfHref)
            <div class="talk-card__footer">
                <div class="talk-card__formats"
                    aria-label="{{ $isEnglish ? 'Downloads' : 'Downloads' }}">
                    <a class="format-badge" href="{{ $pdfHref }}" target="_blank" rel="external noopener noreferrer"
                        aria-label="{{ $isEnglish ? 'Download PDF' : 'Baixar PDF' }}">PDF</a>
                </div>
            </div>
        @endif
    </div>
</article>
