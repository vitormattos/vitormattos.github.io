// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const repository = process.env.GITHUB_REPOSITORY;
if (!repository) throw new Error('GITHUB_REPOSITORY is required.');

const metadataFiles = execFileSync('find', [
  'presentations/slideshare',
  '-mindepth', '2',
  '-maxdepth', '2',
  '-name', 'metadata.json',
], { encoding: 'utf8' })
  .trim()
  .split('\n')
  .filter(Boolean)
  .sort();

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ acceptDownloads: true, locale: 'pt-BR' });

let uploaded = 0;
let skipped = 0;
let failed = 0;

try {
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
        execFileSync('gh', [
          'release', 'create', tag,
          '--repo', repository,
          '--target', 'main',
          '--title', String(metadata.title ?? tag),
          '--notes', releaseNotes(metadata),
        ], { stdio: 'inherit' });
        release = getRelease(tag);
      }

      if ((release?.assets ?? []).some((asset) => String(asset.name).toLowerCase().endsWith('.pdf'))) {
        console.log(`${id}: PDF already archived; skipping.`);
        skipped += 1;
        continue;
      }

      console.log(`${id}: opening ${metadata.source_url}`);
      await page.goto(metadata.source_url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {});

      let saved = false;

      if (metadata.source_download_url) {
        saved = await downloadViaSession(context, metadata.source_download_url, output);
        if (saved) {
          console.log(`${id}: downloaded PDF using source_download_url.`);
        }
      }

      if (!saved) {
        saved = await downloadByClick(page, output);
        if (saved) {
          console.log(`${id}: downloaded PDF using browser interaction.`);
        }
      }

      if (!saved) {
        console.log(`::warning::${id}: no downloadable PDF was exposed to the browser.`);
        failed += 1;
        continue;
      }

      assertPdf(output, id);
      execFileSync('gh', ['release', 'upload', tag, output, '--repo', repository, '--clobber'], { stdio: 'inherit' });
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

console.log(`SlideShare PDF backfill finished: ${uploaded} uploaded, ${skipped} already present, ${failed} unavailable/failed.`);
if (failed > 0) {
  process.exitCode = 2;
}

function getRelease(tag) {
  try {
    return JSON.parse(execFileSync(
      'gh',
      ['release', 'view', tag, '--repo', repository, '--json', 'assets'],
      { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] },
    ));
  } catch {
    return null;
  }
}

function releaseNotes(metadata) {
  const lines = [
    metadata.description ? String(metadata.description).trim() : null,
    '',
    'Archived presentation imported from SlideShare.',
    '',
    `- **SlideShare:** ${metadata.source_url}`,
    metadata.published_at ? `- **Published:** ${String(metadata.published_at).slice(0, 10)}` : null,
    metadata.language ? `- **Language:** ${metadata.language}` : null,
    '',
    'This release is keyed by the immutable SlideShare presentation ID. Archived assets may be added as they become available.',
  ];

  return lines.filter((line) => line !== null).join('\n');
}

async function downloadViaSession(context, url, output) {
  try {
    const response = await context.request.get(url, {
      timeout: 60_000,
      failOnStatusCode: false,
      headers: {
        Accept: 'application/pdf,application/octet-stream;q=0.9,*/*;q=0.8',
        Referer: 'https://www.slideshare.net/',
      },
    });

    if (!response.ok()) return false;

    const body = await response.body();
    if (!looksLikePdf(body)) return false;

    writeFileSync(output, body);
    return true;
  } catch {
    return false;
  }
}

async function downloadByClick(page, output) {
  const candidates = [
    page.getByRole('link', { name: /baixar agora|download now|download/i }),
    page.getByRole('button', { name: /baixar agora|download now|download/i }),
    page.locator('a[download]'),
    page.locator('a[href*="dwnld_file"]'),
  ];

  for (const candidate of candidates) {
    const first = candidate.first();
    if (await first.count() === 0 || !(await first.isVisible().catch(() => false))) continue;

    const download = await Promise.all([
      page.waitForEvent('download', { timeout: 45_000 }),
      first.click(),
    ]).then(([event]) => event).catch(() => null);

    if (!download) continue;

    await download.saveAs(output);
    return true;
  }

  return false;
}

function looksLikePdf(buffer) {
  return buffer.length >= 1000 && buffer.subarray(0, 5).toString() === '%PDF-';
}

function assertPdf(path, id) {
  const header = readFileSync(path).subarray(0, 5).toString();
  if (header !== '%PDF-' || statSync(path).size < 1000) {
    throw new Error(`${id}: downloaded file is not a valid PDF.`);
  }
}
