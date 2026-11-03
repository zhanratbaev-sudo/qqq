#!/usr/bin/env bash
# Собирает webfor-ai.zip рядом с папкой плагина: библиотеки (composer, без dev-пакетов, без лишнего) + код плагина.
set -euo pipefail
cd "$(dirname "$0")"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
tmp="$(mktemp -d)"
mkdir -p "$tmp/webfor-ai"
cp -r webfor-ai.php includes assets vendor README.md "$tmp/webfor-ai/"
# тесты, примеры и служебные файлы библиотек в плагине не нужны
find "$tmp/webfor-ai/vendor" -type d \( -name tests -o -name .git -o -name examples -o -name fixtures -o -name scripts -o -name docs \) -prune -exec rm -rf {} +
# из библиотеки Anthropic нужен только обычный чат (messages): бета-функции, админ-API, навыки и облака
# Amazon/Microsoft плагин не вызывает — их типы не загружаются, а весят ~17 МБ
sdk="$tmp/webfor-ai/vendor/anthropic-ai/sdk"
rm -rf "$sdk/src/Beta" "$sdk/src/Organization" "$sdk/src/UserProfiles" "$sdk/src/Skills" "$sdk/src/Bedrock" "$sdk/src/Foundry" "$sdk/CHANGELOG.md"
find "$tmp/webfor-ai/vendor" -name '*.md' -not -name 'LICENSE*' -delete
rm -f ../webfor-ai.zip
(cd "$tmp" && zip -qr "$OLDPWD/../webfor-ai.zip" webfor-ai)
rm -rf "$tmp"
echo "$(cd .. && pwd)/webfor-ai.zip"
