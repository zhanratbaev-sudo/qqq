#!/usr/bin/env bash
# Rebuild everything: cover art → PDF → editable PPTX.
set -euo pipefail
cd "$(dirname "$0")/.."
node tools/make_assets.js
node tools/export_pdf.js
if [ -x /opt/pw-browsers/chromium ]; then export CHROMIUM_PATH=/opt/pw-browsers/chromium; fi
python3 tools/pptx_from_rendered.py slides.html -o output/Saty_Guesthouse_Tax_Presentation.pptx \
  --selector ".slide" --font-latin "Calibri" --font-head "Cambria" --font-ea "Calibri" \
  --asset-dir output/_pptx_assets
