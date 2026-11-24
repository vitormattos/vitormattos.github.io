// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import assert from 'node:assert/strict';
import { createReadStream } from 'node:fs';
import { stat } from 'node:fs/promises';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { resolve, extname, sep } from 'node:path';

const require = createRequire(new URL('../../scripts/slides/package.json', import.meta.url));
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer');
const origin = 'http://127.0.0.1:18675';
const prefix = '/pr-preview/pr-local';
const root = resolve(process.env.SITE_BUILD_DIR || 'build_preview');
const types = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.geojson': 'application/geo+json',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.webp': 'image/webp',
    '.svg': 'image/svg+xml',
};
const server = createServer(async (req, res) => {
    try {
        const path = new URL(req.url, origin).pathname;
        if (!path.startsWith(prefix + '/')) throw new Error('Not found');
        let file = resolve(root, '.' + decodeURIComponent(path.slice(prefix.length)));
        if (!file.startsWith(root + sep)) throw new Error('Not found');
        if ((await stat(file)).isDirectory()) file = resolve(file, 'index.html');
        await stat(file);
        res.setHeader('Content-Type', types[extname(file)] || 'application/octet-stream');
        createReadStream(file).pipe(res);
    } catch {
        res.writeHead(404).end();
    }
});
await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(18675, '127.0.0.1', resolve);
});
let browser;
try {
    browser = await puppeteer.launch({
        headless: true,
        ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}),
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.setRequestInterception(true);
    page.on('request', (request) => {
        // Keep browser regression checks deterministic, independent of YouTube,
        // podcast hosting, Gravatar, or any external service availability.
        if (request.url().startsWith(origin + '/') || request.url().startsWith('data:')) request.continue();
        else if (request.resourceType() === 'document')
            request.respond({
                status: 200,
                contentType: 'text/html',
                body: '<!doctype html><title>External player fixture</title>',
            });
        else request.abort();
    });
    await page.evaluateOnNewDocument(() => localStorage.setItem('site-locale', 'pt-BR'));
    await page.setViewport({ width: 1440, height: 1000 });
    const url = origin + prefix + '/pt-BR/acervo/';
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.media-grid.is-enhanced');
    const count = () => page.$$eval('[data-record]:not([hidden])', (records) => records.length);
    const total = await count();
    assert.ok(total >= 8);
    assert.equal(
        await page.$$eval(
            '[data-record][data-type="presentation"], [data-record][data-type="press"]',
            (records) => records.length,
        ),
        0,
    );
    assert.equal(await page.$$eval('iframe, audio, video', (elements) => elements.length), 0);
    await page.click('[data-type-filter="video"]');
    assert.equal(
        await page.$$eval('[data-record]:not([hidden]):not([data-type="video"])', (records) => records.length),
        0,
    );
    assert.match(page.url(), /type=video/);
    await page.click('#github-open-source-friday-2025 [data-open]');
    await page.waitForSelector('dialog[open] iframe');
    assert.match(await page.$eval('dialog iframe', (frame) => frame.src), /youtube-nocookie\.com\/embed\/a34UxYzVU1k/);
    await page.click('[data-fullscreen]');
    await page.waitForFunction(() => document.fullscreenElement?.hasAttribute('data-viewer'));
    await page.click('[data-close]');
    await page.waitForFunction(() => !document.fullscreenElement && !document.querySelector('dialog iframe'));
    await page.click('[data-type-filter="audio"]');
    await page.click('#latinoware-bate-papo-nerd-2026 [data-open]');
    await page.waitForSelector('dialog[open] audio[controls]');
    await page.click('[data-close]');
    await page.waitForFunction(() => !document.querySelector('dialog audio'));
    await page.click('[data-view=mosaic]');
    assert.equal(await count(), total);
    await page.click('[data-view=map]');
    await page.waitForSelector('.leaflet-container');
    await page.waitForSelector('.media-map-pin[aria-label]');
    await page.waitForSelector('.media-map-country-label');
    const geo = await page.evaluate(() => {
        const places = new Set(
            [...document.querySelectorAll('[data-cities] button[data-city]')].map((button) => button.dataset.city),
        );
        const counts = {};
        let unlocated = 0;
        for (const record of document.querySelectorAll('[data-record]')) {
            if (places.has(record.dataset.city)) counts[record.dataset.city] = (counts[record.dataset.city] || 0) + 1;
            else unlocated++;
        }
        return { counts, unlocated };
    });
    assert.equal(await page.$$eval('.media-map-pin', (pins) => pins.length), Object.keys(geo.counts).length);
    assert.ok(
        (await page.$eval('[data-map-summary]', (summary) => summary.textContent)).startsWith(
            String(Object.keys(geo.counts).length),
        ),
    );
    assert.equal(
        await page.$eval('[data-map-unlocated]', (summary) => summary.textContent),
        geo.unlocated
            ? geo.unlocated +
                  (geo.unlocated === 1
                      ? ' registro sem localidade documentada'
                      : ' registros sem localidade documentada')
            : '',
    );
    await page.waitForFunction(() =>
        [...document.querySelectorAll('.media-map-country-label')].some((label) => label.textContent === 'Brasil'),
    );
    assert.ok(
        await page.$eval(
            '.leaflet-countryLabels-pane',
            (pane) =>
                Number(getComputedStyle(pane).zIndex) >
                Number(getComputedStyle(document.querySelector('.leaflet-overlay-pane')).zIndex),
        ),
    );
    assert.ok(await page.$$eval('.media-map-admin-boundary', (lines) => lines.length > 0));
    await page.click('[data-map-fit]');
    await page.click('.media-map-pin[aria-label^="Rio de Janeiro,"]');
    assert.equal(
        await page.$eval('button[data-city="Rio de Janeiro"]', (button) => button.getAttribute('aria-pressed')),
        'true',
    );
    assert.equal(
        await page.$eval('[data-map-results-title]', (heading) => heading.textContent),
        'Mídias em Rio de Janeiro',
    );
    assert.equal(await count(), geo.counts['Rio de Janeiro']);
    await page.click('.media-map-results-heading [data-city-clear]');
    assert.equal(await count(), total);
    await page.click('[data-cities] button[data-city="Rio de Janeiro"]');
    assert.ok((await count()) < total);
    await page.click('[data-view=mosaic]');
    assert.equal(await count(), total);
    await page.click('[data-view=albums]');
    await page.click('[data-albums] button');
    await page.click('[data-jump="2012"]');
    assert.equal(await count(), total);
    await page.waitForFunction(
        () => document.querySelector('[data-jump="2012"]').getAttribute('aria-current') === 'true',
    );
    await page.type('input[name=q]', 'a-phrase-with-no-matching-media');
    assert.equal(await count(), 0);
    await page.click('[data-clear]');
    assert.equal(await count(), total);
    await page.click('[data-view=map]');
    await page.click('[data-type-filter="audio"]');
    await page.waitForFunction(() => document.querySelectorAll('.media-map-pin').length === 1);
    assert.equal(
        await page.$eval('[data-map-summary]', (summary) => summary.textContent),
        '1 localidade · 1 registro no mapa',
    );
    await page.type('input[name=q]', 'a-phrase-with-no-matching-media');
    await page.waitForFunction(() => document.querySelectorAll('.media-map-pin').length === 0);
    assert.equal(await page.$eval('[data-map-fit]', (button) => button.disabled), true);
    assert.equal(await page.$eval('[data-map-empty]', (element) => element.hidden), false);
    await page.setViewport({ width: 390, height: 844 });
    await page.goto(url + '?view=map', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.media-map-pin');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2));

    await page.goto(url + '?type=audio', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.media-grid.is-enhanced');
    assert.equal(await count(), 1);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2));
    assert.deepEqual(errors, []);
    console.log(
        'Media browser smoke: filters, history state, albums, map, timeline, video/audio lifecycle, fullscreen and responsive layout',
    );
} finally {
    await browser?.close();
    await new Promise((resolve) => server.close(resolve));
}
