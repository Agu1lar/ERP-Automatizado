# Roteiro de testes de interface — Gestão Acesso

Checklist manual para validar **todos os módulos em produção** após deploy ou mudança grande. Complementa os testes automatizados (`SmokeRoutesTest`, `LivewirePagesSmokeTest`).

**Tempo estimado:** 2–3 horas (completo) · 45 min (smoke rápido — só itens marcados com ⚡)

---

## Antes de começar

| Item | Como verificar |
|------|----------------|
| Login | `admin@acesso.local` (ou usuário real) — senha alterada em produção |
| Empresa ativa | Seletor no topo mostra CNPJ correto (Acesso / Super Máquinas) |
| Flash de erro | Após cada ação, mensagem verde/vermelha no topo da tela |
| CI verde | GitHub Actions → workflow **Tests** passou no último commit |

### Smoke automatizado (desenvolvedor)

```bash
php artisan test --filter=SmokeRoutesTest
php artisan test --filter=LivewirePagesSmokeTest
```

---

## 0. Autenticação e navegação ⚡

- [ ] Acessar `/` redireciona para `/dashboard`
- [ ] Login com credencial válida abre o painel
- [ ] Logout encerra a sessão
- [ ] Menu lateral: 6 seções (Dashboard, Comercial, Logística, Estoque, Financeiro, Configurações)
- [ ] Troca de empresa operacional no topo recarrega dados coerentes
- [ ] Busca global (topo): digitar código de patrimônio ou nome de cliente → resultados
- [ ] Abas multitarefa: abrir 2 fichas e alternar sem perder contexto

---

## 1. Dashboard e relatórios

### 1.1 Painel principal ⚡

- [ ] `/dashboard` carrega cards (frota, locações, manutenção, financeiro)
- [ ] Links dos cards abrem telas corretas
- [ ] Badge de retornos atrasados (se houver) leva a Locações

### 1.2 Relatórios (perfil Gestor/Admin)

- [ ] **Relatório comercial** — lista e filtros; exportar CSV
- [ ] **Análise financeira** — gráficos/tabelas carregam
- [ ] **Indicadores de frota** — taxas por categoria
- [ ] **Custo OS vs faturamento** — sem erro 500

---

## 2. Comercial

### 2.1 Locações ⚡

- [ ] Lista `/locacoes` abre; abas (lista, painel locados) funcionam
- [ ] Criar **reserva**: cliente + patrimônio disponível → status Reservado
- [ ] Abrir ficha da locação `/locacoes/{id}`
- [ ] Fluxo **saída** com checklist → patrimônio Locado
- [ ] Fluxo **retorno** → inspeção → concluir ou enviar para manutenção
- [ ] Exportar painel locados (CSV)
- [ ] PDF resumo e **contrato PDF** baixam

### 2.2 Orçamentos

- [ ] Lista `/orcamentos` abre
- [ ] Criar orçamento com validade
- [ ] Converter orçamento em reserva

### 2.3 CRM

- [ ] **Pipeline** — mover oportunidade entre estágios
- [ ] **Campanha inativos** — listar clientes sem locação recente
- [ ] **Mensagens** — fila de outbound (driver log/link em dev)

### 2.4 Cadastros comerciais ⚡

| Tela | URL | Testar |
|------|-----|--------|
| Clientes | `/clientes` | Criar, editar ficha, limite de crédito |
| Pessoas | `/pessoas` | Criar, vincular empresa, filtrar |
| Empresas CRM | `/empresas` | Criar, contatos/e-mails, **Arquivar** registro de teste |
| Tabela de preços | `/frota/precos` | Preço por modelo/categoria |

**Arquivamento (cadastros):**

- [ ] Marcar **Ver arquivados** → lista só excluídos lógicos
- [ ] **Restaurar** dentro de 30 dias
- [ ] Tentar arquivar empresa **com pessoas vinculadas** → mensagem de erro (não arquiva)
- [ ] Tentar arquivar patrimônio **locado** → mensagem de erro

---

## 3. Logística

### 3.1 Operação do dia ⚡

- [ ] **Lista do dia** `/logistica/lista-do-dia` — locações do dia
- [ ] **Mapa de obras** `/logistica/mapa-obras` — pins ou lista (sem 500)
- [ ] **Motoristas e veículos** — cadastrar, editar, arquivar motorista de teste
- [ ] **Pátios** — criar pátio; tentar arquivar pátio **com patrimônios** → erro

