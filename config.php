<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
    'baseUrl' => '',
    'production' => false,
    'siteName' => 'Vitor Mattos',
    'siteDescription' => 'Software livre, PHP, LibreSign, pesquisa e palestras.',
    'collections' => [
        'articles' => [
            'path' => 'artigos/{filename}',
            'sort' => '-date',
        ],
        'talks' => [
            'path' => 'palestras/{filename}',
            'sort' => '-date',
        ],
    ],
];
