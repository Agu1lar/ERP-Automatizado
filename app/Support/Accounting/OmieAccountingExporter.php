<?php

namespace App\Support\Accounting;

use App\Models\Domain\Finance\ReceivableTitle;

class OmieAccountingExporter extends AbstractAccountingExporter
{
    public function headers(): array
    {
        return [
            'codigo_lancamento_integracao',
            'codigo_cliente_fornecedor_integracao',
            'data_vencimento',
            'valor_documento',
            'codigo_categoria',
            'data_previsao',
            'id_conta_corrente',
            'numero_documento',
            'observacao',
        ];
    }

    public function mapRow(ReceivableTitle $title): array
    {
        $title->loadMissing(['customer', 'rental']);

        return [
            $title->codigo,
            $this->onlyDigits($title->customer->cpf_cnpj),
            $this->dateBr($title->vencimento),
            $this->money($title->valor),
            config('accounting.omie.categoria'),
            $this->dateBr($title->vencimento),
            config('accounting.omie.conta_corrente'),
            $title->codigo,
            'Locação '.($title->rental?->codigo ?? '').' — '.$title->parcelLabel(),
        ];
    }

    public function filename(): string
    {
        return 'omie-contas-receber-'.now()->format('Y-m-d').'.csv';
    }
}
