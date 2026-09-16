{{-- SPDX-FileCopyrightText: 2026 Vitor Mattos --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<button
    class="theme-toggle"
    type="button"
    data-theme-toggle
    data-label-light="{{ $isEnglish ? 'Use light theme' : 'Usar tema claro' }}"
    data-label-dark="{{ $isEnglish ? 'Use dark theme' : 'Usar tema escuro' }}"
    aria-pressed="false"
>
    <span class="theme-toggle__icon" aria-hidden="true"></span>
    <span class="visually-hidden">{{ $isEnglish ? 'Theme' : 'Tema' }}</span>
</button>

<script>
(() => {
    const button = document.currentScript.previousElementSibling;
    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    const currentTheme = () => root.dataset.theme || (media.matches ? 'dark' : 'light');

    const syncButton = () => {
        const theme = currentTheme();
        const isDark = theme === 'dark';
        button.setAttribute('aria-pressed', String(isDark));
        button.setAttribute('aria-label', isDark ? button.dataset.labelLight : button.dataset.labelDark);
    };

    button.addEventListener('click', () => {
        const nextTheme = currentTheme() === 'dark' ? 'light' : 'dark';
        root.dataset.theme = nextTheme;

        try {
            localStorage.setItem('theme', nextTheme);
        } catch (_) {
            // Theme selection still works for this page when storage is unavailable.
        }

        syncButton();
    });

    media.addEventListener?.('change', () => {
        if (! root.dataset.theme) {
            syncButton();
        }
    });

    syncButton();
})();
</script>
