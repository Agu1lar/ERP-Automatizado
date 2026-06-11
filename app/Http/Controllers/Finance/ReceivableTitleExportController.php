<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Support\BrandContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivableTitleExportController extends Controller
{
    public function __invoke(Request $request, ReceivableTitle $title): StreamedResponse
    {
        abort_unless($request->user()?->can('view', $title), 403);

        $title->load(['customer', 'rental']);

        return response()->streamDownload(function () use ($title) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [BrandContext::exportTitle('Título a receber')], ';');
            fputcsv($handle, ['Exportado em', now()->format('d/m/Y H:i')], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, [
                'Código', 'Cliente', 'CPF/CNPJ', 'Locação', 'Parcela', 'Valor', 'Vencimento', 'Status', 'Pago em', 'Forma', 'Observações',
            ], ';');
            fputcsv($handle, [
                $title->codigo,
                $title->customer->nome,
                $title->customer->formattedDocument(),
                $title->rental?->codigo ?? '',
                $title->parcelLabel(),
                number_format($title->valor, 2, ',', '.'),
                $title->vencimento->format('d/m/Y'),
                $title->statusEnum()->label(),
                $title->pago_em?->format('d/m/Y') ?? '',
                $title->paymentMethodEnum()?->label() ?? '',
                $title->observacoes ?? '',
            ], ';');

            fclose($handle);
        }, 'titulo-'.$title->codigo.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
