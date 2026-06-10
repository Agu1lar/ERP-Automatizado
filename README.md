# Linha Leve — Sistema de Gerenciamento

ERP interno para controle de frota, patrimônios, clientes e auditoria operacional da linha leve.

## Stack

- **Backend:** Laravel 13
- **Frontend:** Livewire 3 + Blade + Tailwind CSS
- **Banco:** PostgreSQL (recomendado) ou SQLite (dev)
- **Auth:** Laravel Breeze + Spatie Permission (RBAC)
- **Fila:** Laravel Queue (driver `database`)

## Requisitos

- PHP 8.2+ (extensões: pdo_pgsql, pgsql, mbstring, openssl, curl, fileinfo, zip)
- Composer 2.x
- Node.js 18+ e npm
- PostgreSQL 15+ (produção) ou SQLite (desenvolvimento)

## Instalação

```bash
# 1. Dependências PHP
composer install

# 2. Ambiente
cp .env.example .env
php artisan key:generate

# 3. Banco — PostgreSQL: criar database "linha_leve" e configurar .env
# Ou SQLite: descomentar DB_CONNECTION=sqlite no .env

php artisan migrate --seed

# 4. Frontend
npm install && npm run build

# 5. Storage
php artisan storage:link
```

## Executar

```bash
php artisan serve
```

Acesse: http://localhost:8000

### Credenciais iniciais

| Campo | Valor |
|-------|-------|
| E-mail | `admin@acesso.local` |
| Senha | `Acesso@2026` |

**Troque a senha após o primeiro login.**

### Reiniciar o sistema (zerar e recarregar demo)

Use quando quiser apagar tudo e voltar ao ambiente de demonstração com muitos clientes, patrimônios e locações:

```bash
# Apaga tabelas, roda migrations e seeds (inclui dados demo grandes)
php artisan migrate:fresh --seed

# Recompila CSS/JS (necessário se abas ou painel não aparecerem)
npm run build

# Sobe o servidor
php artisan serve
```

Depois acesse http://localhost:8000 e entre com `admin@acesso.local` / `Acesso@2026`.

**Onde ver o painel de locados:** menu **Locações** → aba **Painel locados** (URL: `/locacoes?aba=painel`). Não é um item separado no menu — fica na primeira aba da tela de Locações.

### Dados demo incluídos no seed

| Item | Quantidade aproximada |
|------|----------------------|
| Clientes | 30 |
| Patrimônios | ~75 (5 categorias × 3 modelos × 5 unidades) |
| Locações locadas (painel) | 22 |
| Reservas / inspeção / histórico | 6 + 5 + 35 |
| Peças no catálogo | 8 |

### Outros usuários demo (mesma senha `Acesso@2026`)

| Perfil | E-mail |
|--------|--------|
| Gestor | `gestor@acesso.local` |
| Comercial | `comercial@acesso.local` |
| Operação | `operacao@acesso.local` |
| Manutenção | `manutencao@acesso.local` |

## Estrutura modular

```
app/
  Domain/          # Models por domínio (Fleet, Customer, Audit, Attachment)
  Enums/           # AssetStatus, UserRole, AuditAction
  Services/        # AssetStatusService, AuditService, AttachmentService
  Livewire/        # Componentes de UI
  Policies/        # Permissões granulares
  Observers/       # Auditoria automática
```

## Módulos (Fase 1)

- Login individual com perfis (Admin, Gestor, Comercial, Operação, Manutenção)
- Cadastro de categorias, modelos e patrimônios
- Máquina de estados de equipamentos com histórico
- Ficha individual do patrimônio com anexos (PDF, fotos, docs)
- Clientes com validação CPF/CNPJ
- Administração de usuários
- Logs de auditoria
- Dashboard com resumo por status

## Storage e backup

- Anexos: `storage/app/assets/{id}/`
- Backups recomendados: `pg_dump` + cópia de `storage/`

## Testes

```bash
php artisan test
```

## Fase 2 — Frota e status (implementada)

- **QR Code** por patrimônio (job assíncrono, reprocessamento manual)
- **Rota de scan** `/patrimonios/scan/{codigo}` → abre a ficha
- **Movimentação de localização** com histórico na timeline
- **Timeline unificada** (status + localização)
- **Impressão de ficha** com QR Code
- **Busca inteligente** em patrimônios (acentos/case insensitive)

### Processar fila (QR Code)

