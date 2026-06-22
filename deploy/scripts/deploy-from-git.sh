#!/usr/bin/env bash
# Deploy via git pull + atualizar.sh (usado pelo GitHub Actions runner na VM)
# Uso manual: cd /var/www/ERP-Acesso && sudo bash deploy/scripts/deploy-from-git.sh

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
BRANCH="${DEPLOY_BRANCH:-main}"
cd "${APP_PATH}"

echo "============================================"
echo " Deploy via Git — ${APP_PATH} (${BRANCH})"
echo "============================================"

if [ ! -d .git ]; then
    echo "ERRO: ${APP_PATH} não é um repositório git."
    echo "Siga deploy/CICD.md para clonar o repositório na VM."
    exit 1
fi

echo "[1/3] Atualizando código (git)..."
git fetch origin "${BRANCH}"
git reset --hard "origin/${BRANCH}"

git config core.hooksPath deploy/git-hooks 2>/dev/null || true

if [ -d deploy/scripts ]; then
    sed -i 's/\r$//' deploy/scripts/*.sh deploy/git-hooks/* 2>/dev/null || true
    chmod +x deploy/scripts/*.sh deploy/git-hooks/* 2>/dev/null || true
fi

echo "[2/3] Rodando atualizar.sh..."
bash "${APP_PATH}/deploy/scripts/atualizar.sh"

echo "[3/3] Deploy concluído."
echo "============================================"
