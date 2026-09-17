#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

shopt -s nullglob
tex_files=(presentations/latex/*/main.tex)

if [ "${#tex_files[@]}" -eq 0 ]; then
  echo "No LaTeX presentations found."
  exit 0
fi

for tex_file in "${tex_files[@]}"; do
  presentation_dir="$(dirname "$tex_file")"
  slug="$(basename "$presentation_dir")"
  build_dir="$(mktemp -d)"
  output_dir="source/presentations/latex/$slug"

  echo "Building LaTeX presentation: $slug"
  cp -R "$presentation_dir"/. "$build_dir"/

  latexmk \
    -pdf \
    -interaction=nonstopmode \
    -halt-on-error \
    -output-directory="$build_dir" \
    "$build_dir/main.tex"

  mkdir -p "$output_dir"
  cp "$build_dir/main.pdf" "$output_dir/$slug.pdf"

  pdftoppm \
    -f 1 \
    -singlefile \
    -png \
    -r 150 \
    "$build_dir/main.pdf" \
    "$output_dir/thumbnail"

  if [ -f "$presentation_dir/metadata.json" ]; then
    cp "$presentation_dir/metadata.json" "$output_dir/metadata.json"
  fi

  rm -rf "$build_dir"
done
