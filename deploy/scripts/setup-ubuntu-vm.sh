#!/usr/bin/env bash
# Prepara Ubuntu (VirtualBox) para o Gestão Acesso — rodar UMA VEZ como root/sudo
# Uso: sudo bash deploy/scripts/setup-ubuntu-vm.sh SENHA_DO_BANCO

set -euo pipefail

DB_PASSWORD="${1:-}"
APP_PATH="${APP_PATH:-/var/www/ERP-Acesso}"
DB_NAME="${DB_NAME:-sistema_acesso}"
DB_USER="${DB_USER:-sistema_acesso}"

if [ -z "${DB_PASSWORD}" ]; then
  echo "Uso: sudo bash $0 SENHA_DO_BANCO" >&2
  echo "Exemplo: sudo bash deploy/scripts/setup-ubuntu-vm.sh 'MinhaSenhaForte123'" >&2
  exit 1
fi

detect_php_version_from_apt() {
  local ver
  for ver in 8.5 8.4 8.3 8.2; do
    if apt-cache show "php${ver}-fpm" >/dev/null 2>&1; then
      echo "${ver}"
      return 0
    fi
  done
  return 1
}

detect_php_version_installed() {
  local sock ver
  for sock in /run/php/php*-fpm.sock; do
    [ -e "${sock}" ] || continue
    ver="$(basename "${sock}" | sed -E 's/php([0-9]+\.[0-9]+)-fpm\.sock/\1/')"
    echo "${ver}"
    return 0
  done
  if command -v php >/dev/null 2>&1; then
    php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'
    return 0
  fi
  return 1
}

enable_apt_repos() {
  apt-get install -y software-properties-common
  add-apt-repository -y universe 2>/dev/null || true
  add-apt-repository -y multiverse 2>/dev/null || true
  apt-get update -qq
}

install_ondrej_php() {
  echo "  Adicionando PPA ondrej/php..."
  add-apt-repository -y ppa:ondrej/php
  apt-get update -qq
  detect_php_version_from_apt || return 1
}

install_php_packages() {
  local ver="$1"
  apt-get install -y \
    "php${ver}-fpm" "php${ver}-cli" "php${ver}-pgsql" "php${ver}-mbstring" "php${ver}-xml" \
    "php${ver}-curl" "php${ver}-zip" "php${ver}-gd" "php${ver}-bcmath" "php${ver}-intl"
}

echo "============================================"
echo " Setup Ubuntu — Gestão Acesso"
echo "============================================"

export DEBIAN_FRONTEND=noninteractive

PHP_VER="$(detect_php_version_installed || true)"

if [ -n "${PHP_VER}" ] && command -v php >/dev/null 2>&1; then
  echo "[1/7] PHP já instalado (${PHP_VER}) — pulando pacotes PHP."
  NEED_BASE=false
  for pkg in nginx postgresql supervisor; do
    if ! dpkg -s "${pkg}" >/dev/null 2>&1; then
      NEED_BASE=true
      break
    fi
  done
  if [ "${NEED_BASE}" = true ]; then
    enable_apt_repos
    apt-get install -y nginx postgresql postgresql-client git curl unzip supervisor
  fi
else
  echo "[1/7] Pacotes..."
  enable_apt_repos

  PHP_VER="$(detect_php_version_from_apt || true)"
  if [ -z "${PHP_VER}" ]; then
    PHP_VER="$(install_ondrej_php || true)"
  fi

  if [ -z "${PHP_VER}" ]; then
    echo "ERRO: não foi possível encontrar php-fpm (8.2+)." >&2
    echo "Instale manualmente e rode este script de novo." >&2
    exit 1
  fi

  echo "  Instalando PHP ${PHP_VER}..."
  apt-get install -y nginx postgresql postgresql-client git curl unzip supervisor
  install_php_packages "${PHP_VER}"
fi

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v node >/dev/null 2>&1; then
  apt-get install -y nodejs npm
fi

echo "[2/7] PostgreSQL..."
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASSWORD}';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"

sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"

echo "[3/7] Pasta do projeto..."
mkdir -p "${APP_PATH}"
mkdir -p "${APP_PATH}/storage/logs"
chown -R www-data:www-data "${APP_PATH}/storage" 2>/dev/null || true

echo "[4/7] PHP-FPM..."
systemctl enable "php${PHP_VER}-fpm" nginx postgresql supervisor 2>/dev/null || \
  systemctl enable php-fpm nginx postgresql supervisor
systemctl start "php${PHP_VER}-fpm" nginx postgresql supervisor 2>/dev/null || \
  systemctl start php-fpm nginx postgresql supervisor

echo "[5/7] Versão PHP para deploy..."
mkdir -p "${APP_PATH}/deploy"
echo "${PHP_VER}" > "${APP_PATH}/deploy/.php-version"
chmod 644 "${APP_PATH}/deploy/.php-version"

echo "[6/7] Firewall (ufw)..."
if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH 2>/dev/null || true
  ufw allow 'Nginx HTTP' 2>/dev/null || ufw allow 80/tcp 2>/dev/null || true
fi

echo "[7/7] Concluído."
echo ""
echo "Próximos passos:"
echo "  1. cp deploy/env/.env.production.example .env && nano .env"
echo "  2. php artisan key:generate && php artisan migrate --force --seed"
echo "  3. Nginx — socket: /run/php/php${PHP_VER}-fpm.sock"
echo "  4. sudo APP_PATH=${APP_PATH} bash deploy/scripts/instalar-servicos.sh"
echo ""
echo "Banco: ${DB_NAME} / usuário: ${DB_USER} / PHP: ${PHP_VER}"
echo "============================================"
