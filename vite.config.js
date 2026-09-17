// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import jigsaw from '@tighten/jigsaw-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        jigsaw({
            input: [
                'source/_assets/scss/main.scss',
                'source/_assets/scss/presentations.scss',
                'source/_assets/scss/talks-catalog.scss',
                'source/_assets/scss/talks-list-flow.scss',
                'source/_assets/scss/talk-preview.scss',
                'source/_assets/js/presentations.js',
                'source/_assets/js/talk-preview.js',
                'source/_assets/js/social-icons.js',
            ],
            refresh: true,
        }),
    ],
});
