#!/usr/bin/env bash
# Собирает webfor-ai.zip рядом с папкой плагина: библиотека Anthropic (composer, без dev-пакетов) + код плагина.
set -euo pipefail
cd "$(dirname "$0")"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
tmp="$(mktemp -d)"
mkdir -p "$tmp/webfor-ai"
cp -r webfor-ai.php includes assets vendor README.md "$tmp/webfor-ai/"
# тесты, примеры и служебные файлы библиотек в плагине не нужны
find "$tmp/webfor-ai/vendor" -type d \( -name tests -o -name .git -o -name examples -o -name fixtures -o -name scripts -o -name docs \) -prune -exec rm -rf {} +
rm -f ../webfor-ai.zip
(cd "$tmp" && zip -qr "$OLDPWD/../webfor-ai.zip" webfor-ai)
rm -rf "$tmp"
echo "$(cd .. && pwd)/webfor-ai.zip"
