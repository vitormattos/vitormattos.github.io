<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Stable technology relationships used to enrich the editorial taxonomy.
 *
 * These are not arbitrary SEO aliases. A related topic is added only when it
 * represents a technology that is structurally part of the tagged project or
 * tool. This lets a talk about LibreSign also be discoverable under Nextcloud
 * and PHP without duplicating those relationships in every presentation.
 *
 * Keys must be lowercase because TalkTopics normalizes topic lookup keys.
 */
return [
    'libresign' => ['Nextcloud', 'PHP'],
    'nextcloud' => ['PHP'],
    'e-cidade' => ['PHP'],
    'i-educar' => ['PHP'],
    'composer' => ['PHP'],
    'xdebug' => ['PHP'],
    'behat' => ['PHP'],
    'glpi' => ['PHP'],
    'mediawiki' => ['PHP'],
];
