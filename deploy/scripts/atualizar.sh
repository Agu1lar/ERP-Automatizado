#!/usr/bin/env bash
# Atualização do ERP na VM — um comando após copiar o código
# Uso na VM:  cd /var/www/ERP-Acesso && sudo bash deploy/scripts/atualizar.sh
#
# No Windows (envia código + atualiza):
#   powershell -File deploy\windows\atualizar.ps1

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
cd "${APP_PATH}"

cleanup() {
    if [ "$(id -u)" -eq 0 ]; then
        sudo -u www-data php artisan up 2>/dev/null || true
    else
        php artisan up 2>/dev/null || true
    fi
}
trap cleanup EXIT

echo "============================================"
echo " Atualizando Gestão Acesso em ${APP_PATH}"
echo "============================================"

echo "[0/9] Verificando arquivos essenciais..."
for f in \
    resources/views/components/sidebar-nav-group.blade.php \
    resources/views/livewire/layout/partials/sidebar-menu.blade.php \
    resources/views/livewire/layout/navigation.blade.php
do
    if [ ! -f "${f}" ]; then
        echo "ERRO: ${f} ausente. Rode atualizar.ps1 no PC antes de continuar."
        exit 1
    fi
done

run_as_deploy() {
    if [ -n "${SUDO_USER:-}" ] && [ "$(id -u)" -eq 0 ]; then
        sudo -u "${SUDO_USER}" "$@"
    else
        "$@"
    fi
}

run_artisan() {
    if [ "$(id -u)" -eq 0 ]; then
        sudo -u www-data php artisan "$@"
    else
        php artisan "$@"
    fi
}

fix_permissions() {
    DEPLOY_USER="${SUDO_USER:-jose}"
    chown -R "${DEPLOY_USER}:www-data" "${APP_PATH}"
    find "${APP_PATH}" -type f ! -path '*/storage/*' ! -path '*/bootstrap/cache/*' -exec chmod 644 {} + 2>/dev/null || true
    find "${APP_PATH}" -type d ! -path '*/storage/*' ! -path '*/bootstrap/cache/*' -exec chmod 755 {} + 2>/dev/null || true
    mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
    chown -R "${DEPLOY_USER}:www-data" storage bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache
}

echo "[1/9] Permissões (storage e bootstrap/cache)..."
fix_permissions

echo "[2/9] Composer..."
run_as_deploy composer install --no-dev --optimize-autoloader --no-interaction

echo "[3/9] Modo manutenção..."
run_artisan down --retry=60 || true

echo "[4/9] Frontend (npm)..."
if [ -f package-lock.json ]; then
    npm ci
else
    npm install
fi
npm run build

echo "[5/9] Migrações..."
run_artisan migrate --force

echo "[6/9] Storage link..."
run_artisan storage:link 2>/dev/null || true

echo "[7/9] Permissões (pós-build)..."
fix_permissions

echo "[8/9] Cache de produção..."
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php
rm -rf storage/framework/views/*
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan package:discover --ansi
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan event:cache
fix_permissions

echo "[9/9] Reiniciando serviços..."
systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php8.5-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
supervisorctl restart erp-acesso-worker:* 2>/dev/null || echo "  (fila: rode instalar-servicos.sh se ainda não instalou)"

trap - EXIT
run_artisan up

echo ""
echo "Concluído. Teste: http://$(hostname -I | awk '{print $1}')"
echo "============================================"
