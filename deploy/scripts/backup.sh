#!/usr/bin/env bash
# Backup PostgreSQL + storage do Gestão Acesso
# Uso: ./deploy/scripts/backup.sh [/caminho/destino]
#
# Variáveis (ou export antes de rodar):
#   DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, PGPASSWORD
#   APP_PATH — raiz do projeto (default: diretório pai de deploy/)

set -euo pipefail

APP_PATH="${APP_PATH:-$(cd "$(dirname "$0")/../.." && pwd)}"
BACKUP_ROOT="${1:-${BACKUP_ROOT:-/var/backups/erp-acesso}}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DEST="${BACKUP_ROOT}/${TIMESTAMP}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-linha_leve}"
DB_USERNAME="${DB_USERNAME:-linha_leve}"

mkdir -p "${DEST}"

echo "[backup] Destino: ${DEST}"

echo "[backup] PostgreSQL dump..."
PGPASSWORD="${PGPASSWORD:-}" pg_dump \
  -h "${DB_HOST}" \
  -p "${DB_PORT}" \
  -U "${DB_USERNAME}" \
  -Fc \
  -f "${DEST}/database.dump" \
  "${DB_DATABASE}"

echo "[backup] Storage (anexos, QR codes)..."
tar -czf "${DEST}/storage-app.tar.gz" -C "${APP_PATH}/storage" app

if [ -f "${APP_PATH}/.env" ]; then
  cp "${APP_PATH}/.env" "${DEST}/env.snapshot"
fi

echo "${TIMESTAMP}" > "${DEST}/README.txt"
echo "Restaurar DB: pg_restore -h HOST -U USER -d linha_leve -c database.dump" >> "${DEST}/README.txt"
echo "Restaurar storage: tar -xzf storage-app.tar.gz -C storage/" >> "${DEST}/README.txt"

find "${BACKUP_ROOT}" -maxdepth 1 -type d -mtime +"${RETENTION_DAYS}" -exec rm -rf {} + 2>/dev/null || true

if command -v pg_restore >/dev/null 2>&1; then
  if pg_restore --list "${DEST}/database.dump" >/dev/null 2>&1; then
    echo "[backup] Verificação: dump íntegro."
  else
    echo "[backup] AVISO: dump pode estar corrompido." >&2
    exit 1
  fi
fi

echo "[backup] Concluído: ${DEST}"
