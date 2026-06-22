#!/usr/bin/env bash
# Instala GitHub Actions self-hosted runner na VM (Ubuntu)
# Uso:
#   export GITHUB_RUNNER_TOKEN="cole_o_token_aqui"
#   sudo bash deploy/scripts/install-github-runner.sh
#
# Token: GitHub → Repositório → Settings → Actions → Runners → New self-hosted runner

set -euo pipefail

RUNNER_USER="${RUNNER_USER:-jose}"
RUNNER_NAME="${RUNNER_NAME:-ServidorTecAcesso}"
RUNNER_LABELS="${RUNNER_LABELS:-erp-acesso}"
RUNNER_DIR="${RUNNER_DIR:-/home/${RUNNER_USER}/actions-runner}"
REPO_URL="${GITHUB_REPO_URL:-https://github.com/Agu1lar/ERP-Automatizado}"

if [ -z "${GITHUB_RUNNER_TOKEN:-}" ]; then
    echo "ERRO: defina GITHUB_RUNNER_TOKEN (token de registro do runner, válido ~1h)."
    echo "GitHub → Settings → Actions → Runners → New self-hosted runner → copie o token."
    exit 1
fi

echo "============================================"
echo " GitHub Actions runner — ${RUNNER_NAME}"
echo "============================================"

apt-get update -qq
apt-get install -y curl git ca-certificates

mkdir -p "${RUNNER_DIR}"
cd "${RUNNER_DIR}"

RUNNER_VERSION="2.335.1"
ARCH="linux-x64"
if [ "$(uname -m)" = "aarch64" ]; then
    ARCH="linux-arm64"
fi

if [ ! -f ./config.sh ]; then
    echo "Baixando actions-runner v${RUNNER_VERSION}..."
    curl -fsSL -o actions-runner.tar.gz \
        "https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/actions-runner-${ARCH}-${RUNNER_VERSION}.tar.gz"
    tar xzf actions-runner.tar.gz
    rm -f actions-runner.tar.gz
fi

chown -R "${RUNNER_USER}:${RUNNER_USER}" "${RUNNER_DIR}"

sudo -u "${RUNNER_USER}" ./config.sh \
    --url "${REPO_URL}" \
    --token "${GITHUB_RUNNER_TOKEN}" \
    --name "${RUNNER_NAME}" \
    --labels "${RUNNER_LABELS}" \
    --unattended \
    --replace

./svc.sh install "${RUNNER_USER}"
./svc.sh start
./svc.sh status

SUDOERS_FILE="/etc/sudoers.d/erp-deploy"
if [ "$(id -u)" -eq 0 ]; then
    echo "Configurando sudo sem senha para deploy..."
    cat > "${SUDOERS_FILE}" <<EOF
${RUNNER_USER} ALL=(ALL) NOPASSWD: /var/www/ERP-Acesso/deploy/scripts/deploy-from-git.sh
${RUNNER_USER} ALL=(ALL) NOPASSWD: /var/www/ERP-Acesso/deploy/scripts/atualizar.sh
EOF
    chmod 440 "${SUDOERS_FILE}"
    visudo -cf "${SUDOERS_FILE}"
    echo "  OK: ${SUDOERS_FILE}"
else
    echo "AVISO: rode com sudo para criar ${SUDOERS_FILE} automaticamente."
fi

echo ""
echo "Runner instalado. Labels: ${RUNNER_LABELS}"
echo "Workflow deploy.yml usa: runs-on: [self-hosted, erp-acesso]"
echo ""
echo "Se o sudoers não foi criado, configure manualmente (como root):"
echo "  sudo tee ${SUDOERS_FILE} <<'EOF'"
echo "${RUNNER_USER} ALL=(ALL) NOPASSWD: /var/www/ERP-Acesso/deploy/scripts/deploy-from-git.sh"
echo "${RUNNER_USER} ALL=(ALL) NOPASSWD: /var/www/ERP-Acesso/deploy/scripts/atualizar.sh"
echo "EOF"
echo "  sudo chmod 440 ${SUDOERS_FILE}"
echo ""
echo "Importante: use 'sudo /caminho/script.sh' — NÃO 'sudo bash script.sh'"
echo "============================================"