```bash
php artisan queue:work --queue=default
```

## Fase 3 — Locação (implementada)

- **Reserva** — vincula patrimônio disponível a um cliente (`Disponível` → `Reservado`)
- **Saída** — checklist de saída + registro de entrega (`Reservado` → `Locado`)
- **Retorno** — checklist de retorno (`Locado` → `Em inspeção`)
- **Conclusão** — inspeção final (`Em inspeção` → `Disponível` ou `Em manutenção`)
- **Cancelamento** de reserva antes da saída
- **Dashboard** — saídas pendentes e retornos previstos para hoje
- Menu **Locações** (`/locacoes`)

### Fluxo operacional

```
Reservar → Registrar saída (checklist) → Registrar retorno (checklist) → Concluir inspeção
```

### Permissões

| Ação | Perfis |
|------|--------|
| Ver locações | Todos com `rentals.view` |
| Criar reserva / cancelar | Comercial, Gestor, Admin |
| Saída, retorno, inspeção | Operação, Gestor, Admin |

## Fase 4 — Manutenção (implementada)

Lógica operacional de OS — **sem template PDF** (formato do documento virá na Fase 5).

- **Abertura de OS** — vincula patrimônio, tipo, prioridade, flag impeditiva
- **Fluxo:** Aberta → Em execução → Aguardando peça → Concluída (ou cancelamento na abertura)
- **Peças** — descrição, código, quantidade, valor unitário
- **Horas** — data, técnico, horas trabalhadas, atividade
- **OS impeditiva** — bloqueia patrimônio de voltar a `Disponível` enquanto aberta
- **Integração locação** — retorno com "enviar para manutenção" abre OS automaticamente
- **Dashboard** — OS atrasadas (previsão vencida)
- Menu **Manutenção** (`/manutencao`)

### Permissões

| Ação | Perfis |
|------|--------|
| Ver OS | Comercial, Operação, Manutenção, Gestor, Admin |
| Abrir OS, peças, horas, dados técnicos | Manutenção, Admin |
| Iniciar, concluir, aguardar peça | Manutenção, Gestor, Admin |

## Fase 5 — Documentos PDF (implementada)

Geração de PDF via **DomPDF** com templates Blade editáveis.

| Documento | Rota | Template |
|-----------|------|----------|
| Ordem de Serviço | `/manutencao/{os}/pdf` | `resources/views/documents/maintenance-order.blade.php` |
| Resumo de locação | `/locacoes/{locacao}/pdf` | `resources/views/documents/rental-summary.blade.php` |
| Ficha do patrimônio | `/patrimonios/{id}/pdf` | `resources/views/documents/asset-sheet.blade.php` |

### Personalizar layout da OS

1. Edite `resources/views/documents/maintenance-order.blade.php`
2. Estilos compartilhados em `resources/views/documents/partials/styles.blade.php`
3. Dados da empresa no `.env`:

```env
DOCUMENTS_COMPANY_NAME="Sua Empresa"
DOCUMENTS_COMPANY_DOCUMENT="CNPJ 00.000.000/0001-00"
DOCUMENTS_COMPANY_ADDRESS="Endereço completo"
DOCUMENTS_COMPANY_PHONE="(11) 0000-0000"
DOCUMENTS_COMPANY_EMAIL="contato@empresa.com"
DOCUMENTS_LOGO_PATH="stack/assets/logo.png"
```

Botão **Baixar PDF** nas fichas de patrimônio, locação e OS.

### Ficha com dados opcionais e alertas

Patrimônio e locação possuem **aba/seção Ficha** com campos editáveis (descrição, horímetro, série, dados do cliente, etc.).  
Você pode **salvar incompleto** a qualquer momento.

Quando faltar informação importante, a ficha exibe **! Ficha incompleta**:

| Contexto | Alerta exemplo |
|----------|----------------|
| Patrimônio | Descrição, horímetro ou série vazios |
| Cliente (locação) | Sem telefone **e** sem e-mail |
| Cliente | Endereço ou nome do contato vazios |
| Locação em uso | Horímetro de saída não registrado |
| Após retorno | Horímetro de retorno não registrado |

O PDF também mostra o aviso no topo quando a ficha estiver incompleta.

## Hierarquia de permissões e campos personalizados

