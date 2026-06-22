# CI/CD — GitHub → VM VirtualBox

Fluxo: **commit/push no `main`** → GitHub roda **testes** na nuvem → se passarem, o **runner na VM** faz `git pull` + `atualizar.sh`.

```
┌─────────────┐     push      ┌──────────────────┐     OK      ┌─────────────────────┐
│  Seu PC     │ ────────────► │ GitHub Actions   │ ──────────► │ VM (self-hosted     │
│  git push   │               │ Tests (nuvem)    │             │ runner) deploy.yml  │
└─────────────┘               └──────────────────┘             └─────────────────────┘
```

A VM está em rede privada (`192.168.5.x`), então o deploy **não** usa SSH a partir da nuvem — um **runner self-hosted** roda os jobs diretamente na VM.

---

## Pré-requisitos

- Repositório no GitHub (ex.: `Agu1lar/ERP-Automatizado`)
- VM Ubuntu com PHP, Composer, Node, Nginx já configurados (`deploy/VIRTUALBOX.md`)
- App em `/var/www/ERP-Acesso` com `.env` de produção (não vai para o Git)

---

## Passo 1 — Repositório git na VM (uma vez)

Na VM:

```bash
# Se a pasta já existe mas veio por SCP (sem .git), faça backup do .env e clone:
sudo cp /var/www/ERP-Acesso/.env ~/erp-env-backup.env

sudo mv /var/www/ERP-Acesso /var/www/ERP-Acesso.old
sudo git clone https://github.com/Agu1lar/ERP-Automatizado.git /var/www/ERP-Acesso
sudo cp ~/erp-env-backup.env /var/www/ERP-Acesso/.env
sudo chown -R jose:www-data /var/www/ERP-Acesso
```

**Autenticação git (escolha uma):**

### Opção A — HTTPS com token (mais simples)

1. GitHub → Settings → Developer settings → Personal access tokens → gerar token com `repo`
2. Na VM:

```bash
cd /var/www/ERP-Acesso
git config credential.helper store
git pull   # usuário: seu GitHub; senha: o token
```

### Opção B — Deploy key (somente leitura)

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N ""
cat ~/.ssh/github_deploy.pub
# Cole em GitHub → Repo → Settings → Deploy keys → Add
cd /var/www/ERP-Acesso
git remote set-url origin git@github.com:Agu1lar/ERP-Automatizado.git
GIT_SSH_COMMAND="ssh -i ~/.ssh/github_deploy" git pull
```

Primeira instalação completa (se clone novo):

```bash
cd /var/www/ERP-Acesso
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
sudo bash deploy/scripts/instalar-servicos.sh
```

---

## Passo 2 — Instalar o runner na VM (uma vez)

No GitHub: **Settings → Actions → Runners → New self-hosted runner** → copie o **token** (expira em ~1 hora).

Na VM:

```bash
cd /var/www/ERP-Acesso
git pull   # para ter install-github-runner.sh
sudo sed -i 's/\r$//' deploy/scripts/*.sh

export GITHUB_RUNNER_TOKEN="cole_o_token_aqui"
sudo -E bash deploy/scripts/install-github-runner.sh
```

Sudo sem senha para deploy automático:

```bash
sudo tee /etc/sudoers.d/erp-deploy <<'EOF'
jose ALL=(ALL) NOPASSWD: /var/www/ERP-Acesso/deploy/scripts/deploy-from-git.sh
jose ALL=(ALL) NOPASSWD: /var/www/ERP-Acesso/deploy/scripts/atualizar.sh
EOF
sudo chmod 440 /etc/sudoers.d/erp-deploy
```

Verifique o runner em **GitHub → Actions → Runners** (deve aparecer `ServidorTecAcesso` com label `erp-acesso`).

---

## Passo 3 — Workflows no repositório

Já incluídos:

| Arquivo | Função |
|---------|--------|
| `.github/workflows/tests.yml` | CI — PHPUnit em cada push/PR no `main` |
| `.github/workflows/deploy.yml` | CD — após testes OK no `main`, deploy na VM |

Deploy manual (sem esperar push):

GitHub → **Actions → Deploy → Run workflow**

---

## Passo 4 — Uso no dia a dia

No PC:

```bash
git add .
git commit -m "sua mensagem"
git push origin main
```

1. Actions roda **Tests** (~2–5 min)
2. Se passar, **Deploy** roda na VM (`git pull` + `atualizar.sh`)
3. Acompanhe em **GitHub → Actions**

Na VM, deploy manual equivalente:

```bash
cd /var/www/ERP-Acesso
sudo bash deploy/scripts/deploy-from-git.sh
```

---

## O que o deploy faz

`deploy/scripts/deploy-from-git.sh`:

1. `git fetch` + `reset --hard origin/main`
2. `deploy/scripts/atualizar.sh` (composer, npm build, migrate, cache, PHP-FPM)

**Não** sobrescreve `.env`, `storage/` nem uploads.

---

## Troubleshooting

| Problema | Solução |
|----------|---------|
| Job Deploy fica *Queued* | Runner offline — `sudo /home/jose/actions-runner/svc.sh status` |
| `git pull` falha na VM | Token/SSH — ver Passo 1 |
| `sudo: a password is required` | Configurar `/etc/sudoers.d/erp-deploy` |
| Erro 500 após deploy | `sudo bash deploy/scripts/corrigir-500.sh` |
| Tests falham, deploy não roda | Corrija testes antes; deploy só após CI verde |

Logs do runner:

```bash
journalctl -u actions.runner.* -f
```

---

## Alternativa sem runner (não recomendada)

Se não quiser runner na VM:

- **Webhook** na VM + `git pull` (expor porta na rede)
- **`atualizar.ps1`** no Windows após push (PC precisa estar ligado)
- **Cron** `git pull` a cada X minutos (não é CD de verdade)

Para rede doméstica/VirtualBox, **self-hosted runner** é a opção mais estável.
