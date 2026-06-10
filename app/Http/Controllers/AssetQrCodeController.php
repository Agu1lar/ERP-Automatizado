<?php

namespace App\Http\Controllers;

use App\Models\Domain\Fleet\Asset;
use App\Services\QrCodeService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetQrCodeController extends Controller
{
    public function show(Asset $asset, QrCodeService $qrCodeService): Response|StreamedResponse
    {
        Gate::authorize('view', $asset);

        abort_unless($qrCodeService->existsOnDisk($asset), 404);

        return Storage::disk('local')->response(
            $asset->qr_code_path,
            "qr-{$asset->codigo_patrimonio}.png",
            ['Content-Type' => 'image/png'],
        );
    }
}
