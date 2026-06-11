# Gestão Acesso — Visão de produto

Sistema de gestão para **locadoras de equipamentos de linha leve** (betoneiras, marteletes, compactadores, geradores e similares). Pensado para operação regional — equipes de pátio, comercial, manutenção e financeiro trabalhando no mesmo lugar, com menos planilha e menos retrabalho.

> Este documento descreve **o que o produto entrega e para quem**. Detalhes técnicos, instalação e roadmap técnico estão no [README](../README.md).

---

## Para quem é

| Perfil | O que ganha |
|--------|-------------|
| **Comercial** | Reservar, orçar, acompanhar locações, ver se o cliente está bloqueado, fechar proposta com validade |
| **Operação / pátio** | Painel do que está locado, saída e retorno com checklist, scan do QR no celular |
| **Manutenção** | OS com peças e horas, preventiva por horímetro, integração com retorno de locação |
| **Financeiro / gestão** | Títulos a receber, inadimplência, fila a faturar, exportação para contador (CSV, **Omie**, **Bling**, Sisloc legado) |
| **Gestor / admin** | Dashboards, auditoria, multi-empresa, usuários, copiloto operacional |

**Público-alvo:** locadora de **médio porte**, operação **regional** (ex.: BH e região metropolitana).

---

## O que o sistema faz hoje

### Frota e patrimônio

- Cadastro de categorias, modelos e patrimônios com **status em tempo real** (disponível, reservado, locado, em manutenção, etc.).
- **Ficha do patrimônio** com anexos, horímetro, localização e histórico de mudanças.
- **QR Code** em cada patrimônio — escaneie no pátio e vá direto à ficha ou ao checklist de saída/retorno.
- **Campos personalizados** nas fichas (sem precisar de programador).
- Visão por **categoria**: quantos disponíveis, locados e em manutenção.

### Locação — do orçamento ao encerramento

Fluxo completo, com checklists e rastreio:

```
Orçamento (opcional) → Reserva → Saída → Locado → Retorno → Inspeção → Concluído
```

- **Orçamento / pré-reserva** com prazo de validade; ao converter, vira reserva oficial.
- **Reserva** amarra patrimônio + cliente; impede dupla reserva do mesmo equipamento.
- **Saída e retorno** com checklist (visual, acessórios, identificação) — no computador ou no **modo pátio** no celular.
- **Local da obra** na locação; na saída, atualiza onde o equipamento está.
- **Prorrogação** recalcula valor automaticamente conforme tabela de preços.
- **Substituição de equipamento** em locação ativa, com histórico preservado.
- **Contrato e resumo em PDF** para entregar ao cliente.
- **Painel de locados**: filtros, retornos atrasados, exportação CSV.
- Alertas de **retorno atrasado** no dashboard e no menu.

### Comercial e preços

- **Tabela de preços** (diária, semanal, mensal) por modelo ou categoria.
- **Cálculo automático** do valor na reserva/saída — comercial não depende de planilha paralela.
- **Relatório comercial** por tipo de equipamento (faturamento agrupado por modelo/categoria).
- **Caução / depósito** registrado na locação.

### Manutenção

- **Ordens de serviço** com fluxo: aberta → em execução → aguardando peça → concluída.
- **Catálogo de peças** com autocomplete na OS.
- **Manutenção preventiva** configurável por modelo e horímetro.
- Retorno de locação pode **abrir OS automaticamente** se houver problema na inspeção.
- **Painel operacional** de OS (atrasadas, por status, filtros).
- **PDF da OS** no layout da oficina (modelo MANUTENCAO / identidade ACESSO).

### Financeiro

- **Títulos a receber** gerados na saída da locação (ou pelo ciclo de faturamento).
- **Fila a faturar** — pendências de locação/renovação com autorização e geração de fatura.
- **Ciclos de faturamento** e renovações automáticas (job diário).
- **Inadimplência** com aging (1–30, 31–60, 61–90, 90+ dias) e multa/juros configuráveis.
- **Fluxo de caixa previsto** por vencimento.
- **Baixa manual** de pagamento (PIX, transferência, etc.).
- **Bloqueio** de nova locação para cliente inadimplente ou bloqueado manualmente.
- **Limite de crédito** opcional por cliente.
- **Exportação contábil** em layout fixo: CSV padrão, **Omie**, **Bling** ou Sisloc (legado) — sem emitir NF-e pelo sistema; ver [Transição fiscal](TRANSICAO_FISCAL.md).
- **Análise financeira** gerencial (visão consolidada de recebíveis e operação).

