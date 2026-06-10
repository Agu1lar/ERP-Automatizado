<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\CommercialReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommercialReportExportController extends Controller
{
    public function __invoke(Request $request, CommercialReportService $service): StreamedResponse
    {
        abort_unless($request->user()->can('dashboard.analytics'), 403);

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'group_by' => 'in:model,category',
        ]);

        $from = Carbon::parse($validated['date_from'])->startOfDay();
        $to = Carbon::parse($validated['date_to'])->endOfDay();
        $groupBy = $validated['group_by'] ?? 'model';

        $rows = $service->revenueByEquipmentType($from, $to, $groupBy);
        $total = $service->totalRevenueInPeriod($from, $to);

        $filename = sprintf(
            'relatorio-comercial-%s-a-%s.csv',
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        return response()->streamDownload(function () use ($rows, $total, $from, $to, $groupBy) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['Relatório comercial — Linha Leve'], ';');
            fputcsv($handle, ['Período', $from->format('d/m/Y').' a '.$to->format('d/m/Y')], ';');
            fputcsv($handle, ['Agrupamento', $groupBy === 'category' ? 'Categoria' : 'Modelo'], ';');
            fputcsv($handle, ['Total faturamento', number_format($total, 2, ',', '.')], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, ['Tipo de equipamento', 'Locações', 'Faturamento (R$)', 'Ticket médio (R$)'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->grupo_nome,
                    $row->total_locacoes,
                    number_format($row->faturamento_total, 2, ',', '.'),
                    number_format($row->ticket_medio, 2, ',', '.'),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
