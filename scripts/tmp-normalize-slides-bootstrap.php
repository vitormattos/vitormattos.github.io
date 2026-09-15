<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$htmlHeader = "<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->\n<!-- SPDX-License-Identifier: CC-BY-SA-4.0 -->\n";
$cssHeader = "/* SPDX-FileCopyrightText: 2026 Vitor Mattos */\n/* SPDX-License-Identifier: CC-BY-SA-4.0 */\n";

foreach (glob('presentations/slides.com/*/deck.html') ?: [] as $path) {
    $content = (string) file_get_contents($path);
    if (str_starts_with($content, $htmlHeader)) {
        file_put_contents($path, substr($content, strlen($htmlHeader)));
    }
}

foreach (glob('presentations/slides.com/*/metadata.json') ?: [] as $path) {
    $metadata = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if (!array_key_exists('_spdx', $metadata)) {
        continue;
    }
    unset($metadata['_spdx']);
    file_put_contents(
        $path,
        json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
    );
}

foreach (glob('presentations/slides.com/*/deck.css') ?: [] as $path) {
    $content = (string) file_get_contents($path);
    if (str_starts_with($content, $cssHeader)) {
        $content = substr($content, strlen($cssHeader));
    }
    if (trim($content) === '') {
        unlink($path);
        continue;
    }
    file_put_contents($path, $content);
}

foreach (['source/_talks', 'source/_talksEn'] as $collection) {
    foreach (glob($collection . '/slides-com-*.md') ?: [] as $path) {
        $content = (string) file_get_contents($path);
        $content = preg_replace_callback(
            '#^  localCss: /(?<path>presentations/slides\.com/[^\s]+/deck\.css)\R#m',
            static fn(array $match): string => is_file($match['path']) ? $match[0] : '',
            $content,
        );
        file_put_contents($path, $content);
    }
}
