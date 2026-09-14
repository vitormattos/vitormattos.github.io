// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import Reveal from 'reveal.js';
import Markdown from 'reveal.js/plugin/markdown';
import Highlight from 'reveal.js/plugin/highlight';
import Notes from 'reveal.js/plugin/notes';

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
        keyboard: isThumbnail ? false : true,
        keyboardCondition: isThumbnail ? null : 'focused',
        touch: !isThumbnail,
        overview: !isThumbnail,
        transition: isThumbnail ? 'none' : 'slide',
        backgroundTransition: isThumbnail ? 'none' : 'fade',
        scrollActivationWidth: isThumbnail ? 0 : 720,
        plugins: isThumbnail ? [Markdown] : [Markdown, Highlight, Notes],
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
            if (! entries.some((entry) => entry.isIntersecting)) {
                return;
            }

            observer.disconnect();
            initializeDeck(root).then(resolve);
        }, { rootMargin: '240px' });

        observer.observe(root);
    });
}

function bindToolbar(toolbar) {
    const deck = decks.get(toolbar.dataset.presentationFor);

    if (! deck) {
        return;
    }

    toolbar.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-presentation-action]');

        if (! button) {
            return;
        }

        const action = button.dataset.presentationAction;

        if (action === 'overview') {
            deck.toggleOverview();
            return;
        }

        if (action === 'reading') {
            const isReading = button.getAttribute('aria-pressed') === 'true';
            deck.toggleOverview(false);
            deck.configure({
                view: isReading ? 'slide' : 'scroll',
                scrollLayout: 'compact',
                scrollProgress: 'auto',
            });
            button.setAttribute('aria-pressed', String(! isReading));
            return;
        }

        if (action === 'fullscreen') {
            const frame = document.getElementById(toolbar.dataset.presentationFor)?.closest('.presentation-frame');

            if (! frame) {
                return;
            }

            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await frame.requestFullscreen();
            }
        }
    });
}

const roots = [...document.querySelectorAll('.js-reveal-deck')];
await Promise.all(roots.map(initializeWhenVisible));
document.querySelectorAll('[data-presentation-for]').forEach(bindToolbar);
