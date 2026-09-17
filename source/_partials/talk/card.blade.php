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
    $hasArchivedPdf = false;
    if ($talk->slidesId ?? false) {
        $sourceDirectory = $type === 'slideshare' ? 'slideshare' : 'slides.com';
        $deckDir = 'presentations/' . $sourceDirectory . '/' . $talk->slidesId;
        $manifestPath = $deckDir . '/export.json';
        if (is_file($manifestPath)) {
            try {
                $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
                $hasArchivedPdf = isset($manifest['pdf']['url']) && $manifest['pdf']['url'] !== '';
            } catch (Throwable) {
                $hasArchivedPdf = false;
            }
        }
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

        <div class="talk-card__meta-line">
            @if ($talk->date ?? false)
                <span><time
                        datetime="{{ date('Y-m-d', $talk->date) }}">{{ date($isEnglish ? 'M d, Y' : 'd/m/Y', $talk->date) }}</time></span>
            @endif
            @if ($presentation['slideCount'] ?? 0)
                <span>{{ $presentation['slideCount'] }} slides</span>
            @endif
            @if ($presentation['language'] ?? false)
                <span>{{ $presentation['language'] }}</span>
            @endif
        </div>

        @if ($topics !== [])
            <nav class="talk-card__tags" aria-label="{{ $isEnglish ? 'Topics' : 'Tópicos' }}">
                @foreach ($topics as $topic => $label)
                    <a class="talk-tag"
                        href="{{ $page->baseUrl }}{{ $tagIndexPath }}?tag={{ rawurlencode($topic) }}">{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        <div class="talk-card__footer">
            <div class="talk-card__formats"
                aria-label="{{ $isEnglish ? 'Available formats' : 'Formatos disponíveis' }}">
                <span class="format-badge">{{ $type }}</span>
                @if ($presentation['localHtml'] ?? false)
                    <span class="format-badge">HTML</span>
                @endif
                @if ($hasArchivedPdf || ($presentation['localPdf'] ?? false) || ($presentation['pdf'] ?? false))
                    <span class="format-badge">PDF</span>
                @endif
                @if ($presentation['pptx'] ?? false)
                    <span class="format-badge">PPTX</span>
                @endif
                @if ($presentation['video'] ?? false)
                    <span class="format-badge">{{ $isEnglish ? 'video' : 'vídeo' }}</span>
                @endif
            </div>
        </div>
    </div>
</article>
