# Produção — Gestão Acesso (P7)

Runbook mínimo antes de colocar o sistema em operação real.

> **Passo a passo completo (deploy + Asaas + fiscal):** [GO_LIVE.md](GO_LIVE.md)

## Health check

| Endpoint | Uso |
|----------|-----|
| `GET /up` | Laravel built-in (processo vivo) |
| `GET /health` | DB + cache — use no load balancer / monitoramento |

Resposta saudável (`200`):

```json
{"status":"healthy","checks":{"app":"ok","database":"ok","cache":"ok","queue":"database"},"timestamp":"..."}
```

Configure alerta se `/health` retornar `503` por mais de 2 minutos.

## Checklist obrigatório

- [ ] PostgreSQL 15+ provisionado (não usar SQLite em produção)
- [ ] `.env` com `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `php artisan migrate --force` executado
- [ ] `npm run build` — assets compilados em `public/build`
- [ ] Supervisor rodando `queue:work` (QR Code e jobs futuros)
- [ ] Cron `schedule:run` a cada minuto
- [ ] Backup automático diário (`deploy/scripts/backup.sh`)
- [ ] HTTPS via Nginx/Caddy + certificado válido
- [ ] CI verde em SQLite **e** PostgreSQL (GitHub Actions)

## PostgreSQL

```bash
sudo -u postgres psql
CREATE USER linha_leve WITH PASSWORD 'senha_forte';
CREATE DATABASE linha_leve OWNER linha_leve;
GRANT ALL PRIVILEGES ON DATABASE linha_leve TO linha_leve;
```

`.env` de produção:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=linha_leve
DB_USERNAME=linha_leve
DB_PASSWORD=senha_forte

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

## Fila (Supervisor)

1. Copie `deploy/supervisor/laravel-worker.conf` para `/etc/supervisor/conf.d/`
2. Ajuste `command` e `stdout_logfile` com o caminho real do projeto
3. `sudo supervisorctl reread && sudo supervisorctl update`
4. Verifique: `sudo supervisorctl status`

Sem worker supervisionado, QR Codes ficam pendentes e jobs de e-mail/preventiva não executam.

## Fila `database` — monitoramento

Com `QUEUE_CONNECTION=database`, jobs ficam na tabela `jobs`. Para até ~20 usuários isso é suficiente; ainda assim:

| Rotina | Comando / agendamento |
|--------|------------------------|
| Renovações de locação | Cron 06:30 — `rentals:process-billing-renewals` |
| QR Code (patrimônio) | `queue:work` via Supervisor |
| Limpeza de falhas | Semanal — `queue:prune-failed --hours=168` (já no scheduler) |

**Sinais de alerta**

- Tabela `jobs` com milhares de linhas `reserved_at` antigas → worker parado ou job travado.
- Pico de `failed_jobs` → investigar antes que retries saturem conexões PostgreSQL.
- Jobs pesados (PDF, QR em lote) → preferir fila dedicada ou `timeout`/`tries` explícitos no job.

Consultas úteis (PostgreSQL):

```sql
SELECT queue, COUNT(*) FROM jobs GROUP BY queue;
SELECT COUNT(*) FROM failed_jobs WHERE failed_at > NOW() - INTERVAL '7 days';
```

Reinicie o worker após deploy: `sudo supervisorctl restart laravel-worker:*`

## Alertas operacionais por e-mail

Job diário **07:45** — `notifications:operational-alerts`:

- Retornos de locação atrasados
- OS com previsão vencida
- Preventiva vencida por horímetro

Destinatários: usuários **ativos** com e-mail e permissão (`rentals.view` / `maintenance.view`). Opcional: `OPERATIONAL_ALERTS_EXTRA_RECIPIENTS` no `.env`.

Configure SMTP real em produção (`MAIL_MAILER=smtp`, etc.). Em dev, `MAIL_MAILER=log` grava em `storage/logs/laravel.log`.

Teste manual:

```bash
php artisan notifications:operational-alerts --dry-run
php artisan notifications:operational-alerts
```

## Multi-empresa (performance)

Tabelas com `operating_company_id` possuem índices compostos `(operating_company_id, status)` (ou colunas equivalentes) para listagens e dashboards não degradarem com volume.

O escopo global (`BelongsToOperatingCompany`) aplica o filtro em toda query — o índice composto evita sequential scan. Após restore de backup antigo, rode `php artisan migrate` para garantir índices atuais.

## Cron (agendador)

Instale `deploy/cron/laravel-scheduler` no crontab do usuário que executa o PHP (geralmente `www-data`).

Necessário para P2 (preventiva vencida) e notificações futuras.

## Deploy

```bash
cd /var/www/linha-leve
git pull origin main
./deploy/scripts/deploy.sh
```

O script: `composer install --no-dev`, `npm run build`, `migrate --force`, cache de config/rota/view, reinicia worker.

## Backup

```bash
export PGPASSWORD='senha_forte'
export DB_DATABASE=linha_leve
export DB_USERNAME=linha_leve
./deploy/scripts/backup.sh /var/backups/linha-leve
```

Agende no cron (ex.: 02:00 diário):

```cron
0 2 * * * PGPASSWORD='...' /var/www/linha-leve/deploy/scripts/backup.sh
```

Retenção padrão: 30 dias (`RETENTION_DAYS`).

### Restaurar

```bash
pg_restore -h 127.0.0.1 -U linha_leve -d linha_leve -c database.dump
tar -xzf storage-app.tar.gz -C /var/www/linha-leve/storage/
```

## Nginx (referência)

```nginx
server {
    listen 443 ssl http2;
    server_name erp.acesso.local;
    root /var/www/linha-leve/public;

    index index.php;
    client_max_body_size 32M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Testes antes do deploy

```bash
# Local (SQLite)
php artisan test

# Local com PostgreSQL (Docker ou instalado)
php artisan test --configuration=phpunit.pgsql.xml
```

A CI no GitHub executa ambos em todo push para `main`.

## Testes de integração críticos

| Arquivo | O que valida |
|---------|----------------|
| `tests/Feature/Integration/RentalMaintenanceIntegrationTest.php` | Retorno → inspeção → OS automática |
| `tests/Feature/Integration/RentalReservationConcurrencyTest.php` | Dupla reserva bloqueada (app + índice único) |

## Por que PostgreSQL na CI?

SQLite e PostgreSQL divergem em `decimal`, constraints parciais, transações e tipos. Bugs que passam só em SQLite aparecem em produção. A job `test-pgsql` no GitHub Actions evita essa bomba-relógio.

## Health check

Laravel expõe `/up` — configure monitoramento externo (UptimeRobot, etc.) apontando para essa rota.
