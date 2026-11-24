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
        (!filters.topic || record.topics.split(' ').includes(filters.topic)) &&
        normalize(filters.q)
            .split(/\s+/)
            .filter(Boolean)
            .every((word) => normalize(record.search).includes(word))
    );
}
