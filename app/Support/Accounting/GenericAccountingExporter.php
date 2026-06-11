<?php

namespace App\Support\Accounting;

use App\Models\Domain\Finance\ReceivableTitle;

class GenericAccountingExporter extends AbstractAccountingExporter
{
    public function headers(): array
    {
        return [
            'Codigo_Titulo',
            'Cliente',
            'CPF_CNPJ',
            'Locacao',
            'Patrimonio',
            'Parcela',
            'Emissao',
            'Vencimento',
            'Valor',
            'Status',
            'Pago_Em',
            'Forma_Pagamento',
            'Historico',
        ];
    }

    public function mapRow(ReceivableTitle $title): array
    {
        $title->loadMissing(['customer', 'rental.asset']);

        $historico = 'Locação '.($title->rental?->codigo ?? '—').' — '.$title->parcelLabel();

        return [
            $title->codigo,
            $title->customer->nome,
            $title->customer->formattedDocument(),
            $title->rental?->codigo ?? '',
            $title->rental?->asset?->codigo_patrimonio ?? '',
            $title->parcelLabel(),
            $title->created_at?->format('d/m/Y') ?? '',
            $this->dateBr($title->vencimento),
            $this->money($title->valor),
            $title->statusEnum()->label(),
            $this->dateBr($title->pago_em),
            $title->paymentMethodEnum()?->label() ?? '',
            $historico,
        ];
    }

    public function filename(): string
    {
        return 'contabil-titulos-'.now()->format('Y-m-d').'.csv';
    }
}
