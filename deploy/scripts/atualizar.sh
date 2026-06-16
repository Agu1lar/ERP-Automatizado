#!/usr/bin/env bash
# Atualização do ERP na VM — um comando após copiar o código
# Uso na VM:  cd /var/www/ERP-Acesso && sudo bash deploy/scripts/atualizar.sh
#
# No Windows (envia código + atualiza):
#   powershell -File deploy\windows\atualizar.ps1

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
cd "${APP_PATH}"

echo "============================================"
echo " Atualizando Gestão Acesso em ${APP_PATH}"
echo "============================================"

echo "[1/8] Modo manutenção..."
php artisan down --retry=60 || true

echo "[2/8] Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "[3/8] Frontend (npm)..."
if [ -f package-lock.json ]; then
    npm ci
else
    npm install
fi
npm run build

echo "[4/8] Migrações..."
php artisan migrate --force

echo "[5/8] Storage link..."
php artisan storage:link 2>/dev/null || true

echo "[6/8] Permissões..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "[7/8] Cache de produção..."
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[8/8] Reiniciando serviços..."
systemctl reload php8.5-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
supervisorctl restart erp-acesso-worker:* 2>/dev/null || echo "  (fila: rode instalar-servicos.sh se ainda não instalou)"

php artisan up

echo ""
echo "Concluído. Teste: http://192.168.5.6"
echo "============================================"
