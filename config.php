<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
    'baseUrl' => '',
    'production' => false,
    'indexable' => false,
    'siteName' => 'Vitor Mattos',
    'defaultLocale' => 'en',
    'locales' => ['en', 'pt-BR'],
    'siteDescription' => 'Free software, PHP, LibreSign, research and talks.',
    'collections' => [
        'articles' => [
            'path' => 'pt-BR/artigos/{filename}',
            'sort' => '-date',
        ],
        'articlesEn' => [
            'path' => 'articles/{filename}',
            'sort' => '-date',
        ],
        'talks' => [
            'path' => 'pt-BR/palestras/{filename}',
            'sort' => '-date',
        ],
        'talksEn' => [
            'path' => 'talks/{filename}',
            'sort' => '-date',
        ],
    ],
];
