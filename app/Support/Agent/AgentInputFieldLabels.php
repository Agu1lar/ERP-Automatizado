<?php

namespace App\Support\Agent;

class AgentInputFieldLabels
{
    /** @var array<string, string> */
    private const LABELS = [
        'asset_id' => 'Patrimônio (ID)',
        'asset_codigo' => 'Patrimônio',
        'new_asset_id' => 'Patrimônio substituto (ID)',
        'new_asset_codigo' => 'Patrimônio substituto',
        'customer_id' => 'Cliente (ID)',
        'customer_cpf_cnpj' => 'Cliente (CPF/CNPJ)',
        'customer_name' => 'Cliente',
        'rental_id' => 'Locação (ID)',
        'rental_codigo' => 'Locação',
        'quote_id' => 'Orçamento (ID)',
        'quote_codigo' => 'Orçamento',
        'order_id' => 'Ordem de serviço (ID)',
        'order_codigo' => 'Ordem de serviço',
        'entry_id' => 'Pendência de faturamento (ID)',
        'entry_codigo' => 'Pendência de faturamento',
        'title_id' => 'Título (ID)',
        'title_codigo' => 'Título',
        'person_id' => 'Pessoa (ID)',
        'person_cpf' => 'Pessoa (CPF)',
        'company_id' => 'Empresa (ID)',
        'company_cnpj' => 'Empresa (CNPJ)',
        'part_id' => 'Peça (ID)',
        'part_codigo' => 'Peça',
        'yard_id' => 'Pátio (ID)',
        'yard_name' => 'Pátio',
        'nome' => 'Nome',
        'cpf_cnpj' => 'CPF/CNPJ',
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'endereco' => 'Endereço',
        'local_obra' => 'Local da obra',
        'expected_return_at' => 'Previsão de retorno',
        'pricing_period' => 'Período de cobrança',
        'observacoes' => 'Observações',
        'descricao' => 'Descrição',
        'solucao' => 'Solução aplicada',
        'reason' => 'Motivo',
        'motivo' => 'Motivo',
        'destino' => 'Destino',
        'tipo' => 'Tipo',
        'status' => 'Status',
        'impeditiva' => 'Manutenção impeditiva',
        'horimetro' => 'Horímetro',
        'horimetro_saida' => 'Horímetro de saída',
        'horimetro_retorno' => 'Horímetro de retorno',
        'payment_method' => 'Forma de pagamento',
        'pago_em' => 'Data do pagamento',
        'validity_days' => 'Validade (dias)',
        'periodo_de' => 'Período inicial',
        'periodo_ate' => 'Período final',
        'document_type' => 'Tipo de documento',
        'contrato_clausula_prorata' => 'Cláusula pro-rata no contrato',
        'confirm_checklist_all' => 'Checklist de campo',
        'commercial_user_id' => 'Comercial responsável (ID)',
        'commercial_user_email' => 'Comercial responsável (e-mail)',
        'action' => 'Ação',
        'q' => 'Busca',
        'limit' => 'Limite',
        'open_only' => 'Somente abertas',
        'date' => 'Data',
        'view' => 'Visão',
        'result' => 'Resultado da inspeção',
        'extend_days' => 'Dias de prorrogação',
        'new_expected_return_at' => 'Nova previsão de retorno',
    ];

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::LABELS;
    }
}
