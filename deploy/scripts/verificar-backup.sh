#!/usr/bin/env bash
# Verifica integridade de um backup (sem restaurar).
# Uso: ./deploy/scripts/verificar-backup.sh /var/backups/erp-acesso/20260616_020000

set -euo pipefail

BACKUP_DIR="${1:-}"

if [ -z "${BACKUP_DIR}" ] || [ ! -d "${BACKUP_DIR}" ]; then
  echo "Uso: $0 /caminho/do/backup/TIMESTAMP" >&2
  exit 1
fi

DUMP="${BACKUP_DIR}/database.dump"
STORAGE="${BACKUP_DIR}/storage-app.tar.gz"

errors=0

echo "[verificar] Pasta: ${BACKUP_DIR}"

if [ ! -f "${DUMP}" ]; then
  echo "  ERRO: database.dump ausente" >&2
  errors=$((errors + 1))
else
  echo "  OK: database.dump ($(du -h "${DUMP}" | cut -f1))"
  if ! pg_restore --list "${DUMP}" >/dev/null 2>&1; then
    echo "  ERRO: database.dump corrompido ou ilegível" >&2
    errors=$((errors + 1))
  else
    tables="$(pg_restore --list "${DUMP}" | grep -c 'TABLE DATA' || true)"
    echo "  OK: pg_restore --list (${tables} tabelas com dados)"
  fi
fi

if [ ! -f "${STORAGE}" ]; then
  echo "  AVISO: storage-app.tar.gz ausente" >&2
else
  echo "  OK: storage-app.tar.gz ($(du -h "${STORAGE}" | cut -f1))"
  if ! tar -tzf "${STORAGE}" >/dev/null 2>&1; then
    echo "  ERRO: storage-app.tar.gz corrompido" >&2
    errors=$((errors + 1))
  else
    files="$(tar -tzf "${STORAGE}" | wc -l)"
    echo "  OK: tar legível (${files} entradas)"
  fi
fi

if [ -f "${BACKUP_DIR}/env.snapshot" ]; then
  echo "  OK: env.snapshot presente"
fi

if [ "${errors}" -gt 0 ]; then
  echo "[verificar] FALHOU com ${errors} erro(s)" >&2
  exit 1
fi

echo "[verificar] Backup íntegro."
