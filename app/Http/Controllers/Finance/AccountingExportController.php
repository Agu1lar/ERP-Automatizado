<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Services\AccountingExportService;
use App\Support\Accounting\AccountingExportRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingExportController extends Controller
{
    public function __invoke(
        Request $request,
        AccountingExportRegistry $registry,
        AccountingExportService $exportService,
    ): StreamedResponse {
        abort_unless($request->user()->can('finance.view'), 403);

        $format = $request->query('format', config('accounting.default_format', 'csv'));
        $exporter = $registry->get($format);
        $markExported = $request->boolean('mark_exported', true);
        $excludeExported = $request->boolean('exclude_exported', true);

        $titles = ReceivableTitle::query()
            ->with(['customer', 'rental.asset'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($excludeExported, fn ($q) => $q->notExportedToErp())
            ->orderBy('vencimento')
            ->get();

        $user = $request->user();

        return response()->streamDownload(function () use ($titles, $exporter, $exportService, $format, $markExported, $user) {
            $handle = fopen('php://output', 'w');
            $exporter->write($titles, $handle);
            fclose($handle);

            if ($markExported && $titles->isNotEmpty()) {
                $exportService->markExportedToErp($titles, $format, $user);
            }
        }, $exporter->filename(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
