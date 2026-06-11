<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Support\BrandContext;
use App\Support\DelinquencyReportQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivableExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('finance.view'), 403);

        $tipo = $request->query('tipo', 'titulos');

        return $tipo === 'inadimplencia'
            ? $this->exportDelinquency()
            : $this->exportTitles();
    }

    private function exportTitles(): StreamedResponse
    {
        $titles = ReceivableTitle::query()
            ->with(['customer', 'rental'])
            ->orderBy('vencimento')
            ->get();

        return response()->streamDownload(function () use ($titles) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [BrandContext::exportTitle('Títulos a receber')], ';');
            fputcsv($handle, ['Exportado em', now()->format('d/m/Y H:i')], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, [
                'Código', 'Cliente', 'CPF/CNPJ', 'Locação', 'Parcela', 'Valor', 'Vencimento', 'Status', 'Pago em', 'Forma',
            ], ';');

            foreach ($titles as $title) {
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
                ], ';');
            }

            fclose($handle);
        }, 'titulos-receber-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportDelinquency(): StreamedResponse
    {
        $query = app(DelinquencyReportQuery::class);
        $rows = $query->customersWithAging();
        $summary = $query->summary();
        $chargeSummary = $query->chargeSummary();
        $titleDetails = $query->overdueTitlesWithCharges();

        return response()->streamDownload(function () use ($rows, $summary, $chargeSummary, $titleDetails) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [BrandContext::exportTitle('Relatório de inadimplência')], ';');
            fputcsv($handle, ['Exportado em', now()->format('d/m/Y H:i')], ';');
            fputcsv($handle, ['Total atrasado (valor limpo)', number_format($summary['total_atrasado'], 2, ',', '.')], ';');
            fputcsv($handle, ['Multa total', number_format($chargeSummary['multa_valor'], 2, ',', '.')], ';');
            fputcsv($handle, ['Juros total', number_format($chargeSummary['juros_valor'], 2, ',', '.')], ';');
            fputcsv($handle, ['Total com encargos', number_format($chargeSummary['valor_total'], 2, ',', '.')], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, ['Resumo por cliente'], ';');
            fputcsv($handle, [
                'Cliente', 'Total atrasado', '1-30 dias', '31-60 dias', '61-90 dias', '90+ dias', 'Qtd títulos atrasados',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->customer_nome,
                    number_format($row->total_atrasado, 2, ',', '.'),
                    number_format($row->ate_30, 2, ',', '.'),
                    number_format($row->ate_60, 2, ',', '.'),
                    number_format($row->ate_90, 2, ',', '.'),
                    number_format($row->acima_90, 2, ',', '.'),
                    $row->titulos_atrasados,
                ], ';');
            }

            fputcsv($handle, [], ';');
            fputcsv($handle, ['Detalhamento por título (valor limpo + multa + juros)'], ';');
            fputcsv($handle, [
                'Código', 'Cliente', 'Locação', 'Vencimento', 'Dias atraso', 'Valor limpo',
                'Multa %', 'Multa R$', 'Juros % a.m.', 'Juros R$', 'Total', 'Regra', 'Encargos aplicados',
            ], ';');

            foreach ($titleDetails as $row) {
                fputcsv($handle, [
                    $row->title->codigo,
                    $row->title->customer->nome,
                    $row->title->rental?->codigo ?? '',
                    $row->title->vencimento->format('d/m/Y'),
                    $row->dias_atraso,
                    number_format($row->valor_limpo, 2, ',', '.'),
                    number_format($row->multa_percent, 2, ',', '.'),
                    number_format($row->multa_valor, 2, ',', '.'),
                    number_format($row->juros_mensal_percent, 2, ',', '.'),
                    number_format($row->juros_valor, 2, ',', '.'),
                    number_format($row->valor_total, 2, ',', '.'),
                    $row->rule_source,
                    $row->is_applied ? 'Sim' : 'Não (projeção)',
                ], ';');
            }

            fputcsv($handle, [], ';');
            fputcsv($handle, [
                'Totais', '', '', '', '',
                number_format($chargeSummary['valor_limpo'], 2, ',', '.'),
                '',
                number_format($chargeSummary['multa_valor'], 2, ',', '.'),
                '',
                number_format($chargeSummary['juros_valor'], 2, ',', '.'),
                number_format($chargeSummary['valor_total'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, 'inadimplencia-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
