// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const repository = process.env.GITHUB_REPOSITORY;
if (!repository) throw new Error('GITHUB_REPOSITORY is required.');

const metadataFiles = execFileSync('find', ['presentations/slideshare', '-mindepth', '2', '-maxdepth', '2', '-name', 'metadata.json'], { encoding: 'utf8' })
  .trim().split('\n').filter(Boolean).sort();

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ acceptDownloads: true, locale: 'pt-BR' });

try {
  for (const metadataFile of metadataFiles) {
    const metadata = JSON.parse(readFileSync(metadataFile, 'utf8'));
    const id = String(metadata.id);
    const tag = `slideshare-${id}`;

    const release = JSON.parse(execFileSync('gh', ['release', 'view', tag, '--repo', repository, '--json', 'assets'], { encoding: 'utf8' }));
    if ((release.assets ?? []).some((asset) => /^slideshare-.*\.pdf$/i.test(asset.name))) {
      console.log(`${id}: PDF already archived; skipping.`);
      continue;
    }

    const page = await context.newPage();
    const work = mkdtempSync(join(tmpdir(), `slideshare-${id}-`));
    try {
      console.log(`${id}: opening ${metadata.source_url}`);
      await page.goto(metadata.source_url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {});

      const candidates = [
        page.getByRole('link', { name: /baixar agora|download now|download/i }),
        page.getByRole('button', { name: /baixar agora|download now|download/i }),
        page.locator('a[download]'),
      ];

      let download = null;
      for (const candidate of candidates) {
        const first = candidate.first();
        if (await first.count() === 0 || !(await first.isVisible().catch(() => false))) continue;
        download = await Promise.all([
          page.waitForEvent('download', { timeout: 45_000 }),
          first.click(),
        ]).then(([event]) => event).catch(() => null);
        if (download) break;
      }

      if (!download) {
        console.log(`::warning::${id}: no downloadable PDF was exposed to the browser.`);
        continue;
      }

      const filename = `slideshare-${id}.pdf`;
      const output = join(work, filename);
      await download.saveAs(output);
      const header = readFileSync(output).subarray(0, 5).toString();
      if (header !== '%PDF-' || statSync(output).size < 1000) {
        throw new Error(`${id}: downloaded file is not a valid PDF.`);
      }

      execFileSync('gh', ['release', 'upload', tag, output, '--repo', repository, '--clobber'], { stdio: 'inherit' });
      console.log(`${id}: uploaded ${filename}.`);
    } finally {
      await page.close();
      rmSync(work, { recursive: true, force: true });
    }
  }
} finally {
  await context.close();
  await browser.close();
}
