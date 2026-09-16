// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { faGithub, faLinkedin } from '@fortawesome/free-brands-svg-icons';

const icons = {
    github: faGithub,
    linkedin: faLinkedin,
};

document.querySelectorAll('[data-brand-icon]').forEach((container) => {
    const icon = icons[container.dataset.brandIcon];
    if (!icon) {
        return;
    }

    const [width, height, , , path] = icon.icon;
    container.innerHTML = `<svg viewBox="0 0 ${width} ${height}" aria-hidden="true" focusable="false"><path fill="currentColor" d="${path}"></path></svg>`;
});
