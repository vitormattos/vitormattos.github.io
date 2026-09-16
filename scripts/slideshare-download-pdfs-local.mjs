// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { join } from 'node:path';

const repository = 'vitormattos/vitormattos.github.io';
const outputDir = '.cache/slideshare-asset-backfill';
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

let uploadedAssets = 0;
let skippedAssets = 0;
let presentationsWithFailures = 0;

try {
  for (const metadataFile of metadataFiles) {
    const metadata = JSON.parse(readFileSync(metadataFile, 'utf8'));
    const id = String(metadata.id);
    const tag = `slideshare-${id}`;

    ensureRelease(tag, metadata);
    const existingAssets = releaseAssetNames(tag);
    const missingTypes = ['pdf', 'pptx'].filter((type) => !existingAssets.has(`slideshare-${id}.${type}`));

    if (missingTypes.length === 0) {
      console.log(`${id}: release already has PDF and PPTX; skipping.`);
      skippedAssets += 2;
      continue;
    }

    console.log(`${id}: ${metadata.source_url}`);
    const page = await context.newPage();
    let failed = false;

    try {
      await page.goto(metadata.source_url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await page.waitForTimeout(1500);

      for (const type of missingTypes) {
        const filename = `${tag}.${type}`;
        const output = join(outputDir, filename);
        rmSync(output, { force: true });

        try {
          const saved = await downloadAsset(page, output, type);
          if (!saved) {
            console.log(`  ${type.toUpperCase()}: automatic download was not detected.`);
            console.log(`  In the open browser, click Download and choose ${type.toUpperCase()}.`);
            console.log('  When the download has started or finished, return here and press ENTER.');
            const manual = await waitForManualDownload(page, output, type);
            if (!manual) throw new Error(`no downloadable ${type.toUpperCase()} was exposed by SlideShare`);
          }

          assertAsset(output, type);
          console.log(`  Uploading ${filename} to ${tag}...`);
          execFileSync('gh', ['release', 'upload', tag, output, '--repo', repository, '--clobber'], { stdio: 'inherit' });
          uploadedAssets++;
        } catch (error) {
          failed = true;
          console.error(`  ${type.toUpperCase()}: FAILED: ${error instanceof Error ? error.message : String(error)}`);
        }
      }

      execFileSync('gh', ['release', 'edit', tag, '--repo', repository, '--notes', releaseNotes(metadata)], { stdio: 'inherit' });
    } finally {
      await page.close();
    }

    if (failed) presentationsWithFailures++;
  }
} finally {
  await context.close();
  await browser.close();
}

console.log(`\nFinished: ${uploadedAssets} assets uploaded, ${skippedAssets} assets already present, ${presentationsWithFailures} presentations with failures.`);
if (presentationsWithFailures) process.exitCode = 2;

async function downloadAsset(page, output, type) {
  const direct = type === 'pdf'
    ? [/download pdf/i, /baixar pdf/i, /^pdf$/i]
    : [/download pptx/i, /download powerpoint/i, /baixar pptx/i, /baixar powerpoint/i, /^pptx$/i, /^powerpoint$/i];

  for (const pattern of direct) {
    const candidate = page.getByRole('link', { name: pattern }).first();
    if (await tryDownloadFromElement(page, candidate, output, type)) return true;
    const button = page.getByRole('button', { name: pattern }).first();
    if (await tryDownloadFromElement(page, button, output, type)) return true;
  }

  const genericCandidates = [
    page.getByRole('button', { name: /download|baixar/i }).first(),
    page.getByRole('link', { name: /download|baixar/i }).first(),
    page.locator('a[href*="download" i]').first(),
  ];

  for (const trigger of genericCandidates) {
    if (!(await trigger.isVisible({ timeout: 700 }).catch(() => false))) continue;

    const directDownload = await Promise.all([
      page.waitForEvent('download', { timeout: 2500 }),
      trigger.click(),
    ]).then(([download]) => download).catch(() => null);

    if (directDownload) {
      await directDownload.saveAs(output);
      if (isAsset(output, type)) return true;
      rmSync(output, { force: true });
      continue;
    }

    await page.waitForTimeout(400);
    if (await clickFormatOption(page, output, type)) return true;
  }

  return false;
}

async function clickFormatOption(page, output, type) {
  const patterns = type === 'pdf'
    ? [/download pdf/i, /baixar pdf/i, /^pdf$/i]
    : [/download pptx/i, /download powerpoint/i, /baixar pptx/i, /baixar powerpoint/i, /^pptx$/i, /^powerpoint$/i];

  for (const frame of page.frames()) {
    for (const pattern of patterns) {
      for (const locator of [
        frame.getByRole('menuitem', { name: pattern }).first(),
        frame.getByRole('button', { name: pattern }).first(),
        frame.getByRole('link', { name: pattern }).first(),
        frame.getByText(pattern).first(),
      ]) {
        if (await tryDownloadFromElement(page, locator, output, type)) return true;
      }
    }
  }
  return false;
}

async function waitForManualDownload(page, output, type) {
  console.log('  Waiting for your manual download...');
  const downloadPromise = page.waitForEvent('download', { timeout: 120_000 }).catch(() => null);
  await waitForEnter();
  const download = await Promise.race([
    downloadPromise,
    new Promise((resolve) => setTimeout(() => resolve(null), 2_000)),
  ]);

  if (!download) return false;
  await download.saveAs(output);
  if (isAsset(output, type)) return true;
  rmSync(output, { force: true });
  return false;
}

async function tryDownloadFromElement(page, element, output, type) {
  if (!(await element.isVisible({ timeout: 500 }).catch(() => false))) return false;
  const download = await Promise.all([
    page.waitForEvent('download', { timeout: 20_000 }),
    element.click(),
  ]).then(([event]) => event).catch(() => null);
  if (!download) return false;
  await download.saveAs(output);
  if (isAsset(output, type)) return true;
  rmSync(output, { force: true });
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

function releaseAssetNames(tag) {
  const names = execFileSync('gh', [
    'release', 'view', tag,
    '--repo', repository,
    '--json', 'assets',
    '--jq', '.assets[].name',
  ], { encoding: 'utf8' });
  return new Set(names.split('\n').map((name) => name.trim()).filter(Boolean));
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
    'Archived download assets were obtained from the authenticated download options exposed by SlideShare.',
    '',
    'This release is keyed by the immutable SlideShare presentation ID.',
  ].filter((line) => line !== null).join('\n');
}

function isAsset(path, type) {
  if (!existsSync(path) || statSync(path).size < 1000) return false;
  const header = readFileSync(path).subarray(0, 8);
  if (type === 'pdf') return header.subarray(0, 5).toString() === '%PDF-';
  if (type === 'pptx') return header.subarray(0, 2).toString() === 'PK';
  return false;
}

function assertAsset(path, type) {
  if (!isAsset(path, type)) throw new Error(`downloaded file is not a valid ${type.toUpperCase()}`);
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
