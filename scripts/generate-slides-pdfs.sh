#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

DECKTAPE_VERSION='3.16.1'
DECKTAPE=(npm exec --yes --package="decktape@${DECKTAPE_VERSION}" -- decktape)

success_count=0
failure_count=0

while IFS= read -r -d '' metadata; do
    deck_dir="$(dirname "$metadata")"
    deck_id="$(basename "$deck_dir")"
    output="$deck_dir/deck.pdf"

    readarray -t deck_info < <(php -r '
        $m = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
        echo ($m["visibility"] ?? "") . PHP_EOL;
        echo ($m["url"] ?? "") . PHP_EOL;
        echo (int) ($m["width"] ?? 0) . PHP_EOL;
        echo (int) ($m["height"] ?? 0) . PHP_EOL;
        echo (int) ($m["slide_count"] ?? 0) . PHP_EOL;
        echo ($m["title"] ?? "") . PHP_EOL;
    ' "$metadata")

    visibility="${deck_info[0]:-}"
    public_url="${deck_info[1]:-}"
    width="${deck_info[2]:-0}"
    height="${deck_info[3]:-0}"
    expected_slides="${deck_info[4]:-0}"
    title="${deck_info[5]:-}"

    # Synced archives are public-only. Never render a URL that has lost this invariant.
    if [[ "$visibility" != "all" || "$public_url" != https://slides.com/* ]]; then
        rm -f "$output"
        echo "::warning::Skipping PDF for deck $deck_id because its public Slides.com URL is unavailable."
        failure_count=$((failure_count + 1))
        continue
    fi

    if (( width <= 0 || height <= 0 )); then
        width=1280
        height=720
    fi

    tmp_output="$deck_dir/.deck.pdf.tmp"
    rm -f "$tmp_output"

    echo "Generating $output from $public_url (${width}x${height}, ${expected_slides} slides expected)"
    if ! "${DECKTAPE[@]}" reveal \
        --size "${width}x${height}" \
        --pause 250 \
        --load-pause 2000 \
        --url-load-timeout 60000 \
        --page-load-timeout 30000 \
        --pdf-title "$title" \
        --pdf-author 'Vitor Mattos' \
        "$public_url" \
        "$tmp_output"; then
        rm -f "$tmp_output"
        echo "::warning::DeckTape failed to generate a PDF for deck $deck_id; preserving the previous valid archive if present."
        failure_count=$((failure_count + 1))
        continue
    fi

    if [[ ! -s "$tmp_output" ]]; then
        rm -f "$tmp_output"
        echo "::warning::DeckTape produced no PDF for deck $deck_id."
        failure_count=$((failure_count + 1))
        continue
    fi

    pdf_size="$(stat -c '%s' "$tmp_output")"
    if (( pdf_size < 10000 )); then
        rm -f "$tmp_output"
        echo "::warning::DeckTape render rejected for deck $deck_id: ${pdf_size} bytes."
        failure_count=$((failure_count + 1))
        continue
    fi

    mv "$tmp_output" "$output"
    success_count=$((success_count + 1))
done < <(find presentations/slides.com -mindepth 2 -maxdepth 2 -name metadata.json -print0)

echo "PDF generation finished: ${success_count} generated, ${failure_count} skipped."
