#!/usr/bin/env bash
# Wrapper para cron — lê credenciais do .env e roda backup.sh

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
cd "${APP_PATH}"

if [ -f "${APP_PATH}/.env" ]; then
  export DB_HOST="$(grep '^DB_HOST=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")"
  export DB_PORT="$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")"
  export DB_DATABASE="$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")"
  export DB_USERNAME="$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")"
  export PGPASSWORD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")"
fi

export APP_PATH
bash "${APP_PATH}/deploy/scripts/backup.sh" >> "${APP_PATH}/storage/logs/backup.log" 2>&1