### Clientes e cadastros

- Clientes com validação **CPF/CNPJ**, ficha completa e histórico de locações.
- **Pessoas** e **empresas (CNPJ)** em cadastro separado, quando a operação exige.
- **Busca global** — encontre patrimônio, locação, cliente ou OS pelo código ou nome.

### Gestão, auditoria e multi-empresa

- **Várias empresas operacionais (CNPJ)** no mesmo sistema — seletor no topo; dados isolados por empresa.
- **Perfis e permissões** (Admin, Gestor, Comercial, Operação, Manutenção).
- **Auditoria** de ações relevantes (quem alterou o quê).
- **Abas multitarefa** no navegador — trabalhe em várias fichas sem perder contexto.

### Copiloto operacional

Assistente interno (menu **Copiloto**) para quem tem permissão:

- Consultar fichas, resumo financeiro, pendências a faturar.
- Avançar locação (saída, retorno, inspeção), faturar, baixar título, operar OS — com **confirmação** antes de alterar dados.
- **Prévia (dry-run)** em ações financeiras antes de confirmar.
- **Histórico auditável** do que o copiloto fez (Admin → Copiloto logs).
- **API** para integrações futuras (manifest de comandos, contexto, chat).

Modo heurístico (sem IA) ou com **modelo de linguagem** opcional via configuração.

### Experiência no pátio (celular)

- **PWA** instalável no celular.
- Scan do QR → tela enxuta → checklist de **saída** ou **retorno** com um toque.
- Checklist mantém **estado local no aparelho** (Alpine + sessionStorage) e só envia ao servidor ao confirmar — tolera Wi‑Fi/4G instável no galpão.
- Ideal para quem não quer abrir ficha completa no desktop no meio do pátio.

---

## Notas de arquitetura (para gestores e devs)

### Multi-empresa e performance

Várias empresas operacionais (CNPJ) compartilham o mesmo sistema; locação, frota e financeiro são **isolados por `operating_company_id`**. Listagens e dashboards dependem de índices compostos no banco — essencial conforme auditoria e títulos crescem.

### Quem é quem no cadastro

| Entidade | Papel |
|----------|--------|
| **User** | Funcionário com login (pátio, comercial, financeiro). Permissões Spatie. |
| **Customer** | Cliente da **locação** — CPF/CNPJ, contratos, títulos. Sem login. |
| **Person / Company** | Cadastro **CRM** (contatos, fornecedores). Separado do cliente de locação. |
| **OperatingCompany** | CNPJ operacional (Acesso, Super Máquinas…) — seletor no topo. |

Operadores **nunca** compartilham tabela com clientes — evita vazamento de escopo e confusão de permissões.

### Fila assíncrona

Jobs (QR Code, rotinas futuras) usam driver `database` — adequado ao porte atual. Monitorar crescimento da tabela `jobs` e falhas; runbook em [`docs/PRODUCTION.md`](PRODUCTION.md).

---

## Diferenciais em relação a ERPs genéricos

- Focado em **locação de equipamentos**, não em varejo ou indústria genérica.
- **Fichas com edição inline** — menos cliques que sistemas tradicionais.
- **Painéis operacionais** (locados, manutenção, financeiro) pensados para o dia a dia.
- Checklists de saída/retorno **ligados ao status** do patrimônio e da locação.
- Código adaptável à operação **ACESSO** / regional MG — sem licença de ERP monolítico.

---

## O que o sistema **não** é (ainda)

Para expectativa alinhada:

