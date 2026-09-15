// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import puppeteer from 'puppeteer';
import { PDFDocument } from 'pdf-lib';
import { writeFile } from 'node:fs/promises';

const [input, output] = process.argv.slice(2);
if (!input || !output) {
  console.error('Usage: node scripts/poc-slides-pdf.mjs <url> <output.pdf>');
  process.exit(2);
}

const width = 960;
const height = 540;
const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  await page.goto(input, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.waitForFunction(() => typeof Reveal !== 'undefined' && Reveal.isReady(), { timeout: 30_000 });

  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(Array.from(document.images).map((image) => {
      if (image.complete) return Promise.resolve();
      return new Promise((resolve) => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
      });
    }));
  });

  const bottomElements = await page.evaluate(() => Array.from(document.querySelectorAll('body *'))
    .map((el) => {
      const rect = el.getBoundingClientRect();
      const style = getComputedStyle(el);
      return {
        tag: el.tagName.toLowerCase(),
        id: el.id,
        className: typeof el.className === 'string' ? el.className : '',
        x: Math.round(rect.x),
        y: Math.round(rect.y),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        position: style.position,
        zIndex: style.zIndex,
      };
    })
    .filter((item) => item.height > 0 && item.width > 0 && item.y + item.height > window.innerHeight - 35)
    .filter((item) => ['fixed', 'absolute'].includes(item.position) || item.height <= 50)
    .slice(0, 40));
  console.log('Bottom DOM candidates:', JSON.stringify(bottomElements));

  const setup = await page.evaluate(() => {
    Reveal.configure({
      transition: 'none',
      backgroundTransition: 'none',
      transitionSpeed: 'fastest',
      controls: false,
      progress: false,
      slideNumber: false,
    });

    const selectors = [
      '.controls',
      '.progress',
      '.slide-number',
      '.speaker-notes',
      '.playback',
      '.pause-overlay',
      '.sl-block-controls',
      '.sl-menu',
      '.sl-watermark',
      '.sl-footer',
      '.sl-deck-footer',
      '.deck-footer',
      'footer',
    ];
    document.querySelectorAll(selectors.join(',')).forEach((el) => {
      el.style.setProperty('display', 'none', 'important');
    });

    const style = document.createElement('style');
    style.dataset.pocPdf = 'true';
    style.textContent = `
      html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; height: 100% !important; overflow: hidden !important; }
      .reveal { width: 100vw !important; height: 100vh !important; margin: 0 !important; }
      .controls, .progress, .slide-number, .speaker-notes, .playback, .pause-overlay,
      .sl-block-controls, .sl-menu, .sl-watermark, .sl-footer, .sl-deck-footer,
      .deck-footer, footer { display: none !important; }
      *, *::before, *::after {
        transition-duration: 0s !important;
        transition-delay: 0s !important;
        animation-duration: 0s !important;
        animation-delay: 0s !important;
      }
    `;
    document.head.appendChild(style);

    Reveal.layout();
    const reveal = document.querySelector('.reveal');
    const rect = reveal?.getBoundingClientRect();
    const indices = Reveal.getSlides().map((slide) => Reveal.getIndices(slide));
    return {
      revealRect: rect ? { x: rect.x, y: rect.y, width: rect.width, height: rect.height } : null,
      indices,
    };
  });

  console.log('Reveal rect after cleanup:', JSON.stringify(setup.revealRect));
  console.log(`Slides discovered: ${setup.indices.length}`);

  const pdf = await PDFDocument.create();
  let pageNumber = 0;

  for (const index of setup.indices) {
    await page.evaluate(({ h, v }) => {
      Reveal.slide(h, v, Number.MAX_SAFE_INTEGER);
      const current = Reveal.getCurrentSlide();
      current?.querySelectorAll('.fragment').forEach((fragment) => {
        fragment.classList.add('visible');
        fragment.classList.remove('current-fragment');
      });
      Reveal.sync();
    }, { h: index.h, v: index.v ?? 0 });

    // Two animation frames are enough after transitions are disabled while still
    // allowing browser layout/paint to settle without a fixed per-slide delay.
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));

    const buffer = await page.screenshot({
      type: 'jpeg',
      quality: 92,
      fullPage: false,
      captureBeyondViewport: false,
    });

    const image = await pdf.embedJpg(buffer);
    const pdfPage = pdf.addPage([width, height]);
    pdfPage.drawImage(image, { x: 0, y: 0, width, height });
    pageNumber += 1;
    console.log(`Captured ${pageNumber}/${setup.indices.length}: h=${index.h} v=${index.v ?? 0}`);
  }

  const bytes = await pdf.save();
  await writeFile(output, bytes);
  console.log(`Generated ${pageNumber} pages, ${bytes.length} bytes -> ${output}`);
} finally {
  await browser.close();
}
