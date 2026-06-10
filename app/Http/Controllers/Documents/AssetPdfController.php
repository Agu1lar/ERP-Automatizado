<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Domain\Fleet\Asset;
use App\Services\DocumentPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AssetPdfController extends Controller
{
    public function __invoke(Asset $asset, DocumentPdfService $pdfService): Response
    {
        Gate::authorize('view', $asset);

        return $pdfService->assetSheet($asset)->download("ficha-{$asset->codigo_patrimonio}.pdf");
    }
}
