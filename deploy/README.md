# Deploy — Gestão Acesso

Runbook para a VM Linux em `/var/www/ERP-Acesso`.

> **IP da VM:** em rede Bridge o endereço vem do DHCP (`hostname -I`). O exemplo `192.168.5.6` nos arquivos de config é só referência — ajuste Nginx, `.env` (`APP_URL`) e scripts se o IP mudar. Guia da VM: [VIRTUALBOX.md](VIRTUALBOX.md).

## CI/CD automático (recomendado)

Fluxo no dia a dia:

```
git push origin main
    → GitHub Actions: Tests (SQLite + PostgreSQL na nuvem)
    → se passar: Deploy na VM (runner self-hosted)
    → git pull + atualizar.sh
```

| Arquivo | Função |
|---------|--------|
| `.github/workflows/tests.yml` | CI — PHPUnit em cada push/PR no `main` |
| `.github/workflows/deploy.yml` | CD — deploy na VM após testes verdes |
| `deploy/scripts/deploy-from-git.sh` | `git fetch` + `reset --hard` + `atualizar.sh` |
| `deploy/scripts/install-github-runner.sh` | Instala o runner na VM (uma vez) |

Guia completo (runner, sudoers, DNS, troubleshooting): **[CICD.md](CICD.md)**

Deploy manual na VM (equivalente ao automático):

```bash
cd /var/www/ERP-Acesso && sudo bash /var/www/ERP-Acesso/deploy/scripts/deploy-from-git.sh
```

## Primeira instalação (uma vez)

```bash
cd /var/www/ERP-Acesso
cp deploy/env/.env.production.example .env
nano .env   # APP_KEY, APP_URL, DB_*, senhas
php artisan key:generate
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force --seed
php artisan storage:link
sudo chown -R jose:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# Nginx (substitua IP_VM pelo IP real)
sudo sed "s/192.168.5.6/IP_VM/g" deploy/nginx/erp-acesso.conf | sudo tee /etc/nginx/sites-available/erp-acesso.conf
sudo ln -sf /etc/nginx/sites-available/erp-acesso.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Fila + cron + backup diário
sudo APP_PATH=/var/www/ERP-Acesso bash deploy/scripts/instalar-servicos.sh
```

Setup inicial da VM Ubuntu (PHP, Node, PostgreSQL): `deploy/scripts/setup-ubuntu-vm.sh`

## Atualizar código

**Automático:** push no `main` (ver CI/CD acima).

**Na VM** (manual):

```bash
cd /var/www/ERP-Acesso && sudo bash deploy/scripts/atualizar.sh
```

**No Windows** (envia arquivos via SCP + atualiza na VM):

```powershell
cd C:\Users\User\Documents\ERP_Acesso
powershell -ExecutionPolicy Bypass -File deploy\windows\atualizar.ps1 -VmHost IP_DA_VM
```

Ou dê duplo clique em `atualizar-erp.bat`.

## Scripts disponíveis

| Script | Uso |
|--------|-----|
| `atualizar.sh` | Composer, npm build, migrate, cache, PHP-FPM |
| `deploy-from-git.sh` | Git pull + `atualizar.sh` (CI/CD) |
| `corrigir-500.sh` | Permissões, cache e views após erro 500 |
| `instalar-servicos.sh` | Supervisor (fila), cron, backup |
| `install-github-runner.sh` | Runner self-hosted do GitHub Actions |
| `setup-ubuntu-vm.sh` | Dependências do servidor (primeira vez) |
| `seed-demo.sh` | Recarrega dados demo em produção |
| `backup.sh` / `restore.sh` | Backup e restore de emergência |
| `verificar-backup.sh` / `testar-restore.sh` | Validar backup sem tocar produção |

## Serviços instalados

| Serviço | O que faz |
|---------|-----------|
| **Supervisor** `erp-acesso-worker` | `queue:work` — QR Code, e-mails, jobs |
| **Cron** `www-data` | `schedule:run` a cada minuto — renovações, preventiva, alertas |
| **Cron** `root` | Backup diário às 02:00 |

### Comandos úteis

```bash
sudo supervisorctl status erp-acesso-worker:*
sudo supervisorctl restart erp-acesso-worker:*
sudo crontab -u www-data -l
tail -f /var/www/ERP-Acesso/storage/logs/worker.log
php artisan schedule:list
journalctl -u actions.runner.* -f   # logs do GitHub runner
```

## Backup e restore

### Backup manual

```bash
cd /var/www/ERP-Acesso
sudo bash deploy/scripts/backup.sh
```

Destino padrão: `/var/backups/erp-acesso/YYYYMMDD_HHMMSS/`

### Verificar integridade (sem restaurar produção)

```bash
bash deploy/scripts/verificar-backup.sh /var/backups/erp-acesso/20260616_020000
```

### Testar restore em banco temporário

```bash
sudo bash deploy/scripts/testar-restore.sh /var/backups/erp-acesso/20260616_020000
```

### Restore real (emergência)

```bash
php artisan down
sudo bash deploy/scripts/restore.sh /var/backups/erp-acesso/20260616_020000 --confirm
php artisan up
```

### Nota importante (CRLF do Windows)

O repositório já força LF em scripts via `.gitattributes` (`*.sh text eol=lf`). No PC Windows, configure também:

```powershell
git config --global core.autocrlf false
```

Se ainda aparecer erro como `deploy/scripts/backup.sh: line X: $'\r': command not found` ou `bad interpreter`, corrija na VM:

```bash
cd /var/www/ERP-Acesso
sudo sed -i 's/\r$//' deploy/scripts/*.sh
sudo chmod +x deploy/scripts/*.sh
```

## Troubleshooting rápido

| Problema | Solução |
|----------|---------|
| Erro 500 após deploy | `sudo bash deploy/scripts/corrigir-500.sh` |
| `Permission denied` em `storage/` ou `bootstrap/cache` | Ver [CICD.md](CICD.md) — permissões antes do composer |
| Menu lateral não abre / links não clicam | `npm run build` na VM + `sudo bash deploy/scripts/atualizar.sh` |
| Job Deploy *Queued* | Runner offline — `cd /home/jose/actions-runner && sudo ./svc.sh status` → `start` |
| `sudo: a password is required` / `A terminal is required to authenticate` | Atualizar `/etc/sudoers.d/erp-deploy` — ver [CICD.md](CICD.md) (`/usr/bin/bash` + caminho do script) |
| `Permission denied (os error 13)` no deploy | `sudo bash /var/www/ERP-Acesso/deploy/scripts/deploy-from-git.sh` — não precisa de `chmod +x` |
| `bad interpreter` / `$'\r'` em scripts `.sh` | [CICD.md](CICD.md) — `core.autocrlf false` no Windows; repo já usa `.gitattributes` com `*.sh eol=lf` |
| IP mudou (curl não responde) | `hostname -I` na VM; atualizar Nginx e `APP_URL` |

### Erro 500 — `PailServiceProvider` not found

Cache `bootstrap/cache/packages.php` gerado com pacotes de dev. Na VM:

```bash
cd /var/www/ERP-Acesso
sudo bash deploy/scripts/corrigir-500.sh
```

O `atualizar.sh` já limpa esse cache antes de recriar.

## Git remoto

```bash
git remote set-url origin https://github.com/Agu1lar/ERP-Automatizado.git
git push -u origin main
```

Repositório anterior (legado): `https://github.com/Agu1lar/ERP-AcessoEquipamentos.git`
