// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import Reveal from 'reveal.js';
import Markdown from 'reveal.js/plugin/markdown';
import Highlight from 'reveal.js/plugin/highlight';
import Notes from 'reveal.js/plugin/notes';
import Search from 'reveal.js/plugin/search';
import Zoom from 'reveal.js/plugin/zoom';
import RevealMath from 'reveal.js/plugin/math';

const decks = new Map();

function initializeDeck(root) {
    const mode = root.dataset.presentationMode ?? 'detail';
    const isThumbnail = mode === 'thumbnail';
    const deck = new Reveal(root, {
        embedded: true,
        controls: !isThumbnail,
        progress: !isThumbnail,
        slideNumber: isThumbnail ? false : 'c/t',
        hash: false,
        history: false,
        keyboard: !isThumbnail,
        keyboardCondition: isThumbnail ? null : 'focused',
        touch: !isThumbnail,
        overview: !isThumbnail,
        transition: isThumbnail ? 'none' : 'slide',
        backgroundTransition: isThumbnail ? 'none' : 'fade',
        scrollActivationWidth: isThumbnail ? 0 : 720,
        pdfMaxPagesPerSlide: 1,
        pdfSeparateFragments: false,
        plugins: isThumbnail
            ? [Markdown]
            : [Markdown, Highlight, Notes, Search, Zoom, RevealMath.KaTeX],
    });

    decks.set(root.id, deck);
    return deck.initialize();
}

function initializeWhenVisible(root) {
    if (root.dataset.presentationMode !== 'thumbnail' || !('IntersectionObserver' in window)) {
        return initializeDeck(root);
    }

    return new Promise((resolve) => {
        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            observer.disconnect();
            initializeDeck(root).then(resolve);
        }, { rootMargin: '240px' });
        observer.observe(root);
    });
}

function bindToolbar(toolbar) {
    const deck = decks.get(toolbar.dataset.presentationFor);
    if (!deck) return;

    toolbar.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-presentation-action]');
        if (!button) return;
        const action = button.dataset.presentationAction;

        if (action === 'overview') return deck.toggleOverview();
        if (action === 'search') {
            const search = deck.getPlugin('search');
            search?.open?.();
            return;
        }
        if (action === 'reading') {
            const isReading = button.getAttribute('aria-pressed') === 'true';
            deck.toggleOverview(false);
            deck.configure({ view: isReading ? 'slide' : 'scroll', scrollLayout: 'compact', scrollProgress: 'auto' });
            button.setAttribute('aria-pressed', String(!isReading));
            return;
        }
        if (action === 'fullscreen') {
            const frame = document.getElementById(toolbar.dataset.presentationFor)?.closest('.presentation-frame');
            if (!frame) return;
            if (document.fullscreenElement) await document.exitFullscreen();
            else await frame.requestFullscreen();
        }
    });
}

function normalizeTag(tag) {
    return String(tag ?? '').trim().toLocaleLowerCase();
}

function readTags(card) {
    try {
        return JSON.parse(card.dataset.talkTags ?? '[]').map(normalizeTag);
    } catch {
        return [];
    }
}

function initializeTagFilter(gallery) {
    const filter = document.querySelector('[data-talk-tag-filter]');
    if (!filter) return;

    const cards = [...gallery.querySelectorAll('.talk-card')];
    const status = document.querySelector('[data-talk-filter-status]');
    const empty = document.querySelector('[data-talk-filter-empty]');

    const applyTag = (tag, updateHistory = false) => {
        const normalized = normalizeTag(tag);
        let visible = 0;

        for (const card of cards) {
            const matches = normalized === '' || readTags(card).includes(normalized);
            card.hidden = !matches;
            if (matches) visible += 1;
        }

        for (const link of filter.querySelectorAll('[data-talk-tag]')) {
            const selected = normalizeTag(link.dataset.talkTag) === normalized;
            if (selected) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }

        if (status) {
            status.textContent = normalized === ''
                ? ''
                : `${visible} ${visible === 1 ? 'presentation' : 'presentations'} · ${tag.trim()}`;
        }
        if (empty) empty.hidden = visible !== 0;

        if (updateHistory) {
            const url = new URL(window.location.href);
            if (normalized === '') url.searchParams.delete('tag');
            else url.searchParams.set('tag', normalized);
            window.history.pushState({}, '', url);
        }
    };

    filter.addEventListener('click', (event) => {
        const link = event.target.closest('[data-talk-tag]');
        if (!link) return;
        event.preventDefault();
        applyTag(link.dataset.talkTag ?? '', true);
    });

    window.addEventListener('popstate', () => {
        applyTag(new URL(window.location.href).searchParams.get('tag') ?? '');
    });

    applyTag(new URL(window.location.href).searchParams.get('tag') ?? '');
}

function initializeGallery() {
    const gallery = document.querySelector('[data-talk-gallery]');
    const switcher = document.querySelector('[data-gallery-switcher]');
    if (!gallery || !switcher) return;

    initializeTagFilter(gallery);

    const trigger = switcher.querySelector('[data-gallery-menu-trigger]');
    const menu = switcher.querySelector('[data-gallery-menu]');
    const current = switcher.querySelector('[data-gallery-current]');

    const closeMenu = () => {
        if (!menu || !trigger) return;
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };

    const apply = (view) => {
        const normalized = view === 'list' ? 'list' : 'grid';
        gallery.dataset.view = normalized;
        localStorage.setItem('talk-gallery-view', normalized);
        for (const button of switcher.querySelectorAll('[data-gallery-view]')) {
            const selected = button.dataset.galleryView === normalized;
            button.setAttribute('aria-pressed', String(selected));
            if (selected && current) {
                current.textContent = button.querySelector('strong')?.textContent ?? normalized;
            }
        }
        closeMenu();
    };

    apply(localStorage.getItem('talk-gallery-view') ?? 'grid');

    trigger?.addEventListener('click', () => {
        const opening = menu?.hidden ?? false;
        if (menu) menu.hidden = !opening;
        trigger.setAttribute('aria-expanded', String(opening));
    });

    switcher.addEventListener('click', (event) => {
        const button = event.target.closest('[data-gallery-view]');
        if (button) apply(button.dataset.galleryView);
    });

    document.addEventListener('click', (event) => {
        if (!switcher.contains(event.target)) closeMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
            trigger?.focus();
        }
    });
}

initializeGallery();
for (const root of document.querySelectorAll('.js-reveal-deck')) {
    initializeWhenVisible(root).then(() => {
        const toolbar = document.querySelector(`[data-presentation-for="${root.id}"]`);
        if (toolbar) bindToolbar(toolbar);
    });
}
