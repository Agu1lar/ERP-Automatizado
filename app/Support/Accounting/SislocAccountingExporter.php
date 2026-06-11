<?php

namespace App\Support\Accounting;

use App\Models\Domain\Finance\ReceivableTitle;

class SislocAccountingExporter extends AbstractAccountingExporter
{
    public function headers(): array
    {
        return [
            'COD_EMPRESA',
            'COD_CLIENTE',
            'NOME_CLIENTE',
            'CNPJ_CPF',
            'TIPO_DOC',
            'NUM_DOCUMENTO',
            'DT_EMISSAO',
            'DT_VENCTO',
            'VALOR',
            'LOCACAO',
            'PARCELA',
            'STATUS',
            'OBSERVACAO',
        ];
    }

    public function mapRow(ReceivableTitle $title): array
    {
        $title->loadMissing(['customer', 'rental']);

        return [
            config('accounting.sisloc.empresa_codigo'),
            $title->customer_id,
            $title->customer->nome,
            $this->onlyDigits($title->customer->cpf_cnpj),
            config('accounting.sisloc.tipo_documento'),
            $title->codigo,
            $title->created_at?->format('d/m/Y') ?? '',
            $this->dateBr($title->vencimento),
            $this->money($title->valor),
            $title->rental?->codigo ?? '',
            $title->parcelLabel(),
            strtoupper($title->status),
            'Importado Gestão Acesso — '.$title->codigo,
        ];
    }

    public function filename(): string
    {
        return 'sisloc-car-'.now()->format('Y-m-d').'.csv';
    }
}
