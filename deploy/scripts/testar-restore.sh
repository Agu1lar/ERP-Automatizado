#!/usr/bin/env bash
# Testa restore em banco temporário — não altera produção.
# Uso: sudo bash deploy/scripts/testar-restore.sh /var/backups/erp-acesso/TIMESTAMP

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
BACKUP_DIR="${1:-}"
TEST_DB="${RESTORE_TEST_DB:-erp_acesso_restore_test}"
PG_ADMIN_USER="${PG_ADMIN_USER:-postgres}"

if [ -z "${BACKUP_DIR}" ] || [ ! -d "${BACKUP_DIR}" ]; then
  echo "Uso: sudo $0 /caminho/do/backup/TIMESTAMP" >&2
  exit 1
fi

DUMP="${BACKUP_DIR}/database.dump"
if [ ! -f "${DUMP}" ]; then
  echo "ERRO: ${DUMP} não encontrado" >&2
  exit 1
fi

load_env_var() {
  local key="$1"
  grep "^${key}=" "${APP_PATH}/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r'
}

cd "${APP_PATH}"
bash "${APP_PATH}/deploy/scripts/verificar-backup.sh" "${BACKUP_DIR}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-}"

if [ -f "${APP_PATH}/.env" ]; then
  DB_HOST="$(load_env_var DB_HOST)"
  DB_PORT="$(load_env_var DB_PORT)"
  DB_USERNAME="$(load_env_var DB_USERNAME)"
  export PGPASSWORD="$(load_env_var DB_PASSWORD)"
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-postgres}"

psql_admin() {
  sudo -u "${PG_ADMIN_USER}" psql -h "${DB_HOST}" -p "${DB_PORT}" -d postgres "$@"
}

echo "[testar-restore] Criando banco temporário ${TEST_DB} (via ${PG_ADMIN_USER})..."
psql_admin -c "DROP DATABASE IF EXISTS ${TEST_DB};"
psql_admin -c "CREATE DATABASE ${TEST_DB} OWNER ${DB_USERNAME};"

cleanup() {
  echo "[testar-restore] Removendo banco temporário ${TEST_DB}..."
  psql_admin -c "DROP DATABASE IF EXISTS ${TEST_DB};" 2>/dev/null || true
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
