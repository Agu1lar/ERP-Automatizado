#!/usr/bin/env bash
# Configura hooks git na VM (uma vez) — evita scripts sem +x após pull
# Uso: sudo bash deploy/scripts/setup-git-hooks.sh

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
cd "${APP_PATH}"

if [ ! -d .git ]; then
    echo "ERRO: ${APP_PATH} não é um repositório git."
    exit 1
fi

OWNER="$(stat -c '%U' . 2>/dev/null || echo jose)"
git config core.hooksPath deploy/git-hooks
sed -i 's/\r$//' deploy/git-hooks/* deploy/scripts/*.sh 2>/dev/null || true
chmod +x deploy/git-hooks/* deploy/scripts/*.sh 2>/dev/null || true
chown -R "${OWNER}:$(stat -c '%G' .)" deploy/git-hooks 2>/dev/null || true

echo "OK: core.hooksPath=deploy/git-hooks"
echo "Scripts: $(ls -l deploy/scripts/deploy-from-git.sh | awk '{print $1}')"
