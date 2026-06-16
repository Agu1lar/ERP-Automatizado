#!/usr/bin/env bash
# Deploy em produção (Linux) — executar como usuário de deploy no servidor
# Uso: ./deploy/scripts/deploy.sh
#
# Pré-requisitos: PHP 8.3+, Composer, Node 20+, PostgreSQL, Supervisor, Nginx

set -euo pipefail

APP_PATH="${APP_PATH:-$(cd "$(dirname "$0")/../.." && pwd)}"
cd "${APP_PATH}"

echo "[deploy] Modo manutenção..."
php artisan down --retry=60 || true

echo "[deploy] Código atualizado (git pull deve ter sido feito antes)"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

echo "[deploy] Migrações..."
php artisan migrate --force

echo "[deploy] Cache de produção..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[deploy] Reiniciando fila (Supervisor)..."
sudo supervisorctl restart erp-acesso-worker:* || echo "Aviso: Supervisor não configurado"

echo "[deploy] Saindo do modo manutenção..."
php artisan up

echo "[deploy] Concluído."
