#!/usr/bin/env bash
# Testa restore em banco temporário — não altera produção.
# Uso: sudo bash deploy/scripts/testar-restore.sh /var/backups/erp-acesso/TIMESTAMP

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
BACKUP_DIR="${1:-}"
TEST_DB="${RESTORE_TEST_DB:-erp_acesso_restore_test}"

if [ -z "${BACKUP_DIR}" ] || [ ! -d "${BACKUP_DIR}" ]; then
  echo "Uso: sudo $0 /caminho/do/backup/TIMESTAMP" >&2
  exit 1
fi

DUMP="${BACKUP_DIR}/database.dump"
if [ ! -f "${DUMP}" ]; then
  echo "ERRO: ${DUMP} não encontrado" >&2
  exit 1
fi

cd "${APP_PATH}"
bash "${APP_PATH}/deploy/scripts/verificar-backup.sh" "${BACKUP_DIR}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-}"

if [ -z "${DB_USERNAME}" ] && [ -f "${APP_PATH}/.env" ]; then
  DB_USERNAME="$(grep '^DB_USERNAME=' "${APP_PATH}/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")"
fi
if [ -z "${DB_USERNAME}" ]; then
  DB_USERNAME="postgres"
fi

if [ -z "${PGPASSWORD:-}" ] && [ -f "${APP_PATH}/.env" ]; then
  export PGPASSWORD="$(grep '^DB_PASSWORD=' "${APP_PATH}/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")"
fi

echo "[testar-restore] Criando banco temporário ${TEST_DB}..."
psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d postgres \
  -c "DROP DATABASE IF EXISTS ${TEST_DB};" \
  -c "CREATE DATABASE ${TEST_DB};"

cleanup() {
  echo "[testar-restore] Removendo banco temporário ${TEST_DB}..."
  psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d postgres \
    -c "DROP DATABASE IF EXISTS ${TEST_DB};" 2>/dev/null || true
}
trap cleanup EXIT

echo "[testar-restore] Restaurando dump no banco de teste..."
pg_restore -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${TEST_DB}" --no-owner --no-privileges "${DUMP}"

tables="$(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${TEST_DB}" -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';")"

users="$(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${TEST_DB}" -tAc \
  "SELECT count(*) FROM users;" 2>/dev/null || echo 0)"

echo "[testar-restore] OK — ${tables} tabelas, ${users} usuário(s) no dump."
echo "[testar-restore] Restore validado sem alterar o banco de produção."
