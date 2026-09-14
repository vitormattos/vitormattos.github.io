// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import jigsaw from '@tighten/jigsaw-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        jigsaw({
            input: ['source/_assets/scss/main.scss'],
            refresh: true,
        }),
    ],
});