| Nível | Perfis | Capacidades |
|-------|--------|-------------|
| **Administrador** | `admin` | Ver e criar tudo; desativar campos personalizados; gestão de usuários |
| **Gerencial** | `gestor` | Tudo do operacional + **criar campos** personalizados, abrir OS, auditoria |
| **Operacional** | `comercial`, `operacao`, `manutencao` | **Mesmo nível** — criar locação e cliente, operar OS e locações, preencher campos, gráficos |

### Campos personalizados

Disponíveis nas fichas de **patrimônio**, **locação** e **OS**:

- **Gestor** (e Admin) podem **criar** novos campos (ex.: "Valor do patrimônio") nas fichas de patrimônio, locação e OS
- **Comercial, Operação e Manutenção** veem e **preenchem** os campos existentes, mas **não criam** nem ocultam campos
- Gestor pode **ocultar** campos para a própria visualização (ícone 👁)
- Admin pode **desativar** campos globalmente
- Campos podem gerar alerta **!** quando vazios
- Valores aparecem nos **PDFs** gerados
- Toda ação é registrada em **Auditoria** (`/admin/auditoria`):
  - Gestor **cria** campo → log `CustomFieldDefinition` / `created`
  - Comercial **preenche** valor → log `Asset`, `Rental` ou `MaintenanceOrder` / `updated` com `custom_fields` no JSON
  - Gestor **oculta** campo → log `CustomFieldDefinition` / `updated`
  - Admin **desativa** campo → log `CustomFieldDefinition` / `updated`

### Gráficos (dashboard)

Usuários com permissão `dashboard.analytics` (todos os perfis operacionais, Gestor e Admin) veem barras de distribuição:

- Frota por status
- Locações por status
- OS por status

### Locação inteligente (operacional)

Ao criar uma locação em **Locações → Nova locação**:

1. **Cole ou digite** o código do patrimônio — o sistema busca e seleciona automaticamente
2. Exibe **prévia** do equipamento (modelo, série, horímetro, descrição)
3. **Preenche a ficha** com horímetro de saída e descrição vindos do patrimônio
4. Busca cliente por **nome ou CPF/CNPJ**, ou **cadastro rápido** sem sair da tela

### Resumo por perfil

| Perfil | Criar locação | Criar cliente | Operar OS/locação | Criar campos | Preencher campos | Gráficos |
|--------|---------------|---------------|-------------------|--------------|------------------|----------|
| Admin | Sim | Sim | Sim | Sim | Sim | Sim |
| Gestor | Sim | Sim | Sim | Sim | Sim | Sim |
| Comercial | Sim | Sim | Sim | Não | Sim | Sim |
| Operação | Sim | Sim | Sim | Não | Sim | Sim |
| Manutenção | Sim | Sim | Sim | Não | Sim | Sim |

## Fase 6 — PDF da OS (modelo MANUTENCAO)

Template oficial da ordem de serviço com **logo da empresa** e campos do formulário físico:

| Campo no documento | Origem no sistema |
|--------------------|-------------------|
| Logo | `DOCUMENTS_LOGO_PATH` no `.env` |
| Manutenção / Indenização | Tipo da OS (`Indenização` no cadastro) |
| EQUP / MARCA / PATRIMÔNIO / VOLTAGEM | Patrimônio + modelo |
| CLIENTE | Cliente da OS ou da locação vinculada |
| Tabela de peças | QUANT, código, descrição, código alternativo, valor |
| PARECER TÉCNICO | Campo dedicado (ou diagnóstico + solução) |
| caixa / orçado por / montado por | Assinaturas na ficha da OS |

Configure a logo:

```env
DOCUMENTS_LOGO_PATH="stack/assets/logo.png"
```

A logo padrão do projeto está em `stack/assets/logo.png` (ACESSO equipamentos).  
Também aceita caminhos em `public/` ou `storage/app/`.

## Fase 7 — Prioridade 1 (implementada)

### OS inteligente
- **Nova OS** com busca por código/série do patrimônio (como na locação)
- Prévia com marca, voltagem, horímetro, cliente da locação ativa e peças usadas recentemente

### Catálogo de peças
- Rota **Manutenção → Catálogo de peças** (`/manutencao/pecas`)
- Cadastro: código, alternativo, descrição, valor padrão
- **Autocomplete** ao adicionar peças na OS (gestor cadastra, todos usam na operação)

### PDFs com identidade ACESSO
- Cabeçalho unificado com logo + dados da empresa nos 3 documentos (OS, locação, patrimônio)
- Nome padrão: `ACESSO equipamentos`

