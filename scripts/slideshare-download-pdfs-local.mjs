// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { join } from 'node:path';

const repository = 'vitormattos/vitormattos.github.io';
const outputDir = '.cache/slideshare-pdf-backfill';
mkdirSync(outputDir, { recursive: true });

checkCommand('gh', ['auth', 'status']);

const metadataFiles = execFileSync('find', [
  'presentations/slideshare', '-mindepth', '2', '-maxdepth', '2', '-name', 'metadata.json',
], { encoding: 'utf8' }).trim().split('\n').filter(Boolean).sort();

const browser = await chromium.launch({ headless: false });
const context = await browser.newContext({ acceptDownloads: true, locale: 'pt-BR' });
const loginPage = await context.newPage();

console.log('\nA browser window was opened.');
console.log('Log in to SlideShare/Scribd manually.');
console.log('When SlideShare is visibly authenticated, return to this terminal and press ENTER.\n');
await loginPage.goto('https://www.slideshare.net/login', { waitUntil: 'domcontentloaded', timeout: 90_000 });
await waitForEnter();
await loginPage.goto('https://www.slideshare.net/', { waitUntil: 'domcontentloaded', timeout: 60_000 });

const signIn = loginPage.getByRole('link', { name: /^sign in$/i }).first();
if (await signIn.isVisible({ timeout: 3_000 }).catch(() => false)) {
  throw new Error('SlideShare still appears signed out. Log in successfully before pressing ENTER.');
}
console.log('Authenticated browser session confirmed. Starting backfill.\n');
await loginPage.close();

let uploaded = 0;
let skipped = 0;
let failed = 0;

try {
  for (const metadataFile of metadataFiles) {
    const metadata = JSON.parse(readFileSync(metadataFile, 'utf8'));
    const id = String(metadata.id);
    const tag = `slideshare-${id}`;
    const filename = `${tag}.pdf`;
    const output = join(outputDir, filename);

    try {
      ensureRelease(tag, metadata);
      if (releaseHasPdf(tag)) {
        console.log(`${id}: release already has a PDF; skipping.`);
        skipped++;
        continue;
      }

      rmSync(output, { force: true });
      const page = await context.newPage();
      try {
        console.log(`${id}: ${metadata.source_url}`);
        await page.goto(metadata.source_url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
        await page.waitForTimeout(1500);

        const saved = await downloadPdf(page, output);
        if (!saved) {
          console.log(`  No browser download detected automatically.`);
          console.log(`  Click the SlideShare download button in the open browser if needed.`);
          console.log(`  Then return here and press ENTER. The script will check the browser download event again by reopening the page.`);
          await waitForEnter();
          rmSync(output, { force: true });
          await page.reload({ waitUntil: 'domcontentloaded', timeout: 90_000 });
          await page.waitForTimeout(1000);
          if (!(await downloadPdf(page, output))) {
            throw new Error('no downloadable PDF was exposed by SlideShare');
          }
        }
      } finally {
        await page.close();
      }

      assertPdf(output);
      console.log(`  Uploading ${filename} to ${tag}...`);
      execFileSync('gh', ['release', 'upload', tag, output, '--repo', repository, '--clobber'], { stdio: 'inherit' });
      execFileSync('gh', ['release', 'edit', tag, '--repo', repository, '--notes', releaseNotes(metadata)], { stdio: 'inherit' });
      uploaded++;
    } catch (error) {
      failed++;
      console.error(`${id}: FAILED: ${error instanceof Error ? error.message : String(error)}`);
    }
  }
} finally {
  await context.close();
  await browser.close();
}

console.log(`\nFinished: ${uploaded} uploaded, ${skipped} already present, ${failed} failed.`);
if (failed) process.exitCode = 2;

async function downloadPdf(page, output) {
  const candidates = [
    page.getByRole('link', { name: /download|baixar/i }),
    page.getByRole('button', { name: /download|baixar/i }),
    page.locator('a[download]'),
    page.locator('a[href*="download" i]'),
  ];

  for (const candidate of candidates) {
    const element = candidate.first();
    if (!(await element.isVisible({ timeout: 800 }).catch(() => false))) continue;

    const download = await Promise.all([
      page.waitForEvent('download', { timeout: 30_000 }),
      element.click(),
    ]).then(([event]) => event).catch(() => null);

    if (!download) continue;
    await download.saveAs(output);
    if (isPdf(output)) return true;
    rmSync(output, { force: true });
  }
  return false;
}

function ensureRelease(tag, metadata) {
  try {
    execFileSync('gh', ['release', 'view', tag, '--repo', repository], { stdio: 'ignore' });
  } catch {
    console.log(`${tag}: creating release.`);
    execFileSync('gh', [
      'release', 'create', tag,
      '--repo', repository,
      '--target', 'main',
      '--title', String(metadata.title ?? tag),
      '--notes', releaseNotes(metadata),
    ], { stdio: 'inherit' });
  }
}

function releaseHasPdf(tag) {
  const names = execFileSync('gh', [
    'release', 'view', tag,
    '--repo', repository,
    '--json', 'assets',
    '--jq', '.assets[].name',
  ], { encoding: 'utf8' });
  return names.split('\n').some((name) => name.trim().toLowerCase().endsWith('.pdf'));
}

function releaseNotes(metadata) {
  return [
    metadata.description ? String(metadata.description).trim() : null,
    '',
    'Archived presentation imported from SlideShare.',
    '',
    `- **SlideShare:** ${metadata.source_url}`,
    metadata.published_at ? `- **Published:** ${String(metadata.published_at).slice(0, 10)}` : null,
    metadata.language ? `- **Language:** ${metadata.language}` : null,
    '',
    'The archived PDF was obtained from the authenticated download exposed by SlideShare.',
    '',
    'This release is keyed by the immutable SlideShare presentation ID.',
  ].filter((line) => line !== null).join('\n');
}

function isPdf(path) {
  return existsSync(path)
    && statSync(path).size >= 1000
    && readFileSync(path).subarray(0, 5).toString() === '%PDF-';
}

function assertPdf(path) {
  if (!isPdf(path)) throw new Error('downloaded file is not a valid PDF');
}

function checkCommand(command, args) {
  try {
    execFileSync(command, args, { stdio: 'inherit' });
  } catch {
    throw new Error(`${command} is unavailable or not authenticated.`);
  }
}

async function waitForEnter() {
  process.stdin.resume();
  await new Promise((resolve) => process.stdin.once('data', resolve));
  process.stdin.pause();
}
