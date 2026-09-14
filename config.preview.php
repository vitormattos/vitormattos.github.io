<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
    'baseUrl' => getenv('PREVIEW_BASE_URL') ?: '',
    'production' => false,
];
