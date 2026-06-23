# Arquitetura do agente ERP

> **Panorama operacional (fluxos, personalidade, modos, configuração):** [IA.md](IA.md)

O copiloto **não usa visão computacional** nem automação de tela. Ele opera como um operador com **acesso total via API** — o mesmo tipo de liberdade que um agente de IDE tem sobre o código, mas aplicado ao domínio do negócio (locações, frota, financeiro, OS, logística).

## Visualização vs execução

O modelo recebe contexto explícito (`AgentModeContext`) sobre **dois tipos de API**:

| Superfície | Modo copiloto | O que faz | Exemplos |
|------------|---------------|-----------|----------|
| **visualization** | Pergunta | Consultar, filtrar, abrir telas, exportar PDF/CSV. **Não persiste** alterações. | `rental.list`, `rental.stats`, `asset.get`, `finance.summary`, `GET /api/agent/context/*`, atalhos `url` |
| **execution** | Agente | Mutar cadastros, campos e status; avançar fluxos operacionais. | `customer.create`, `rental.reserve/checkout/return`, `maintenance.open`, `billing.*`, `document.apply_plan`, `POST /api/agent/tasks` |

**Por que separar:** no modo Pergunta o usuário quer entender e navegar sem risco. No modo Agente autoriza mudanças reais — com confirmação na UI ou fila em background, e detecção de conflito se editar a mesma ficha manualmente.

Cada comando no manifest inclui `surface`, `kind` (`read`/`write`) e `copilot_mode` sugerido. O LLM no modo Pergunta só recebe ferramentas com `surface=visualization`.

## Princípios

1. **API-first** — Tudo passa por endpoints estruturados (`/api/agent/*`), não por cliques simulados.
2. **Manifesto de capacidades** — `GET /api/agent/manifest` lista comandos, modos, superfícies e URLs de contexto.
3. **Contexto rico** — `GET /api/agent/context/{entidade}` devolve JSON pronto para a IA (status, workflow, URLs). **`GET /api/agent/context/knowledge`** expõe a base de conhecimento operacional (`AgentSystemKnowledge`).
4. **Comandos atômicos** — Cada ação de negócio é um comando com permissão Spatie, validação e auditoria.
5. **Modos Pergunta / Agente** — Pergunta = visualização; Agente = execução (com confirmação ou fila).
6. **Background com concorrência** — Planos multi-passo rodam em fila; alterações manuais no ERP invalidam a tarefa.

## Superfície da API

| Endpoint | Superfície | Uso |
|----------|------------|-----|
| `GET /api/agent/manifest` | — | Descoberta: modos, superfícies, comandos |
| `GET /api/agent/context/*` | visualização | Ler fichas (locação, cliente, sistema, OS, knowledge, pricing, part…) |
| `POST /api/agent/commands/{name}` | conforme comando | Executar um comando (sync) |
| `POST /api/agent/chat` | conforme modo | Chat com modo `ask` / `agent` |
| `POST /api/agent/tasks` | execução | Enfileirar plano multi-passo (background) |
| `GET /api/agent/tasks/{id}` | visualização | Acompanhar progresso / conflito |

## Concorrência (agente vs usuário)

Quando uma tarefa é enfileirada:

1. Capturamos `updated_at` dos recursos afetados (locação, patrimônio, cliente…).
2. Antes de cada passo **write**, verificamos se o snapshot ainda é válido.
3. Se o usuário editar a ficha na UI, o observer dispara **conflito** → status `conflict` na tarefa.
4. Comandos sync também verificam snapshot quando aplicável (`resource_conflict`).

Isso evita que o agente sobrescreva trabalho manual sem aviso — similar a merge conflicts, não a locks globais.

## Extensão contínua

Novos módulos entram no agente adicionando:

1. Comando em `app/Agent/Commands/*` (extends `AbstractAgentCommand` ou `AbstractReadAgentCommand`).
2. Registro em `config/agent.php`.
3. Opcional: `affectedResources()` para concorrência.
4. Contexto em `AgentContextBuilder` + rota em `ContextController`.

Com `AGENT_LLM_ENABLED`, a IA escolhe ferramentas do manifest — **sem hardcode por tela**.

## Roadmap natural

- Comandos para **todas** as entidades (orçamento, logística, pátio, títulos…)
- Webhook / SSE para progresso de tarefas no copiloto
- Idempotency keys em integrações externas
- Dead-letter queue para tarefas falhas
