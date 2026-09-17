{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $presentation = $talk->presentation ?? [];
    $type = $presentation['type'] ?? 'external';
    $isEnglish = ($page->locale ?? ($page->defaultLocale ?? 'en')) === 'en';
    $sourcePath = isset($presentation['source']) ? '/' . ltrim($presentation['source'], '/') : null;
    $localHtml = $presentation['localHtml'] ?? null;
    $localCss = $presentation['localCss'] ?? null;
    if (!$localCss && $localHtml) {
        $cssCandidate = preg_replace('/\.html$/', '.css', $localHtml);
        if (is_string($cssCandidate) && is_file(ltrim($cssCandidate, '/'))) {
            $localCss = $cssCandidate;
        }
    }
    $revealPreviewable = $type === 'reveal' && $sourcePath;
    $slidesComPreviewable = $type === 'slides.com' && $localHtml;
    $previewable = $revealPreviewable || $slidesComPreviewable;
    $previewSource = $sourcePath ?? $localHtml;
    $thumbnailId = $previewable ? 'thumb-' . substr(sha1((string) $previewSource), 0, 10) : null;
    $topics = \App\Presentations\TalkTopics::localized(
        \App\Presentations\TalkTopics::resolve($talk),
        $isEnglish ? 'en' : 'pt-BR',
    );
    $topicKeys = array_keys($topics);
    $tagIndexPath = $tagIndexPath ?? ($isEnglish ? '/talks/' : '/pt-BR/palestras/');
    $encodedTags = htmlspecialchars(
        json_encode($topicKeys, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8',
    );

    $thumbnail = $page->presentationThumbnail($talk);
    $shareLabel = $isEnglish ? 'Share presentation' : 'Compartilhar apresentação';
    $copiedLabel = $isEnglish ? 'Link copied' : 'Link copiado';
    $previewLabel = $isEnglish ? 'Preview' : 'Pré-visualizar';
    $closePreviewLabel = $isEnglish ? 'Close preview' : 'Fechar pré-visualização';
    $fullscreenLabel = $isEnglish ? 'Open presentation' : 'Abrir apresentação';
    $activityTimestamp = \App\Presentations\TalkTopics::activityTimestamp($talk);
    $publishedTimestamp = (int) ($talk->date ?? 0);
    $wasUpdated = $activityTimestamp > $publishedTimestamp;
    $activityLabel = $wasUpdated ? ($isEnglish ? 'Updated' : 'Atualizado') : ($isEnglish ? 'Published' : 'Publicado');
@endphp
<article class="talk-card" data-talk-tags="{!! $encodedTags !!}"
    @if ($previewable) data-talk-preview-card @endif>
    <div class="talk-card__media" @if ($previewable) data-talk-preview-media @endif>
        <a class="talk-card__preview" href="{{ $talk->getUrl() }}" aria-label="{{ $talk->title }}">
            @if ($thumbnail)
                <img src="{{ $thumbnail['url'] }}" alt="" loading="lazy"
                    @if ($thumbnail['width'] > 0 && $thumbnail['height'] > 0) width="{{ $thumbnail['width'] }}" height="{{ $thumbnail['height'] }}" @endif>
            @elseif (!$previewable && in_array($type, ['slides.com', 'iframe'], true) && ($presentation['embed'] ?? false))
                <iframe src="{{ $presentation['embed'] }}" title="" loading="lazy" tabindex="-1"
                    aria-hidden="true"></iframe>
            @elseif (!$previewable)
                <div class="presentation-fallback"><strong>{{ $talk->title }}</strong></div>
            @endif
        </a>

        @if ($previewable)
            <div class="talk-card__live-preview" data-talk-live-preview
                data-preview-overlay="{{ $thumbnail ? 'true' : 'false' }}"
                @if ($thumbnail) hidden @endif>
                <div class="reveal js-talk-preview-deck" id="{{ $thumbnailId }}" data-presentation-mode="thumbnail"
                    @if ($slidesComPreviewable) data-preview-html="{{ $page->baseUrl }}{{ $localHtml }}" @endif
                    @if ($slidesComPreviewable && $localCss) data-preview-css="{{ $page->baseUrl }}{{ $localCss }}" @endif>
                    <div class="slides">
                        @if ($revealPreviewable)
                            <section data-markdown="{{ $page->baseUrl }}{{ $sourcePath }}"
                                data-separator="^\r?\n---\r?\n$" data-separator-vertical="^\r?\n--\r?\n$"
                                data-separator-notes="^Notes?:"></section>
                        @endif
                    </div>
                </div>
            </div>

            <div class="talk-card__preview-actions">
                <button class="talk-card__preview-action talk-card__preview-toggle" type="button"
                    data-talk-preview-toggle data-tooltip="{{ $previewLabel }}" aria-label="{{ $previewLabel }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"></path>
                        <circle cx="12" cy="12" r="2.6"></circle>
                    </svg>
                </button>
                <button class="talk-card__preview-action talk-card__preview-active-action" type="button"
                    data-talk-preview-fullscreen data-tooltip="{{ $fullscreenLabel }}"
                    aria-label="{{ $fullscreenLabel }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"></path>
                    </svg>
                </button>
                <button class="talk-card__preview-action talk-card__preview-active-action" type="button"
                    data-talk-preview-close data-tooltip="{{ $closePreviewLabel }}"
                    aria-label="{{ $closePreviewLabel }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M3 3l18 18"></path>
                        <path
                            d="M10.6 6.2A10.9 10.9 0 0 1 12 6c6.1 0 9.5 6 9.5 6a17 17 0 0 1-2.8 3.6M14.1 14.1A3 3 0 0 1 9.9 9.9M6.1 6.1C3.7 7.9 2.5 12 2.5 12s3.4 6 9.5 6a10.8 10.8 0 0 0 4.1-.8">
                        </path>
                    </svg>
                </button>
            </div>
        @endif
    </div>

    <div class="talk-card__body">
        <h2 class="talk-card__title"><a href="{{ $talk->getUrl() }}">{{ $talk->title }}</a></h2>
        <p class="talk-card__description">{{ $talk->description }}</p>

        <div class="talk-card__meta-line">
            @if ($activityTimestamp > 0)
                <span>{{ $activityLabel }} <time
                        datetime="{{ date('Y-m-d', $activityTimestamp) }}">{{ date($isEnglish ? 'M d, Y' : 'd/m/Y', $activityTimestamp) }}</time></span>
            @endif
            <button class="talk-card__share" type="button" data-talk-share data-talk-url="{{ $talk->getUrl() }}"
                data-talk-title="{{ $talk->title }}" data-share-label="{{ $shareLabel }}"
                data-copied-label="{{ $copiedLabel }}" aria-label="{{ $shareLabel }}" title="{{ $shareLabel }}">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="18" cy="5" r="2.5"></circle>
                    <circle cx="6" cy="12" r="2.5"></circle>
                    <circle cx="18" cy="19" r="2.5"></circle>
                    <path d="m8.2 10.8 7.6-4.6M8.2 13.2l7.6 4.6"></path>
                </svg>
            </button>
        </div>

        @if ($topics !== [])
            <nav class="talk-card__tags" aria-label="{{ $isEnglish ? 'Topics' : 'Tópicos' }}">
                @foreach ($topics as $topic => $label)
                    <a class="talk-tag"
                        href="{{ $page->baseUrl }}{{ $tagIndexPath }}?tag={{ rawurlencode($topic) }}">{{ $label }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</article>
