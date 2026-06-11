<?php

namespace App\Http\Controllers;

use App\Models\Domain\Fleet\Asset;
use App\Services\QrCodeService;
use App\Support\BrandContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class AssetPrintController extends Controller
{
    public function __invoke(Asset $asset, QrCodeService $qrCodeService): View
    {
        Gate::authorize('view', $asset);

        $asset->load(['equipmentModel.category', 'operatingCompany', 'statusHistories.user', 'movements.user']);

        return view('assets.print', [
            'asset' => $asset,
            'hasQr' => $qrCodeService->existsOnDisk($asset),
            'company' => BrandContext::documentHeader($asset->operatingCompany),
        ]);
    }
}
