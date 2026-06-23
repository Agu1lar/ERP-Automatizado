<?php

namespace App\Support\Agent;

/**
 * Base de conhecimento operacional do ERP para o copiloto/agente.
 * Consumida via GET /api/agent/context/knowledge e embutida no manifest.
 */
class AgentSystemKnowledge
{
    public const VERSION = '2026.06';

    /** @return array<string, mixed> */
    public static function manifest(): array
    {
        return [
            'version' => self::VERSION,
            'command' => 'knowledge.get',
            'product' => 'ERP Gestão Acesso — locação de equipamentos',
            'api_philosophy' => 'Visualização (GET context + comandos read) separada de execução (comandos write). Sem automação de tela.',
            'code_patterns' => [
                'rental' => 'LOC-000123 ou id numérico',
                'asset' => 'PAT-XXXX ou id',
                'maintenance_order' => 'OS-000123 ou id',
                'billing_entry' => 'FAT-... ou id',
                'receivable' => 'TIT-... ou id',
                'quote' => 'ORC-... ou id',
            ],
            'domains' => self::domains(),
            'workflows' => self::workflows(),
            'documents' => self::documents(),
            'deep_links' => self::deepLinks(),
            'operational_rules' => self::operationalRules(),
        ];
    }

    /** @return list<array<string, string>> */
    private static function domains(): array
    {
        return [
            ['key' => 'rentals', 'label' => 'Locações', 'commands' => 'rental.*, billing.create_renewal'],
            ['key' => 'quotes', 'label' => 'Orçamentos', 'commands' => 'quote.*'],
            ['key' => 'fleet', 'label' => 'Frota / patrimônio', 'commands' => 'asset.*, yard.list, fleet.analytics'],
            ['key' => 'maintenance', 'label' => 'Manutenção', 'commands' => 'maintenance.*, preventive.*, part.*'],
            ['key' => 'finance', 'label' => 'Financeiro', 'commands' => 'billing.*, receivable.*, finance.*'],
            ['key' => 'crm', 'label' => 'CRM pessoa/empresa', 'commands' => 'person.*, company.*, customer.*'],
            ['key' => 'pricing', 'label' => 'Tabela de preços', 'commands' => 'pricing.*, category.list, model.list'],
            ['key' => 'logistics', 'label' => 'Logística', 'commands' => 'logistics.daily'],
            ['key' => 'reports', 'label' => 'Relatórios', 'commands' => 'report.*'],
            ['key' => 'search', 'label' => 'Busca global', 'commands' => 'search.global'],
            ['key' => 'documents', 'label' => 'PDFs', 'commands' => 'document.export'],
        ];
    }

