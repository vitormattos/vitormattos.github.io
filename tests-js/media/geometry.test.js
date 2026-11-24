// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { containsCoordinate } from '../../source/_assets/js/media-map-geometry.js';

test('country label positions remain inside the geometry and outside holes', () => {
    const outer = [
        [0, 0],
        [10, 0],
        [10, 10],
        [0, 10],
        [0, 0],
    ];
    const hole = [
        [3, 3],
        [7, 3],
        [7, 7],
        [3, 7],
        [3, 3],
    ];
    const island = [
        [20, 0],
        [25, 0],
        [25, 5],
        [20, 5],
        [20, 0],
    ];
    const geometry = { type: 'MultiPolygon', coordinates: [[outer, hole], [island]] };
    assert.equal(containsCoordinate(geometry, [2, 2]), true);
    assert.equal(containsCoordinate(geometry, [5, 5]), false);
    assert.equal(containsCoordinate(geometry, [22, 2]), true);
    assert.equal(containsCoordinate(geometry, [15, 5]), false);
});
