#!/usr/bin/env bash
# Carga demo completa (FullDemoSeeder) — rodar na VM após deploy
# Uso: cd /var/www/ERP-Acesso && sudo bash deploy/scripts/seed-demo.sh

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
cd "${APP_PATH}"

echo "============================================"
echo " Seed demo — Gestão Acesso"
echo "============================================"

export GEOCODING_ENABLED=false

DEPLOY_USER="${SUDO_USER:-jose}"

echo "[1/7] Permissões (www-data precisa ler o código)..."
chown -R "${DEPLOY_USER}:www-data" "${APP_PATH}"
find "${APP_PATH}" -type f ! -path '*/storage/*' ! -path '*/bootstrap/cache/*' -exec chmod 644 {} + 2>/dev/null || true
find "${APP_PATH}" -type d ! -path '*/storage/*' ! -path '*/bootstrap/cache/*' -exec chmod 755 {} + 2>/dev/null || true
mkdir -p bootstrap/cache storage/logs storage/framework/{cache,sessions,views}
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "[3/7] Composer (Faker necessário para seeds em produção)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "[4/7] Limpando cache bootstrap (evita Pail/Breeze de dev em produção)..."
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php

echo "[5/7] Limpando cache de config..."
sudo -u www-data php artisan config:clear

echo "[6/7] Recriando banco + seeds (FullDemoSeeder)..."
sudo -u www-data php artisan migrate:fresh --force --seed

echo "[7/7] Permissões e cache de produção..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
sudo -u www-data php artisan package:discover --ansi
sudo -u www-data php artisan config:cache

echo ""
echo "Concluído. Login: admin@acesso.local / Acesso@2026"
echo "============================================"
