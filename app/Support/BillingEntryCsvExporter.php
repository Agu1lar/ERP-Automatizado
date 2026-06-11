<?php

namespace App\Support;

use App\Models\Domain\Rental\RentalBillingQueueEntry;

class BillingEntryCsvExporter
{
    /**
     * @param  resource  $handle
     */
    public function write(RentalBillingQueueEntry $entry, $handle): void
    {
        $entry->loadMissing(['customer', 'rental.asset.equipmentModel', 'receivableTitle']);

        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($handle, [BrandContext::exportTitle('Fatura / NF de locação')], ';');
        fputcsv($handle, ['Exportado em', now()->format('d/m/Y H:i')], ';');
        fputcsv($handle, [], ';');
        fputcsv($handle, ['Campo', 'Valor'], ';');

        $rows = [
            ['Código da fatura', $entry->codigo],
            ['Status', $entry->statusEnum()->label()],
            ['Tipo', $entry->tipoEnum()->label()],
            ['Cliente', $entry->customer->nome],
            ['CPF/CNPJ', $entry->customer->formattedDocument()],
            ['Locação', $entry->rental?->codigo ?? ''],
            ['Patrimônio', $entry->rental?->asset?->codigo_patrimonio ?? ''],
            ['Equipamento', $entry->rental?->asset?->equipmentDisplayName() ?? ''],
            ['Período início', $entry->periodo_inicio?->format('d/m/Y') ?? ''],
            ['Período fim', $entry->periodo_fim?->format('d/m/Y') ?? ''],
            ['Valor NF', number_format((float) $entry->valor_nf, 2, ',', '.')],
            ['Valor a receber (CAR)', number_format((float) $entry->valor_car, 2, ',', '.')],
            ['Gerado em', $entry->gerado_em?->format('d/m/Y H:i') ?? ''],
            ['Autorizado em', $entry->autorizado_em?->format('d/m/Y H:i') ?? ''],
            ['Faturado em', $entry->faturado_em?->format('d/m/Y H:i') ?? ''],
            ['Título a receber', $entry->receivableTitle?->codigo ?? ''],
            ['Vencimento título', $entry->receivableTitle?->vencimento?->format('d/m/Y') ?? ''],
            ['Status pagamento', $entry->receivableTitle?->statusEnum()->label() ?? ''],
            ['Pago em', $entry->receivableTitle?->pago_em?->format('d/m/Y') ?? ''],
            ['Observações', $entry->observacoes ?? ''],
        ];

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
    }

    public function filename(RentalBillingQueueEntry $entry): string
    {
        return 'fatura-'.$entry->codigo.'-'.now()->format('Y-m-d').'.csv';
    }
}
