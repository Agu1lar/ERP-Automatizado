<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\BrandContext;
use App\Support\RentalPanelQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalPanelExportController extends Controller
{
    public function __invoke(Request $request, RentalPanelQuery $panelQuery): StreamedResponse
    {
        abort_unless($request->user()->can('viewAny', \App\Models\Domain\Rental\Rental::class), 403);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status_scope' => 'nullable|string|max:50',
            'category_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'valor_min' => 'nullable|numeric|min:0',
            'valor_max' => 'nullable|numeric|min:0',
            'sort_by' => 'nullable|string|max:50',
            'sort_dir' => 'nullable|in:asc,desc',
            'show_customer_history' => 'nullable|boolean',
            'overdue_only' => 'nullable|boolean',
        ]);

        $filters = [
            'search' => $validated['search'] ?? '',
            'status_scope' => $validated['status_scope'] ?? 'locado',
            'category_id' => $validated['category_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'valor_min' => $validated['valor_min'] ?? '',
            'valor_max' => $validated['valor_max'] ?? '',
            'sort_by' => $validated['sort_by'] ?? 'retorno',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'show_customer_history' => (bool) ($validated['show_customer_history'] ?? false),
            'overdue_only' => (bool) ($validated['overdue_only'] ?? false),
        ];

        $rows = $panelQuery->apply($filters)
            ->with(['asset.equipmentModel.category', 'customer'])
            ->get();

        $filename = 'painel-locados-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [BrandContext::exportTitle('Painel de locados')], ';');
            fputcsv($handle, ['Exportado em', now()->format('d/m/Y H:i')], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, [
                'Locação',
                'Patrimônio',
                'Categoria',
                'Cliente',
                'Local obra',
                'Status',
                'Saída',
                'Retorno previsto',
                'Faturamento (R$)',
            ], ';');

            foreach ($rows as $rental) {
                fputcsv($handle, [
                    $rental->codigo,
                    $rental->asset?->codigo_patrimonio ?? '—',
                    $rental->asset?->equipmentModel?->category?->nome ?? '—',
                    $rental->customer?->nome ?? '—',
                    $rental->local_obra ?? '—',
                    $rental->statusEnum()->label(),
                    $rental->checkout_at?->format('d/m/Y H:i') ?? '—',
                    $rental->expected_return_at?->format('d/m/Y') ?? '—',
                    $rental->valor_faturamento !== null
                        ? number_format((float) $rental->valor_faturamento, 2, ',', '.')
                        : '—',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
