<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Domain\Rental\Rental;
use App\Services\DocumentPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RentalContractPdfController extends Controller
{
    public function __invoke(Rental $rental, DocumentPdfService $pdfService): Response
    {
        Gate::authorize('view', $rental);

        return $pdfService->rentalContract($rental)->download("{$rental->codigo}-contrato.pdf");
    }
}
