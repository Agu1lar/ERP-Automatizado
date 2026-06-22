# Deploy — Gestão Acesso

Runbook para a VM Linux (`/var/www/ERP-Acesso`, IP interno `192.168.5.6`).

## Primeira instalação (uma vez)

```bash
cd /var/www/ERP-Acesso
cp deploy/env/.env.production.example .env
nano .env   # APP_KEY, DB_*, senhas
php artisan key:generate
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force --seed
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache

# Nginx
sudo cp deploy/nginx/erp-acesso.conf /etc/nginx/sites-available/erp-acesso.conf
sudo ln -sf /etc/nginx/sites-available/erp-acesso.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# Fila + cron + backup diário
sudo APP_PATH=/var/www/ERP-Acesso bash deploy/scripts/instalar-servicos.sh
```

## Atualizar código

**CI/CD (recomendado):** push no `main` → testes no GitHub → deploy automático na VM.  
Guia completo: **[deploy/CICD.md](CICD.md)**

**Na VM** (manual ou após git pull):

```bash
cd /var/www/ERP-Acesso && sudo bash deploy/scripts/atualizar.sh
```

**No Windows** (envia arquivos + atualiza na VM):

```powershell
cd C:\Users\User\Documents\ERP_Acesso
powershell -ExecutionPolicy Bypass -File deploy\windows\atualizar.ps1
```

Ou dê duplo clique em `atualizar-erp.bat`.

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

Prova que o dump restaura sem tocar no banco de produção:

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

Se aparecer erro como `deploy/scripts/backup.sh: line X: $'\r': command not found` ou `set: pipefail`,
é sinal de que os `.sh` vieram com fim de linha **Windows (CRLF)**.

Corrija na VM:

```bash
cd /var/www/ERP-Acesso
sudo sed -i 's/\r$//' deploy/scripts/*.sh
sudo chmod +x deploy/scripts/*.sh
```

Depois rode novamente o backup:

```bash
sudo bash deploy/scripts/backup.sh
```

Em validação recente na sua VM, esse fluxo gerou backup e o `testar-restore.sh` completou com sucesso.

Também foi validado que o `backup.sh` atual lê as credenciais do `.env` (evitando tentativa de logar com usuário antigo).

### Erro 500 — `PailServiceProvider` not found

O cache `bootstrap/cache/packages.php` foi gerado com pacotes de **dev** (Pail, Breeze). Em produção (`composer install --no-dev`) eles não existem.

Na VM:

```bash
cd /var/www/ERP-Acesso
sudo rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php
sudo -u www-data php artisan package:discover
sudo -u www-data php artisan config:cache
sudo systemctl reload php8.5-fpm
```

O `atualizar.sh` já limpa esse cache antes de recriar.

## Git remoto

```bash
git remote set-url origin https://github.com/Agu1lar/ERP-Automatizado.git
git push -u origin main
```

Repositório anterior (legado): `https://github.com/Agu1lar/ERP-AcessoEquipamentos.git`
