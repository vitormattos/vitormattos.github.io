// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import Masonry from 'masonry-layout';
import PhotoSwipe from 'photoswipe';
import photoSwipeCss from 'photoswipe/style.css?inline';
import leafletCss from 'leaflet/dist/leaflet.css?inline';

import { browseMode, catalogYears, matchesMedia } from './media-filters.js';

function addStyles(css) {
    const style = document.createElement('style');
    style.textContent = css;
    document.head.append(style);
}

function init(root) {
    const en = root.dataset.locale === 'en';
    const $ = (selector) => root.querySelector(selector);
    const $$ = (selector) => [...root.querySelectorAll(selector)];
    const grid = $('[data-grid]');
    const form = $('[data-filters]');
    const cards = $$('[data-record]');
    const albumPanel = $('[data-albums]');
    const mapPanel = $('[data-map-panel]');
    const timeline = $('[data-timeline]');
    const range = $('[data-time-range]');
    const dialog = $('[data-dialog]');
    const dialogBody = $('[data-dialog-body]');
    const fieldNames = ['q', 'type', 'topic', 'city', 'year'];
    const modes = ['mosaic', 'albums', 'map'];
    let view = 'mosaic';
    let album = '';
    let visible = [];
    const years = catalogYears(cards.map((card) => card.dataset));
    let selectedJump = null;
    let current = null;
    let lastFocus = null;
    let masonry = null;
    let map = null;
    let markers = null;
    let mapLoading = null;
    let leaflet = null;
    let zoom = null;
    let zoomStyled = false;
    let thumbs = false;
    let scheduled = false;
    let lastWidth = 0;
    let scrollFrame = false;

    const filters = () =>
        Object.fromEntries([...fieldNames.map((key) => [key, form.elements.namedItem(key).value]), ['album', album]]);
    function loadState() {
        const params = new URLSearchParams(window.location.search);
        view = modes.includes(params.get('view')) ? params.get('view') : 'mosaic';
        album = view === 'albums' ? params.get('album') || '' : '';
        for (const key of fieldNames) {
            const field = form.elements.namedItem(key);
            const value = params.get(key) || '';
            field.value =
                field.tagName === 'SELECT' && ![...field.options].some((option) => option.value === value) ? '' : value;
        }
        if (!cards.some((card) => card.dataset.album === album)) album = '';
    }

    function writeState(hash = '') {
        const url = new URL(location.href);
        for (const [key, value] of Object.entries({ ...filters(), view: view === 'mosaic' ? '' : view })) {
            if (value) url.searchParams.set(key, value);
            else url.searchParams.delete(key);
        }
        url.hash = hash;
        history.replaceState(null, '', url);
    }

    function layout() {
        if (grid.hidden || !visible.length) return;
        grid.classList.add('is-enhanced');
        const gutter = parseFloat(getComputedStyle(grid).getPropertyValue('--media-gap'));
        if (!masonry)
            masonry = new Masonry(grid, {
                itemSelector: '.media-card:not([hidden])',
                columnWidth: '.media-card:not([hidden])',
                gutter,
                horizontalOrder: true,
                transitionDuration: 0,
                percentPosition: true,
                resize: false,
            });
        else {
            masonry.options.gutter = gutter;
            masonry.reloadItems();
            masonry.layout();
        }
    }
    function scheduleLayout() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            layout();
            updateActiveYear();
        });
    }
    new ResizeObserver(() => {
        if (grid.clientWidth !== lastWidth) {
            lastWidth = grid.clientWidth;
            scheduleLayout();
        }
    }).observe(grid);
    grid.addEventListener('load', scheduleLayout, true);
    grid.addEventListener(
        'error',
        (event) => {
            if (event.target instanceof HTMLImageElement) {
                event.target.hidden = true;
                scheduleLayout();
            }
        },
        true,
    );

    function showAlbum(name) {
        album = name;
        view = 'albums';
        render();
        $('[data-album-bar]').scrollIntoView({ block: 'start' });
    }
    function renderAlbums(records) {
        albumPanel.replaceChildren();
        const grouped = new Map();
        for (const card of records) {
            const key = card.dataset.album;
            if (!grouped.has(key)) grouped.set(key, []);
            grouped.get(key).push(card);
        }
        for (const [name, entries] of grouped) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'media-album';
            button.addEventListener('click', () => showAlbum(name));
            const cover = document.createElement('div');
            cover.className = 'media-album__cover';
            for (const card of entries.slice(0, 3)) {
                const image = card.querySelector('.media-visual img:not([hidden])');
                if (image) {
                    const clone = image.cloneNode();
                    clone.removeAttribute('id');
                    cover.append(clone);
                } else {
                    const placeholder = document.createElement('span');
                    placeholder.textContent = card.dataset.type === 'video' ? '▶' : '▧';
                    cover.append(placeholder);
                }
            }
            const title = document.createElement('strong');
            title.textContent = name;
            const count = document.createElement('small');
            count.textContent = entries.length + (en ? ' records' : ' registros');
            button.append(cover, title, count);
            albumPanel.append(button);
        }
    }

    const cityButtons = $$('[data-city][data-lat]');
    function selectCity(city) {
        form.elements.namedItem('city').value = form.elements.namedItem('city').value === city ? '' : city;
        render();
    }
    function updateMarkers() {
        const counts = new Map();
        const withoutCity = { ...filters(), city: '', album: '' };
        for (const card of cards.filter((card) => matchesMedia(card.dataset, withoutCity))) {
            const city = card.dataset.city;
            if (city) counts.set(city, (counts.get(city) || 0) + 1);
        }
        for (const button of cityButtons) {
            const city = button.dataset.city;
            const count = counts.get(city) || 0;
            button.textContent = city + ' (' + count + ')';
            button.disabled = count === 0;
            button.setAttribute('aria-pressed', String(filters().city === city));
        }
        if (!markers) return;
        markers.clearLayers();
        for (const button of cityButtons.filter((button) => !button.disabled)) {
            const city = button.dataset.city;
            const active = filters().city === city;
            const marker = leaflet.circleMarker([Number(button.dataset.lat), Number(button.dataset.lng)], {
                radius: 8 + Math.min(counts.get(city), 8),
                color: active ? '#0f172a' : '#2563eb',
                weight: 2,
                fillColor: active ? '#0f172a' : '#60a5fa',
                fillOpacity: 0.9,
            });
            const label = document.createElement('span');
            label.textContent = city + ' · ' + counts.get(city);
            marker.bindTooltip(label);
            marker.on('click', () => selectCity(city));
            marker.addTo(markers);
        }
    }

    async function ensureMap() {
        if (map) {
            map.invalidateSize();
            updateMarkers();
            return;
        }
        if (mapLoading) return mapLoading;
        mapLoading = (async () => {
            try {
                const [{ default: L }, response] = await Promise.all([import('leaflet'), fetch(root.dataset.mapUrl)]);
                if (!response.ok) throw new Error('Map data unavailable');
                const geography = await response.json();
                leaflet = L;
                addStyles(leafletCss);
                map = L.map($('[data-map]'), {
                    scrollWheelZoom: false,
                    attributionControl: true,
                    minZoom: 2,
                    maxZoom: 8,
                }).setView([-21, -53], 4);
                L.geoJSON(geography, {
                    style: { color: '#82958a', weight: 1, fillColor: '#c4d4c6', fillOpacity: 0.8 },
                    interactive: false,
                }).addTo(map);
                map.attributionControl.addAttribution('Natural Earth · public domain');
                markers = L.layerGroup().addTo(map);
                updateMarkers();
                if (!mapPanel.hidden) map.invalidateSize();
            } catch {
                $('[data-map-error]').hidden = false;
            } finally {
                mapLoading = null;
            }
        })();
        return mapLoading;
    }

    function render({ persist = true } = {}) {
        const criteria = filters();
        selectedJump = null;
        visible = cards.filter((card) => matchesMedia(card.dataset, criteria));
        const visibleSet = new Set(visible);
        for (const card of cards) card.hidden = !visibleSet.has(card);
        for (const button of $$('[data-view]'))
            button.setAttribute('aria-pressed', String(button.dataset.view === view));
        const showAlbums = view === 'albums' && !album;
        grid.hidden = showAlbums;
        albumPanel.hidden = !showAlbums;
        mapPanel.hidden = view !== 'map';
        $('[data-album-bar]').hidden = view !== 'albums' || !album;
        $('[data-album-title]').textContent = album;
        $('[data-count]').textContent = visible.length + (en ? ' records' : ' registros');
        $('[data-empty]').hidden = visible.length > 0;
        // The year rail is a navigator over the entire archive, not a facet of the current results.
        timeline.hidden = years.length === 0;
        setActiveYear(!showAlbums && visible.length ? visible[0].dataset.year : null);
        const hasFilters = Object.values(criteria).some(Boolean);
        $('[data-active-scope]').hidden = !hasFilters;
        $('[data-clear]').hidden = !hasFilters;
        if (showAlbums || visible.length === 0) {
            if (masonry) {
                masonry.destroy();
                masonry = null;
                grid.classList.remove('is-enhanced');
            }
            if (showAlbums) renderAlbums(visible);
        } else scheduleLayout();
        if (view === 'map') {
            updateMarkers();
            ensureMap();
        }
        if (persist) writeState();
    }

    function switchView(mode) {
        const next = browseMode(mode, filters());
        view = mode;
        album = next.album;
        for (const key of fieldNames) form.elements.namedItem(key).value = next[key];
        render();
    }

    function jumpToYear(year) {
        if (!years.includes(year)) return;
        const card = cards.find((item) => item.dataset.year === year);
        if (!card) return;
        // A jump always opens the complete mosaic, even from an album or a filtered map.
        if (view !== 'mosaic' || Object.values(filters()).some(Boolean)) switchView('mosaic');
        layout();
        // Instant jumps keep dragging responsive and prevent scroll events during an animation
        // from moving the slider back to a previous year.
        card.scrollIntoView({ block: 'start', behavior: 'instant' });
        selectedJump = { year, scrollY: window.scrollY };
        setActiveYear(year);
    }
    function setActiveYear(year) {
        for (const button of $$('[data-jump]'))
            button.setAttribute('aria-current', button.dataset.jump === year ? 'true' : 'false');
        if (years.includes(year)) {
            range.value = String(years.indexOf(year));
            range.setAttribute('aria-valuetext', year === 'unknown' ? (en ? 'Undated' : 'Sem data') : year);
        } else {
            range.value = '0';
            range.setAttribute('aria-valuetext', en ? 'Choose a year' : 'Escolha um ano');
        }
    }
    function updateActiveYear() {
        if (timeline.hidden || view !== 'mosaic' || !visible.length) return;
        if (selectedJump && Math.abs(window.scrollY - selectedJump.scrollY) < 2) {
            setActiveYear(selectedJump.year);
            return;
        }
        selectedJump = null;
        const point = Math.min(170, innerHeight / 3);
        const onScreen = visible.find((card) => card.getBoundingClientRect().bottom > point);
        setActiveYear((onScreen || visible.at(-1)).dataset.year);
    }

    function openCard(card, focus = null) {
        current = card;
        if (!dialog.open) lastFocus = focus || document.activeElement;
        dialogBody.replaceChildren(card.querySelector('template').content.cloneNode(true));
        dialogBody.querySelector('[data-detail-title]').id = 'media-dialog-heading';
        const index = visible.indexOf(card);
        $('[data-position]').textContent = index + 1 + ' / ' + visible.length;
        $('[data-prev]').disabled = index <= 0;
        $('[data-next]').disabled = index >= visible.length - 1;
        if (!dialog.open) dialog.showModal();
        writeState(card.id);
        $('[data-close]').focus();
    }
    function step(delta) {
        const index = visible.indexOf(current);
        if (visible[index + delta]) openCard(visible[index + delta]);
    }
    function closeDialog() {
        if (zoom) {
            zoom.close();
            return;
        }
        if (dialog.open) dialog.close();
    }
    dialog.addEventListener('close', () => {
        current = null;
        writeState('');
        if (lastFocus?.isConnected && !lastFocus.closest('[hidden]')) lastFocus.focus();
    });
    dialog.addEventListener('click', (event) => {
        const target = event.target.closest('button, a');
        if (!target) return;
        if (target.matches('[data-close]')) closeDialog();
        if (target.matches('[data-prev]')) step(-1);
        if (target.matches('[data-next]')) step(1);
        if (target.matches('[data-topic]')) {
            form.reset();
            form.elements.namedItem('topic').value = target.dataset.topic;
            view = 'mosaic';
            album = '';
            closeDialog();
            render();
        }
        if (target.matches('[data-same-album]') && current) {
            const name = current.dataset.album;
            form.reset();
            closeDialog();
            showAlbum(name);
        }
        if (
            target.matches('[data-zoom]') &&
            !zoom &&
            !event.ctrlKey &&
            !event.metaKey &&
            !event.shiftKey &&
            !event.altKey
        ) {
            const image = target.querySelector('img');
            const width = Number(target.dataset.width) || image.naturalWidth;
            const height = Number(target.dataset.height) || image.naturalHeight;
            if (!width || !height) return;
            event.preventDefault();
            if (!zoomStyled) {
                addStyles(photoSwipeCss);
                zoomStyled = true;
            }
            zoom = new PhotoSwipe({
                dataSource: [{ src: target.href, width, height, alt: image.alt }],
                index: 0,
                appendToEl: dialog,
                showAnimationDuration: 0,
                hideAnimationDuration: 0,
                returnFocus: false,
            });
            zoom.on('destroy', () => {
                zoom = null;
                target.focus();
            });
            zoom.init();
        }
    });
    dialog.addEventListener('keydown', (event) => {
        if (zoom || /^(INPUT|SELECT|TEXTAREA)$/.test(event.target.tagName)) return;
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            step(1);
        }
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            step(-1);
        }
    });
    dialog.addEventListener('cancel', (event) => {
        if (zoom) {
            event.preventDefault();
            zoom.close();
        }
    });
    grid.addEventListener('click', (event) => {
        const anchor = event.target.closest('[data-open]');
        if (!anchor || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        openCard(anchor.closest('[data-record]'), anchor);
    });

    function toggleThumbnails() {
        thumbs = !thumbs;
        const toggle = $('[data-external-thumbs]');
        toggle.setAttribute('aria-pressed', String(thumbs));
        toggle.textContent = thumbs
            ? en
                ? 'Hide YouTube thumbnails'
                : 'Ocultar miniaturas do YouTube'
            : en
              ? 'Load YouTube thumbnails'
              : 'Carregar miniaturas do YouTube';
        for (const card of cards) {
            if (!/^[A-Za-z0-9_-]{11}$/.test(card.dataset.youtube)) continue;
            const visual = card.querySelector('.media-visual');
            const originals = [...visual.children].filter((item) =>
                item.matches('img:not([data-external]), [data-placeholder], [data-cover-label]'),
            );
            const previous = visual.querySelector('[data-external]');
            if (!thumbs) {
                previous?.remove();
                for (const item of originals) item.hidden = false;
                continue;
            }
            const img = document.createElement('img');
            img.dataset.external = '';
            img.alt = '';
            img.width = 480;
            img.height = 360;
            img.loading = 'eager';
            img.decoding = 'async';
            img.referrerPolicy = 'no-referrer';
            img.hidden = true;
            img.addEventListener('load', () => {
                if (!thumbs || !img.isConnected || img.naturalWidth < 120) return;
                for (const item of originals) item.hidden = true;
                img.hidden = false;
                scheduleLayout();
            });
            img.addEventListener('error', () => img.remove(), { once: true });
            visual.prepend(img);
            img.src = 'https://i.ytimg.com/vi/' + card.dataset.youtube + '/hqdefault.jpg';
        }
        scheduleLayout();
    }

    for (const button of $$('[data-view]')) button.addEventListener('click', () => switchView(button.dataset.view));
    form.addEventListener('input', () => render());
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        render();
    });
    $('[data-advanced-toggle]').addEventListener('click', () => {
        const toggle = $('[data-advanced-toggle]');
        const panel = $('#media-advanced-filters');
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        panel.dataset.collapsed = String(expanded);
    });
    $('[data-clear]').addEventListener('click', () => {
        form.reset();
        album = '';
        render();
    });
    $('[data-album-back]').addEventListener('click', () => {
        album = '';
        render();
    });
    $('[data-density]').addEventListener('input', (event) => {
        grid.dataset.density = event.target.value;
        scheduleLayout();
    });
    $('[data-external-thumbs]').addEventListener('click', toggleThumbnails);
    for (const button of cityButtons) button.addEventListener('click', () => selectCity(button.dataset.city));
    for (const button of $$('[data-jump]')) button.addEventListener('click', () => jumpToYear(button.dataset.jump));
    range.addEventListener('input', () => jumpToYear(years[Number(range.value)]));
    window.addEventListener(
        'scroll',
        () => {
            if (scrollFrame) return;
            scrollFrame = true;
            requestAnimationFrame(() => {
                scrollFrame = false;
                updateActiveYear();
            });
        },
        { passive: true },
    );
    window.addEventListener('popstate', () => {
        loadState();
        render({ persist: false });
    });
    $('[data-media-controls]').hidden = false;
    range.max = String(Math.max(0, years.length - 1));
    range.disabled = years.length < 2;
    loadState();
    render({ persist: false });
    const target = cards.find((card) => '#' + card.id === location.hash);
    if (target && visible.includes(target)) {
        if (view === 'albums' && !album) {
            album = target.dataset.album;
            render();
        }
        openCard(target);
    }
}

if (typeof document !== 'undefined') {
    for (const root of document.querySelectorAll('[data-media-catalog]')) init(root);
}