### 3.2 Romaneio (se houver dados)

- [ ] Gerar romaneio na lista do dia
- [ ] Abrir ficha do romaneio
- [ ] Comprovante de parada (se implementado na rota)

---

## 4. Estoque / Frota / Manutenção

### 4.1 Patrimônios ⚡

- [ ] Lista `/patrimonios` — busca e filtros
- [ ] Criar patrimônio → status Disponível
- [ ] Ficha `/patrimonios/{id}` — inline edit, anexos, histórico status
- [ ] QR Code / PDF etiqueta
- [ ] Modo pátio: `/patio/{codigo}` no celular (checklist)

### 4.2 Categorias e modelos

- [ ] `/frota/categorias` — CRUD; arquivar categoria **sem modelos**
- [ ] `/frota/modelos` — CRUD; arquivar modelo **sem patrimônios**
- [ ] Ficha categoria → patrimônios da categoria

### 4.3 Manutenção ⚡

- [ ] **OS** `/manutencao` — lista, filtros, criar OS
- [ ] Ficha OS — peças, horas, mudança de status
- [ ] **Catálogo de peças** — criar peça; arquivar só com estoque zero
- [ ] **Pedidos de compra** — listar (banner estoque baixo sem erro 500)
- [ ] **Preventiva** — regras por modelo; job diário documentado na tela
- [ ] PDF da OS baixa

---

## 5. Financeiro

### 5.1 Recebíveis ⚡

- [ ] **Títulos a receber** — lista, filtros, baixa manual
- [ ] **A faturar** — pendências de ciclo; autorizar e gerar fatura
- [ ] **Inadimplência** — aging por faixa
- [ ] **Fluxo de caixa** — projeção por vencimento

### 5.2 Pagar e fiscal

- [ ] **Contas a pagar** — criar título, baixar
- [ ] **Fiscal (ERP)** — documentos pendentes; envio ao Omie (se configurado)
- [ ] Exportação contábil `/financeiro/exportar-contabil` (CSV/Omie/Bling)

### 5.3 Integrações (produção)

- [ ] Asaas sandbox/produção — gerar PIX/boleto em título de teste
- [ ] Webhook Asaas registrado e respondendo

---

## 6. Configurações / Admin

### 6.1 Usuários e empresas ⚡

- [ ] **Usuários** — criar, perfil (Admin, Comercial…), desativar
- [ ] Não arquivar **o próprio** usuário logado
- [ ] **Empresas operacionais (CNPJ)** — editar dados e logo
- [ ] Não arquivar a **última** empresa operacional

### 6.2 Auditoria e copiloto

- [ ] **Auditoria** — registros de alterações recentes
- [ ] **Copiloto logs** — histórico de comandos
- [ ] **Copiloto métricas** — resumo por período
- [ ] Painel flutuante do copiloto: consulta simples (“quantos locados?”) + confirmação em ação destrutiva

---

## 7. Perfis de permissão (amostra)

Repetir smoke ⚡ com usuários não-admin:

| Perfil | Deve acessar | Deve ser bloqueado |
|--------|--------------|-------------------|
| Comercial | Locações, clientes, orçamentos | Admin → usuários |
| Operação | Locações, logística, patrimônios (view) | Financeiro export |
| Manutenção | OS, catálogo peças | Admin |
| Gestor | Relatórios + financeiro view | — |

---

## 8. Pós-deploy na VM

```bash
# Health
curl -s http://192.168.5.29/health | jq .

# Deploy manual se necessário
sudo bash /var/www/ERP-Acesso/deploy/scripts/deploy-from-git.sh

# Logs de erro
sudo tail -50 /var/www/ERP-Acesso/storage/logs/laravel.log
```

- [ ] `/health` retorna `"status": "healthy"`
- [ ] Último deploy CI verde
- [ ] Migration `add_soft_deletes_to_archivable_models` aplicada
- [ ] `archive:purge` no schedule (`php artisan schedule:list`)

---

## Registro de execução

| Data | Executor | Ambiente | Resultado | Observações |
|------|----------|----------|-----------|-------------|
| | | VM / local | ☐ OK ☐ Falhas | |

**Falhas encontradas:** anotar URL, passo, mensagem de erro e print do `laravel.log`.

---

*Documento alinhado ao menu de junho/2026. Atualize quando novas telas forem adicionadas.*