    /** @return array<string, mixed> */
    private static function workflows(): array
    {
        return [
            'rental_lifecycle' => [
                'steps' => ['reservado', 'locado', 'em_inspecao', 'concluido'],
                'actions' => [
                    'reservado' => ['rental.checkout' => 'Registrar saída'],
                    'locado' => ['rental.return' => 'Registrar retorno', 'rental.extend' => 'Prorrogar', 'rental.substitute' => 'Trocar patrimônio (exige substituto disponível)'],
                    'em_inspecao' => ['rental.complete_inspection' => 'Concluir inspeção (ok | maintenance | indenizacao)'],
                ],
                'swap_vs_field_repair' => 'Troca real (rental.substitute) exige outro patrimônio disponível. Sem substituto: abrir OS tipo campo no mesmo equipamento (maintenance.open tipo=campo) — locação permanece locada.',
            ],
            'maintenance_workshop' => [
                'steps' => ['aberta', 'em_execucao', 'aguardando_peca', 'concluida'],
                'actions' => [
                    'aberta' => ['maintenance.start' => 'Iniciar execução'],
                    'em_execucao' => ['maintenance.wait_part', 'maintenance.complete'],
                    'aguardando_peca' => ['maintenance.resume', 'maintenance.complete'],
                ],
                'ui_deep_link' => 'Ficha OS aceita ?acao=executar|retomar|concluir para abrir ação correspondente.',
            ],
            'maintenance_field' => [
                'description' => 'Manutenção em campo — equipamento continua locado na obra.',
                'open' => 'maintenance.open com tipo=campo e rental vinculado, ou tela mobile /campo/{codigo_patrimonio}',
                'complete' => 'maintenance.complete_field com checklist (ou confirm_checklist_all=true); alternativa: tela /campo/{codigo_patrimonio}.',
                'checklist_keys' => array_keys(\App\Services\MaintenanceOrderService::CHECKLIST_CAMPO),
                'when_to_use' => 'Cliente pediu troca mas não há equipamento substituto; reparo no local.',
            ],
            'billing_renewal' => [
                'description' => 'Ciclos de faturamento e renovação automática na fila (billing.create_renewal).',
                'pro_rata_contract' => 'Cláusula opcional no contrato: após prazo previsto, locação prorroga automaticamente com cobrança pro-rata até devolução.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function documents(): array
    {
        return [
            'export_command' => 'document.export',
            'types' => [
                'rental_summary' => 'Resumo/extrato da ficha — pode listar avisos de ficha incompleta (não bloqueia download).',
                'rental_contract' => 'Contrato PDF — inclui cláusulas padrão + pro-rata se contrato_clausula_prorata=true na locação.',
                'rental_statement' => 'Demonstrativo por período — requer periodo_de e periodo_ate (YYYY-MM-DD).',
                'asset_sheet' => 'Ficha do patrimônio',
                'maintenance_order' => 'PDF da OS',
                'billing_invoice' => 'PDF da fatura da fila',
            ],
            'rental_fields' => [
                'contrato_clausula_prorata' => 'boolean na locação — rental.update ou checkbox na ficha. Padrão true.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function deepLinks(): array
    {
        return [
            'rental_inspecao' => '?acao=inspecao na ficha da locação em status em_inspecao abre modal de conclusão.',
            'maintenance_executar' => '?acao=executar na ficha OS aberta inicia execução.',
            'maintenance_retomar' => '?acao=retomar quando aguardando peça.',
            'maintenance_concluir' => '?acao=concluir abre modal de conclusão.',
        ];
    }

    /** @return array<string, mixed> */
    private static function operationalRules(): array
    {
        return [
            'horimetro' => [
                'category_flag' => 'usa_horimetro na categoria — quando false, horímetro não é exigido em avisos nem PDF.',
                'rental_fields' => 'horimetro_saida no checkout; horimetro_retorno no retorno — apenas se categoria usa horímetro.',
                'asset_field' => 'horimetro no patrimônio é leitura atual; avisos da locação não duplicam esse campo.',
            ],
            'global_search' => [
                'behavior' => 'Patrimônio com locação ativa retorna duas opções: ficha do contrato (LOC) e ficha do patrimônio.',
                'contract_by_number' => 'Busca LOC-000123 ou número 123 encontra contrato.',
            ],
            'ficha_completeness' => 'Avisos não bloqueiam PDFs; informam campos faltantes (descrição, série, contato cliente, local obra, etc.).',
        ];
    }

    /** Resumo compacto para o system prompt do LLM (sempre presente). */
    public static function compactForLlm(): string
    {
        return <<<'TXT'
=== BASE DE CONHECIMENTO (resumo v2026.06) ===
Códigos: LOC (locação), PAT (patrimônio), OS (manutenção), FAT (fila faturamento), TIT (título), ORC (orçamento).
Locação: reservado → checkout/saída → locado → retorno → em_inspecao → concluido. Em locado: prorrogar (rental.extend), trocar patrimônio (rental.substitute — exige substituto disponível), ou OS campo no mesmo equipamento (maintenance.open tipo=campo) se não houver troca.
Manutenção oficina: aberta → em_execucao → aguardando_peca → concluida. Deep links OS: ?acao=executar|retomar|concluir.
Manutenção campo: locação permanece locada; concluir com maintenance.complete_field (checklist) ou tela /campo/{patrimônio}.
Documentos PDF: rental_summary, rental_contract (pro-rata se contrato_clausula_prorata), rental_statement (periodo_de/ate), asset_sheet, maintenance_order, billing_invoice — via document.export.
Horímetro: só exige aviso/PDF se categoria usa_horimetro; usar horimetro_saida/retorno na locação, não duplicar no patrimônio.
Busca global: patrimônio locado retorna ficha LOC + PAT; LOC-000123 ou número 123 encontra contrato.
Limites do copiloto: modo Pergunta = só leitura; modo Agente = escrita com confirmação; sem automação de cliques em tela; respeitar permissões do usuário.
Detalhe completo: use a ferramenta knowledge.get ou GET /api/agent/context/knowledge.
TXT;
    }
}
