#!/usr/bin/env bash
# Поднимает локальную копию сайта PetGo с демо-данными на http://127.0.0.1:8080
#   video/demo-site/setup.sh /путь/к/petgo-cabinet.zip
# WordPress 6.5 + SQLite берутся из npm-пакета @wp-playground/wordpress-builds (wordpress.org не нужен).
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
ZIP="${1:?Укажите путь к zip плагина petgo-cabinet}"
WP="${WP_DIR:-$HERE/.wp}"
PORT="${PORT:-8080}"

if [ ! -f "$WP/wp-settings.php" ]; then
  tmp="$(mktemp -d)"
  (cd "$tmp" && npm pack @wp-playground/wordpress-builds@0.9.19 --silent >/dev/null && tar xzf wp-playground-wordpress-builds-0.9.19.tgz)
  mkdir -p "$WP"
  unzip -q "$tmp/package/src/wordpress/wp-6.5.zip" -d "$WP"
  cp -Rn "$tmp/package/public/wp-6.5/." "$WP/" 2>/dev/null || true
  unzip -q "$tmp/package/src/sqlite-database-integration/sqlite-database-integration.zip" -d "$WP/wp-content/plugins"
  mv "$WP/wp-content/plugins/sqlite-database-integration-main" "$WP/wp-content/plugins/sqlite-database-integration"
  sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP/wp-content/plugins/sqlite-database-integration#" \
      -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
      "$WP/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP/wp-content/db.php"
  rm -rf "$tmp"
  python3 - "$WP/wp-config.php" "$PORT" <<'PY'
import secrets, sys
p, port = sys.argv[1], sys.argv[2]
s = open(p).read()
while 'put your unique phrase here' in s:
    s = s.replace('put your unique phrase here', secrets.token_hex(24), 1)
s = s.replace('/* Add any custom values between this line and the "stop editing" line. */', f"""
define('WP_HOME', 'http://127.0.0.1:{port}');
define('WP_SITEURL', 'http://127.0.0.1:{port}');
define('DB_DIR', __DIR__ . '/wp-content/database/');
define('DB_FILE', 'petgo.sqlite');
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);""")
open(p, 'w').write(s)
PY
fi

rm -rf "$WP/wp-content/plugins/petgo-cabinet"
unzip -q -o "$ZIP" -d "$WP/wp-content/plugins"
mkdir -p "$WP/wp-content/themes/petgo-blank" "$WP/wp-content/mu-plugins"
cp "$HERE/theme/"* "$WP/wp-content/themes/petgo-blank/"
cp "$HERE/mu-local.php" "$WP/wp-content/mu-plugins/local.php"
cp "$HERE/router.php" "$WP/router.php"

php "$HERE/install.php" "$WP"
php "$HERE/seed.php" "$WP"

if ! curl -s -o /dev/null "http://127.0.0.1:$PORT/"; then
  nohup php -S "127.0.0.1:$PORT" -t "$WP" "$WP/router.php" > "$WP/server.log" 2>&1 &
  sleep 1
fi
echo "Сайт: http://127.0.0.1:$PORT/  (владелец: owner / Demo-Owner-2026!)"
