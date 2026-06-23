<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Domain\Rental\Rental;
use App\Services\DocumentPdfService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RentalStatementPdfController extends Controller
{
    public function __invoke(Rental $rental, DocumentPdfService $pdfService): Response
    {
        Gate::authorize('view', $rental);

        $data = request()->validate([
            'de' => 'required|date',
            'ate' => 'required|date|after_or_equal:de',
        ]);

        $from = Carbon::parse($data['de'])->startOfDay();
        $to = Carbon::parse($data['ate'])->startOfDay();

        return $pdfService
            ->rentalStatement($rental, $from, $to)
            ->download("{$rental->codigo}-demonstrativo.pdf");
    }
}
