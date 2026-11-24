{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $locale = ($page->locale ?? 'en') === 'pt-BR' ? 'pt-BR' : 'en';
    $en = $locale === 'en';
    $base = rtrim((string) $page->baseUrl, '/');
    $archive = $page->mediaCatalog($talksEn, $talks);
    $records = $archive['items'];
    $typeCounts = array_count_values(array_column($records, 'type'));
    $typeFilters = array_filter(
        $archive['types'],
        static fn($type) => isset($typeCounts[$type]) || $type === 'photo',
        ARRAY_FILTER_USE_KEY,
    );
    $years = array_values(array_unique(array_filter(array_column($records, 'year'))));
    rsort($years);
    $hasUndated = in_array(null, array_column($records, 'year'), true);
    $cities = array_keys($archive['cities']);
    sort($cities);
    $relatedPages = [];
    foreach (
        [[$talksEn, 'talks'], [$talks, 'talks'], [$articlesEn, 'articles'], [$articles, 'articles']]
        as [$collection, $kind]
    ) {
        foreach ($collection as $relatedPage) {
            $slug = (string) ($relatedPage->slug ?? basename(trim((string) $relatedPage->getPath(), '/')));
            $key = $kind . ':' . $slug;
            $language = (string) ($relatedPage->locale ?? 'en');
            if (!isset($relatedPages[$key]) || $language === $locale) {
                $relatedPages[$key] = [
                    'url' => $relatedPage->getUrl(),
                    'title' => $relatedPage->title,
                    'locale' => $language,
                ];
            }
        }
    }
@endphp

<div class="media-archive" data-media-catalog data-locale="{{ $locale }}"
    data-map-url="{{ $base }}/media-assets/world.geojson">
    <header class="media-heading">
        <p class="eyebrow">
            {{ $en ? 'PUBLIC RECORD · TALKS & COMMUNITIES' : 'MEMÓRIA PÚBLICA · PALESTRAS E COMUNIDADES' }}</p>
        <h1>{{ $page->title }}</h1>
        <p>{{ $en ? 'A history of sharing knowledge, building free software and meeting communities.' : 'Uma trajetória de compartilhamento de conhecimento, construção de software livre e encontros com comunidades.' }}
        </p>
    </header>

    <div class="media-controls" data-media-controls hidden>
        <div class="media-toolbar">
            <div class="media-views" role="group" aria-label="{{ $en ? 'Display mode' : 'Modo de visualização' }}">
                @foreach (['mosaic' => [$en ? 'All media' : 'Todas as mídias', '▦'], 'albums' => [$en ? 'Albums' : 'Álbuns', '▣'], 'map' => [$en ? 'Map' : 'Mapa', '⌖']] as $mode => [$name, $symbol])
                    <button type="button" data-view="{{ $mode }}"
                        aria-pressed="{{ $mode === 'mosaic' ? 'true' : 'false' }}"><span
                            aria-hidden="true">{{ $symbol }}</span> {{ $name }}</button>
                @endforeach
            </div>
            <label class="media-density">
                <span>{{ $en ? 'Size' : 'Tamanho' }}</span>
                <input type="range" min="1" max="3" value="2" data-density
                    aria-label="{{ $en ? 'Thumbnail size' : 'Tamanho das miniaturas' }}">
            </label>
        </div>
        <div class="media-type-filters" role="group" aria-label="{{ $en ? 'Media type' : 'Tipo de mídia' }}">
            <button type="button" data-type-filter="" aria-pressed="true">{{ $en ? 'All types' : 'Todos os tipos' }}
                <small>{{ count($records) }}</small></button>
            @foreach ($typeFilters as $type => $label)
                <button type="button" data-type-filter="{{ $type }}"
                    aria-pressed="false">{{ $label[$locale] }} <small>{{ $typeCounts[$type] ?? 0 }}</small></button>
            @endforeach
        </div>
        <form class="media-filters" data-filters role="search">
            <select name="type" hidden aria-label="{{ $en ? 'Media type' : 'Tipo de mídia' }}">
                <option value="">{{ $en ? 'All types' : 'Todos os tipos' }}</option>
                @foreach ($typeFilters as $type => $label)
                    <option value="{{ $type }}">{{ $label[$locale] }}</option>
                @endforeach
            </select>
            <label class="media-search">
                <span>{{ $en ? 'Search' : 'Buscar' }}</span>
                <input type="search" name="q"
                    placeholder="{{ $en ? 'Event, topic, recording…' : 'Evento, assunto, gravação…' }}">
            </label>
            <button class="media-advanced-toggle" type="button" data-advanced-toggle aria-expanded="false"
                aria-controls="media-advanced-filters">{{ $en ? 'Filters' : 'Filtros' }} <span
                    aria-hidden="true">▾</span></button>
            <div class="media-advanced-filters" id="media-advanced-filters" data-collapsed="true">
                <label>
                    <span>{{ $en ? 'Topic' : 'Assunto' }}</span>
                    <select name="topic">
                        <option value="">{{ $en ? 'All topics' : 'Todos os assuntos' }}</option>
                        @foreach ($archive['topics'] as $topic => $label)
                            <option value="{{ $topic }}">{{ $label[$locale] }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ $en ? 'City' : 'Cidade' }}</span>
                    <select name="city">
                        <option value="">{{ $en ? 'All cities' : 'Todas as cidades' }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ $en ? 'Year' : 'Ano' }}</span>
                    <select name="year">
                        <option value="">{{ $en ? 'All years' : 'Todos os anos' }}</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                        @if ($hasUndated)
                            <option value="unknown">{{ $en ? 'Undated' : 'Sem data' }}</option>
                        @endif
                    </select>
                </label>
            </div>
        </form>
        <div class="media-results">
            <div class="media-result-status">
                <output data-count aria-live="polite">{{ count($records) }}</output>
                <span data-active-scope hidden>{{ $en ? 'Filtered results' : 'Resultados filtrados' }}</span>
                <button type="button" data-clear hidden>{{ $en ? 'Clear filters' : 'Limpar filtros' }}</button>
            </div>
        </div>
    </div>

    <div class="media-browse">
        <div class="media-content">
            <div class="media-album-bar" data-album-bar hidden><button type="button"
                    data-album-back>{{ $en ? '← All albums' : '← Todos os álbuns' }}</button><strong
                    data-album-title></strong></div>
            <div class="media-albums" data-albums hidden></div>
            <section class="media-map-panel" data-map-panel hidden aria-labelledby="media-map-title">
                <div class="media-map-heading">
                    <div>
                        <h2 id="media-map-title">{{ $en ? 'Explore by place' : 'Explore por localidade' }}</h2>
                        <p data-map-summary aria-live="polite"></p>
                    </div>
                    <button type="button" class="media-map-fit" data-map-fit disabled>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M8 3H3v5m13-5h5v5M3 16v5h5m8 0h5v-5M8 12h8m-4-4v8" />
                        </svg>
                        {{ $en ? 'Fit locations' : 'Enquadrar cidades' }}
                    </button>
                </div>
                <div class="media-map-shell">
                    <div class="media-map" data-map
                        aria-label="{{ $en ? 'Interactive map of public events' : 'Mapa interativo de eventos públicos' }}">
                    </div>
                    <aside class="media-map-directory" aria-label="{{ $en ? 'Locations' : 'Localidades' }}">
                        <div class="media-map-directory__heading">
                            <h3>{{ $en ? 'Locations' : 'Localidades' }}</h3>
                            <button type="button" data-city-clear hidden>{{ $en ? 'Clear' : 'Limpar' }}</button>
                        </div>
                        <div class="media-city-list" data-cities>
                            @foreach ($archive['cities'] as $city => $place)
                                <button type="button" data-city="{{ $city }}"
                                    data-lat="{{ $place['coordinates'][0] }}"
                                    data-lng="{{ $place['coordinates'][1] }}" data-country="{{ $place['country'] }}"
                                    data-region="{{ $place['region'] ?? '' }}" aria-pressed="false">
                                    <span class="media-city-icon" aria-hidden="true">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                                            <circle cx="12" cy="10" r="2.5" />
                                        </svg>
                                    </span>
                                    <span class="media-city-copy">
                                        <strong>{{ $city }}</strong>
                                        <small data-city-region></small>
                                        <small data-city-events></small>
                                    </span>
                                    <span class="media-city-count" data-city-count></span>
                                </button>
                            @endforeach
                        </div>
                        <p class="media-map-directory__empty" data-map-empty hidden>
                            {{ $en ? 'No locations match the current filters.' : 'Nenhuma localidade corresponde aos filtros atuais.' }}
                        </p>
                    </aside>
                </div>
                <div class="media-map-footnote">
                    <p>{{ $en ? 'Locations represent public events at city level, not personal GPS data.' : 'Localidades de eventos públicos, em nível de cidade. Não são dados de GPS pessoal.' }}
                    </p>
                    <p data-map-unlocated aria-live="polite"></p>
                </div>
                <p data-map-error hidden>
                    {{ $en ? 'Map unavailable. The location directory remains usable.' : 'Mapa indisponível. A lista de localidades continua disponível.' }}
                </p>
                <div class="media-map-results-heading">
                    <h3 data-map-results-title>
                        {{ $en ? 'Media from all locations' : 'Mídias de todas as localidades' }}</h3>
                    <button type="button" data-city-clear
                        hidden>{{ $en ? 'All locations' : 'Todas as localidades' }}</button>
                </div>
            </section>
            <div class="media-grid" data-grid>
                @foreach ($records as $record)
                    @php
                        $id = $record['id'];
                        $publicHref = static fn(string $href): string => \App\Presentations\TalkMetadata::publicHref(
                            $href,
                            $base,
                        );
                        $year = $record['year'] ?? 'unknown';
                        $album =
                            $record['event'] . ' · ' . ($year === 'unknown' ? ($en ? 'Undated' : 'Sem data') : $year);
                        $thumb = $record['thumbnail'] ?? ($record['cover'] ?? null);
                        $image = $record['image'] ?? ($record['cover'] ?? null);
                        $coverLabel =
                            $record['type'] === 'photo'
                                ? ($en
                                    ? 'Photograph'
                                    : 'Fotografia')
                                : ($en
                                    ? 'Preview'
                                    : 'Prévia');
                        $typeLabel = $archive['types'][$record['type']][$locale];
                        $youtube = $record['youtube'] ?? null;
                        $youtubeThumb = $youtube ? 'https://i.ytimg.com/vi/' . $youtube . '/hqdefault.jpg' : null;
                        $isPlayable = $youtube || isset($record['video']) || isset($record['audio']);
                        $search =
                            $record['title']['en'] .
                            ' ' .
                            $record['title']['pt-BR'] .
                            ' ' .
                            $record['description'][$locale] .
                            ' ' .
                            $album .
                            ' ' .
                            ($record['city'] ?? '') .
                            ' ' .
                            $record['source'] .
                            ' ' .
                            implode(
                                ' ',
                                array_map(static fn($topic) => $archive['topics'][$topic][$locale], $record['topics']),
                            );
                    @endphp
                    <article id="{{ $id }}" class="media-card" data-record
                        data-type="{{ $record['type'] }}" data-year="{{ $year }}"
                        data-city="{{ $record['city'] ?? '' }}" data-album="{{ $album }}"
                        data-event="{{ $record['event'] }}"
                        data-topics="{{ json_encode($record['topics'], JSON_UNESCAPED_UNICODE) }}"
                        data-search="{{ $search }}" data-youtube="{{ $record['youtube'] ?? '' }}">
                        <a class="media-card__open" data-open href="{{ $publicHref($record['url']) }}"
                            rel="external noopener noreferrer">
                            <div class="media-visual media-visual--{{ $record['type'] }}">
                                @if ($youtubeThumb)
                                    <img src="{{ $youtubeThumb }}" alt="" width="480" height="360"
                                        loading="lazy" decoding="async" referrerpolicy="no-referrer"
                                        data-youtube-thumbnail>
                                    <div class="media-thumbnail-fallback" data-thumbnail-fallback hidden>
                                        @if ($thumb)
                                            <img src="{{ $base }}{{ $thumb }}" alt=""
                                                loading="lazy" decoding="async">
                                            <span class="media-cover-label">{{ $coverLabel }}</span>
                                        @else
                                            <div class="media-placeholder">
                                                <strong>{{ $record['event'] }}</strong><small>{{ $en ? 'Video preview unavailable' : 'Prévia de vídeo indisponível' }}</small>
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($thumb)
                                    <img src="{{ $base }}{{ $thumb }}" alt=""
                                        width="{{ $record['width'] ?? ($record['coverWidth'] ?? 640) }}"
                                        height="{{ $record['height'] ?? ($record['coverHeight'] ?? 424) }}"
                                        loading="lazy" decoding="async">
                                    <span class="media-cover-label">{{ $coverLabel }}</span>
                                @else
                                    <div class="{{ isset($record['audio']) ? 'media-placeholder media-audio-cover' : 'media-placeholder' }}"
                                        data-placeholder>
                                        @if (isset($record['audio']))
                                            <span class="media-audio-decoration"
                                                aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                                            <strong>{{ $record['source'] }}</strong><small>Podcast</small>
                                        @else
                                            <span
                                                aria-hidden="true">{{ ['video' => '▶', 'photo' => '▧', 'audio' => '♫'][$record['type']] }}</span>
                                            <strong>{{ $record['event'] }}</strong><small>{{ $record['type'] === 'photo' ? ($en ? 'Photographic reference' : 'Referência fotográfica') : $typeLabel }}</small>
                                        @endif
                                    </div>
                                @endif
                                @if ($isPlayable)
                                    <span class="media-play-indicator" data-play-indicator aria-hidden="true"><svg
                                            viewBox="0 0 24 24" width="25" height="25" fill="currentColor">
                                            <path d="M8 5.5v13l11-6.5z" />
                                        </svg></span>
                                @endif
                                <span
                                    class="media-type">{{ $record['type'] === 'video' ? '▶ ' : '' }}{{ $typeLabel }}</span>
                            </div>
                            <div class="media-card__text">
                                <p class="media-meta">{{ $record['event'] }} ·
                                    {{ $year === 'unknown' ? ($en ? 'Undated' : 'Sem data') : $year }}</p>
                                <h2>{{ $record['title'][$locale] }}</h2>
                                @if (!empty($record['city']))
                                    <p class="media-meta">{{ $record['city'] }}</p>
                                @endif
                            </div>
                        </a>
                        <template data-detail>
                            <div
                                class="{{ $isPlayable ? 'media-detail-visual media-detail-visual--player' : 'media-detail-visual' }}">
                                @if ($youtube)
                                    <div class="media-video-player">
                                        <iframe
                                            src="https://www.youtube-nocookie.com/embed/{{ $youtube }}?rel=0&amp;playsinline=1"
                                            title="{{ $record['title'][$locale] }}"
                                            allow="encrypted-media; picture-in-picture; fullscreen"
                                            referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                    </div>
                                    <p class="media-player-note"><a href="{{ $publicHref($record['url']) }}"
                                            target="_blank"
                                            rel="external noopener noreferrer">{{ $en ? 'Watch on YouTube if the player is unavailable' : 'Se o player estiver indisponível, assista no YouTube' }}
                                            ↗</a></p>
                                @elseif (isset($record['video']))
                                    <div class="media-video-player"><video src="{{ $publicHref($record['video']) }}"
                                            poster="{{ $thumb ? $base . $thumb : '' }}" controls playsinline
                                            preload="metadata" aria-label="{{ $record['title'][$locale] }}"></video>
                                    </div>
                                    <p class="media-player-note"><a href="{{ $publicHref($record['url']) }}"
                                            target="_blank"
                                            rel="external noopener noreferrer">{{ $en ? 'Open original video' : 'Abrir vídeo original' }}
                                            ↗</a></p>
                                @elseif (isset($record['audio']))
                                    <div class="media-audio-player">
                                        <div class="media-audio-decoration" aria-hidden="true">
                                            <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                                        </div>
                                        <strong>{{ $record['source'] }}</strong>
                                        <p>{{ $record['title'][$locale] }}</p>
                                        <audio controls preload="metadata" src="{{ $publicHref($record['audio']) }}"
                                            aria-label="{{ $record['title'][$locale] }}"></audio>
                                    </div>
                                    <p class="media-player-note"><a href="{{ $publicHref($record['audio']) }}"
                                            target="_blank"
                                            rel="external noopener noreferrer">{{ $en ? 'Listen to original audio' : 'Ouvir áudio original' }}
                                            ↗</a></p>
                                @elseif ($image)
                                    <a href="{{ $base }}{{ $image }}" data-zoom
                                        data-width="{{ $record['width'] ?? ($record['coverWidth'] ?? 0) }}"
                                        data-height="{{ $record['height'] ?? ($record['coverHeight'] ?? 0) }}">
                                        <img src="{{ $base }}{{ $image }}"
                                            alt="{{ $coverLabel }} — {{ $record['title'][$locale] }}">
                                    </a>
                                    <small>{{ $coverLabel }} ·
                                        {{ $en ? 'Select to enlarge' : 'Selecione para ampliar' }}</small>
                                @else
                                    <div class="media-placeholder">
                                        <strong>{{ $record['event'] }}</strong><small>{{ $typeLabel }}</small>
                                    </div>
                                @endif
                            </div>
                            <div class="media-detail-copy">
                                <p class="eyebrow">{{ $record['event'] }} ·
                                    {{ $year === 'unknown' ? ($en ? 'Undated' : 'Sem data') : $year }}</p>
                                <h2 data-detail-title>{{ $record['title'][$locale] }}</h2>
                                <p>{{ $record['description'][$locale] }}</p>
                                <p class="media-meta">{{ $en ? 'Role' : 'Atuação' }}:
                                    {{ $archive['roles'][$record['role']][$locale] }}</p>
                                <p class="media-meta">{{ $record['city'] ?? '' }}</p>
                                <a class="media-source" href="{{ $publicHref($record['url']) }}" target="_blank"
                                    rel="external noopener noreferrer">{{ $en ? 'Open original source' : 'Abrir fonte original' }}
                                    ↗</a>
                                <p class="media-meta">{{ $record['source'] }}</p>
                                @if (isset($record['credit']))
                                    <p>{{ $record['credit'] }}</p>
                                @endif
                                @if (isset($record['rights']))
                                    <p class="media-meta">{{ $record['rights'][$locale] }}</p>
                                @endif
                                @if (isset($record['license']))
                                    <p class="media-meta"><a
                                            href="{{ $record['license'] }}">{{ $en ? 'License' : 'Licença' }}</a>
                                    </p>
                                @endif
                                @if (isset($record['reference']))
                                    <p><a href="{{ $publicHref($record['reference']) }}" target="_blank"
                                            rel="external noopener noreferrer">{{ $en ? 'Documented reference' : 'Referência documentada' }}</a>
                                    </p>
                                @endif
                                <h3>{{ $en ? 'Explore related content' : 'Explore conteúdos relacionados' }}</h3>
                                <div class="media-tags">
                                    @foreach ($record['topics'] as $topic)
                                        <button type="button"
                                            data-topic="{{ $topic }}">{{ $archive['topics'][$topic][$locale] }}</button>
                                    @endforeach
                                </div>
                                <ul class="media-links">
                                    @foreach (['talks', 'articles'] as $kind)
                                        @php $seenLinks = []; @endphp
                                        @foreach ($record[$kind] ?? [] as $slug)
                                            @php $related = $relatedPages[$kind . ':' . $slug] ?? null; @endphp
                                            @if ($related && !in_array($related['url'], $seenLinks, true))
                                                @php $seenLinks[] = $related['url']; @endphp
                                                <li><a
                                                        href="{{ $related['url'] }}">{{ $related['title'] }}{{ $related['locale'] !== $locale ? ' (' . $related['locale'] . ')' : '' }}</a>
                                                </li>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </ul>
                                <button type="button"
                                    data-same-album>{{ $en ? 'More from this event' : 'Mais registros deste evento' }}</button>
                            </div>
                        </template>
                    </article>
                @endforeach
            </div>
            <p class="media-empty" data-empty hidden>
                {{ $en ? 'No media match these filters.' : 'Nenhuma mídia corresponde aos filtros.' }}</p>
        </div>
        <aside class="media-timeline" data-timeline hidden>
            <nav
                aria-label="{{ $en ? 'Jump to a year in the complete archive' : 'Ir para um ano no acervo completo' }}">
                <p>{{ $en ? 'Timeline' : 'Linha do tempo' }}<small>{{ $en ? 'Full archive' : 'Acervo completo' }}</small>
                </p>
                @foreach ($years as $year)
                    <button type="button" data-jump="{{ $year }}"
                        aria-label="{{ $en ? 'Browse all media from' : 'Navegar pelas mídias de' }} {{ $year }}">{{ $year }}</button>
                @endforeach
                @if ($hasUndated)
                    <button type="button" data-jump="unknown">{{ $en ? 'Undated' : 'Sem data' }}</button>
                @endif
            </nav>
            <input type="range" min="0" max="{{ count($years) }}" value="0" data-time-range
                aria-label="{{ $en ? 'Navigate by year' : 'Navegar por ano' }}">
        </aside>
    </div>
    <dialog class="media-dialog" data-dialog aria-labelledby="media-dialog-heading">
        <div class="media-viewer" data-viewer>
            <div class="media-dialog-toolbar">
                <button type="button" data-prev aria-label="{{ $en ? 'Previous media' : 'Mídia anterior' }}"
                    title="{{ $en ? 'Previous media' : 'Mídia anterior' }}">←</button>
                <span data-position aria-live="polite"></span>
                <button type="button" data-next aria-label="{{ $en ? 'Next media' : 'Próxima mídia' }}"
                    title="{{ $en ? 'Next media' : 'Próxima mídia' }}">→</button>
                <div class="media-viewer-actions">
                    <button type="button" data-fullscreen aria-pressed="false"
                        aria-label="{{ $en ? 'Fullscreen' : 'Tela cheia' }}"
                        title="{{ $en ? 'Fullscreen' : 'Tela cheia' }}"><span aria-hidden="true">⛶</span> <span
                            data-fullscreen-label>{{ $en ? 'Fullscreen' : 'Tela cheia' }}</span></button>
                    <button type="button" data-close aria-label="{{ $en ? 'Close' : 'Fechar' }}"
                        title="{{ $en ? 'Close' : 'Fechar' }}">✕</button>
                </div>
            </div>
            <div class="media-dialog-body" data-dialog-body></div>
        </div>
    </dialog>
</div>
