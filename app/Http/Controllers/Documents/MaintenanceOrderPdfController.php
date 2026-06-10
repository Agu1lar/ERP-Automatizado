<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Services\DocumentPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MaintenanceOrderPdfController extends Controller
{
    public function __invoke(MaintenanceOrder $order, DocumentPdfService $pdfService): Response
    {
        Gate::authorize('view', $order);

        return $pdfService->maintenanceOrder($order)->download("{$order->codigo}.pdf");
    }
}
