<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
    'baseUrl' => '',
    'production' => false,
    'siteName' => 'Vitor Mattos',
    'defaultLocale' => 'pt-BR',
    'locales' => ['pt-BR', 'en'],
    'siteDescription' => 'Software livre, PHP, LibreSign, pesquisa e palestras.',
    'collections' => [
        'articles' => [
            'path' => 'artigos/{filename}',
            'sort' => '-date',
        ],
        'articlesEn' => [
            'path' => 'en/articles/{filename}',
            'sort' => '-date',
        ],
        'talks' => [
            'path' => 'palestras/{filename}',
            'sort' => '-date',
        ],
        'talksEn' => [
            'path' => 'en/talks/{filename}',
            'sort' => '-date',
        ],
    ],
];