## Fase 8 — Prioridade 2 (implementada)

### Relatório comercial por tipo de equipamento
- Rota **Relatórios** (`/relatorios/comercial`) — perfis com `dashboard.analytics`
- Agrupa faturamento por **modelo** (ex.: Bosch GBH 2-26) ou por **categoria** (ex.: Marteletes, Betoneiras)
- Não agrupa por patrimônio individual — várias betoneiras do mesmo modelo somam juntas
- Campo **Valor de faturamento (R$)** na ficha da locação (locações concluídas no período)

### Manutenção preventiva configurável
- Rota **Manutenção → Preventiva — regras** (`/manutencao/preventiva`) — gestor/admin
- Regra: **a cada Y horas** para o **modelo de equipamento X** (descrição definida pelo usuário)
- Vencimento calculado pelo horímetro atual vs. última preventiva concluída
- Botão **Abrir OS preventiva** na ficha do patrimônio quando vencida

### Histórico de manutenção no patrimônio
- Aba **Manutenção** na ficha do patrimônio
- Lista corretivas, preventivas e demais OS do patrimônio
- Exibe status das regras preventivas aplicáveis ao tipo de equipamento

### Alertas no dashboard
- Preventiva vencida (por horímetro)
- Fichas de patrimônio incompletas
- Link rápido para o relatório comercial

## Locações — painel operacional

Menu **Locações** → aba **Painel locados** (`/locacoes?aba=painel`):

- Lista o que está **locado** (padrão), com ordenação **crescente** pela previsão de retorno
- **Filtros:** categoria, cliente, valor mín./máx., escopo de status, busca livre
- **Ordenar por:** retorno, saída, faturamento, cliente, categoria, código, conclusão (↑ ou ↓)
- **Histórico do cliente:** selecione o cliente e marque *Ver histórico completo* para todas as locações dele (concluídas, canceladas, etc.)
- Clique no nome do cliente na tabela para filtrar rapidamente

A aba **Todas as locações** mantém a listagem geral e o fluxo de nova reserva.

## Categorias — visão por patrimônio

Em **Cadastros → Categorias**, cada categoria exibe contadores (disponíveis, locados, em manutenção).

Ao abrir uma categoria (`Ver patrimônios`), três colunas listam os patrimônios daquela categoria:

| Coluna | Status incluídos |
|--------|------------------|
| **Disponíveis** | Disponível, Reservado |
| **Locados** | Locado, Em inspeção, Manutenção em campo |
| **Em manutenção** | Em manutenção, Aguardando peça |

Cada patrimônio tem link **Abrir ficha** (e impressão rápida).

## Edição inline da ficha

Campos da ficha são **clicáveis** — não é preciso abrir aba separada nem clicar em "Salvar ficha":

| Onde | Quem edita |
|------|------------|
| **Patrimônio → Resumo** | Usuários com `records.edit` ou gestão de frota |
| **Locação** | Comercial, operação, manutenção (`records.edit` / operar locação) |
| **OS de manutenção** | Quem pode atualizar a OS aberta |
| **Campos personalizados** | Operacional preenche; gestor cria campos |

**Como usar:** clique no valor → edite → **Enter** ou clique fora para salvar · **Esc** cancela.

## Abas do sistema (multitarefa)

Barra de abas abaixo do menu principal para trabalhar em várias telas sem perder o contexto:

| Ação | Como fazer |
|------|------------|
| Abrir em nova aba | **Ctrl+clique** (ou **Cmd+clique** no Mac) em qualquer link interno |
| Abrir em nova aba | **Clique do meio** do mouse no link |
| Nova aba vazia | Botão **+** na barra de abas |
| Alternar entre telas | Clique na aba desejada |
| Fechar aba | **×** na aba (sempre permanece ao menos uma aberta) |

As abas ficam salvas no navegador (`localStorage`) e são limpas ao sair do sistema.

## Fase 9 — Operacional imediato (implementada)

### Local da obra na locação
- Campo **Local da obra** na ficha da locação e no formulário de nova reserva
- Preenchimento automático com o **endereço do cliente** ao selecioná-lo
- Na **saída** (checkout), o local da obra vira a **localização do patrimônio** (com histórico de movimentação)
- Ao **concluir a inspeção**, o patrimônio volta à localização anterior (pátio/depósito)
- Alerta na ficha quando o local da obra não estiver preenchido em reserva/locação ativa
- Coluna **Local obra** no painel de locados e no PDF da locação

