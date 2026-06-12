# Transição fiscal — Sisloc → Omie / Bling

Runbook para o financeiro e gestão. O **Gestão Acesso** não emite NF-e/NFS-e; o Sisloc (hoje) ou um ERP fiscal enxuto (Omie, Bling, Conta Azul) assume impostos, notas e contabilidade pesada.

## Divisão de responsabilidades

| Camada | Sistema | O que faz |
|--------|---------|-----------|
| **Operação** | Gestão Acesso | Pátio, reserva, saída/retorno, substituição, manutenção, fila a faturar, títulos a receber operacionais |
| **Fiscal / NF** | Omie, Bling ou Sisloc (transição) | Emissão de notas, retenções, SPED, integração contábil completa |
| **Ponte** | Exportação contábil (Fase 12A) | CSV de títulos abertos → importação no ERP fiscal |

**Economia:** Omie/Bling costumam representar custo **muito inferior** ao Sisloc para o módulo fiscal, enquanto o Gestão Acesso cobre o que a locadora usa todo dia (pátio + comercial).

## Regra de ouro

> **Não desligue o Sisloc** (ou não migre 100% do fiscal) até a exportação para Omie/Bling rodar **em paralelo**, com conferência humana, por **pelo menos um ciclo de faturamento completo** (locação + renovação + frete + baixa).

Operação pode migrar antes; **fiscal só depois** da ponte validada.

---

## Formatos de exportação

Menu **Financeiro → Títulos a receber → Exportar contábil**:

| Formato | Uso |
|---------|-----|
| **Omie** | Importação de contas a receber (recomendado para locadoras médias) |
| **Bling** | Planilha modelo oficial Bling (`;` UTF-8) |
| **CSV padrão** | Contador / planilha genérica |
| **Sisloc (legado)** | Enquanto Sisloc ainda for o emissor fiscal — transição |

Por padrão, os links exportam **títulos abertos ainda não marcados como exportados** (`exclude_exported=1`). Após o download, o sistema grava `exportado_erp_em` no título. Use o filtro **Ainda não exportados ao ERP** em Financeiro → Títulos, ou marque/desmarque manualmente.

URL direta (com filtros):

```
/financeiro/exportar-contabil?format=omie&status=aberto
/financeiro/exportar-contabil?format=bling&status=aberto
```

---

## Configuração (`.env`)

Ajuste categorias/portadores **antes** do primeiro import real — devem existir no Omie/Bling:

```env
# Omie
ACCOUNTING_OMIE_CATEGORIA=1.01.01
ACCOUNTING_OMIE_CONTA_CORRENTE=1

# Bling (nomes exatos cadastrados no Bling)
ACCOUNTING_BLING_CATEGORIA=Receitas de locação
ACCOUNTING_BLING_PORTADOR=Caixa
ACCOUNTING_BLING_FORMA_PAGAMENTO=Transferência
```

---

## Checklist — validar em paralelo (Sisloc + export)

Use este checklist **mensalmente** até desligar o Sisloc fiscal:

### 1. Cadastro e mapeamento

- [ ] Clientes no Gestão Acesso com **CPF/CNPJ** idêntico ao Omie/Bling
- [ ] Categoria de receita de locação criada no ERP fiscal
- [ ] Portador / conta corrente (Bling/Omie) configurados no `.env`
- [ ] Um título de teste importado manualmente e conferido

### 2. Ciclo operacional → fiscal

- [ ] Locação com saída → título gerado no Gestão Acesso
- [ ] Fila a faturar → fatura → título `aberto` com valor correto (incl. frete de entrega, se houver)
- [ ] Export Omie/Bling → import no ERP **sem erro de coluna**
- [ ] Valor e vencimento batem com o título no Gestão Acesso
- [ ] NF emitida no ERP fiscal com **mesmo valor base** (impostos calculados no ERP)

### 3. Renovação e exceções

- [ ] Job 06:30 gerou renovação → novo título exportado
- [ ] Substituição de equipamento manteve **valor contratual** no título
- [ ] Frete de recolhida (retorno) aparece como título separado, se aplicável
- [ ] Baixa de pagamento no Gestão Acesso refletida manualmente ou via rotina no ERP (até API futura)

### 4. Conferência cruzada

- [ ] Total **a receber aberto** no Gestão Acesso ≈ total importado no ERP (± títulos já pagos no período)
- [ ] Inadimplência (aging) coerente entre sistemas
- [ ] Financeiro assina **go/no-go** para reduzir uso do Sisloc

### 5. Go-live fiscal (desligar Sisloc)

- [ ] ≥ 1 mês de operação 100% no pátio/comercial via Gestão Acesso
- [ ] ≥ 1 ciclo de faturamento exportado **sem divergência** material
- [ ] Contador validou layout Omie/Bling ou CSV
- [ ] Plano B: manter backup Sisloc read-only por 90 dias

---

## Fluxo recomendado (financeiro)

1. **Manhã:** fila a faturar autorizada / faturada no Gestão Acesso  
2. **Exportar contábil** (Omie ou Bling) — títulos abertos  
3. **Importar** no ERP fiscal  
4. **Emitir NF** no Omie/Bling (impostos ficam lá)  
5. **Baixa** no Gestão Acesso quando o pagamento chegar (PIX, boleto, etc.)

O Sisloc continua disponível para **conferência** até o passo 4 rodar sem surpresa por um mês.

---

## Limitações atuais (by design)

- Sem emissão de NF-e pelo Gestão Acesso  
- Exportação Bling/Omie é **CSV** — import manual no ERP  
- Ponte fiscal **Omie (API)** envia registro; emissão da nota continua no Omie  
- Bling/Omie **não atualizam** contas existentes via planilha — reexportar só títulos ainda `aberto`  
- Sisloc layout CAR mantido para **período de transição**, não como destino final

**Já implementado:** marcação `exportado_erp_em` após download da exportação; filtro “Ainda não exportados ao ERP” em Financeiro → Títulos.

---

## Próximos passos (roadmap)

| Item | Benefício |
|------|-----------|
| Webhook de NF emitida | Vincular número da nota ao título no Gestão Acesso |
| API Bling (contas a receber) | Elimina import manual no Bling |
| Baixa bidirecional ERP ↔ Gestão Acesso | Menos retrabalho no financeiro |

---

*Relacionado: [VISAO_PRODUTO.md](VISAO_PRODUTO.md) · [PRODUCTION.md](PRODUCTION.md) · `config/accounting.php`*
