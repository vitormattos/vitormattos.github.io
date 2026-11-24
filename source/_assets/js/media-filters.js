// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

export const normalize = (value) =>
    String(value || '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

export function matchesMedia(record, filters) {
    return (
        ['type', 'city', 'year', 'album'].every((key) => !filters[key] || record[key] === filters[key]) &&
        (!filters.topic || JSON.parse(record.topics).includes(filters.topic)) &&
        normalize(filters.q)
            .split(/\s+/)
            .filter(Boolean)
            .every((word) => normalize(record.search).includes(word))
    );
}

export function catalogYears(records) {
    return [...new Set(records.map((record) => record.year))].sort((a, b) =>
        a === 'unknown' ? 1 : b === 'unknown' ? -1 : Number(b) - Number(a),
    );
}

export function browseMode(mode, currentFilters) {
    if (mode === 'mosaic') {
        return { q: '', type: '', topic: '', city: '', year: '', album: '' };
    }
    return { ...currentFilters, city: '', album: '' };
}
