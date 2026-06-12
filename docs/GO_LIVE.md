# Go-live — passo a passo

Guia operacional para colocar o **Gestão Acesso** em produção, configurar **Asaas**, validar a **transição fiscal** e manter a **CI** verde.

Relacionado: [PRODUCTION.md](PRODUCTION.md) · [TRANSICAO_FISCAL.md](TRANSICAO_FISCAL.md)

---

## Passo 0 — O que fazer no seu computador (antes do servidor)

### 0.1 Commit e push

No diretório do projeto:

```bash
git status
git add .
git commit -m "Preparar go-live: testes CI, docs e menu atualizado"
git push origin main
```

Confirme que o workflow **Tests** no GitHub ficou verde (SQLite + PostgreSQL).

### 0.2 Testes locais

```bash
php artisan test
php artisan test --configuration=phpunit.pgsql.xml   # se tiver PostgreSQL local
```

---

## Passo 1 — Deploy no servidor real

### 1.1 Requisitos do servidor

| Item | Mínimo |
|------|--------|
| SO | Ubuntu 22.04+ ou Debian 12+ |
| PHP | 8.3 com extensões: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`, `gd` |
| Node | 18+ (só para build no deploy) |
| PostgreSQL | 15+ |
| Web | Nginx ou Caddy + PHP-FPM |
| Processos | Supervisor (fila) + cron (agendador) |

### 1.2 Provisionar PostgreSQL

```bash
sudo -u postgres psql
```

```sql
CREATE USER linha_leve WITH PASSWORD 'SENHA_FORTE_AQUI';
CREATE DATABASE linha_leve OWNER linha_leve;
GRANT ALL PRIVILEGES ON DATABASE linha_leve TO linha_leve;
\q
```

### 1.3 Clonar o projeto

```bash
sudo mkdir -p /var/www
sudo chown $USER:www-data /var/www
cd /var/www
git clone https://github.com/SEU_USUARIO/SEU_REPO.git linha-leve
cd linha-leve
```

### 1.4 Arquivo `.env` de produção

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Valores essenciais:

```env
APP_NAME="Gestão Acesso"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.suaempresa.com.br

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=linha_leve
DB_USERNAME=linha_leve
DB_PASSWORD=SENHA_FORTE_AQUI

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.seuprovedor.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@suaempresa.com.br
MAIL_FROM_NAME="${APP_NAME}"

# Geocoding — use Google em produção se Nominatim for instável
GEOCODING_ENABLED=true
GEOCODING_DRIVER=google
GOOGLE_GEOCODING_API_KEY=sua_chave

# Pagamento — comece em sandbox (Passo 2)
PAYMENT_GATEWAY_DRIVER=asaas
ASAAS_ENVIRONMENT=sandbox
ASAAS_API_KEY=
ASAAS_WEBHOOK_TOKEN=

# Fiscal — ponte Omie (Passo 3)
FISCAL_BRIDGE_ENABLED=true
FISCAL_DEFAULT_ERP=omie
OMIE_APP_KEY=
OMIE_APP_SECRET=

# Exportação contábil
ACCOUNTING_OMIE_CATEGORIA=1.01.01
ACCOUNTING_OMIE_CONTA_CORRENTE=1
ACCOUNTING_BLING_CATEGORIA=Receitas de locação
ACCOUNTING_BLING_PORTADOR=Caixa
```

### 1.5 Instalar e migrar

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force --seed
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

**Importante:** troque a senha do `admin@acesso.local` após o primeiro login.

### 1.6 Nginx (referência)

Ver bloco completo em [PRODUCTION.md](PRODUCTION.md#nginx-referência). Aponte `root` para `/var/www/linha-leve/public` e configure HTTPS (Let's Encrypt / Certbot).

### 1.7 Supervisor — fila de jobs

```bash
sudo cp deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
```

Edite o arquivo e ajuste o caminho do projeto e do PHP:

```ini
command=php /var/www/linha-leve/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
stdout_logfile=/var/www/linha-leve/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

### 1.8 Cron — agendador Laravel

```bash
sudo crontab -u www-data -e
```

