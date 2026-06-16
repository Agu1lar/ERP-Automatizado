#!/usr/bin/env bash
# Instala fila (Supervisor) + cron (agendador + backup) — rodar UMA VEZ na VM
# Uso: cd /var/www/ERP-Acesso && sudo APP_PATH=/var/www/ERP-Acesso bash deploy/scripts/instalar-servicos.sh

set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
cd "${APP_PATH}"

echo "============================================"
echo " Instalando fila + cron em ${APP_PATH}"
echo "============================================"

echo "[1/5] Pacotes..."
need_apt=false
for pkg in supervisor cron postgresql-client; do
  if ! dpkg -s "${pkg}" >/dev/null 2>&1; then
    need_apt=true
    break
  fi
done

if [ "${need_apt}" = true ]; then
  if ! apt-get update -qq; then
    echo "  AVISO: apt-get update falhou (relógio ou mirror Ubuntu?)." >&2
    echo "  Corrija com: sudo timedatectl set-ntp true && sudo apt-get clean && sudo apt-get update" >&2
    echo "  Ou instale manualmente: sudo apt-get install -y supervisor cron postgresql-client" >&2
    exit 1
  fi
  apt-get install -y supervisor cron postgresql-client
else
  echo "  supervisor, cron e postgresql-client já instalados — pulando apt."
fi

echo "[2/5] Supervisor (fila queue:work)..."
mkdir -p "${APP_PATH}/storage/logs"
chown -R www-data:www-data "${APP_PATH}/storage"

sed "s|/var/www/ERP-Acesso|${APP_PATH}|g" \
  "${APP_PATH}/deploy/supervisor/erp-acesso-worker.conf" \
  > /etc/supervisor/conf.d/erp-acesso-worker.conf

supervisorctl reread
supervisorctl update
supervisorctl start erp-acesso-worker:* || supervisorctl restart erp-acesso-worker:*

echo "[3/5] Cron Laravel (schedule:run a cada minuto)..."
SCHEDULE_LINE="* * * * * cd ${APP_PATH} && php artisan schedule:run >> /dev/null 2>&1"
(crontab -u www-data -l 2>/dev/null | grep -v "artisan schedule:run" || true; echo "${SCHEDULE_LINE}") | crontab -u www-data -

echo "[4/5] Cron backup diário (02:00)..."
BACKUP_LINE="0 2 * * * APP_PATH=${APP_PATH} bash ${APP_PATH}/deploy/scripts/backup-cron.sh"
(crontab -l 2>/dev/null | grep -v "backup-cron.sh" || true; echo "${BACKUP_LINE}") | crontab -

chmod +x "${APP_PATH}/deploy/scripts/"*.sh

echo "[5/5] Status..."
supervisorctl status erp-acesso-worker:* || true
echo ""
echo "Cron www-data (agendador):"
crontab -u www-data -l | grep schedule || true
echo ""
echo "Cron root (backup):"
crontab -l | grep backup-cron || true

echo ""
echo "Fila + cron instalados."
echo "Teste backup:  sudo -u www-data bash deploy/scripts/backup.sh"
echo "Teste restore: sudo bash deploy/scripts/testar-restore.sh /var/backups/erp-acesso/..."
echo "============================================"
