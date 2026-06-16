#!/usr/bin/env bash
# Restore de emergência — PostgreSQL + storage/app
# Uso: sudo bash deploy/scripts/restore.sh /var/backups/erp-acesso/TIMESTAMP --confirm

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
BACKUP_DIR="${1:-}"
CONFIRM="${2:-}"

if [ -z "${BACKUP_DIR}" ] || [ ! -d "${BACKUP_DIR}" ]; then
  echo "Uso: sudo $0 /caminho/do/backup/TIMESTAMP --confirm" >&2
  exit 1
fi

if [ "${CONFIRM}" != "--confirm" ]; then
  echo "ATENÇÃO: isso substitui o banco e storage/app de produção." >&2
  echo "Rode com --confirm para executar." >&2
  exit 1
fi

DUMP="${BACKUP_DIR}/database.dump"
STORAGE="${BACKUP_DIR}/storage-app.tar.gz"

cd "${APP_PATH}"
bash "${APP_PATH}/deploy/scripts/verificar-backup.sh" "${BACKUP_DIR}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"

if [ -f "${APP_PATH}/.env" ]; then
  [ -z "${DB_DATABASE}" ] && DB_DATABASE="$(grep '^DB_DATABASE=' "${APP_PATH}/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")"
  [ -z "${DB_USERNAME}" ] && DB_USERNAME="$(grep '^DB_USERNAME=' "${APP_PATH}/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")"
  [ -z "${PGPASSWORD:-}" ] && export PGPASSWORD="$(grep '^DB_PASSWORD=' "${APP_PATH}/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")"
fi

echo "[restore] Parando fila..."
supervisorctl stop erp-acesso-worker:* 2>/dev/null || true

echo "[restore] Modo manutenção..."
php artisan down --retry=60 || true

echo "[restore] PostgreSQL (${DB_DATABASE})..."
pg_restore -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
  --clean --if-exists --no-owner --no-privileges "${DUMP}"

if [ -f "${STORAGE}" ]; then
  echo "[restore] storage/app..."
  rm -rf "${APP_PATH}/storage/app"/*
  tar -xzf "${STORAGE}" -C "${APP_PATH}/storage"
  chown -R www-data:www-data "${APP_PATH}/storage"
fi

echo "[restore] Cache..."
php artisan config:clear
php artisan cache:clear

echo "[restore] Subindo aplicação..."
php artisan up
supervisorctl start erp-acesso-worker:* 2>/dev/null || supervisorctl restart erp-acesso-worker:* 2>/dev/null || true

echo "[restore] Concluído a partir de ${BACKUP_DIR}"
