#!/bin/sh
# First-run setup: permissions, config template.
#
# Git tracks directory existence via .gitkeep files, but cannot store
# ownership or mode bits. config.php is gitignored — it must be created
# from the template and filled with real credentials.
#
# Usage:
#   ./bin/setup-runtime.sh /var/www/jolate-proposal    # production
#   ./bin/setup-runtime.sh                             # current dir

set -eu

APP_DIR="${1:-.}"
WARN=0

# ── Runtime directories ──────────────────────────────────────────
mkdir -p "${APP_DIR}/backend/uploads" "${APP_DIR}/backend/logs" "${APP_DIR}/backend/certificados"
chown -R www-data:www-data "${APP_DIR}/backend/uploads" "${APP_DIR}/backend/logs" "${APP_DIR}/backend/certificados"
chmod 755                      "${APP_DIR}/backend/uploads" "${APP_DIR}/backend/logs" "${APP_DIR}/backend/certificados"

echo "✓ Directorios de runtime listos en ${APP_DIR}/backend/"

# ── config.php desde template ──────────────────────────────────
CONFIG="${APP_DIR}/backend/config.php"
EXAMPLE="${APP_DIR}/backend/config.example.php"

if [ ! -f "${CONFIG}" ]; then
    cp "${EXAMPLE}" "${CONFIG}"
    echo "✓ Copiado config.example.php → config.php"
fi

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  ⚠  editar backend/config.php con credenciales reales"
echo "     antes de poner el sitio en producción."
echo ""
echo "     PHP lee los valores de variables de entorno (getenv)."
echo "     En Docker se definen en .env → docker-compose.yml."
echo "     En Apache producción: configuralas con SetEnv en el"
echo "     VirtualHost, o hardcodeá los valores en config.php."
echo "══════════════════════════════════════════════════════════════"
