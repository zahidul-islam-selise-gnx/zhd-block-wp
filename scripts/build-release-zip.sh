#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="zhd-elementor-blocks"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}.zip"

rm -rf "${BUILD_DIR}" "${ZIP_PATH}"
mkdir -p "${BUILD_DIR}"

rsync -a \
  --delete \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude 'dist/' \
  --exclude 'scripts/' \
  --exclude 'docs/' \
  --exclude '.DS_Store' \
  --exclude '.gitignore' \
  --exclude '.gitattributes' \
  --exclude 'CONTRIBUTING.md' \
  "${ROOT_DIR}/" "${BUILD_DIR}/"

(cd "${DIST_DIR}" && zip -rq "${ZIP_PATH}" "${PLUGIN_SLUG}")

echo "Created ${ZIP_PATH}"

