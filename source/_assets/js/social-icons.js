// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { siGithub, siLinkedin } from 'simple-icons';

const icons = {
    github: siGithub,
    linkedin: siLinkedin,
};

document.querySelectorAll('[data-simple-icon]').forEach((container) => {
    const icon = icons[container.dataset.simpleIcon];
    if (!icon) {
        return;
    }

    container.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="${icon.path}"></path></svg>`;
});
