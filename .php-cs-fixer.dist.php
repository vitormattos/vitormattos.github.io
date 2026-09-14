<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['build_local', 'build_production', 'vendor']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
