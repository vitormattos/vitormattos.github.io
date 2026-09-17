<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use App\Presentations\LatexPresentation;

require dirname(__DIR__) . '/vendor/autoload.php';

$metadataFiles = glob('presentations/latex/*/metadata.json') ?: [];
if ($metadataFiles === []) {
    fwrite(STDOUT, "No LaTeX presentations found.\n");
    exit(0);
}

foreach ($metadataFiles as $metadataPath) {
    $presentation = LatexPresentation::fromMetadataFile($metadataPath);
    $sourcePath = $presentation->sourcePath();
    if (!is_file($sourcePath)) {
        throw new RuntimeException("LaTeX source not found: {$sourcePath}");
    }

    $temporary = sys_get_temp_dir() . '/latex-presentation-' . bin2hex(random_bytes(6));
    if (!mkdir($temporary, 0777, true) && !is_dir($temporary)) {
        throw new RuntimeException("Could not create temporary build directory: {$temporary}");
    }

    try {
        copyDirectory($presentation->directory, $temporary);
        $temporarySource = $temporary . '/' . basename($sourcePath);

        run([
            'latexmk',
            '-pdf',
            '-interaction=nonstopmode',
            '-halt-on-error',
            '-output-directory=' . $temporary,
            $temporarySource,
        ]);

        $pdf = $temporary . '/' . pathinfo($temporarySource, PATHINFO_FILENAME) . '.pdf';
        if (!is_file($pdf) || filesize($pdf) < 5) {
            throw new RuntimeException('LaTeX build did not produce a valid PDF.');
        }

        $outputDirectory = $presentation->generatedDirectory();
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
            throw new RuntimeException("Could not create generated presentation directory: {$outputDirectory}");
        }

        if (!copy($pdf, $presentation->pdfPath())) {
            throw new RuntimeException('Could not copy generated PDF.');
        }

        run([
            'pdftoppm',
            '-f', '1',
            '-singlefile',
            '-png',
            '-r', '150',
            $pdf,
            $outputDirectory . '/thumbnail',
        ]);

        if (!copy($metadataPath, $presentation->generatedMetadataPath())) {
            throw new RuntimeException('Could not copy presentation metadata.');
        }

        fwrite(STDOUT, "Built LaTeX presentation: {$presentation->slug()}\n");
    } finally {
        removeDirectory($temporary);
    }
}

function run(array $arguments): void
{
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed ({$exitCode}): {$command}");
    }
}

function copyDirectory(string $source, string $destination): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        $target = $destination . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                throw new RuntimeException("Could not copy directory: {$target}");
            }
            continue;
        }
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException("Could not copy file: {$item->getPathname()}");
        }
    }
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
