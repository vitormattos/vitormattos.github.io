#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

chrome="${CHROME_BIN:-}"
if [[ -z "$chrome" ]]; then
    for candidate in google-chrome-stable google-chrome chromium chromium-browser; do
        if command -v "$candidate" >/dev/null 2>&1; then
            chrome="$(command -v "$candidate")"
            break
        fi
    done
fi

if [[ -z "$chrome" ]]; then
    echo 'Chrome or Chromium is required to generate presentation PDFs.' >&2
    exit 1
fi

find presentations/slides.com -mindepth 2 -maxdepth 2 -name metadata.json -print0 | while IFS= read -r -d '' metadata; do
    deck_dir="$(dirname "$metadata")"
    url="$(php -r '$m=json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR); echo $m["urls"]["public"] ?? $m["url"] ?? "";' "$metadata")"

    if [[ -z "$url" ]]; then
        echo "Skipping $metadata: no public URL in metadata." >&2
        continue
    fi

    separator='?'
    [[ "$url" == *\?* ]] && separator='&'
    output="$deck_dir/deck.pdf"

    echo "Generating $output from $url"
    "$chrome" \
        --headless=new \
        --no-sandbox \
        --disable-gpu \
        --virtual-time-budget=10000 \
        --print-to-pdf-no-header \
        --print-to-pdf="$output" \
        "${url}${separator}print-pdf"

done
