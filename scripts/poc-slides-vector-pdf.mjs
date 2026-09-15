// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

import puppeteer from 'puppeteer';
import { PDFDocument } from 'pdf-lib';
import { writeFile } from 'node:fs/promises';

const [input, output] = process.argv.slice(2);
if (!input || !output) {
  console.error('Usage: node scripts/poc-slides-vector-pdf.mjs <url> <output.pdf>');
  process.exit(2);
}

const logicalWidth = 1280;
const logicalHeight = 720;
const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });

try {
  const page = await browser.newPage();
  await page.setViewport({ width: logicalWidth, height: logicalHeight, deviceScaleFactor: 1 });
  await page.emulateMediaType('screen');
  await page.goto(input, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.waitForFunction(() => typeof Reveal !== 'undefined' && Reveal.isReady(), { timeout: 30_000 });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(Array.from(document.images).map((image) => image.complete ? Promise.resolve() : new Promise((resolve) => {
      image.addEventListener('load', resolve, { once: true });
      image.addEventListener('error', resolve, { once: true });
    })));
  });

  const setup = await page.evaluate(() => {
    Reveal.configure({
      margin: 0,
      controls: false,
      progress: false,
      slideNumber: false,
      transition: 'none',
      backgroundTransition: 'none',
      autoAnimate: false,
    });
    document.querySelectorAll('.embed-footer, footer').forEach((el) => el.remove());

    const style = document.createElement('style');
    style.dataset.vectorPdfPoc = 'true';
    style.textContent = `
      @page { size: 1280px 720px; margin: 0; }
      html, body, .reveal-viewport, .reveal {
        margin: 0 !important;
        padding: 0 !important;
        width: 1280px !important;
        height: 720px !important;
        min-height: 720px !important;
        max-height: 720px !important;
        overflow: hidden !important;
      }
      .reveal {
        position: absolute !important;
        inset: 0 !important;
        opacity: 1 !important;
      }
      .controls, .progress, .slide-number, .speaker-notes, .playback, .pause-overlay {
        display: none !important;
      }
      *, *::before, *::after {
        transition-duration: 0s !important;
        transition-delay: 0s !important;
        animation-duration: 0s !important;
        animation-delay: 0s !important;
      }
    `;
    document.head.appendChild(style);
    Reveal.layout();
    return Reveal.getSlides().map((slide) => Reveal.getIndices(slide));
  });

  const result = await PDFDocument.create();

  for (let pageNumber = 0; pageNumber < setup.length; pageNumber++) {
    const index = setup[pageNumber];
    await page.evaluate(({ h, v }) => {
      Reveal.slide(h, v);
      Reveal.sync();
      const current = Reveal.getCurrentSlide();
      current?.querySelectorAll('.fragment').forEach((fragment) => {
        fragment.classList.add('visible');
        fragment.classList.remove('current-fragment');
      });
    }, { h: index.h, v: index.v ?? 0 });

    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));

    const bytes = await page.pdf({
      width: '1280px',
      height: '720px',
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      printBackground: true,
      preferCSSPageSize: true,
      tagged: true,
      outline: false,
    });

    const single = await PDFDocument.load(bytes);
    const [copied] = await result.copyPages(single, [0]);
    result.addPage(copied);
    console.log(`Printed ${pageNumber + 1}/${setup.length}: h=${index.h} v=${index.v ?? 0}`);
  }

  const bytes = await result.save();
  await writeFile(output, bytes);
  console.log(`Generated ${setup.length} vector pages, ${bytes.length} bytes -> ${output}`);
} finally {
  await browser.close();
}
