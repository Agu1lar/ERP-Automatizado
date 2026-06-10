<?php

namespace App\Jobs;

use App\Models\Domain\Fleet\Asset;
use App\Services\QrCodeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAssetQrCodeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $assetId,
    ) {}

    public function handle(QrCodeService $qrCodeService): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            return;
        }

        $qrCodeService->generate($asset);
    }

    public function failed(?Throwable $exception): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            return;
        }

        app(QrCodeService::class)->markFailed(
            $asset,
            $exception?->getMessage() ?? 'Falha desconhecida ao gerar QR Code.',
        );

        Log::error('GenerateAssetQrCodeJob failed', [
            'asset_id' => $this->assetId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
