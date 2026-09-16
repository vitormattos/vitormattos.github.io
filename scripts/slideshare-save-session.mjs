// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const output = resolve(process.argv[2] ?? '.tmp/slideshare-storage-state.json');
mkdirSync(dirname(output), { recursive: true });

const browser = await chromium.launch({ headless: false });
const context = await browser.newContext({ locale: 'pt-BR' });
const page = await context.newPage();

console.log('Opening SlideShare login in a real browser.');
console.log('Complete the login and reCAPTCHA manually.');
console.log('After SlideShare shows you as signed in, return to this terminal and press Enter.');

await page.goto('https://www.slideshare.net/login', {
  waitUntil: 'domcontentloaded',
  timeout: 90_000,
});

await new Promise((resolveInput) => {
  process.stdin.resume();
  process.stdin.setEncoding('utf8');
  process.stdin.once('data', resolveInput);
});

await page.goto('https://www.slideshare.net/', {
  waitUntil: 'domcontentloaded',
  timeout: 60_000,
});

const signInLink = page.getByRole('link', { name: /^sign in$/i }).first();
if (await signInLink.isVisible({ timeout: 3_000 }).catch(() => false)) {
  await browser.close();
  throw new Error('SlideShare still appears signed out. Complete the login before saving the session.');
}

const cookies = await context.cookies();
const sessionCookies = cookies.filter((cookie) => /slideshare|scribd/i.test(cookie.domain));
if (sessionCookies.length === 0) {
  await browser.close();
  throw new Error('No SlideShare/Scribd session cookies were found.');
}

await context.storageState({ path: output });
await browser.close();

console.log(`Authenticated session saved to ${output}`);
console.log('Do not commit this file. Store its contents as the GitHub Actions secret SLIDESHARE_STORAGE_STATE.');
