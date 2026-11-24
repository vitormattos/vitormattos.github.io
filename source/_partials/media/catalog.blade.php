{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $locale = ($page->locale ?? 'en') === 'pt-BR' ? 'pt-BR' : 'en';
    $en = $locale === 'en';
    $base = rtrim((string) $page->baseUrl, '/');
    $archive = $page->mediaArchive;
    if (is_object($archive) && method_exists($archive, 'toArray')) {
        $archive = $archive->toArray();
    }
    $records = $archive['items'];
    $years = array_values(array_unique(array_filter(array_column($records, 'year'))));
    rsort($years);
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
    data-map-url="{{ $base }}/media-assets/south-america.geojson">
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
        <form class="media-filters" data-filters role="search">
            <label class="media-search">
                <span>{{ $en ? 'Search' : 'Buscar' }}</span>
                <input type="search" name="q"
                    placeholder="{{ $en ? 'Event, topic, recording…' : 'Evento, assunto, gravação…' }}">
            </label>
            <label>
                <span>{{ $en ? 'Type' : 'Tipo' }}</span>
                <select name="type">
                    <option value="">{{ $en ? 'All types' : 'Todos os tipos' }}</option>
                    @foreach ($archive['types'] as $type => $label)
                        <option value="{{ $type }}">{{ $label[$locale] }}</option>
                    @endforeach
                </select>
            </label>
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
                    <option value="unknown">{{ $en ? 'Undated' : 'Sem data' }}</option>
                </select>
            </label>
            <button type="button" data-clear>{{ $en ? 'Clear' : 'Limpar' }}</button>
        </form>
        <div class="media-results">
            <output data-count aria-live="polite">{{ count($records) }}</output>
            <button type="button" data-external-thumbs
                aria-pressed="false">{{ $en ? 'Load YouTube thumbnails' : 'Carregar miniaturas do YouTube' }}</button>
        </div>
    </div>

    <div class="media-browse">
        <div class="media-content">
            <div class="media-album-bar" data-album-bar hidden><button type="button"
                    data-album-back>{{ $en ? '← All albums' : '← Todos os álbuns' }}</button><strong
                    data-album-title></strong></div>
            <div class="media-albums" data-albums hidden></div>
            <div class="media-map-panel" data-map-panel hidden>
                <p>{{ $en ? 'Approximate city centers of past public events. Select a city to filter the collection.' : 'Centros aproximados de cidades de eventos públicos passados. Selecione uma cidade para filtrar o acervo.' }}
                </p>
                <div class="media-map" data-map
                    aria-label="{{ $en ? 'Map of public events' : 'Mapa de eventos públicos' }}"></div>
                <div class="media-city-list" data-cities>
                    @foreach ($archive['cities'] as $city => $coordinates)
                        <button type="button" data-city="{{ $city }}" data-lat="{{ $coordinates[0] }}"
                            data-lng="{{ $coordinates[1] }}">{{ $city }}</button>
                    @endforeach
                </div>
                <p data-map-error hidden>
                    {{ $en ? 'Map unavailable. City filters remain available.' : 'Mapa indisponível. Os filtros por cidade continuam disponíveis.' }}
                </p>
            </div>
            <div class="media-grid" data-grid>
                @foreach ($records as $record)
                    @php
                        $id = $record['id'];
                        $year = $record['year'] ?? 'unknown';
                        $album =
                            $record['event'] . ' · ' . ($year === 'unknown' ? ($en ? 'Undated' : 'Sem data') : $year);
                        $thumb = $record['thumbnail'] ?? ($record['cover'] ?? null);
                        $image = $record['image'] ?? ($record['cover'] ?? null);
                        $coverLabel = isset($record['image'])
                            ? ($en
                                ? 'Photograph'
                                : 'Fotografia')
                            : ($en
                                ? 'Slide cover'
                                : 'Capa dos slides');
                        $typeLabel = $archive['types'][$record['type']][$locale];
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
                    <article id="{{ $id }}" class="media-card" data-record data-type="{{ $record['type'] }}"
                        data-year="{{ $year }}" data-city="{{ $record['city'] ?? '' }}"
                        data-album="{{ $album }}" data-topics="{{ implode(' ', $record['topics']) }}"
                        data-search="{{ $search }}" data-youtube="{{ $record['youtube'] ?? '' }}">
                        <a class="media-card__open" data-open href="{{ $record['url'] }}"
                            rel="external noopener noreferrer">
                            <div class="media-visual media-visual--{{ $record['type'] }}">
                                @if ($thumb)
                                    <img src="{{ $base }}{{ $thumb }}" alt=""
                                        width="{{ $record['coverWidth'] ?? 640 }}"
                                        height="{{ $record['coverHeight'] ?? 424 }}" loading="lazy" decoding="async">
                                    <span class="media-cover-label" data-cover-label>{{ $coverLabel }}</span>
                                @else
                                    <div class="media-placeholder" data-placeholder><span
                                            aria-hidden="true">{{ ['video' => '▶', 'photo' => '▧', 'audio' => '♫', 'press' => '↗', 'poster' => '▣'][$record['type']] }}</span><strong>{{ $record['event'] }}</strong><small>{{ $record['type'] === 'photo' ? ($en ? 'Photographic reference' : 'Referência fotográfica') : $typeLabel }}</small>
                                    </div>
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
                            <div class="media-detail-visual">
                                @if ($image)
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
                                <p class="media-meta">{{ $record['city'] ?? '' }}</p>
                                <a class="media-source" href="{{ $record['url'] }}" target="_blank"
                                    rel="external noopener noreferrer">{{ $en ? 'Open original source' : 'Abrir fonte original' }}
                                    ↗</a>
                                <p class="media-meta">{{ $record['source'] }}</p>
                                @if (isset($record['credit']))
                                    <p>{{ $record['credit'] }}</p>
                                @endif
                                @if (isset($record['rights']))
                                    <p class="media-meta">{{ $record['rights'][$locale] }} @if (isset($record['license']))
                                            <a href="{{ $record['license'] }}">CC BY 2.0</a>
                                        @endif
                                    </p>
                                @endif
                                @if (isset($record['reference']))
                                    <p><a href="{{ $record['reference'] }}" target="_blank"
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
            <nav aria-label="{{ $en ? 'Jump to year' : 'Ir para o ano' }}">
                <p>{{ $en ? 'Timeline' : 'Linha do tempo' }}</p>
                @foreach ($years as $year)
                    <button type="button" data-jump="{{ $year }}">{{ $year }}</button>
                @endforeach
                <button type="button" data-jump="unknown">{{ $en ? 'Undated' : 'Sem data' }}</button>
            </nav>
            <input type="range" min="0" max="{{ count($years) }}" value="0" data-time-range
                aria-label="{{ $en ? 'Navigate by year' : 'Navegar por ano' }}">
        </aside>
    </div>
    <dialog class="media-dialog" data-dialog aria-labelledby="media-dialog-heading">
        <div class="media-dialog-toolbar">
            <button type="button" data-prev aria-label="{{ $en ? 'Previous media' : 'Mídia anterior' }}">←</button>
            <span data-position aria-live="polite"></span>
            <button type="button" data-next aria-label="{{ $en ? 'Next media' : 'Próxima mídia' }}">→</button>
            <button type="button" data-close aria-label="{{ $en ? 'Close' : 'Fechar' }}">✕</button>
        </div>
        <div class="media-dialog-body" data-dialog-body></div>
    </dialog>
</div>
