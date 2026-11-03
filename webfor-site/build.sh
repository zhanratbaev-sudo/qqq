#!/usr/bin/env bash
# Собирает webfor-site.zip рядом с папкой плагина (для загрузки в WordPress).
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f webfor-site.zip
zip -qr webfor-site.zip webfor-site -x 'webfor-site/build.sh' 'webfor-site/.*'
echo "$(pwd)/webfor-site.zip"
