<?php

namespace App\Http\Controllers;

use App\Models\Domain\Fleet\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AssetScanController extends Controller
{
    public function __invoke(string $codigo): RedirectResponse
    {
        $asset = Asset::query()
            ->where('codigo_patrimonio', $codigo)
            ->firstOrFail();

        Gate::authorize('view', $asset);

        return redirect()->route('assets.show', $asset);
    }
}
