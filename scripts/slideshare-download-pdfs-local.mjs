// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
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
let unavailableAssets = 0;
let presentationsWithFailures = 0;

try {
  for (const metadataFile of metadataFiles) {
    const metadata = JSON.parse(readFileSync(metadataFile, 'utf8'));
    const id = String(metadata.id);
    const tag = `slideshare-${id}`;

    ensureRelease(tag, metadata);
    const existingAssets = releaseAssetNames(tag);
    const missingTypes = ['pdf', 'pptx'].filter((type) => !hasAssetType(existingAssets, type));

    for (const type of ['pdf', 'pptx']) {
      if (hasAssetType(existingAssets, type)) {
        console.log(`${id}: ${type.toUpperCase()} already exists in release; will not download it again.`);
        skippedAssets++;
      }
    }

    if (missingTypes.length === 0) {
      console.log(`${id}: all archived formats already present; skipping presentation.\n`);
      continue;
    }

    console.log(`${id}: ${metadata.source_url}`);
    const page = await context.newPage();
    let failed = false;

    try {
      await page.goto(metadata.source_url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await page.waitForTimeout(1200);

      for (const type of missingTypes) {
        const filename = `${tag}.${type}`;
        const output = join(outputDir, filename);
        rmSync(output, { force: true });

        try {
          const result = await obtainAsset(context, page, output, type, id);

          if (result.status === 'unavailable') {
            console.log(`  ${type.toUpperCase()}: not offered by this SlideShare upload; skipping.`);
            unavailableAssets++;
            continue;
          }

          if (result.status !== 'saved') {
            throw new Error(`could not capture ${type.toUpperCase()} download`);
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
    console.log('');
  }
} finally {
  await context.close();
  await browser.close();
}

console.log(`Finished: ${uploadedAssets} assets uploaded, ${skippedAssets} already present, ${unavailableAssets} formats not offered, ${presentationsWithFailures} presentations with failures.`);
if (presentationsWithFailures) process.exitCode = 2;

async function obtainAsset(context, page, output, type, id) {
  // Only interact automatically when SlideShare exposes an explicit option for
  // the requested format. Do not click the generic Download control: on some
  // pages that element changes slide/carousel state and causes unwanted
  // scrolling instead of opening the format menu.
  if (await clickFormatOption(context, page, output, type)) {
    return { status: 'saved' };
  }

  // Keep the page completely still and let the user open the Download menu and
  // choose the requested format. While the user does that, capture downloads,
  // popup downloads and download URLs returned by SlideShare network calls.
  return await manualCapture(context, page, output, type, id);
}

async function clickFormatOption(context, page, output, type) {
  const patterns = type === 'pdf'
    ? [/download pdf/i, /baixar pdf/i, /^pdf$/i]
    : [/download pptx/i, /download powerpoint/i, /baixar pptx/i, /baixar powerpoint/i, /^pptx$/i, /^powerpoint$/i];

  for (const frame of page.frames()) {
    for (const pattern of patterns) {
      const locators = [
        frame.getByRole('menuitem', { name: pattern }).first(),
        frame.getByRole('button', { name: pattern }).first(),
        frame.getByRole('link', { name: pattern }).first(),
      ];
      for (const locator of locators) {
        if (!(await locator.isVisible({ timeout: 250 }).catch(() => false))) continue;
        const capture = createAssetCapture(context, output, type, 'format-option');
        try {
          await locator.click();
          const result = await capture.wait(20_000);
          if (result.saved) return true;
        } finally {
          capture.close();
        }
      }
    }
  }
  return false;
}

async function manualCapture(context, page, output, type, id) {
  const capture = createAssetCapture(context, output, type, id);
  try {
    console.log(`  ${type.toUpperCase()}: explicit format option is not currently visible.`);
    console.log(`  The script will NOT click or scroll the page automatically.`);
    console.log(`  In the open browser, open Download and choose ${type === 'pptx' ? 'PPTX/PowerPoint' : 'PDF'}.`);
    console.log('  If that format is not offered, just press ENTER without downloading it.');
    console.log('  Return here and press ENTER after the download starts/finishes or after confirming the format is unavailable.');
    await waitForEnter();

    const result = await capture.wait(6000);
    if (result.saved) return { status: 'saved' };
    if (result.observedTypes.size > 0 && !result.observedTypes.has(type)) return { status: 'unavailable' };

    console.log(`  No ${type.toUpperCase()} download was observed. Treating it as unavailable for this run.`);
    return { status: 'unavailable' };
  } finally {
    capture.close();
  }
}

function createAssetCapture(context, output, expectedType, id) {
  let resolveSaved;
  const savedPromise = new Promise((resolve) => { resolveSaved = resolve; });
  const observedTypes = new Set();
  const attachedPages = new Set();
  let saved = false;

  const saveDownload = async (download) => {
    try {
      const suggested = download.suggestedFilename();
      const type = typeFromFilename(suggested);
      if (type) observedTypes.add(type);
      if (type !== expectedType || saved) return;
      await download.saveAs(output);
      if (isAsset(output, expectedType)) {
        saved = true;
        console.log(`  Captured ${expectedType.toUpperCase()} download (${suggested}).`);
        resolveSaved(true);
      }
    } catch {}
  };

  const inspectResponse = async (response) => {
    try {
      const headers = response.headers();
      const disposition = headers['content-disposition'] ?? '';
      const contentType = (headers['content-type'] ?? '').toLowerCase();
      const responseType = typeFromResponse(response.url(), disposition, contentType);
      if (responseType) observedTypes.add(responseType);

      if (responseType === expectedType && !saved) {
        const body = await response.body();
        writeFileSync(output, body);
        if (isAsset(output, expectedType)) {
          saved = true;
          console.log(`  Captured ${expectedType.toUpperCase()} from network response: ${response.url()}`);
          resolveSaved(true);
          return;
        }
        rmSync(output, { force: true });
      }

      if (/json|text/i.test(contentType) && /download|export|file/i.test(response.url())) {
        const text = await response.text().catch(() => '');
        for (const candidate of extractUrls(text)) {
          const candidateType = typeFromFilename(candidate);
          if (candidateType) observedTypes.add(candidateType);
          if (candidateType === expectedType && !saved) {
            const request = await context.request.get(candidate, { failOnStatusCode: false, timeout: 20_000 }).catch(() => null);
            if (!request?.ok()) continue;
            const body = await request.body();
            writeFileSync(output, body);
            if (isAsset(output, expectedType)) {
              saved = true;
              console.log(`  Captured ${expectedType.toUpperCase()} URL from SlideShare response.`);
              resolveSaved(true);
              return;
            }
            rmSync(output, { force: true });
          }
        }
      }
    } catch {}
  };

  const attachPage = (p) => {
    if (attachedPages.has(p)) return;
    attachedPages.add(p);
    p.on('download', saveDownload);
    p.on('response', inspectResponse);
  };

  for (const p of context.pages()) attachPage(p);
  context.on('page', attachPage);

  return {
    observedTypes,
    async wait(timeoutMs) {
      if (saved) return { saved: true, observedTypes };
      await Promise.race([
        savedPromise,
        new Promise((resolve) => setTimeout(resolve, timeoutMs)),
      ]);
      return { saved, observedTypes };
    },
    close() {
      context.off('page', attachPage);
      for (const p of attachedPages) {
        p.off('download', saveDownload);
        p.off('response', inspectResponse);
      }
    },
  };
}

function typeFromResponse(url, disposition, contentType) {
  const byName = typeFromFilename(`${url} ${disposition}`);
  if (byName) return byName;
  if (contentType.includes('application/pdf')) return 'pdf';
  if (contentType.includes('presentationml.presentation') || contentType.includes('powerpoint')) return 'pptx';
  return null;
}

function typeFromFilename(value) {
  const clean = String(value).toLowerCase();
  if (/\.pdf(?:\?|$|["'\s])/.test(clean) || /filename[^;]*\.pdf/i.test(clean)) return 'pdf';
  if (/\.pptx(?:\?|$|["'\s])/.test(clean) || /filename[^;]*\.pptx/i.test(clean)) return 'pptx';
  return null;
}

function extractUrls(text) {
  const decoded = String(text).replaceAll('\\u002F', '/').replaceAll('\\/', '/');
  return [...decoded.matchAll(/https?:\/\/[^"'<>\s]+/g)].map((match) => match[0].replaceAll('&amp;', '&'));
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

function hasAssetType(names, type) {
  return [...names].some((name) => name.toLowerCase().endsWith(`.${type}`));
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
