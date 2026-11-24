// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import L from 'leaflet';
import leafletCss from 'leaflet/dist/leaflet.css?inline';
import { containsCoordinate } from './media-map-geometry.js';

export async function createMediaMap(container, geography, { locale, selectCity, fitButton }) {
    const style = document.createElement('style');
    style.textContent = leafletCss;
    document.head.append(style);

    const map = L.map(container, {
        scrollWheelZoom: false,
        zoomControl: false,
        minZoom: 1,
        maxZoom: 6,
        zoomSnap: 0.25,
        zoomDelta: 0.75,
        maxBounds: [
            [-85, -180],
            [85, 180],
        ],
        maxBoundsViscosity: 0.85,
    }).setView([0, 0], 2);

    L.control
        .zoom({
            position: 'topright',
            zoomInTitle: locale === 'en' ? 'Zoom in' : 'Ampliar mapa',
            zoomOutTitle: locale === 'en' ? 'Zoom out' : 'Reduzir mapa',
        })
        .addTo(map);
    L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);
    map.attributionControl.setPrefix(false);
    map.attributionControl.addAttribution(
        '<a href="https://www.naturalearthdata.com/about/terms-of-use/" target="_blank" rel="noopener noreferrer">Natural Earth</a>',
    );
    L.geoJSON(geography, {
        style: { className: 'media-map-land', weight: 1, fillOpacity: 1 },
        interactive: false,
    }).addTo(map);

    const boundaries = L.geoJSON(geography.admin1, {
        style: { className: 'media-map-admin-boundary', weight: 0.7, fill: false },
        interactive: false,
    });
    function updateBoundaries() {
        if (map.getZoom() >= 3.5) boundaries.addTo(map);
        else boundaries.remove();
    }

    const countryPane = map.createPane('countryLabels');
    countryPane.style.zIndex = '450';
    countryPane.style.pointerEvents = 'none';
    const labels = L.layerGroup().addTo(map);
    const markers = new Map();
    let locations = [];
    let signature = null;
    let frame = 0;
    let compactLabels = null;

    function fit() {
        if (!locations.length || !container.clientWidth) return;
        map.invalidateSize({ pan: false });
        const bounds = L.latLngBounds(locations.map((item) => item.coordinates));
        // Keep national context for a single location rather than zooming to a street.
        map.fitBounds(bounds.pad(locations.length > 1 ? 0.32 : 0), {
            padding: [48, 42],
            maxZoom: container.clientWidth < 480 ? 3.5 : 4.25,
            animate: false,
        });
    }

    function drawLabels() {
        if (!container.clientWidth) return;
        labels.clearLayers();
        const zoom = map.getZoom();
        const size = map.getSize();
        const occupied = locations.map(({ coordinates }) => {
            const p = map.latLngToContainerPoint(coordinates);
            return {
                left: p.x - (p.x > size.x / 2 ? 112 : 23),
                right: p.x + (p.x > size.x / 2 ? 23 : 112),
                top: p.y - 24,
                bottom: p.y + 24,
            };
        });
        const intersects = (a, b) => a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
        const eventCountries = new Set(locations.map(({ country }) => country));
        const ordered = [...geography.features].sort(
            (a, b) =>
                Number(eventCountries.has(b.properties.country)) - Number(eventCountries.has(a.properties.country)) ||
                a.properties.rank - b.properties.rank,
        );
        for (const feature of ordered) {
            const { label, names, minZoom, rank, country } = feature.properties;
            if (zoom + 0.5 < minZoom || (zoom < 2.5 && rank > 2 && !eventCountries.has(country))) continue;
            const text = names[locale] || names.en;
            const width = Math.min(148, Math.max(40, text.length * 6.5));
            const boxAt = (point) => ({
                left: point.x - width / 2,
                right: point.x + width / 2,
                top: point.y - 9,
                bottom: point.y + 9,
            });
            const isClear = (box) =>
                box.left >= 10 &&
                box.right <= size.x - 10 &&
                box.top >= 12 &&
                box.bottom <= size.y - 12 &&
                !occupied.some((other) => intersects(box, other));
            let position = label;
            let point = map.latLngToContainerPoint(position);
            let box = boxAt(point);
            if (!isClear(box) && eventCountries.has(country)) {
                // A fixed Natural Earth label may sit outside a regional viewport.
                // Search visible, unobstructed points that really lie inside that country.
                const candidates = [];
                for (let y = 28; y < size.y - 24; y += 32) {
                    for (let x = width / 2 + 14; x < size.x - width / 2 - 14; x += 40) {
                        const candidate = L.point(x, y);
                        if (isClear(boxAt(candidate))) candidates.push(candidate);
                    }
                }
                candidates.sort((a, b) => a.distanceTo(point) - b.distanceTo(point));
                const candidate = candidates.find((candidate) => {
                    const { lng, lat } = map.containerPointToLatLng(candidate);
                    return containsCoordinate(feature.geometry, [lng, lat]);
                });
                if (candidate) {
                    const { lat, lng } = map.containerPointToLatLng(candidate);
                    position = [lat, lng];
                    box = boxAt(candidate);
                }
            }
            if (!isClear(box)) continue;
            occupied.push(box);
            const element = document.createElement('span');
            element.textContent = text;
            const icon = L.divIcon({
                className: 'media-map-country-label',
                html: element,
                iconSize: [width, 18],
                iconAnchor: [width / 2, 9],
            });
            L.marker(position, { icon, pane: 'countryLabels', interactive: false, keyboard: false }).addTo(labels);
        }
    }

    function updateCityLabels() {
        const compact = container.clientWidth < 480;
        const changed = compactLabels !== compact;
        for (const item of locations) {
            const marker = markers.get(item.city);
            if (!marker) continue;
            if (changed || !marker.getTooltip()) {
                marker.unbindTooltip();
                const cityLabel = document.createElement('span');
                cityLabel.textContent = item.city;
                marker.bindTooltip(cityLabel, {
                    className: 'media-map-city-label',
                    direction: 'auto',
                    offset: [20, 0],
                    permanent: !compact,
                    opacity: 1,
                });
            }
            if (compact) {
                if (item.active) marker.openTooltip();
                else marker.closeTooltip();
            }
        }
        compactLabels = compact;
    }

    function scheduleLabels() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(drawLabels);
    }

    map.on('moveend zoomend resize', scheduleLabels);
    map.on('zoomend', updateBoundaries);
    updateBoundaries();
    fitButton.addEventListener('click', fit);
    const observer = new ResizeObserver(() => {
        if (container.clientWidth && container.clientHeight) {
            map.invalidateSize({ pan: false });
            updateCityLabels();
            scheduleLabels();
        }
    });
    observer.observe(container);

    function refresh(nextLocations) {
        locations = nextLocations;
        const nextCities = new Set(locations.map((item) => item.city));
        for (const [city, marker] of markers) {
            if (!nextCities.has(city)) {
                marker.remove();
                markers.delete(city);
            }
        }
        for (const item of locations) {
            let marker = markers.get(item.city);
            if (!marker) {
                const count = document.createElement('span');
                count.className = 'media-map-pin-count';
                const icon = L.divIcon({
                    className: 'media-map-pin',
                    html: count,
                    iconSize: [36, 36],
                    iconAnchor: [18, 18],
                    tooltipAnchor: [0, 0],
                });
                marker = L.marker(item.coordinates, {
                    icon,
                    keyboard: true,
                    riseOnHover: true,
                }).addTo(map);
                marker.on('click', () => selectCity(item.city));
                markers.set(item.city, marker);
            }
            const element = marker.getElement();
            element.querySelector('.media-map-pin-count').textContent = String(item.count);
            element.setAttribute('aria-label', item.label);
            element.setAttribute('aria-pressed', String(item.active));
            element.title = item.label;
            marker.setZIndexOffset(item.active ? 1000 : 0);
        }
        updateCityLabels();
        fitButton.disabled = locations.length === 0;
        const nextSignature = [...nextCities].sort().join('\n');
        if (container.clientWidth) {
            map.invalidateSize({ pan: false });
            if (nextSignature !== signature && nextCities.size) fit();
            scheduleLabels();
        }
        signature = nextSignature;
    }
    return { refresh };
}