### Retornos atrasados
- Alerta destacado no **dashboard** com link para o painel filtrado
- Filtro **Somente retornos atrasados** no painel de locações
- Linhas em vermelho e contagem de dias de atraso
- Badge no menu **Locações** quando houver atrasos

### Ficha do cliente
- Rota **Cadastros → Clientes** → clique no nome (`/clientes/{id}`)
- Dados editáveis inline (mesmo padrão das outras fichas)
- Locações ativas, histórico paginado, total faturado e OS recentes

### Exportação do relatório comercial
- Botão **Exportar CSV** em `/relatorios/comercial`
- Respeita período e agrupamento selecionados (UTF-8 com BOM para Excel)

### Painel de manutenção
- Menu **Manutenção** → aba **Painel operacional** (`/manutencao?aba=painel`)
- Colunas: Abertas, Em execução, Aguardando peça, Atrasadas
- Filtros por categoria, técnico, prioridade, tipo e somente atrasadas
- Badge no menu **Manutenção** quando houver OS atrasadas

## Fase 10 — Comercial e precificação (implementada)

### Tabela de preços
- Cadastro em **Cadastros → Tabela de preços** (`/frota/precos`)
- Diária, semanal e mensal por **modelo** ou **categoria** (modelo tem prioridade)
- Permissões: `pricing.view` (todos operacionais), `pricing.manage` (gestor/admin)

### Cálculo automático
- Na **reserva** (com previsão de retorno) e na **saída**, o sistema calcula dias faturados e valor
- Período automático escolhe o **menor valor total** entre diária/semanal/mensal disponíveis
- Campos na locação: `pricing_period`, `billed_days`, `valor_calculado`, `valor_faturamento`
- Estimativa na tela de nova reserva; breakdown na ficha da locação

### Prorrogação
- Botão **Prorrogar** em locações ativas — nova data + recálculo automático do valor

### Demo
- `LargeDemoSeeder` inclui preços por categoria e overrides em modelos Bosch GBH 2-24 e Honda EG 6500

---

## Roadmap — visão geral

**Público-alvo:** locadora de equipamentos de **médio porte**, operação **regional** (BH e região metropolitana de Minas Gerais).

**Posicionamento atual:** ERP **operacional** forte (frota, locação, manutenção, painéis, PDFs). Ainda **não substitui** Sisloc ou Protheus de ponta a ponta — o gap principal está em **comercial-financeiro-fiscal** e **logística regional**.

| Comparativo estimado | Cobertura |
|---------------------|-----------|
| Sisloc — módulo operacional | ~65–75% |
| Sisloc — produto completo | ~45–55% |
| Protheus — ERP completo | ~15–25% |

**Estratégia recomendada:** Linha Leve = **operação + comercial**; fiscal/contábil pesado via integração (Omie, Conta Azul, Bling) ou módulo fiscal em fase posterior.

---

## Roadmap — o que já está pronto (Fases 1–9)

| Fase | Escopo | Status |
|------|--------|--------|
| 1 | Auth, RBAC, categorias, patrimônios, clientes, auditoria | Implementada |
| 2 | QR Code, scan, movimentação, timeline, impressão | Implementada |
| 3 | Fluxo locação (reserva → saída → retorno → inspeção) | Implementada |
| 4 | OS manutenção, peças, horas, integração locação | Implementada |
| 5 | PDFs (patrimônio, locação, OS) | Implementada |
| 6 | PDF OS modelo MANUTENCAO / identidade ACESSO | Implementada |
| 7 | OS inteligente, catálogo peças, autocomplete | Implementada |
| 8 | Relatório comercial, preventiva, alertas dashboard | Implementada |
| 9 | Retornos atrasados, ficha cliente, painel manutenção, CSV, local da obra | Implementada |
| 10 | Tabela de preços, cálculo automático, prorrogação (10.1–10.4) | Implementada |

---

## Roadmap — pendências rápidas (refino do que já existe)

Itens pequenos, discutidos e ainda **não implementados**, que complementam as fases 7–9:

