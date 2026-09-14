<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Listeners;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TightenCo\Jigsaw\Jigsaw;

final class CopyPresentations
{
    public function handle(Jigsaw $jigsaw): void
    {
        $source = dirname(__DIR__) . '/presentations';

        if (! is_dir($source)) {
            return;
        }

        $destination = $jigsaw->getDestinationPath() . '/presentations';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($source) + 1);
            $target = $destination . '/' . $relativePath;
            $targetDirectory = dirname($target);

            if (! is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0777, true);
            }

            copy($file->getPathname(), $target);
        }
    }
}
