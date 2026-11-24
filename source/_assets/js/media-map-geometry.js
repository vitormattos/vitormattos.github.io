// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

// Ray casting over the GeoJSON rings. Holes are excluded and each polygon
// in a MultiPolygon is tested independently.
export function containsCoordinate(geometry, [x, y]) {
    const ringContains = (ring) => {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const [xi, yi] = ring[i];
            const [xj, yj] = ring[j];
            if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside;
        }
        return inside;
    };
    const polygons =
        geometry.type === 'Polygon'
            ? [geometry.coordinates]
            : geometry.type === 'MultiPolygon'
              ? geometry.coordinates
              : [];
    return polygons.some(([outer, ...holes]) => ringContains(outer) && !holes.some(ringContains));
}
