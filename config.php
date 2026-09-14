<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
    'baseUrl' => '',
    'siteUrl' => 'https://vitormattos.github.io',
    'production' => false,
    'indexable' => false,
    'siteName' => 'Vitor Mattos',
    'defaultLocale' => 'en',
    'locales' => ['en', 'pt-BR'],
    'siteDescription' => 'Vitor Mattos writes about free software, PHP, Linux, LibreSign, digital signatures, research, engineering and sustainable software communities.',
    'author' => [
        'name' => 'Vitor Mattos',
        'id' => 'https://vitormattos.github.io/#person',
        'github' => 'https://github.com/vitormattos',
        'linkedin' => 'https://www.linkedin.com/in/vitormattos/',
        'organization' => [
            'name' => 'LibreCode',
            'url' => 'https://librecode.coop/',
        ],
        'knowsAbout' => [
            'Free software',
            'PHP',
            'Linux',
            'LibreSign',
            'Electronic signatures',
            'Digital signatures',
            'Software engineering',
            'Open source communities',
        ],
    ],
    'collections' => [
        'articles' => [
            'path' => 'pt-BR/artigos/{filename}',
            'sort' => '-year',
            'schemaType' => 'Article',
        ],
        'articlesEn' => [
            'path' => 'articles/{filename}',
            'sort' => '-year',
            'schemaType' => 'Article',
        ],
        'talks' => [
            'path' => 'pt-BR/palestras/{filename}',
            'sort' => '-date',
            'schemaType' => 'CreativeWork',
        ],
        'talksEn' => [
            'path' => 'talks/{filename}',
            'sort' => '-date',
            'schemaType' => 'CreativeWork',
        ],
    ],
];