Adicione:

```cron
* * * * * cd /var/www/linha-leve && php artisan schedule:run >> /dev/null 2>&1
```

Jobs agendados automaticamente:

| Horário | Comando |
|---------|---------|
| 06:30 | Renovações de faturamento |
| 07:45 | Alertas operacionais por e-mail |
| 08:00 | Lembretes de follow-up CRM |
| A cada 5 min | Processar mensagens CRM pendentes |

### 1.9 Backup diário

```bash
chmod +x deploy/scripts/backup.sh
```

Cron (ex.: 02:00):

```cron
0 2 * * * PGPASSWORD='SENHA_FORTE' DB_DATABASE=linha_leve DB_USERNAME=linha_leve /var/www/linha-leve/deploy/scripts/backup.sh /var/backups/linha-leve
```

Teste restore uma vez seguindo [PRODUCTION.md](PRODUCTION.md#restaurar).

### 1.10 Validar saúde

```bash
curl -s https://erp.suaempresa.com.br/health | jq
```

Esperado: `"status":"healthy"` com `database` e `cache` ok.

### 1.11 Deploys seguintes

```bash
cd /var/www/linha-leve
git pull origin main
./deploy/scripts/deploy.sh
```

---

## Passo 2 — Asaas (sandbox → produção)

O sistema gera cobranças PIX/boleto via `ReceivablePaymentService`. Em dev o driver padrão é `mock`; em produção use `asaas`.

### 2.1 Criar conta Asaas

1. Acesse [https://www.asaas.com](https://www.asaas.com) e crie conta **Sandbox** primeiro.
2. No painel: **Integrações → API** → gere a **API Key** do sandbox.

### 2.2 Configurar sandbox no servidor

No `.env`:

```env
PAYMENT_GATEWAY_DRIVER=asaas
ASAAS_ENVIRONMENT=sandbox
ASAAS_API_KEY=sua_api_key_sandbox
ASAAS_WEBHOOK_TOKEN=um_token_secreto_que_voce_inventar
```

```bash
php artisan config:cache
```

### 2.3 Configurar webhook no Asaas

No painel Asaas (sandbox):

1. **Integrações → Webhooks**
2. URL: `https://erp.suaempresa.com.br/webhooks/asaas`
3. Eventos: marque **PAYMENT_RECEIVED** / **PAYMENT_CONFIRMED** (conforme disponível)
4. Token de autenticação: o mesmo valor de `ASAAS_WEBHOOK_TOKEN`

A rota está em `routes/web.php` → `POST /webhooks/asaas`.

### 2.4 Validar um ciclo PIX no sandbox

1. Faça login como Admin ou Gestor.
2. Crie uma locação de teste com saída → gere um **título a receber**.
3. Vá em **Financeiro → Títulos a receber**.
4. No título aberto, clique **Gerar PIX/boleto** → escolha PIX.
5. Confira se aparece QR Code / link (sandbox).
6. No painel Asaas sandbox, simule o pagamento da cobrança.
7. Verifique se o webhook marcou o título como **pago** no sistema.

Teste automatizado de referência: `tests/Feature/PaymentGatewayTest.php`.

### 2.5 Ir para produção Asaas

1. Complete o cadastro comercial/KYC no Asaas.
2. Gere API Key de **produção**.
3. Atualize `.env`:

```env
ASAAS_ENVIRONMENT=production
ASAAS_API_KEY=api_key_producao
ASAAS_WEBHOOK_TOKEN=token_producao_diferente
```

4. Recrie o webhook apontando para a mesma URL em produção.
5. `php artisan config:cache`
6. Repita o ciclo com **um título real de valor baixo** antes de liberar para o financeiro.

### 2.6 Checklist Asaas

- [ ] Cliente do título tem CPF/CNPJ válido (obrigatório para Asaas)
- [ ] Webhook retorna 200 (veja `storage/logs/laravel.log` se falhar)
- [ ] Baixa automática reflete data e forma de pagamento
- [ ] Equipe sabe que baixa manual continua disponível se o webhook falhar

---

## Passo 3 — Transição fiscal (1 mês em paralelo com Sisloc)

O Gestão Acesso **não emite NF-e**. Ele exporta títulos para **Omie** ou **Bling** (CSV) e mantém registro na **ponte fiscal** (`Financeiro → Fiscal ERP`).

### 3.1 Semana 1 — Cadastro e teste único

1. Confira que todos os clientes têm **CPF/CNPJ idêntico** ao Omie/Bling/Sisloc.
2. No Omie/Bling, crie categoria **Receitas de locação** e portador/conta.
3. Ajuste `.env` com `ACCOUNTING_OMIE_*` ou `ACCOUNTING_BLING_*`.
4. Gere **uma locação de teste** → saída → título aberto.
5. **Financeiro → Títulos → Exportar contábil** → formato Omie ou Bling.
6. Importe no ERP fiscal e emita **uma NF de teste**.
7. Confira valor e vencimento lado a lado.

### 3.2 Semanas 2–4 — Operação paralela

Siga o fluxo diário em [TRANSICAO_FISCAL.md](TRANSICAO_FISCAL.md#fluxo-recomendado-financeiro):

```
Manhã: fila a faturar no Gestão Acesso
  → Exportar contábil (só títulos ainda não exportados)
  → Importar no Omie/Bling
  → Emitir NF no ERP fiscal
  → Registrar pagamento no Gestão Acesso (ou via webhook Asaas)
Tarde: Sisloc em paralelo só para CONFERÊNCIA (não duplicar emissão)
```

Use o checklist completo em [TRANSICAO_FISCAL.md](TRANSICAO_FISCAL.md#checklist--validar-em-paralelo-sisloc--export).

### 3.3 Ponte fiscal Omie (opcional, API)

Se tiver `OMIE_APP_KEY` e `OMIE_APP_SECRET`:

1. **Financeiro → Fiscal (ERP)** lista documentos pendentes.
2. Botão **Enviar pendentes ao Omie** registra no ERP.
3. A emissão da nota ainda ocorre **no Omie** — o sistema só faz a ponte.

### 3.4 Critério de sucesso (após ~30 dias)

- [ ] Nenhuma divergência material de valor entre sistemas
- [ ] Financeiro assina go/no-go
- [ ] Contador validou layout de exportação
- [ ] Sisloc fiscal pode ficar read-only (manter backup 90 dias)

---

## Passo 4 — CI e testes (já corrigido no código)

Configuração em `phpunit.xml` e `phpunit.pgsql.xml`:

| Variável | Valor em testes | Motivo |
|----------|-----------------|--------|
| `GEOCODING_ENABLED` | `false` | Evita chamadas HTTP a Nominatim na CI |
| `PAYMENT_GATEWAY_DRIVER` | `mock` | Testes de pagamento sem API externa |
| `AGENT_LLM_ENABLED` | `false` | Testes do copiloto sem LLM real |

Testes específicos corrigidos:

- Manifest do agente na versão **1.4**
- Scan QR: operadores → `/patio/{codigo}`; usuários só leitura → ficha do patrimônio

`GeocodingTest` liga geocoding explicitamente no `setUp` e usa `Http::fake` — continua testando o fluxo com mock.

---

## Passo 5 — Checklist final go-live

| # | Item | Responsável |
|---|------|-------------|
| 1 | CI verde no GitHub | Dev |
| 2 | Servidor com HTTPS + `/health` ok | Dev / infra |
| 3 | Supervisor + cron ativos | Dev / infra |
| 4 | Backup testado (restore) | Dev / infra |
| 5 | Senha admin trocada | Gestor |
| 6 | SMTP enviando alertas 07:45 | Dev |
| 7 | Asaas sandbox validado | Financeiro |
| 8 | Asaas produção (valor baixo) | Financeiro |
| 9 | Export Omie/Bling sem erro de coluna | Financeiro |
| 10 | 1 mês paralelo Sisloc sem divergência | Financeiro + contador |
| 11 | Treinamento equipe (menu: Dashboard, Comercial, Logística, Estoque, Financeiro, Configurações) | Gestor |

---

*Última revisão: junho/2026*
