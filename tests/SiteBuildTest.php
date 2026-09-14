<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SiteBuildTest extends TestCase
{
    public function testProductionBuildContainsExpectedPages(): void
    {
        self::assertFileExists(__DIR__ . '/../build_production/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/artigos/primeiro-artigo/index.html');
        self::assertFileExists(__DIR__ . '/../build_production/palestras/software-livre/index.html');
    }
}
