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
    'talkMetadata' => require __DIR__ . '/data/talks.php',
    'author' => [
        'name' => 'Vitor Mattos',
        'id' => 'https://vitormattos.github.io/#person',
        'avatar' => 'https://gravatar.com/avatar/67d8686e0265c7d5aca8ebd047cf1fff8f41922d1b10126c7398851256feba79?s=112&r=g',
        'summary' => [
            'en' => 'CTO at LibreCode. Free software, privacy, PHP, digital signatures and sustainable software communities.',
            'pt-BR' => 'CTO da LibreCode. Software livre, privacidade, PHP, assinaturas digitais e comunidades sustentáveis de software.',
        ],
        'profiles' => [
            'github' => [
                'label' => 'GitHub',
                'url' => 'https://github.com/vitormattos',
                'sameAs' => true,
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'url' => 'https://www.linkedin.com/in/vitormattos/',
                'sameAs' => true,
            ],
        ],
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
            'path' => 'pt-BR/palestras/{slug}',
            'sort' => '-date',
            'schemaType' => 'CreativeWork',
        ],
        'talksEn' => [
            'path' => 'talks/{slug}',
            'sort' => '-date',
            'schemaType' => 'CreativeWork',
        ],
    ],
];
