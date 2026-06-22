#!/usr/bin/env bash
# Corrige erro 500 após deploy (permissões, cache, views da sidebar)
# Uso: cd /var/www/ERP-Acesso && sudo bash deploy/scripts/corrigir-500.sh

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
cd "${APP_PATH}"

echo "============================================"
echo " Corrigir erro 500 — Gestão Acesso"
echo "============================================"

REQUIRED=(
    "resources/views/components/sidebar-nav-group.blade.php"
    "resources/views/components/sidebar-nav-link.blade.php"
    "resources/views/livewire/layout/partials/sidebar-menu.blade.php"
    "resources/views/livewire/layout/navigation.blade.php"
)

echo "[1/5] Verificando arquivos do layout..."
missing=0
for f in "${REQUIRED[@]}"; do
    if [ ! -f "${f}" ]; then
        echo "  FALTANDO: ${f}"
        missing=1
    fi
done
if [ "${missing}" -eq 1 ]; then
    echo ""
    echo "Arquivos ausentes — envie o código do PC:"
    echo "  powershell -File deploy\\windows\\atualizar.ps1 -VmHost IP_DA_VM"
    exit 1
fi
echo "  OK"

echo "[2/5] Permissões..."
DEPLOY_USER="${SUDO_USER:-jose}"
chown -R "${DEPLOY_USER}:www-data" "${APP_PATH}"
find "${APP_PATH}" -type f ! -path '*/storage/*' ! -path '*/bootstrap/cache/*' -exec chmod 644 {} + 2>/dev/null || true
find "${APP_PATH}" -type d ! -path '*/storage/*' ! -path '*/bootstrap/cache/*' -exec chmod 755 {} + 2>/dev/null || true
mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "[3/5] Limpando caches..."
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php
rm -rf storage/framework/views/*
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear 2>/dev/null || true
sudo -u www-data php artisan event:clear 2>/dev/null || true

echo "[4/5] Recriando cache de produção..."
sudo -u www-data php artisan package:discover --ansi
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan event:cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "[5/5] PHP-FPM..."
systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php8.5-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
php artisan up 2>/dev/null || true

echo ""
echo "Pronto. Teste no navegador (Ctrl+F5)."
echo "Se ainda falhar, veja o erro:"
echo "  sudo tail -40 storage/logs/laravel.log"
echo "============================================"
