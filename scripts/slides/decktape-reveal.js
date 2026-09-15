// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

// Reveal.js DeckTape plugin adapted for public Slides.com embeds.
export const options = {
  fragments: { default: false, flag: true, help: 'Enable or disable fragments' },
  progress: { default: false, flag: true, help: 'Enable or disable progress bar' },
};

export const create = (page, opts) => new SlidesReveal(page, opts);

class SlidesReveal {
  constructor(page, opts) {
    this.page = page;
    this.fragments = opts.fragments;
    this.progress = opts.progress;
  }

  getName() {
    return 'Reveal JS (Slides.com archive)';
  }

  isActive() {
    return this.page.evaluate(() => {
      if (typeof Reveal === 'undefined' || typeof Jupyter !== 'undefined') {
        return false;
      }

      return typeof Reveal.availableFragments === 'function';
    });
  }

  async configure() {
    await this.page.evaluate(config => {
      Reveal.configure({
        controls: false,
        progress: config.progress,
        fragments: config.fragments,
        transition: 'none',
        backgroundTransition: 'none',
        autoAnimate: false,
        margin: 0,
      });

      document.querySelectorAll('.embed-footer, footer').forEach(el => el.remove());

      const style = document.createElement('style');
      style.dataset.slidesArchive = 'true';
      style.textContent = `
        html, body, .reveal-viewport {
          margin: 0 !important;
          padding: 0 !important;
          width: 100vw !important;
          height: 100vh !important;
          min-height: 100vh !important;
          max-height: 100vh !important;
          overflow: hidden !important;
        }
        .reveal {
          position: absolute !important;
          inset: 0 !important;
          width: 100vw !important;
          height: 100vh !important;
          margin: 0 !important;
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
    }, { fragments: this.fragments, progress: this.progress });
  }

  slideCount() {
    return this.page.evaluate(() => typeof Reveal.getTotalSlides === 'function'
      ? Reveal.getTotalSlides()
      : undefined);
  }

  hasNextSlide() {
    return this.page.evaluate(() => !Reveal.isLastSlide() || Reveal.availableFragments().next);
  }

  nextSlide() {
    return this.page.evaluate(() => Reveal.next());
  }

  currentSlideIndex() {
    return this.page.evaluate(() => {
      const indices = Reveal.getIndices();
      const id = Reveal.getCurrentSlide().getAttribute('id');

      return typeof id === 'string' && id.length
        ? '/' + id
        : '/' + indices.h + (indices.v > 0 ? '/' + indices.v : '');
    });
  }
}
