// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { faGithub, faLinkedin } from '@fortawesome/free-brands-svg-icons';
import { faRss } from '@fortawesome/free-solid-svg-icons';

const brandIcons = {
    github: faGithub,
    linkedin: faLinkedin,
};

const uiIcons = {
    rss: faRss,
};

export function iconMarkup(icon) {
    if (!icon) return '';

    const [width, height, , , path] = icon.icon;

    return `<svg viewBox="0 0 ${width} ${height}" aria-hidden="true" focusable="false"><path fill="currentColor" d="${path}"></path></svg>`;
}

export function renderIcon(container, icon) {
    const markup = iconMarkup(icon);
    if (markup === '') return false;

    container.innerHTML = markup;
    return true;
}

export function initializeIcons(root = document) {
    root.querySelectorAll('[data-brand-icon]').forEach((container) => {
        renderIcon(container, brandIcons[container.dataset.brandIcon]);
    });

    root.querySelectorAll('[data-ui-icon]').forEach((container) => {
        renderIcon(container, uiIcons[container.dataset.uiIcon]);
    });
}

if (typeof document !== 'undefined') {
    initializeIcons(document);
}
