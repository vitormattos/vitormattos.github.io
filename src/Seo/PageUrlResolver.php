<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Seo;

final class PageUrlResolver
{
    /**
     * @return array{
     *     locale: string,
     *     isEnglish: bool,
     *     siteUrl: string,
     *     path: string,
     *     canonicalUrl: string,
     *     alternateCanonicalUrl: ?string,
     *     englishCanonicalUrl: string,
     *     isProfilePage: bool,
     *     pageType: string
     * }
     */
    public function resolve(object $page): array
    {
        $locale = (string) ($page->locale ?? $page->defaultLocale);
        $isEnglish = $locale === 'en';
        $siteUrl = rtrim((string) $page->siteUrl, '/');
        $path = $this->normalizePath('/' . ltrim((string) $page->getPath(), '/'));
        $canonicalUrl = $siteUrl . ($path === '/' ? '/' : $path);
        $alternateCanonicalUrl = $this->alternateCanonicalUrl($page, $siteUrl);
        $isProfilePage = in_array($path, ['/', '/pt-BR'], true);

        return [
            'locale' => $locale,
            'isEnglish' => $isEnglish,
            'siteUrl' => $siteUrl,
            'path' => $path,
            'canonicalUrl' => $canonicalUrl,
            'alternateCanonicalUrl' => $alternateCanonicalUrl,
            'englishCanonicalUrl' => $this->englishCanonicalUrl($isEnglish, $canonicalUrl, $alternateCanonicalUrl, $siteUrl),
            'isProfilePage' => $isProfilePage,
            'pageType' => $this->pageType($page, $isProfilePage),
        ];
    }

    private function normalizePath(string $path): string
    {
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function alternateCanonicalUrl(object $page, string $siteUrl): ?string
    {
        $alternateUrl = trim((string) ($page->alternateUrl ?? ''));
        if ($alternateUrl === '') {
            return null;
        }

        $path = $this->normalizePath('/' . ltrim($alternateUrl, '/'));

        return $siteUrl . ($path === '/' ? '/' : $path);
    }

    private function englishCanonicalUrl(
        bool $isEnglish,
        string $canonicalUrl,
        ?string $alternateCanonicalUrl,
        string $siteUrl,
    ): string {
        if ($isEnglish) {
            return $canonicalUrl;
        }

        return $alternateCanonicalUrl ?? $siteUrl . '/';
    }

    private function pageType(object $page, bool $isProfilePage): string
    {
        if ($page->pageType ?? false) {
            return (string) $page->pageType;
        }

        return $isProfilePage ? 'ProfilePage' : 'WebPage';
    }
}
