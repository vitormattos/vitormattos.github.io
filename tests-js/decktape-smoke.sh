#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

cd "$(dirname "$0")/.."
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

cp scripts/slides/decktape-reveal.js scripts/slides/node_modules/decktape/plugins/reveal.js
fixture=$(node --input-type=module -e 'import { pathToFileURL } from "node:url"; console.log(pathToFileURL(process.argv[1]).href)' "$PWD/tests-js/fixtures/decktape.html")
scripts/slides/node_modules/.bin/decktape reveal \
    --size 960x540 --load-pause 0 --pause 0 \
    --url-load-timeout 60000 --page-load-timeout 30000 \
    --chrome-arg=--no-sandbox --chrome-arg=--disable-dev-shm-usage \
    "$fixture" "$tmp/deck.pdf"

test "$(pdfinfo "$tmp/deck.pdf" | awk '/^Pages:/ { print $2 }')" = 2
test "$(wc -c < "$tmp/deck.pdf")" -ge 10000
pdftotext -f 1 -l 1 "$tmp/deck.pdf" "$tmp/first.txt"
pdftotext -f 2 -l 2 "$tmp/deck.pdf" "$tmp/second.txt"
grep -Fq 'DeckTape renderer contract' "$tmp/first.txt"
grep -Fq 'Another slide' "$tmp/second.txt"
echo "DeckTape smoke test: two valid pages with the expected text"