| # | Item | Prioridade | Esforço |
|---|------|------------|---------|
| P1 | Exportação CSV do **painel de locados** (filtros aplicados) | Alta | 1–2 dias |
| P2 | Job agendado de **preventiva vencida** (`schedule:run` diário) | Alta | 1–2 dias |
| P3 | **Notificações por e-mail** (retorno atrasado, OS atrasada, preventiva) | Alta | 3–5 dias |
| P4 | **Estoque de peças** — saldo atual + estoque mínimo + alerta dashboard | Média | 3–5 dias |
| P5 | Baixa automática de peça ao **concluir OS** | Média | 2–3 dias |
| P6 | **PWA / mobile** enxuto para pátio (scan, checklist, entrega) | Média | 1–2 semanas |
| P7 | Ambiente **produção** (PostgreSQL, `queue:work`, cron, backup automático) | Alta | 2–3 dias |
| P8 | Seeds demo com **regras preventivas** e `local_obra` preenchido | Baixa | 0,5 dia |

---

## Roadmap — Fase 10: Comercial e precificação

**Objetivo:** eliminar planilhas e aproximar do **núcleo comercial do Sisloc**.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 10.1 | **Tabela de preços** | Diária / semanal / mensal por modelo ou categoria | ✅ Implementada |
| 10.2 | **Cálculo automático do valor** | Dias entre saída e retorno (inclusivo) | ✅ Implementada |
| 10.3 | Preenchimento automático de `valor_faturamento` | Na reserva/saída, editável depois | ✅ Implementada |
| 10.4 | **Prorrogação de locação** | Novo vencimento + recálculo de valor | ✅ Implementada |
| 10.5 | **Substituição de equipamento** | Troca de patrimônio em locação ativa (histórico preservado) | Alta |
| 10.6 | **Contrato de locação PDF** | Modelo com cláusulas editáveis (além do resumo operacional) | Alta |
| 10.7 | **Orçamento / proposta** | Pré-reserva com validade e conversão em locação | Média |
| 10.8 | Descontos e tabelas promocionais | Por cliente, categoria ou período | Média |
| 10.9 | **Caução / depósito** | Valor registrado na locação, status (recebido/devolvido) | Média |
| 10.10 | Limite de crédito no cliente | Bloqueia nova locação se exceder | Alta |

**Entregável da fase:** comercial consegue fechar locação com **valor calculado** sem planilha externa.

**Estimativa:** 3–6 semanas.

---

## Roadmap — Fase 11: Financeiro e cobrança (leve)

**Objetivo:** contas a receber ligadas à locação — sem virar Protheus completo.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 11.1 | **Títulos a receber** | Parcelas por locação, vencimentos, status (aberto/pago/atrasado) | Crítica |
| 11.2 | **Relatório de inadimplência** | Aging (30/60/90 dias), por cliente | Crítica |
| 11.3 | Baixa manual de pagamento | Data, forma, observação | Alta |
| 11.4 | **Fluxo de caixa previsto** | Entradas por vencimento de títulos | Média |
| 11.5 | Contas a pagar básico | Fornecedores, oficina externa | Baixa |
| 11.6 | Integração exportação contador | CSV/OFX ou API Omie / Conta Azul / Bling | Alta |
| 11.7 | Boleto / PIX (gateway) | Asaas, Gerencianet, etc. | Média |
| 11.8 | Conciliação bancária simples | Importação OFX | Baixa |

**Entregável da fase:** gestor vê **quem deve, quanto e desde quando** direto no sistema.

**Estimativa:** 4–8 semanas.

---

## Roadmap — Fase 12: Logística regional (BH e região)

**Objetivo:** suportar operação em múltiplas cidades da região metropolitana.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 12.1 | **Múltiplos pátios / filiais** | BH, Contagem, Betim etc. como cadastro (não só texto) | Crítica |
| 12.2 | Patrimônio vinculado ao pátio de origem | Transferência entre pátios com histórico | Alta |
| 12.3 | **Agenda de entrega e retirada** | Data/turno por locação | Crítica |
| 12.4 | **Romaneio do dia** | Lista de entregas/retiradas por rota/motorista | Crítica |
| 12.5 | Cidade/bairro na obra | Filtro por região (BH, RMBH, interior MG) | Alta |
| 12.6 | Motorista e veículo de entrega | Cadastro simples + vínculo ao romaneio | Média |
| 12.7 | Comprovante de entrega/retirada | Foto + assinatura no celular | Alta |
| 12.8 | Custo de frete na locação | Campo opcional no faturamento | Média |
| 12.9 | Mapa ou lista geográfica | Visualização de obras ativas na região | Baixa |

