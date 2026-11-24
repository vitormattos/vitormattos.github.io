// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { browseMode, catalogYears, matchesMedia, normalize } from '../../source/_assets/js/media-filters.js';

const record = {
    type: 'video',
    city: 'Florianópolis',
    year: '2017',
    album: 'TDC · 2017',
    topics: 'php community',
    search: 'TDC Florianópolis PHP oportunidades',
};

test('accent-insensitive search combines words with metadata filters', () => {
    assert.equal(normalize('São'), 'sao');
    assert.equal(matchesMedia(record, { q: 'florianopolis PHP', type: 'video', year: '2017', topic: 'php' }), true);
    assert.equal(matchesMedia(record, { q: 'florianopolis PHP', type: 'photo' }), false);
    assert.equal(matchesMedia(record, { q: 'florianopolis segurança' }), false);
});

test('topic, album, year and city filters match exact metadata', () => {
    assert.equal(matchesMedia(record, { topic: 'ph' }), false);
    assert.equal(matchesMedia(record, { city: 'Florianópolis', album: 'TDC · 2017' }), true);
    assert.equal(matchesMedia(record, { year: 'unknown' }), false);
    assert.equal(matchesMedia(record, { album: 'TDC · 2018' }), false);
});

test('all media clears a previous map, album and search scope', () => {
    const filtered = { q: 'PHP', type: 'video', city: 'Rio de Janeiro', year: '2018', topic: 'php', album: 'FISL 18' };
    assert.deepEqual(browseMode('mosaic', filtered), { q: '', type: '', topic: '', city: '', year: '', album: '' });
    assert.deepEqual(browseMode('albums', filtered), { ...filtered, city: '', album: '' });
    assert.deepEqual(browseMode('map', filtered), { ...filtered, city: '', album: '' });
});

test('the chronological rail uses unique years from the full archive, with undated media last', () => {
    assert.deepEqual(
        catalogYears([{ year: 'unknown' }, { year: '2018' }, { year: '2026' }, { year: '2018' }, { year: '2012' }]),
        ['2026', '2018', '2012', 'unknown'],
    );
});
