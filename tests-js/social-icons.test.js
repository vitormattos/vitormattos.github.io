// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import assert from 'node:assert/strict';
import test from 'node:test';

import { iconMarkup, renderIcon } from '../source/_assets/js/social-icons.js';

test('iconMarkup returns accessible inline SVG markup', () => {
    const icon = { icon: [16, 20, [], '', 'M0 0h16v20z'] };

    assert.equal(
        iconMarkup(icon),
        '<svg viewBox="0 0 16 20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M0 0h16v20z"></path></svg>',
    );
});

test('iconMarkup returns an empty string when an icon is unavailable', () => {
    assert.equal(iconMarkup(undefined), '');
});

test('renderIcon mutates the container only when an icon exists', () => {
    const container = { innerHTML: 'unchanged' };

    assert.equal(renderIcon(container, undefined), false);
    assert.equal(container.innerHTML, 'unchanged');

    assert.equal(renderIcon(container, { icon: [8, 8, [], '', 'path'] }), true);
    assert.match(container.innerHTML, /viewBox="0 0 8 8"/);
});
