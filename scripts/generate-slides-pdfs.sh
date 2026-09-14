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

if [[ ! -f node_modules/reveal.js/dist/reveal.js ]]; then
    echo 'Reveal.js is not installed. Run npm install before generating PDFs.' >&2
    exit 1
fi

work_dir=".build/slides-pdf"
rm -rf "$work_dir"
mkdir -p "$work_dir"

cleanup() {
    if [[ -n "${server_pid:-}" ]]; then
        kill "$server_pid" 2>/dev/null || true
    fi
    rm -rf "$work_dir"
}
trap cleanup EXIT

# Serve the repository root so the temporary print documents can use the exact
# Reveal.js version pinned by package-lock.json and the locally archived deck.
php -S 127.0.0.1:8765 -t . >"$work_dir/server.log" 2>&1 &
server_pid=$!

for _ in {1..30}; do
    if curl --fail --silent http://127.0.0.1:8765/package.json >/dev/null; then
        break
    fi
    sleep 0.2
done

if ! curl --fail --silent http://127.0.0.1:8765/package.json >/dev/null; then
    echo 'Could not start local HTTP server for PDF generation.' >&2
    exit 1
fi

find presentations/slides.com -mindepth 2 -maxdepth 2 -name deck.html -print0 | while IFS= read -r -d '' deck_html; do
    deck_dir="$(dirname "$deck_html")"
    deck_id="$(basename "$deck_dir")"
    deck_css="$deck_dir/deck.css"
    metadata="$deck_dir/metadata.json"
    output="$deck_dir/deck.pdf"
    print_file="$work_dir/${deck_id}.html"

    if [[ ! -f "$deck_css" ]]; then
        echo "Skipping $deck_dir: deck.css is missing." >&2
        continue
    fi

    expected_slides=0
    if [[ -f "$metadata" ]]; then
        expected_slides="$(php -r '$m=json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR); echo (int)($m["slide_count"] ?? 0);' "$metadata")"
    fi

    # deck.html is a Reveal slide fragment from the Slides.com API. Wrap that
    # fragment in a minimal standalone Reveal document using our pinned runtime.
    {
        cat <<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/node_modules/reveal.js/dist/reset.css">
<link rel="stylesheet" href="/node_modules/reveal.js/dist/reveal.css">
<style>
html, body, .reveal { margin: 0; width: 100%; height: 100%; }
HTML
        cat "$deck_css"
        cat <<'HTML'
</style>
</head>
<body>
<div class="reveal">
<div class="slides">
HTML
        cat "$deck_html"
        cat <<'HTML'
</div>
</div>
<script src="/node_modules/reveal.js/dist/reveal.js"></script>
<script>
Reveal.initialize({
  hash: false,
  controls: false,
  progress: false,
  slideNumber: false,
  transition: 'none',
  backgroundTransition: 'none',
  pdfMaxPagesPerSlide: 1,
  pdfSeparateFragments: false
});
</script>
</body>
</html>
HTML
    } > "$print_file"

    print_url="http://127.0.0.1:8765/${print_file}?print-pdf"
    if ! curl --fail --silent "$print_url" | grep -q 'class="reveal"'; then
        echo "Local print document is not being served correctly for $deck_id." >&2
        exit 1
    fi

    echo "Generating $output from local archived deck $deck_id (${expected_slides} slides expected)"
    "$chrome" \
        --headless=new \
        --no-sandbox \
        --disable-gpu \
        --virtual-time-budget=15000 \
        --print-to-pdf-no-header \
        --print-to-pdf="$output" \
        "$print_url"

    if [[ ! -s "$output" ]]; then
        echo "PDF generation produced no output for $deck_id." >&2
        exit 1
    fi

    pdf_size="$(stat -c '%s' "$output")"
    if (( pdf_size < 10000 )); then
        echo "PDF generation produced a suspiciously small file for $deck_id: ${pdf_size} bytes (${expected_slides} slides expected)." >&2
        echo 'Treating this as a failed render instead of publishing a broken download.' >&2
        exit 1
    fi

done
