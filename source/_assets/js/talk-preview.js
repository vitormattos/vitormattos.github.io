// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import Reveal from 'reveal.js';
import Markdown from 'reveal.js/plugin/markdown';

const decks = new Map();
const initializations = new Map();

function previewConfiguration(active = false) {
    return {
        embedded: true,
        controls: active,
        progress: false,
        slideNumber: false,
        hash: false,
        history: false,
        keyboard: active,
        keyboardCondition: active ? 'focused' : null,
        touch: active,
        overview: false,
        transition: active ? 'slide' : 'none',
        backgroundTransition: active ? 'fade' : 'none',
        scrollActivationWidth: 0,
        plugins: [Markdown],
    };
}

function ensureDeck(root) {
    if (decks.has(root.id)) return Promise.resolve(decks.get(root.id));
    if (initializations.has(root.id)) return initializations.get(root.id);

    const deck = new Reveal(root, previewConfiguration(false));
    decks.set(root.id, deck);

    const initialization = deck.initialize().then(() => {
        root.dataset.previewReady = 'true';
        return deck;
    }).finally(() => {
        initializations.delete(root.id);
    });

    initializations.set(root.id, initialization);
    return initialization;
}

function initializeVisibleDeck(root) {
    if (!('IntersectionObserver' in window)) {
        ensureDeck(root);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        observer.disconnect();
        ensureDeck(root);
    }, { rootMargin: '240px' });

    observer.observe(root);
}

function ensureEmbed(card) {
    const iframe = card.querySelector('[data-talk-preview-embed]');
    if (!iframe || iframe.src) return;
    iframe.src = iframe.dataset.src ?? '';
}

async function activatePreview(card) {
    const livePreview = card.querySelector('[data-talk-live-preview]');
    if (!livePreview) return;

    livePreview.hidden = false;
    card.classList.add('is-previewing');

    const root = card.querySelector('.js-talk-preview-deck');
    if (root) {
        const deck = await ensureDeck(root);
        deck.configure(previewConfiguration(true));
        deck.layout();
        root.focus();
        return;
    }

    ensureEmbed(card);
}

function deactivatePreview(card) {
    const livePreview = card.querySelector('[data-talk-live-preview]');
    const root = card.querySelector('.js-talk-preview-deck');
    const deck = root ? decks.get(root.id) : null;

    if (deck) {
        deck.slide(0, 0, 0);
        deck.configure(previewConfiguration(false));
        deck.layout();
    }

    card.classList.remove('is-previewing');
    if (livePreview?.dataset.previewOverlay === 'true') livePreview.hidden = true;
}

async function toggleFullscreen(card) {
    const media = card.querySelector('[data-talk-preview-media]');
    if (!media) return;

    if (document.fullscreenElement === media) {
        await document.exitFullscreen();
        return;
    }

    await media.requestFullscreen?.();
    const root = card.querySelector('.js-talk-preview-deck');
    const deck = root ? decks.get(root.id) : null;
    deck?.layout();
}

function initializeTalkPreviews() {
    const cards = [...document.querySelectorAll('[data-talk-preview-card]')];
    if (cards.length === 0) return;

    for (const card of cards) {
        const livePreview = card.querySelector('[data-talk-live-preview]');
        const root = card.querySelector('.js-talk-preview-deck');
        if (root && livePreview && !livePreview.hidden) initializeVisibleDeck(root);
    }

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-talk-preview-toggle]');
        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            const card = trigger.closest('[data-talk-preview-card]');
            if (card) await activatePreview(card);
            return;
        }

        const close = event.target.closest('[data-talk-preview-close]');
        if (close) {
            event.preventDefault();
            event.stopPropagation();
            const card = close.closest('[data-talk-preview-card]');
            if (card) deactivatePreview(card);
            return;
        }

        const fullscreen = event.target.closest('[data-talk-preview-fullscreen]');
        if (fullscreen) {
            event.preventDefault();
            event.stopPropagation();
            const card = fullscreen.closest('[data-talk-preview-card]');
            if (card) await toggleFullscreen(card);
        }
    });

    document.addEventListener('fullscreenchange', () => {
        for (const card of cards) {
            const root = card.querySelector('.js-talk-preview-deck');
            const deck = root ? decks.get(root.id) : null;
            deck?.layout();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || document.fullscreenElement) return;
        const activeCard = document.querySelector('[data-talk-preview-card].is-previewing');
        if (activeCard) deactivatePreview(activeCard);
    });
}

initializeTalkPreviews();