**Entregável da fase:** operação de pátio organiza o **dia de entregas** sem WhatsApp solto.

**Estimativa:** 4–6 semanas.

---

## Roadmap — Fase 13: Gestão de frota avançada

**Objetivo:** decisões de compra, venda e investimento em equipamentos.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 13.1 | **Taxa de ocupação** | Por patrimônio, modelo e categoria (%) | Alta |
| 13.2 | **Rentabilidade por patrimônio** | Faturamento vs custo manutenção vs compra | Alta |
| 13.3 | **Calendário de disponibilidade** | Visão mensal: livre / reservado / locado | Alta |
| 13.4 | Reserva futura com alerta de conflito | Dois clientes no mesmo patrimônio/datas | Média |
| 13.5 | ROI e payback do equipamento | Relatório gerencial | Média |
| 13.6 | Depreciação patrimonial | Valor contábil ao longo do tempo | Baixa |
| 13.7 | Indicador de horas de uso | Horímetro × faturamento | Média |
| 13.8 | Sugestão de desinvestimento (sucata) | Patrimônio com custo > receita | Baixa |

**Entregável da fase:** gestor responde *“vale comprar mais betoneiras?”* com dados do sistema.

**Estimativa:** 4–6 semanas.

---

## Roadmap — Fase 14: Manutenção e estoque (aprofundamento)

**Objetivo:** oficina com controle de peças de verdade.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 14.1 | Estoque com saldo por peça | Entrada, saída, mínimo | Alta |
| 14.2 | Baixa automática na OS concluída | Integrado ao catálogo existente | Alta |
| 14.3 | Pedido de compra de peça | Quando estoque < mínimo | Média |
| 14.4 | Fornecedores de peças | Cadastro + histórico de preço | Média |
| 14.5 | Custo total OS vs faturamento do patrimônio | Relatório | Média |
| 14.6 | Preventiva automática por job | Abre OS ou só alerta (configurável) | Alta |
| 14.7 | Manutenção em campo | App/checklist simplificado no celular | Média |
| 14.8 | Indenização / cobrança de dano | OS tipo indenização → título a receber | Média |

**Estimativa:** 3–5 semanas.

---

## Roadmap — Fase 15: CRM e relacionamento comercial

**Objetivo:** comercial externo e retenção de clientes da construção civil regional.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 15.1 | Pipeline de oportunidades | Lead → proposta → locação | Média |
| 15.2 | Histórico comercial ampliado | Último contato, próximo follow-up | Média |
| 15.3 | Bloqueio por inadimplência | Automático ao gerar título vencido | Alta |
| 15.4 | Consulta de crédito (Serasa/SPC) | Integração opcional | Baixa |
| 15.5 | Campanhas / clientes inativos | Relatório “não loca há X meses” | Média |
| 15.6 | Múltiplos contatos por cliente | Obras diferentes, engenheiro, financeiro | Média |
| 15.7 | WhatsApp / SMS automático | Retorno, cobrança, preventiva | Alta |

**Estimativa:** 4–6 semanas.

---

## Roadmap — Fase 16: Fiscal e documentos legais

**Objetivo:** emissão fiscal ou integração — requisito para escalar sem planilha paralela.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 16.1 | **NFS-e** | BH + municípios da RMBH onde a empresa atua | Crítica (quando escalar) |
| 16.2 | NF de remessa / retorno de bem locado | Se aplicável ao modelo jurídico | Alta |
| 16.3 | CFOP e regras MG | Parametrização por tipo de operação | Alta |
| 16.4 | Integração ERP fiscal externo | Protheus, Omie, etc. (API) | Alta |
| 16.5 | DANFE / XML armazenado | Vínculo à locação | Média |
| 16.6 | Relatórios fiscais mensais | Exportação para contador | Média |

**Nota:** muitas locadoras médias mantêm fiscal em sistema separado; avaliar **integração** antes de construir emissor próprio.

**Estimativa:** 8–16 semanas (depende de integração vs emissor próprio).

---

## Roadmap — Fase 17: Plataforma, integrações e escala

**Objetivo:** sistema pronto para crescimento e integrações.

