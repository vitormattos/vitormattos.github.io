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

  const before = await page.evaluate(() => {
    const rect = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: r.height };
    };
    return {
      config: Reveal.getConfig(),
      reveal: rect('.reveal'),
      slides: rect('.reveal .slides'),
      backgrounds: rect('.reveal .backgrounds'),
      bodyChildren: Array.from(document.body.children).map((el) => ({
        tag: el.tagName.toLowerCase(),
        id: el.id,
        className: typeof el.className === 'string' ? el.className : '',
        rect: (() => {
          const r = el.getBoundingClientRect();
          return { x: r.x, y: r.y, width: r.width, height: r.height };
        })(),
      })),
    };
  });
  console.log('Layout before cleanup:', JSON.stringify(before));

  const setup = await page.evaluate(() => {
    Reveal.configure({
      width: 960,
      height: 540,
      margin: 0,
      minScale: 1,
      maxScale: 1,
      transition: 'none',
      backgroundTransition: 'none',
      transitionSpeed: 'fastest',
      controls: false,
      progress: false,
      slideNumber: false,
    });

    const selectors = [
      '.controls', '.progress', '.slide-number', '.speaker-notes', '.playback',
      '.pause-overlay', '.sl-block-controls', '.sl-menu', '.sl-watermark',
      '.sl-footer', '.sl-deck-footer', '.deck-footer', '.sl-embed-footer',
      '.embed-footer', '[class*="footer"]', 'footer',
    ];
    document.querySelectorAll(selectors.join(',')).forEach((el) => el.remove());

    const style = document.createElement('style');
    style.dataset.pocPdf = 'true';
    style.textContent = `
      html, body, .reveal-viewport, .reveal {
        margin: 0 !important;
        padding: 0 !important;
        width: 960px !important;
        height: 540px !important;
        min-height: 540px !important;
        max-height: 540px !important;
        overflow: hidden !important;
      }
      .reveal { position: absolute !important; inset: 0 !important; }
      .reveal .backgrounds { width: 960px !important; height: 540px !important; }
      *, *::before, *::after {
        transition-duration: 0s !important;
        transition-delay: 0s !important;
        animation-duration: 0s !important;
        animation-delay: 0s !important;
      }
    `;
    document.head.appendChild(style);

    Reveal.layout();

    const rect = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: r.height };
    };

    return {
      reveal: rect('.reveal'),
      slides: rect('.reveal .slides'),
      backgrounds: rect('.reveal .backgrounds'),
      indices: Reveal.getSlides().map((slide) => Reveal.getIndices(slide)),
    };
  });

  console.log('Layout after cleanup:', JSON.stringify({
    reveal: setup.reveal,
    slides: setup.slides,
    backgrounds: setup.backgrounds,
  }));
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

    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));

    const buffer = await page.screenshot({
      type: 'png',
      fullPage: false,
      captureBeyondViewport: false,
      clip: { x: 0, y: 0, width, height },
    });

    const image = await pdf.embedPng(buffer);
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
