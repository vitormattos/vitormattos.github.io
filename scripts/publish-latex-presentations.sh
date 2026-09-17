#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

repository="${GITHUB_REPOSITORY:-vitormattos/vitormattos.github.io}"
site_url="https://vitormattos.github.io"

shopt -s nullglob
metadata_files=(presentations/latex/*/metadata.json)

if [ "${#metadata_files[@]}" -eq 0 ]; then
  echo "No LaTeX presentation metadata found."
  exit 0
fi

for metadata_file in "${metadata_files[@]}"; do
  presentation_dir="$(dirname "$metadata_file")"
  slug="$(jq -r '.slug' "$metadata_file")"
  title="$(jq -r '.title' "$metadata_file")"
  description="$(jq -r '.description' "$metadata_file")"
  language="$(jq -r '.language' "$metadata_file")"
  slide_count="$(jq -r '.slide_count' "$metadata_file")"
  date="$(jq -r '.date' "$metadata_file")"
  source_file="$presentation_dir/$(jq -r '.source' "$metadata_file")"
  generated_dir="source/presentations/latex/$slug"
  pdf_file="$generated_dir/$slug.pdf"
  thumbnail_file="$generated_dir/thumbnail.png"

  if [ ! -f "$source_file" ] || [ ! -f "$pdf_file" ] || [ ! -f "$thumbnail_file" ]; then
    echo "Missing source or generated assets for $slug" >&2
    exit 1
  fi

  source_sha="$(sha256sum "$source_file" | awk '{print $1}')"
  source_short="${source_sha:0:12}"
  pdf_sha="$(sha256sum "$pdf_file" | awk '{print $1}')"
  pdf_short="${pdf_sha:0:12}"
  thumbnail_sha="$(sha256sum "$thumbnail_file" | awk '{print $1}')"
  thumbnail_short="${thumbnail_sha:0:12}"
  metadata_sha="$(sha256sum "$metadata_file" | awk '{print $1}')"
  metadata_short="${metadata_sha:0:12}"

  tag="latex-$slug"
  pdf_asset="$slug-$pdf_short.pdf"
  tex_asset="$slug-$source_short.tex"
  thumbnail_asset="thumbnail-$thumbnail_short.png"
  metadata_asset="metadata-$metadata_short.json"

  release_base="https://github.com/$repository/releases/download/$tag"
  pdf_url="$release_base/$pdf_asset"
  tex_url="$release_base/$tex_asset"
  thumbnail_url="$release_base/$thumbnail_asset"
  metadata_url="$release_base/$metadata_asset"
  portfolio_url="$site_url/pt-BR/palestras/$slug"

  body_file="$(mktemp)"
  cat > "$body_file" <<EOF
$description

<img src="$thumbnail_url" alt="$title" width="1280" height="720">

Seminário acadêmico produzido em LaTeX/Beamer. Esta release preserva instantâneos imutáveis do PDF, da fonte LaTeX, da primeira página renderizada e dos metadados da apresentação.

### Apresentação
- **Página no portfólio:** $portfolio_url
- **PDF desta versão:** $pdf_url
- **Fonte LaTeX desta versão:** $tex_url
- **Metadados:** $metadata_url
- **Idioma:** $language
- **Slides:** $slide_count
- **Data:** $date
- **SHA-256 da fonte:** $source_sha
- **SHA-256 do PDF:** $pdf_sha

### Trabalho analisado
- **Título:** $(jq -r '.reviewed_work.title' "$metadata_file")
- **Autores:** $(jq -r '.reviewed_work.authors | join(", ")' "$metadata_file")
- **Ano:** $(jq -r '.reviewed_work.year' "$metadata_file")
- **DOI:** https://doi.org/$(jq -r '.reviewed_work.doi' "$metadata_file")

A tag da release identifica a apresentação. Quando a fonte mudar, novos ativos com nomes derivados de seus hashes são adicionados, preservando os instantâneos anteriores.
EOF

  if gh release view "$tag" --repo "$repository" >/dev/null 2>&1; then
    gh release edit "$tag" --repo "$repository" --title "$title" --notes-file "$body_file"
  else
    gh release create "$tag" --repo "$repository" --target main --title "$title" --notes-file "$body_file"
  fi

  upload_if_missing() {
    local file="$1"
    local asset="$2"
    if gh release view "$tag" --repo "$repository" --json assets --jq '.assets[].name' | grep -Fxq "$asset"; then
      echo "Release asset already exists: $asset"
      return
    fi
    gh release upload "$tag" "$file#$asset" --repo "$repository"
  }

  upload_if_missing "$pdf_file" "$pdf_asset"
  upload_if_missing "$source_file" "$tex_asset"
  upload_if_missing "$thumbnail_file" "$thumbnail_asset"
  upload_if_missing "$metadata_file" "$metadata_asset"

  mkdir -p "$presentation_dir"
  jq -n \
    --arg source_sha "$source_sha" \
    --arg pdf_sha "$pdf_sha" \
    --arg thumbnail_sha "$thumbnail_sha" \
    --arg metadata_sha "$metadata_sha" \
    --arg release_tag "$tag" \
    --arg pdf_asset "$pdf_asset" \
    --arg pdf_url "$pdf_url" \
    --arg tex_asset "$tex_asset" \
    --arg tex_url "$tex_url" \
    --arg thumbnail_asset "$thumbnail_asset" \
    --arg thumbnail_url "$thumbnail_url" \
    --arg metadata_asset "$metadata_asset" \
    --arg metadata_url "$metadata_url" \
    '{
      schema: 1,
      source: {
        sha256: $source_sha,
        asset: $tex_asset,
        url: $tex_url
      },
      pdf: {
        sha256: $pdf_sha,
        release_tag: $release_tag,
        asset: $pdf_asset,
        url: $pdf_url
      },
      thumbnail: {
        sha256: $thumbnail_sha,
        asset: $thumbnail_asset,
        url: $thumbnail_url
      },
      metadata: {
        sha256: $metadata_sha,
        asset: $metadata_asset,
        url: $metadata_url
      }
    }' > "$presentation_dir/export.json"

  rm -f "$body_file"
done