| # | Funcionalidade | Descrição | Prioridade |
|---|----------------|-----------|------------|
| 17.1 | **API REST** documentada | Patrimônios, locações, clientes, títulos | Alta |
| 17.2 | Webhooks | Eventos: locação criada, retorno atrasado, OS concluída | Média |
| 17.3 | Multi-filial com permissão por unidade | Usuário vê só seu pátio | Alta |
| 17.4 | SSO / LDAP | Se equipe crescer | Baixa |
| 17.5 | BI / dashboards avançados | Metabase, Grafana ou módulo interno | Média |
| 17.6 | Importação em massa | Planilha de patrimônios e clientes | Média |
| 17.7 | Logs e monitoramento produção | Sentry, health check | Alta |
| 17.8 | Backup automático testado | `pg_dump` + storage, restore documentado | Crítica |

**Estimativa:** contínuo.

---

## Roadmap — matriz de prioridade (empresa média regional BH)

Legenda: **A** = fazer primeiro · **B** = próximo ciclo · **C** = quando operação estiver madura

| Área | Itens fase | Prioridade |
|------|------------|------------|
| Comercial / preço | 10.1 – 10.4, 10.10 | **A** |
| Financeiro leve | 11.1 – 11.3, 11.6 | **A** |
| Logística RMBH | 12.1, 12.3, 12.4, 12.7 | **A** |
| Refino operação | P1 – P3, P7 | **A** |
| Frota / ocupação | 13.1 – 13.3 | **B** |
| Manutenção / estoque | 14.1, 14.2, 14.6 | **B** |
| Contrato / caução | 10.6, 10.9 | **B** |
| CRM / WhatsApp | 15.7, 15.3 | **B** |
| Fiscal NFS-e | 16.1, 16.4 | **C** |
| API / multi-filial | 17.1, 17.3, 17.8 | **B–C** |

---

## Roadmap — sequência sugerida de sprints

```
Sprint 1–2   Fase 10 (preços + cálculo + prorrogação) + P7 produção
Sprint 3–4   Fase 11 (títulos a receber + inadimplência) + P3 e-mail
Sprint 5–6   Fase 12 (pátios + romaneio + agenda entrega)
Sprint 7–8   Fase 13 (ocupação + calendário) + P4 estoque peças
Sprint 9–10  Fase 14 (estoque OS) + 10.6 contrato PDF
Sprint 11+   Fase 15 CRM + Fase 16 fiscal (ou integração) + Fase 17 API
```

---

## Roadmap — o que o Sisloc / Protheus têm e o Linha Leve ainda não tem

### Sisloc (locação especializada)

- Motor de faturamento por período automático
- Emissão de boleto e carnê
- Contrato padrão ABNT / customizado com assinatura digital
- Romaneio e logística de entrega
- Calendário de reservas multi-equipamento
- Integração contábil nativa
- App mobile de campo (versões comerciais)
- Multi-filial com estoque por filial
- Análise de crédito integrada

### Protheus / TOTVS (ERP completo)

- Contabilidade (plano de contas, lançamentos, balancete)
- Folha de pagamento e RH
- Compras e suprimentos completos
- WMS / estoque industrial
- Módulo fiscal completo (NF-e, NFS-e, SPED, EFD)
- CRM e força de vendas
- Workflow de aprovação multinível
- Customização AdvPL / Protheus Cloud
- BI corporativo (TOTVS Analytics)

### Linha Leve — diferenciais atuais (manter e evoluir)

- Fluxo operacional enxuto e focado em locação de linha leve
- Fichas com edição inline (menos cliques que ERPs tradicionais)
- Painéis operacionais (locados, manutenção, categorias)
- Checklists de saída/retorno integrados ao status
- Local da obra sincronizado com patrimônio
- Campos personalizados sem customização de código
- Código aberto, adaptável à operação ACESSO / regional MG

---

## Roadmap — critérios de “pronto para substituir Sisloc na operação”

Marque quando todos estiverem concluídos:

- [ ] Tabela de preços e valor calculado automaticamente na locação
- [ ] Títulos a receber com relatório de inadimplência
- [ ] Romaneio / agenda de entrega e retirada
- [ ] Contrato PDF padrão da empresa
- [ ] Múltiplos pátios (BH + pelo menos 1 unidade regional)
- [ ] Notificações automáticas (e-mail ou WhatsApp) de retorno atrasado
- [ ] Ambiente produção estável (PostgreSQL, fila, backup)
- [ ] Fiscal: integração ou NFS-e emitida pelo sistema

Até lá, o Linha Leve funciona como **sistema operacional principal**, com **comercial/fiscal complementar** em planilha ou sistema legado.
