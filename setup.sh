#!/usr/bin/env bash
#
# Web Framework — install / restore setup.
#
# Creates the config files and writable directories and fixes their ownership
# and permissions, so the instance works after a fresh install or a backup
# restore. Idempotent — safe to run repeatedly.
#
# Usage:
#     sudo ./setup.sh [web-user]
#
#   web-user  OS user the web server runs as. Defaults to "daemon" on macOS
#             (XAMPP) and "www-data" on Linux. Override if your server differs
#             (e.g. apache, nginx, or your own user on shared hosting).
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"

# Resolve a PHP binary (prefer XAMPP's on macOS, else the one on PATH).
PHP_BIN="$(command -v php || true)"
if [ -x /Applications/XAMPP/xamppfiles/bin/php ]; then
    PHP_BIN=/Applications/XAMPP/xamppfiles/bin/php
fi
if [ -z "${PHP_BIN}" ]; then
    echo "PHP not found. Install PHP or add it to PATH." >&2
    exit 1
fi

# Pick the web-server user.
if [ "${1:-}" != "" ]; then
    WEB_USER="$1"
elif [ "$(uname)" = "Darwin" ]; then
    WEB_USER="daemon"
else
    WEB_USER="www-data"
fi

WRITABLE_DIRS=("web/pages" "logs" "api/tmp" "cms/views/assets/files" "packages")
CONFIG_FILES=("cms/config.php" "web/config.php")

echo "==> Creating config files and directories"
"${PHP_BIN}" "${ROOT}/tools/setup.php"

echo
echo "==> Setting ownership to '${WEB_USER}' and permissions (775 dirs / 664 files)"
for d in "${WRITABLE_DIRS[@]}"; do
    if [ -d "${ROOT}/${d}" ]; then
        chown -R "${WEB_USER}" "${ROOT}/${d}" 2>/dev/null || true
        chmod -R 775 "${ROOT}/${d}"
        echo "  ${d}/"
    fi
done

# Config files must be readable by the web user.
for f in "${CONFIG_FILES[@]}"; do
    if [ -f "${ROOT}/${f}" ]; then
        chown "${WEB_USER}" "${ROOT}/${f}" 2>/dev/null || true
        chmod 664 "${ROOT}/${f}"
        echo "  ${f}"
    fi
done

echo
echo "==> Done. The instance should now be able to read its config and write"
echo "    to its directories. Review the config.php files for any secrets."
