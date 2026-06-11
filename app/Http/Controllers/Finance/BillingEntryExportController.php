<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Support\BillingEntryCsvExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingEntryExportController extends Controller
{
    public function __invoke(Request $request, RentalBillingQueueEntry $entry): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.view'), 403);

        $exporter = app(BillingEntryCsvExporter::class);

        return response()->streamDownload(function () use ($entry, $exporter) {
            $handle = fopen('php://output', 'w');
            $exporter->write($entry, $handle);
            fclose($handle);
        }, $exporter->filename($entry), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
