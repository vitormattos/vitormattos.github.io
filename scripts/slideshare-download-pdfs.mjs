// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const repository = process.env.GITHUB_REPOSITORY;
const email = process.env.SLIDESHARE_EMAIL;
const password = process.env.SLIDESHARE_PASSWORD;
if (!repository) throw new Error('GITHUB_REPOSITORY is required.');
if (!email || !password) throw new Error('SLIDESHARE_EMAIL and SLIDESHARE_PASSWORD secrets are required.');

const metadataFiles = execFileSync('find', [
  'presentations/slideshare', '-mindepth', '2', '-maxdepth', '2', '-name', 'metadata.json',
], { encoding: 'utf8' }).trim().split('\n').filter(Boolean).sort();

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ acceptDownloads: true, locale: 'pt-BR' });
let uploaded = 0;
let skipped = 0;
let reconstructed = 0;
let failed = 0;

try {
  await authenticate(context, email, password);

  for (const metadataFile of metadataFiles) {
    const metadata = JSON.parse(readFileSync(metadataFile, 'utf8'));
    const id = String(metadata.id);
    const tag = `slideshare-${id}`;
    const filename = `${tag}.pdf`;
    const work = mkdtempSync(join(tmpdir(), `${tag}-`));
    const output = join(work, filename);
    const page = await context.newPage();

    try {
      let release = getRelease(tag);
      if (release === null) {
        console.log(`${id}: release does not exist; creating ${tag}.`);
        execFileSync('gh', ['release', 'create', tag, '--repo', repository, '--target', 'main', '--title', String(metadata.title ?? tag), '--notes', releaseNotes(metadata)], { stdio: 'inherit' });
        release = getRelease(tag);
      }

      if ((release?.assets ?? []).some((asset) => String(asset.name).toLowerCase().endsWith('.pdf'))) {
        console.log(`${id}: PDF already archived; skipping.`);
        skipped += 1;
        continue;
      }

      console.log(`${id}: opening ${metadata.source_url}`);
      await page.goto(metadata.source_url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

      let saved = await downloadAuthenticated(page, output, id);
      let archiveKind = 'native';

      if (!saved && metadata.source_download_url) {
        saved = await downloadViaSession(context, metadata.source_download_url, output);
        if (saved) console.log(`${id}: downloaded native PDF using authenticated source_download_url.`);
      }

      if (!saved) {
        const result = await reconstructFromPublicSlides(page, context, output, id);
        saved = result.saved;
        archiveKind = 'reconstructed';
        if (saved) {
          reconstructed += 1;
          console.log(`${id}: reconstructed PDF from ${result.slideCount} public slide images.`);
        }
      }

      if (!saved) {
        console.log(`::warning::${id}: neither an authenticated download nor public slide images could be archived.`);
        failed += 1;
        continue;
      }

      assertPdf(output, id);
      execFileSync('gh', ['release', 'upload', tag, output, '--repo', repository, '--clobber'], { stdio: 'inherit' });
      updateReleaseNotes(tag, metadata, archiveKind);
      console.log(`${id}: uploaded ${filename}.`);
      uploaded += 1;
    } catch (error) {
      failed += 1;
      console.log(`::warning::${id}: ${error instanceof Error ? error.message : String(error)}`);
    } finally {
      await page.close();
      rmSync(work, { recursive: true, force: true });
    }
  }
} finally {
  await context.close();
  await browser.close();
}

console.log(`SlideShare PDF backfill finished: ${uploaded} uploaded (${reconstructed} reconstructed), ${skipped} already present, ${failed} failed.`);
if (failed > 0) process.exitCode = 2;

async function authenticate(context, username, secret) {
  const page = await context.newPage();
  try {
    console.log('Authenticating with SlideShare/Scribd...');
    await page.goto('https://www.slideshare.net/login', { waitUntil: 'domcontentloaded', timeout: 90_000 });

    const emailInput = page.locator('input[type="email"], input[name*="email" i], input[autocomplete="username"]').first();
    if (!(await emailInput.isVisible({ timeout: 10_000 }).catch(() => false))) {
      throw new Error(`login email field not found at ${page.url()}`);
    }
    await emailInput.fill(username);

    const passwordInput = page.locator('input[type="password"], input[autocomplete="current-password"]').first();
    if (!(await passwordInput.isVisible({ timeout: 3_000 }).catch(() => false))) {
      const next = page.getByRole('button', { name: /continue|next|continuar|avançar|entrar/i }).first();
      if (await next.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await next.click();
        await page.waitForLoadState('domcontentloaded').catch(() => {});
      }
    }

    const passwordField = page.locator('input[type="password"], input[autocomplete="current-password"]').first();
    if (!(await passwordField.isVisible({ timeout: 10_000 }).catch(() => false))) {
      throw new Error(`login password field not found at ${page.url()}`);
    }
    await passwordField.fill(secret);

    const submit = page.locator('button[type="submit"], input[type="submit"]').first();
    if (await submit.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await submit.click();
    } else {
      await passwordField.press('Enter');
    }

    await page.waitForLoadState('domcontentloaded', { timeout: 30_000 }).catch(() => {});
    await page.waitForTimeout(2_000);

    const stillHasPassword = await page.locator('input[type="password"]').isVisible().catch(() => false);
    const url = page.url();
    if (stillHasPassword || /\/login(?:[/?#]|$)|\/sign-?in(?:[/?#]|$)/i.test(url)) {
      const message = await page.locator('[role="alert"], .error, [class*="error" i]').first().textContent().catch(() => null);
      throw new Error(`authentication was not confirmed${message ? `: ${message.trim().slice(0, 200)}` : ''}`);
    }

    const cookies = await context.cookies();
    if (cookies.length === 0) throw new Error('authentication produced no session cookies.');
    console.log(`Authentication confirmed; session established with ${cookies.length} cookies.`);
  } finally {
    await page.close();
  }
}

async function downloadAuthenticated(page, output, id) {
  const candidates = [
    page.getByRole('link', { name: /download|baixar/i }),
    page.getByRole('button', { name: /download|baixar/i }),
    page.locator('a[download]'),
    page.locator('a[href*="download" i]'),
  ];

  for (const candidate of candidates) {
    const first = candidate.first();
    if (!(await first.isVisible({ timeout: 1_000 }).catch(() => false))) continue;

    const download = await Promise.all([
      page.waitForEvent('download', { timeout: 20_000 }),
      first.click(),
    ]).then(([event]) => event).catch(() => null);
    if (!download) continue;

    await download.saveAs(output);
    if (looksLikePdf(readFileSync(output))) {
      console.log(`${id}: downloaded native PDF using authenticated browser session.`);
      return true;
    }
    rmSync(output, { force: true });
  }

  return false;
}

function getRelease(tag) {
  try {
    return JSON.parse(execFileSync('gh', ['release', 'view', tag, '--repo', repository, '--json', 'assets'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }));
  } catch {
    return null;
  }
}

function releaseNotes(metadata, archiveKind = null) {
  const archiveNote = archiveKind === 'reconstructed'
    ? 'The archived PDF was reconstructed from the public slide images exposed by SlideShare. It is an archival rendering, not the original uploaded file.'
    : archiveKind === 'native'
      ? 'The archived PDF was obtained from the authenticated download exposed by SlideShare.'
      : 'Archived assets may be added as they become available.';
  return [metadata.description ? String(metadata.description).trim() : null, '', 'Archived presentation imported from SlideShare.', '', `- **SlideShare:** ${metadata.source_url}`, metadata.published_at ? `- **Published:** ${String(metadata.published_at).slice(0, 10)}` : null, metadata.language ? `- **Language:** ${metadata.language}` : null, '', archiveNote, '', 'This release is keyed by the immutable SlideShare presentation ID.'].filter((line) => line !== null).join('\n');
}

function updateReleaseNotes(tag, metadata, archiveKind) {
  execFileSync('gh', ['release', 'edit', tag, '--repo', repository, '--notes', releaseNotes(metadata, archiveKind)], { stdio: 'inherit' });
}

async function downloadViaSession(context, url, output) {
  try {
    const response = await context.request.get(url, { timeout: 15_000, failOnStatusCode: false, headers: { Accept: 'application/pdf,application/octet-stream;q=0.9,*/*;q=0.8', Referer: 'https://www.slideshare.net/' } });
    if (!response.ok()) return false;
    const body = await response.body();
    if (!looksLikePdf(body)) return false;
    writeFileSync(output, body);
    return true;
  } catch {
    return false;
  }
}

async function reconstructFromPublicSlides(sourcePage, context, output, id) {
  await sourcePage.evaluate(async () => {
    const step = Math.max(window.innerHeight, 800);
    for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 80));
    }
    window.scrollTo(0, 0);
  }).catch(() => {});

  const rawUrls = await sourcePage.evaluate(() => {
    const values = [];
    const attributes = ['src', 'data-src', 'data-normal', 'data-full', 'data-small', 'srcset'];
    for (const element of document.querySelectorAll('img, source')) {
      for (const attribute of attributes) {
        const value = element.getAttribute(attribute);
        if (value) values.push(value);
      }
    }
    values.push(...(document.documentElement.outerHTML.match(/https?:\\?\/\\?\/(?:image|cdn)\.slidesharecdn\.com[^\"'<>\s]+/gi) ?? []));
    return values;
  });

  const bySlide = new Map();
  for (const raw of rawUrls) {
    for (const url of splitCandidateUrls(raw)) {
      const normalized = normalizeSlideUrl(url);
      if (!normalized) continue;
      const slideNumber = getSlideNumber(normalized);
      if (slideNumber === null) continue;
      const current = bySlide.get(slideNumber);
      if (!current || resolutionScore(normalized) > resolutionScore(current)) bySlide.set(slideNumber, normalized);
    }
  }

  const slides = [...bySlide.entries()].sort((a, b) => a[0] - b[0]);
  if (slides.length === 0) return { saved: false, slideCount: 0 };

  const images = [];
  for (const [slideNumber, observedUrl] of slides) {
    const image = await fetchBestSlideImage(context, observedUrl);
    if (!image) {
      console.log(`::warning::${id}: slide ${slideNumber} image could not be downloaded.`);
      return { saved: false, slideCount: images.length };
    }
    images.push(image);
  }

  const renderPage = await context.newPage();
  try {
    const first = images[0];
    await renderPage.setContent(`<img id="probe" src="data:${first.contentType};base64,${first.body.toString('base64')}">`, { waitUntil: 'load' });
    const dimensions = await renderPage.locator('#probe').evaluate((img) => ({ width: img.naturalWidth || 1024, height: img.naturalHeight || 768 }));
    const width = Math.max(640, dimensions.width);
    const height = Math.max(480, dimensions.height);
    const slideHtml = images.map((image) => `<section class="slide"><img src="data:${image.contentType};base64,${image.body.toString('base64')}"></section>`).join('');
    await renderPage.setContent(`<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:0}html,body{margin:0;padding:0}.slide{width:${width}px;height:${height}px;display:flex;align-items:center;justify-content:center;break-after:page;page-break-after:always;overflow:hidden}.slide:last-child{break-after:auto;page-break-after:auto}.slide img{width:100%;height:100%;object-fit:contain;display:block}</style></head><body>${slideHtml}</body></html>`, { waitUntil: 'load', timeout: 90_000 });
    await renderPage.pdf({ path: output, width: `${width}px`, height: `${height}px`, margin: { top: '0', right: '0', bottom: '0', left: '0' }, printBackground: true, preferCSSPageSize: false });
  } finally {
    await renderPage.close();
  }
  return { saved: true, slideCount: images.length };
}

function splitCandidateUrls(value) {
  return String(value).replaceAll('\\u002F', '/').replaceAll('\\/', '/').replaceAll('&amp;', '&').split(',').map((item) => item.trim().split(/\s+/)[0]).filter(Boolean);
}

function normalizeSlideUrl(value) {
  try {
    const url = new URL(value);
    return /^(?:image|cdn)\.slidesharecdn\.com$/i.test(url.hostname) ? url.toString() : null;
  } catch {
    return null;
  }
}

function getSlideNumber(url) {
  const match = url.match(/(?:slide-)?(\d+)-\d+\.(?:jpe?g|png|webp)(?:\?|$)/i);
  return match ? Number(match[1]) : null;
}

function resolutionScore(url) {
  const match = url.match(/-(\d+)\.(?:jpe?g|png|webp)(?:\?|$)/i);
  return match ? Number(match[1]) : 0;
}

async function fetchBestSlideImage(context, observedUrl) {
  const candidates = [2048, 1600, 1024].map((resolution) => observedUrl.replace(/-\d+\.(jpe?g|png|webp)(\?|$)/i, `-${resolution}.jpg$2`));
  candidates.push(observedUrl);
  for (const url of [...new Set(candidates)]) {
    try {
      const response = await context.request.get(url, { timeout: 20_000, failOnStatusCode: false, headers: { Referer: 'https://www.slideshare.net/' } });
      if (!response.ok()) continue;
      const body = await response.body();
      const contentType = (response.headers()['content-type'] ?? 'image/jpeg').split(';')[0];
      if (contentType.startsWith('image/') && body.length >= 1000) return { body, contentType };
    } catch {}
  }
  return null;
}

function looksLikePdf(buffer) {
  return buffer.length >= 1000 && buffer.subarray(0, 5).toString() === '%PDF-';
}

function assertPdf(path, id) {
  const header = readFileSync(path).subarray(0, 5).toString();
  if (header !== '%PDF-' || statSync(path).size < 1000) throw new Error(`${id}: archived file is not a valid PDF.`);
}
