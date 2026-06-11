<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Services\DocumentPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BillingInvoicePdfController extends Controller
{
    public function __invoke(Request $request, RentalBillingQueueEntry $entry, DocumentPdfService $pdfService): Response
    {
        abort_unless($request->user()?->can('finance.view'), 403);

        return $pdfService->billingInvoice($entry)->download("fatura-{$entry->codigo}.pdf");
    }
}