| Expectativa | Situação atual |
|-------------|----------------|
| Emitir **NF-e / NFS-e** | Não — exportação para **Omie/Bling** (fiscal) ou Sisloc durante transição |
| Substituir **Protheus** (RH, compras, contabilidade completa) | Não — escopo é operação + comercial + financeiro leve |
| **Romaneio / rotas** de entrega multi-cidade | Planejado — hoje o local da obra e painéis cobrem parte da operação |
| **Boleto/PIX automático** (gateway) | Planejado — hoje a baixa é manual |
| **App nativo** iOS/Android | Modo PWA no navegador; app dedicado no roadmap |
| **CRM completo** (pipeline, campanhas) | Parcial — histórico de cliente e bloqueios; pipeline formal no roadmap |

---

## O que vem a seguir (visão de produto)

Prioridades naturais para evoluir o produto:

1. **Notificações** — e-mail diário (retorno atrasado, OS atrasada, preventiva vencida) às 07:45; WhatsApp no roadmap.
2. **Estoque de peças** — saldo real na oficina, baixa automática ao concluir OS.
3. **Logística regional** — múltiplos pátios, agenda de entrega, romaneio do dia.
4. **Indicadores de frota** — taxa de ocupação, rentabilidade por patrimônio, calendário de disponibilidade.
5. **Integrações** — API pública documentada, webhooks, conciliação bancária.
6. **Fiscal** — quando escalar: NFS-e ou integração fiscal mais profunda (avaliar emissor vs parceiro).

A sequência exata e estimativas técnicas estão no [README — Roadmap](../README.md#roadmap--visão-geral).

---

## Jornada resumida por persona

### Comercial — “fechar uma locação”

1. Busca patrimônio disponível (ou cria **orçamento** com validade para o cliente).
2. Converte orçamento em **reserva** ou reserva direto.
3. Sistema calcula **valor** pela tabela de preços.
4. Cliente bloqueado por atraso? Sistema **avisa** antes de reservar.
5. Após saída, acompanha **faturamento** e títulos na ficha.

### Operação — “dia no pátio”

1. Abre **painel de locados** — quem devolve hoje, quem está atrasado.
2. Escaneia **QR** na saída ou retorno → checklist no celular → confirma.
3. Patrimônio e locação **mudam de status** automaticamente.

### Manutenção — “equipamento voltou com problema”

1. Inspeção da locação envia para **manutenção** (OS aberta).
2. Técnico registra peças e horas; aguarda peça se necessário.
3. Conclui OS → patrimônio volta a **disponível** (ou permanece bloqueado se impeditivo).

### Financeiro — “fechar o mês”

1. **Fila a faturar** — autoriza e gera faturas pendentes.
2. **Inadimplência** — quem deve, há quanto tempo, com multa/juros.
3. **Exporta** títulos abertos para **Omie** ou **Bling** (ou CSV); emite NF no ERP fiscal.
4. Registra **pagamentos** recebidos.

### Gestor — “visão do negócio”

1. **Dashboard** — frota, atrasos, preventiva, financeiro da semana.
2. **Relatórios** comercial e análise financeira.
3. **Auditoria** e logs do copiloto.
4. Alterna **empresa operacional** se houver mais de um CNPJ.

---

## Posicionamento

O **Gestão Acesso** é o **sistema operacional principal** da locadora: frota, locação, manutenção e financeiro leve no mesmo lugar.

O **Sisloc** (ou similar) costuma resolver hoje a parte **pesada de impostos e NF**. A estratégia não é substituí-lo de um dia para o outro: o Gestão Acesso assume **pátio e comercial**; um ERP fiscal enxuto (**Omie**, **Bling**, Conta Azul) assume notas e contabilidade por **custo muito menor** que o Sisloc completo.

A ponte é a **exportação contábil (Fase 12A)**. Só desligue o Sisloc fiscal depois que Omie/Bling importarem títulos em paralelo, sem divergência, por pelo menos um ciclo de faturamento. Detalhes: [`docs/TRANSICAO_FISCAL.md`](TRANSICAO_FISCAL.md).

**Meta de maturidade:** substituir o **núcleo operacional** do Sisloc (dia a dia do pátio e comercial), mantendo o fiscal em parceiro até a transição validada.

---

*Última revisão: junho/2026 — alinhada às fases 1–12 do produto.*
